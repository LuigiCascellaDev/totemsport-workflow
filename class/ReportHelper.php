<?php

    /**
	 * Genera un PDF con report della giornata corrente.
	 * @return string Path del file PDF generato.
	 */
    class ReportHelper {

        /**
         * Calcola la durata in minuti tra due timestamp stringa (formato compatibile strtotime).
         * @param string $apertura
         * @param string $chiusura
         * @return int|string
         */
        public static function durata_minuti($apertura, $chiusura) {
            if ($apertura && $chiusura) {
                $t1 = strtotime($apertura);
                $t2 = strtotime($chiusura);
                if ($t1 && $t2 && $t2 >= $t1) {
                    $diff = $t2 - $t1;
                    if ($diff < 60) {
                        return '<1';
                    }
                    return round($diff / 60);
                }
            }
            return '';
        }

        /**
         * Genera un PDF con report della giornata specificata (default oggi).
         * @param string|null $data Data in formato 'Y-m-d' (es. '2025-12-23'). Se null, usa oggi.
         * @return string Path del file PDF generato.
         */
        public static function genera_report_giornata_pdf($data = null)
        {
            // Includi Dompdf se necessario
            if (!class_exists('\Dompdf\Dompdf')) {
                require_once plugin_dir_path(__FILE__) . '../lib/vendor/dompdf/src/Dompdf.php';
                require_once plugin_dir_path(__FILE__) . '../lib/vendor/dompdf/src/Options.php';
                if (file_exists(plugin_dir_path(__FILE__) . '../lib/vendor/autoload.php')) {
                    require_once plugin_dir_path(__FILE__) . '../lib/vendor/autoload.php';
                }
            }

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVuSans');
            $dompdf = new \Dompdf\Dompdf($options);

            // Gestione data: se non fornita, usa oggi
            if ($data === null) {
                $data = date('Y-m-d');
            }

            $data_time = strtotime($data);
            $anno = date('Y', $data_time);
            $mese = date('m', $data_time);
            $giorno = date('d', $data_time);

            // Recupera tutti i post appunt_conf (accettazioni) per la data specificata
            $posts = get_posts([
                'post_type' => 'appunt_conf',
                'posts_per_page' => -1,
                'post_status' => ['pending'],
                'date_query' => [
                    [
                        'year' => $anno,
                        'month' => $mese,
                        'day' => $giorno,
                    ],
                ],
            ]);

            $processed_appointments = array();
            $app_data_list = array();

            if (!empty($posts)) {
                foreach ($posts as $p) {
                    $id_app = get_post_meta($p->ID, 'appointment_id', true);
                    if (!$id_app || !get_post($id_app)) continue;
                    if (in_array($id_app, $processed_appointments)) continue;
                    $processed_appointments[] = $id_app;
                    if (!class_exists('AppointmentHelper') && file_exists(plugin_dir_path(__FILE__) . '../class/AppointmentHelper.php')) {
                        require_once plugin_dir_path(__FILE__) . '../class/AppointmentHelper.php';
                    }
                    $appuntamento = class_exists('AppointmentHelper') ? AppointmentHelper::get_app_data($id_app) : null;
                    if (!$appuntamento) continue;
                    $appuntamento['accettazione_id'] = $p->ID; // per eventuali meta conferma
                    $app_data_list[] = $appuntamento;
                }
            }

            // Ordina per ora crescente
            usort($app_data_list, function($a, $b) {
                $oraA = $a['ora'] ?? '';
                $oraB = $b['ora'] ?? '';
                if ($oraA === '' && $oraB === '') return 0;
                if ($oraA === '') return 1;
                if ($oraB === '') return -1;
                return strcmp($oraA, $oraB);
            });

            // Costruisci HTML tabellare
            $html = '<style>';
            $html .= 'body { font-size: 12px; }';
            $html .= 'table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }';
            $html .= 'th, td { border: 1px solid #888; padding: 3px 2px; font-size: 10px; word-break: break-word; }';
            $html .= 'th { background: #f0f0f0; }';
            $html .= 'th.ora, td.ora { width: 3.5%; }';
            $html .= 'th.paziente, td.paziente { width: 7%; }';
            $html .= 'th.cf, td.cf { width: 7%; }';
            $html .= 'th.prestazione, td.prestazione { width: 10%; }';
            $html .= 'th.pagato, td.pagato { width: 3.5%; }';
            $html .= 'th.totale, td.totale { width: 4%; }';
            $html .= 'th.apertura, td.apertura, th.chiusura, td.chiusura { width: 7%; }';
            $html .= 'th.durata, td.durata { width: 4%; }';
            $html .= 'th.account, td.account { width: 7%; }';
            $html .= '</style>';
            $html .= '<h2 style="text-align:center;">Report appuntamenti del ' . date('d/m/Y', $data_time) . '</h2>';
            $html .= '<div style="color:#b30000;font-size:13px;text-align:center;margin:12px 0 8px 0;font-weight:bold;">ATTENZIONE: tutti i dati riportati nel presente report potrebbero presentare incongruenze rispetto alla veridicità effettiva delle operazioni svolte e ai tempi conseguiti.</div>';
            $html .= '<table><thead><tr>';

            $html .= '<th class="ora">Ora</th>';
            $html .= '<th class="paziente">Paziente</th>';
            $html .= '<th class="cf">CF</th>';
            $html .= '<th class="prestazione">Prestazione</th>';
            $html .= '<th class="pagato">Pagato</th>';
            $html .= '<th class="totale">Totale</th>';
            $html .= '<th class="apertura">Apertura Admin</th>';
            $html .= '<th class="chiusura">Chiusura Admin</th>';
            $html .= '<th class="durata">Durata Admin (min)</th>';
            $html .= '<th class="apertura">Apertura Infermiere</th>';
            $html .= '<th class="chiusura">Chiusura Infermiere</th>';
            $html .= '<th class="durata">Durata Infermiere (min)</th>';
            $html .= '<th class="apertura">Apertura Medico</th>';
            $html .= '<th class="chiusura">Chiusura Medico</th>';
            $html .= '<th class="durata">Durata Medico (min)</th>';
            $html .= '<th class="account">Account Medico</th>';
            $html .= '<th class="account">Account Infermiere</th>';
            $html .= '<th class="account">Account Admin</th>';
            $html .= '</tr></thead><tbody>';

            if (!empty($app_data_list)) {
                foreach ($app_data_list as $appuntamento) {
                    $nome = $appuntamento['nome'] ?? '';
                    $cognome = $appuntamento['cognome'] ?? '';
                    $cf = $appuntamento['cf'] ?? '';
                    $prestazione = $appuntamento['tipologia'] ?? '';
                    $pagato = ($appuntamento['pagato'] ?? false) ? 'SI' : 'NO';
                    $totale = $appuntamento['totale'] ?? '';
                    $ora = $appuntamento['ora'] ?? '';
                    $accettazione_id = $appuntamento['accettazione_id'] ?? null;
                    // Account
                    $medico_user = $accettazione_id ? get_post_meta($accettazione_id, 'medico_user', true) : '';
                    $infermiere_user = $accettazione_id ? get_post_meta($accettazione_id, 'infermiere_user', true) : '';
                    $admin_user = $accettazione_id ? get_post_meta($accettazione_id, 'admin_user', true) : '';
                    if (is_numeric($medico_user) && $medico_user > 0) {
                        $user = get_userdata($medico_user);
                        $medico_user = $user ? $user->display_name : $medico_user;
                    }
                    if (is_numeric($infermiere_user) && $infermiere_user > 0) {
                        $user = get_userdata($infermiere_user);
                        $infermiere_user = $user ? $user->display_name : $infermiere_user;
                    }
                    if (is_numeric($admin_user) && $admin_user > 0) {
                        $user = get_userdata($admin_user);
                        $admin_user = $user ? $user->display_name : $admin_user;
                    }

                    // Tempi apertura/chiusura e durata per ogni ruolo
                    $apertura_admin = $accettazione_id ? get_post_meta($accettazione_id, 'admin_form_open_time', true) : '';
                    $chiusura_admin = $accettazione_id ? get_post_meta($accettazione_id, 'admin_close_time', true) : '';
                    $apertura_nurse = $accettazione_id ? get_post_meta($accettazione_id, 'nurse_form_open_time', true) : '';
                    $chiusura_nurse = $accettazione_id ? get_post_meta($accettazione_id, 'nurse_form_submit_time', true) : '';
                    $apertura_med = $accettazione_id ? get_post_meta($accettazione_id, 'med_form_open_time', true) : '';
                    $chiusura_med = $accettazione_id ? get_post_meta($accettazione_id, 'medico_form_submit_time', true) : '';

                    $durata_admin = ReportHelper::durata_minuti($apertura_admin, $chiusura_admin) ?: 'N/A';
                    $durata_nurse = ReportHelper::durata_minuti($apertura_nurse, $chiusura_nurse) ?: 'N/A';
                    $durata_med = ReportHelper::durata_minuti($apertura_med, $chiusura_med) ?: 'N/A';

                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($ora) . '</td>';
                    $html .= '<td>' . esc_html($cognome . ' ' . $nome) . '</td>';
                    $html .= '<td>' . esc_html($cf) . '</td>';
                    $html .= '<td>' . esc_html($prestazione) . '</td>';
                    $html .= '<td>' . esc_html($pagato) . '</td>';
                    $html .= '<td>' . esc_html($totale) . '</td>';
                    $html .= '<td>' . esc_html($apertura_admin) . '</td>';
                    $html .= '<td>' . esc_html($chiusura_admin) . '</td>';
                    $html .= '<td>' . esc_html($durata_admin) . '</td>';
                    $html .= '<td>' . esc_html($apertura_nurse) . '</td>';
                    $html .= '<td>' . esc_html($chiusura_nurse) . '</td>';
                    $html .= '<td>' . esc_html($durata_nurse) . '</td>';
                    $html .= '<td>' . esc_html($apertura_med) . '</td>';
                    $html .= '<td>' . esc_html($chiusura_med) . '</td>';
                    $html .= '<td>' . esc_html($durata_med) . '</td>';
                    $html .= '<td>' . esc_html($medico_user) . '</td>';
                    $html .= '<td>' . esc_html($infermiere_user) . '</td>';
                    $html .= '<td>' . esc_html($admin_user) . '</td>';
                    $html .= '</tr>';
                }
            } else {
                $html .= '<tr><td colspan="9" style="text-align:center;">Nessun appuntamento trovato per oggi.</td></tr>';
            }

            $html .= '</tbody></table>';

            // Riepilogo: conteggio per account infermiere e admin

            $infermiere_count = array();
            $infermiere_tipo_count = array();
            $infermiere_durata = array();
            $infermiere_tipo_durata = array();
            $admin_count = array();
            $admin_durata = array();
            foreach ($app_data_list as $appuntamento) {
                $accettazione_id = $appuntamento['accettazione_id'] ?? null;
                $tipologia = $appuntamento['tipologia'] ?? '';
                // Infermiere
                $infermiere_user = $accettazione_id ? get_post_meta($accettazione_id, 'infermiere_user', true) : '';
                if (is_numeric($infermiere_user) && $infermiere_user > 0) {
                    $user = get_userdata($infermiere_user);
                    $infermiere_user = $user ? $user->display_name : $infermiere_user;
                }
                $apertura_nurse = $accettazione_id ? get_post_meta($accettazione_id, 'nurse_form_open_time', true) : '';
                $chiusura_nurse = $accettazione_id ? get_post_meta($accettazione_id, 'nurse_form_submit_time', true) : '';
                $durata_nurse = ($apertura_nurse && $chiusura_nurse) ? round((strtotime($chiusura_nurse) - strtotime($apertura_nurse)) / 60) : null;
                if (!empty($infermiere_user)) {
                    if (!isset($infermiere_count[$infermiere_user])) $infermiere_count[$infermiere_user] = 0;
                    $infermiere_count[$infermiere_user]++;
                    if (!isset($infermiere_durata[$infermiere_user])) $infermiere_durata[$infermiere_user] = [];
                    if ($durata_nurse !== null && $durata_nurse >= 0) $infermiere_durata[$infermiere_user][] = $durata_nurse;
                    // Per tipologia
                    if (!isset($infermiere_tipo_count[$infermiere_user])) $infermiere_tipo_count[$infermiere_user] = array();
                    if (!isset($infermiere_tipo_count[$infermiere_user][$tipologia])) $infermiere_tipo_count[$infermiere_user][$tipologia] = 0;
                    $infermiere_tipo_count[$infermiere_user][$tipologia]++;
                    if (!isset($infermiere_tipo_durata[$infermiere_user])) $infermiere_tipo_durata[$infermiere_user] = array();
                    if (!isset($infermiere_tipo_durata[$infermiere_user][$tipologia])) $infermiere_tipo_durata[$infermiere_user][$tipologia] = [];
                    if ($durata_nurse !== null && $durata_nurse >= 0) $infermiere_tipo_durata[$infermiere_user][$tipologia][] = $durata_nurse;
                }
                // Admin
                $admin_user = $accettazione_id ? get_post_meta($accettazione_id, 'admin_user', true) : '';
                if (is_numeric($admin_user) && $admin_user > 0) {
                    $user = get_userdata($admin_user);
                    $admin_user = $user ? $user->display_name : $admin_user;
                }
                $apertura_admin = $accettazione_id ? get_post_meta($accettazione_id, 'admin_form_open_time', true) : '';
                $chiusura_admin = $accettazione_id ? get_post_meta($accettazione_id, 'admin_form_submit_time', true) : '';
                $durata_admin = ($apertura_admin && $chiusura_admin) ? round((strtotime($chiusura_admin) - strtotime($apertura_admin)) / 60) : null;
                if (!empty($admin_user)) {
                    if (!isset($admin_count[$admin_user])) $admin_count[$admin_user] = 0;
                    $admin_count[$admin_user]++;
                    if (!isset($admin_durata[$admin_user])) $admin_durata[$admin_user] = [];
                    if ($durata_admin !== null && $durata_admin >= 0) $admin_durata[$admin_user][] = $durata_admin;
                }
            }

            $html .= '<h3 style="margin-top:18px;text-align:center;">Riepilogo pazienti presi in carico</h3>';
            $html .= '<div style="color:#b30000;font-size:13px;text-align:center;margin:12px 0 8px 0;font-weight:bold;">ATTENZIONE: tutti i dati riportati nel presente report potrebbero presentare incongruenze rispetto alla veridicità effettiva delle operazioni svolte e ai tempi conseguiti.</div>';
            $html .= '<table style="width:70%;margin:0 auto 24px auto;border-collapse:collapse;">';
            $html .= '<thead><tr><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">Account Admin</th><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">N. Pazienti</th><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">Media tempo (min)</th></tr></thead><tbody>';
            if (!empty($admin_count)) {
                foreach ($admin_count as $name => $count) {
                    $media = '';
                    if (!empty($admin_durata[$name])) {
                        $media = round(array_sum($admin_durata[$name]) / count($admin_durata[$name]), 1);
                    }
                    $html .= '<tr><td style="border:1px solid #888;padding:8px;">' . esc_html($name) . '</td><td style="border:1px solid #888;padding:8px;text-align:center;">' . intval($count) . '</td><td style="border:1px solid #888;padding:8px;text-align:center;">' . esc_html($media) . '</td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="3" style="text-align:center;">Nessun admin trovato</td></tr>';
            }
            $html .= '</tbody></table>';

            $html .= '<table style="width:90%;margin:0 auto 24px auto;border-collapse:collapse;">';
            $html .= '<thead><tr><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">Account Infermiere</th><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">N. Pazienti</th><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">Media tempo (min)</th><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">Dettaglio per tipologia</th></tr></thead><tbody>';
            if (!empty($infermiere_count)) {
                foreach ($infermiere_count as $name => $count) {
                    $media = '';
                    if (!empty($infermiere_durata[$name])) {
                        $media = round(array_sum($infermiere_durata[$name]) / count($infermiere_durata[$name]), 1);
                    }
                    $tipologie = $infermiere_tipo_count[$name] ?? array();
                    $tipologia_html = '-';
                    if (!empty($tipologie)) {
                        $tipologia_html = '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
                        $tipologia_html .= '<thead><tr><th style="border:1px solid #bbb;padding:4px;background:#f9f9f9;">Tipologia</th><th style="border:1px solid #bbb;padding:4px;background:#f9f9f9;">N.</th><th style="border:1px solid #bbb;padding:4px;background:#f9f9f9;">Media tempo (min)</th></tr></thead><tbody>';
                        foreach ($tipologie as $tipo => $num) {
                            if ($tipo !== '') {
                                $media_tipo = '';
                                if (!empty($infermiere_tipo_durata[$name][$tipo])) {
                                    $media_tipo = round(array_sum($infermiere_tipo_durata[$name][$tipo]) / count($infermiere_tipo_durata[$name][$tipo]), 1);
                                }
                                $tipologia_html .= '<tr><td style="border:1px solid #bbb;padding:4px;">' . esc_html($tipo) . '</td><td style="border:1px solid #bbb;padding:4px;text-align:center;">' . intval($num) . '</td><td style="border:1px solid #bbb;padding:4px;text-align:center;">' . esc_html($media_tipo) . '</td></tr>';
                            }
                        }
                        $tipologia_html .= '</tbody></table>';
                    }
                    $html .= '<tr><td style="border:1px solid #888;padding:8px;">' . esc_html($name) . '</td><td style="border:1px solid #888;padding:8px;text-align:center;">' . intval($count) . '</td><td style="border:1px solid #888;padding:8px;text-align:center;">' . esc_html($media) . '</td><td style="border:1px solid #888;padding:8px;">' . $tipologia_html . '</td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="4" style="text-align:center;">Nessun infermiere trovato</td></tr>';
            }
            $html .= '</tbody></table>';

            $dompdf->setPaper('A4', 'landscape');
            $dompdf->loadHtml($html);
            $dompdf->render();

            // Salva PDF in una cartella temporanea
            $upload_dir = wp_upload_dir();
            $report_dir = $upload_dir['basedir'] . '/report/';
            if (!file_exists($report_dir)) {
                mkdir($report_dir, 0755, true);
            }
            $filename = 'report_giornata_' . date('Ymd', $data_time) . '_' . date('His') . '.pdf';
            $filepath = $report_dir . $filename;

            file_put_contents($filepath, $dompdf->output());

            // DEBUG: Log temporaneo dei meta key medico_user e medico_form_user
            foreach ($app_data_list as $appuntamento) {
                $id = $appuntamento['id'] ?? null;
                $medico_user_val = $appuntamento['medico_user'] ?? null;
                $medico_form_user_val = $appuntamento['medico_form_user'] ?? null;
                error_log('REPORT DEBUG - ID: ' . print_r($id, true) . ' medico_user: ' . print_r($medico_user_val, true) . ' medico_form_user: ' . print_r($medico_form_user_val, true));
            }

            return $filepath;
        }

        /**
         * Genera un PDF con report di tutti gli appuntamenti del mese richiesto.
         * @param int $anno Anno (es. 2025)
         * @param int $mese Mese (1-12)
         * @return string Path del file PDF generato.
         */
        public static function genera_report_mese_pdf($anno, $mese)
        {
            if (!class_exists('\Dompdf\Dompdf')) {
                require_once plugin_dir_path(__FILE__) . '../lib/vendor/dompdf/src/Dompdf.php';
                require_once plugin_dir_path(__FILE__) . '../lib/vendor/dompdf/src/Options.php';
                if (file_exists(plugin_dir_path(__FILE__) . '../lib/vendor/autoload.php')) {
                    require_once plugin_dir_path(__FILE__) . '../lib/vendor/autoload.php';
                }
            }

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVuSans');
            $dompdf = new \Dompdf\Dompdf($options);

            // Recupera tutti i post appunt_conf (accettazioni) per il mese richiesto
            $posts = get_posts([
                'post_type' => 'appunt_conf',
                'posts_per_page' => -1,
                'post_status' => ['pending'],
                'date_query' => [
                    [
                        'year' => $anno,
                        'month' => $mese,
                    ],
                ],
                'orderby' => 'date',
                'order' => 'ASC',
            ]);

            $processed_appointments = array();
            $app_data_list = array();

            if (!empty($posts)) {
                foreach ($posts as $p) {
                    $id_app = get_post_meta($p->ID, 'appointment_id', true);
                    if (!$id_app || !get_post($id_app)) continue;
                    if (in_array($id_app, $processed_appointments)) continue;
                    $processed_appointments[] = $id_app;
                    if (!class_exists('AppointmentHelper') && file_exists(plugin_dir_path(__FILE__) . '../class/AppointmentHelper.php')) {
                        require_once plugin_dir_path(__FILE__) . '../class/AppointmentHelper.php';
                    }
                    $appuntamento = class_exists('AppointmentHelper') ? AppointmentHelper::get_app_data($id_app) : null;
                    if (!$appuntamento) continue;
                    $appuntamento['accettazione_id'] = $p->ID;
                    $appuntamento['data_accettazione'] = get_the_date('Y-m-d', $p->ID);
                    $app_data_list[] = $appuntamento;
                }
            }

            // Ordina per data e ora
            usort($app_data_list, function($a, $b) {
                $dataA = $a['data_accettazione'] ?? '';
                $dataB = $b['data_accettazione'] ?? '';
                if ($dataA === $dataB) {
                    $oraA = $a['ora'] ?? '';
                    $oraB = $b['ora'] ?? '';
                    return strcmp($oraA, $oraB);
                }
                return strcmp($dataA, $dataB);
            });

            // Costruisci HTML tabellare
            $html = '<style>';
            $html .= 'body { font-size: 16px; } table { width: 100%; border-collapse: collapse; margin-bottom: 18px; } th, td { border: 1px solid #888; padding: 8px; font-size: 15px; } th { background: #f0f0f0; }';
            $html .= '</style>';
            $html .= '<h2 style="text-align:center;">Report appuntamenti mese ' . sprintf('%02d', $mese) . '/' . $anno . '</h2>';
            $html .= '<div style="color:#b30000;font-size:13px;text-align:center;margin:12px 0 8px 0;font-weight:bold;">ATTENZIONE: tutti i dati riportati nel presente report potrebbero presentare incongruenze rispetto alla veridicità effettiva delle operazioni svolte e ai tempi conseguiti.</div>';
            $html .= '<table><thead><tr>';
            $html .= '<th>Data</th><th>Ora</th><th>Paziente</th><th>CF</th><th>Prestazione</th><th>Pagato</th><th>Totale</th><th>Account Medico</th><th>Account Infermiere</th><th>Account Admin</th>';
            $html .= '</tr></thead><tbody>';

            if (!empty($app_data_list)) {
                foreach ($app_data_list as $appuntamento) {
                    $nome = $appuntamento['nome'] ?? '';
                    $cognome = $appuntamento['cognome'] ?? '';
                    $cf = $appuntamento['cf'] ?? '';
                    $prestazione = $appuntamento['tipologia'] ?? '';
                    $pagato = ($appuntamento['pagato'] ?? false) ? 'SI' : 'NO';
                    $totale = $appuntamento['totale'] ?? '';
                    $ora = $appuntamento['ora'] ?? '';
                    $data_acc = $appuntamento['data_accettazione'] ?? '';
                    $accettazione_id = $appuntamento['accettazione_id'] ?? null;
                    $medico_user = $accettazione_id ? get_post_meta($accettazione_id, 'medico_user', true) : '';
                    $infermiere_user = $accettazione_id ? get_post_meta($accettazione_id, 'infermiere_user', true) : '';
                    $admin_user = $accettazione_id ? get_post_meta($accettazione_id, 'admin_user', true) : '';
                    if (is_numeric($medico_user) && $medico_user > 0) {
                        $user = get_userdata($medico_user);
                        $medico_user = $user ? $user->display_name : $medico_user;
                    }
                    if (is_numeric($infermiere_user) && $infermiere_user > 0) {
                        $user = get_userdata($infermiere_user);
                        $infermiere_user = $user ? $user->display_name : $infermiere_user;
                    }
                    if (is_numeric($admin_user) && $admin_user > 0) {
                        $user = get_userdata($admin_user);
                        $admin_user = $user ? $user->display_name : $admin_user;
                    }
                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($data_acc) . '</td>';
                    $html .= '<td>' . esc_html($ora) . '</td>';
                    $html .= '<td>' . esc_html($cognome . ' ' . $nome) . '</td>';
                    $html .= '<td>' . esc_html($cf) . '</td>';
                    $html .= '<td>' . esc_html($prestazione) . '</td>';
                    $html .= '<td>' . esc_html($pagato) . '</td>';
                    $html .= '<td>' . esc_html($totale) . '</td>';
                    $html .= '<td>' . esc_html($medico_user) . '</td>';
                    $html .= '<td>' . esc_html($infermiere_user) . '</td>';
                    $html .= '<td>' . esc_html($admin_user) . '</td>';
                    $html .= '</tr>';
                }
            } else {
                $html .= '<tr><td colspan="10" style="text-align:center;">Nessun appuntamento trovato per il mese selezionato.</td></tr>';
            }

            $html .= '</tbody></table>';

            // Riepilogo: conteggio e calcolo tempi per account infermiere e admin su tutto il mese
            $infermiere_count = array();
            $infermiere_tipo_count = array();
            $infermiere_durata = array();
            $infermiere_tipo_durata = array();
            $admin_count = array();
            $admin_durata = array();
            $medico_count = array();
            $medico_durata = array();
            foreach ($app_data_list as $appuntamento) {
                $accettazione_id = $appuntamento['accettazione_id'] ?? null;
                $tipologia = $appuntamento['tipologia'] ?? '';
                // Infermiere
                $infermiere_user = $accettazione_id ? get_post_meta($accettazione_id, 'infermiere_user', true) : '';
                if (is_numeric($infermiere_user) && $infermiere_user > 0) {
                    $user = get_userdata($infermiere_user);
                    $infermiere_user = $user ? $user->display_name : $infermiere_user;
                }
                $apertura_nurse = $accettazione_id ? get_post_meta($accettazione_id, 'nurse_form_open_time', true) : '';
                $chiusura_nurse = $accettazione_id ? get_post_meta($accettazione_id, 'nurse_form_submit_time', true) : '';
                $durata_nurse = ($apertura_nurse && $chiusura_nurse) ? round((strtotime($chiusura_nurse) - strtotime($apertura_nurse)) / 60) : null;
                if (!empty($infermiere_user)) {
                    if (!isset($infermiere_count[$infermiere_user])) $infermiere_count[$infermiere_user] = 0;
                    $infermiere_count[$infermiere_user]++;
                    if (!isset($infermiere_durata[$infermiere_user])) $infermiere_durata[$infermiere_user] = [];
                    if ($durata_nurse !== null && $durata_nurse >= 0) $infermiere_durata[$infermiere_user][] = $durata_nurse;
                    // Per tipologia
                    if (!isset($infermiere_tipo_count[$infermiere_user])) $infermiere_tipo_count[$infermiere_user] = array();
                    if (!isset($infermiere_tipo_count[$infermiere_user][$tipologia])) $infermiere_tipo_count[$infermiere_user][$tipologia] = 0;
                    $infermiere_tipo_count[$infermiere_user][$tipologia]++;
                    if (!isset($infermiere_tipo_durata[$infermiere_user])) $infermiere_tipo_durata[$infermiere_user] = array();
                    if (!isset($infermiere_tipo_durata[$infermiere_user][$tipologia])) $infermiere_tipo_durata[$infermiere_user][$tipologia] = [];
                    if ($durata_nurse !== null && $durata_nurse >= 0) $infermiere_tipo_durata[$infermiere_user][$tipologia][] = $durata_nurse;
                }
                // Admin
                $admin_user = $accettazione_id ? get_post_meta($accettazione_id, 'admin_user', true) : '';
                if (is_numeric($admin_user) && $admin_user > 0) {
                    $user = get_userdata($admin_user);
                    $admin_user = $user ? $user->display_name : $admin_user;
                }
                $apertura_admin = $accettazione_id ? get_post_meta($accettazione_id, 'admin_form_open_time', true) : '';
                $chiusura_admin = $accettazione_id ? get_post_meta($accettazione_id, 'admin_form_submit_time', true) : '';
                $durata_admin = ($apertura_admin && $chiusura_admin) ? round((strtotime($chiusura_admin) - strtotime($apertura_admin)) / 60) : null;
                if (!empty($admin_user)) {
                    if (!isset($admin_count[$admin_user])) $admin_count[$admin_user] = 0;
                    $admin_count[$admin_user]++;
                    if (!isset($admin_durata[$admin_user])) $admin_durata[$admin_user] = [];
                    if ($durata_admin !== null && $durata_admin >= 0) $admin_durata[$admin_user][] = $durata_admin;
                }
                // Medico
                $medico_user = $accettazione_id ? get_post_meta($accettazione_id, 'medico_user', true) : '';
                if (is_numeric($medico_user) && $medico_user > 0) {
                    $user = get_userdata($medico_user);
                    $medico_user = $user ? $user->display_name : $medico_user;
                }
                $apertura_med = $accettazione_id ? get_post_meta($accettazione_id, 'med_form_open_time', true) : '';
                $chiusura_med = $accettazione_id ? get_post_meta($accettazione_id, 'medico_form_submit_time', true) : '';
                $durata_med = ($apertura_med && $chiusura_med) ? round((strtotime($chiusura_med) - strtotime($apertura_med)) / 60) : null;
                if (!empty($medico_user)) {
                    if (!isset($medico_count[$medico_user])) $medico_count[$medico_user] = 0;
                    $medico_count[$medico_user]++;
                    if (!isset($medico_durata[$medico_user])) $medico_durata[$medico_user] = [];
                    if ($durata_med !== null && $durata_med >= 0) $medico_durata[$medico_user][] = $durata_med;
                }
            }
            // Tabella Medici
            $html .= '<table style="width:70%;margin:0 auto 24px auto;border-collapse:collapse;">';
            $html .= '<thead><tr><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">Account Medico</th><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">N. Pazienti</th><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">Media tempo (min)</th></tr></thead><tbody>';
            if (!empty($medico_count)) {
                foreach ($medico_count as $name => $count) {
                    $media = '';
                    if (!empty($medico_durata[$name])) {
                        $media = round(array_sum($medico_durata[$name]) / count($medico_durata[$name]), 1);
                    }
                    $html .= '<tr><td style="border:1px solid #888;padding:8px;">' . esc_html($name) . '</td><td style="border:1px solid #888;padding:8px;text-align:center;">' . intval($count) . '</td><td style="border:1px solid #888;padding:8px;text-align:center;">' . esc_html($media) . '</td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="3" style="text-align:center;">Nessun medico trovato</td></tr>';
            }
            $html .= '</tbody></table>';

            $html .= '<h3 style="margin-top:18px;text-align:center;">Riepilogo pazienti presi in carico</h3>';
            $html .= '<div style="color:#b30000;font-size:13px;text-align:center;margin:12px 0 8px 0;font-weight:bold;">ATTENZIONE: tutti i dati riportati nel presente report potrebbero presentare incongruenze rispetto alla veridicità effettiva delle operazioni svolte e ai tempi conseguiti.</div>';
            $html .= '<table style="width:70%;margin:0 auto 24px auto;border-collapse:collapse;">';
            $html .= '<thead><tr><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">Account Admin</th><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">N. Pazienti</th><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">Media tempo (min)</th></tr></thead><tbody>';
            if (!empty($admin_count)) {
                foreach ($admin_count as $name => $count) {
                    $media = '';
                    if (!empty($admin_durata[$name])) {
                        $media = round(array_sum($admin_durata[$name]) / count($admin_durata[$name]), 1);
                    }
                    $html .= '<tr><td style="border:1px solid #888;padding:8px;">' . esc_html($name) . '</td><td style="border:1px solid #888;padding:8px;text-align:center;">' . intval($count) . '</td><td style="border:1px solid #888;padding:8px;text-align:center;">' . esc_html($media) . '</td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="3" style="text-align:center;">Nessun admin trovato</td></tr>';
            }
            $html .= '</tbody></table>';

            $html .= '<table style="width:90%;margin:0 auto 24px auto;border-collapse:collapse;">';
            $html .= '<thead><tr><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">Account Infermiere</th><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">N. Pazienti</th><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">Media tempo (min)</th><th style="border:1px solid #888;padding:8px;background:#f0f0f0;">Dettaglio per tipologia</th></tr></thead><tbody>';
            if (!empty($infermiere_count)) {
                foreach ($infermiere_count as $name => $count) {
                    $media = '';
                    if (!empty($infermiere_durata[$name])) {
                        $media = round(array_sum($infermiere_durata[$name]) / count($infermiere_durata[$name]), 1);
                    }
                    $tipologie = $infermiere_tipo_count[$name] ?? array();
                    $tipologia_html = '-';
                    if (!empty($tipologie)) {
                        $tipologia_html = '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
                        $tipologia_html .= '<thead><tr><th style="border:1px solid #bbb;padding:4px;background:#f9f9f9;">Tipologia</th><th style="border:1px solid #bbb;padding:4px;background:#f9f9f9;">N.</th><th style="border:1px solid #bbb;padding:4px;background:#f9f9f9;">Media tempo (min)</th></tr></thead><tbody>';
                        foreach ($tipologie as $tipo => $num) {
                            if ($tipo !== '') {
                                $media_tipo = '';
                                if (!empty($infermiere_tipo_durata[$name][$tipo])) {
                                    $media_tipo = round(array_sum($infermiere_tipo_durata[$name][$tipo]) / count($infermiere_tipo_durata[$name][$tipo]), 1);
                                }
                                $tipologia_html .= '<tr><td style="border:1px solid #bbb;padding:4px;">' . esc_html($tipo) . '</td><td style="border:1px solid #bbb;padding:4px;text-align:center;">' . intval($num) . '</td><td style="border:1px solid #bbb;padding:4px;text-align:center;">' . esc_html($media_tipo) . '</td></tr>';
                            }
                        }
                        $tipologia_html .= '</tbody></table>';
                    }
                    $html .= '<tr><td style="border:1px solid #888;padding:8px;">' . esc_html($name) . '</td><td style="border:1px solid #888;padding:8px;text-align:center;">' . intval($count) . '</td><td style="border:1px solid #888;padding:8px;text-align:center;">' . esc_html($media) . '</td><td style="border:1px solid #888;padding:8px;">' . $tipologia_html . '</td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="4" style="text-align:center;">Nessun infermiere trovato</td></tr>';
            }
            $html .= '</tbody></table>';

            $dompdf->setPaper('A4', 'landscape');
            $dompdf->loadHtml($html);
            $dompdf->render();

            $upload_dir = wp_upload_dir();
            $report_dir = $upload_dir['basedir'] . '/report/';
            if (!file_exists($report_dir)) {
                mkdir($report_dir, 0755, true);
            }
            $filename = 'report_mese_' . $anno . sprintf('%02d', $mese) . '_' . date('His') . '.pdf';
            $filepath = $report_dir . $filename;

            file_put_contents($filepath, $dompdf->output());

            return $filepath;
        }
    }
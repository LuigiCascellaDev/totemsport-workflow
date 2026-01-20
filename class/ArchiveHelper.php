<?php

/**
 * Helper per archiviare documenti dei pazienti
 */
class ArchiveHelper
{
    /**
     * Determina la cartella master in base alla sede/staff dell'appuntamento
     */
    public static function get_master_folder($appointment_id)
    {
        $id_app = get_post_meta($appointment_id, 'appointment_id', true);
        if (!$id_app) $id_app = $appointment_id;

        $staff_meta = get_post_meta($id_app, '_appointment_staff_ids', true);
        if (empty($staff_meta)) {
            $staff_meta = get_post_meta($id_app, '_appointment_staff_id', true);
        }

        $ids = maybe_unserialize($staff_meta);
        if (!is_array($ids)) {
            $ids = !empty($ids) ? [$ids] : [];
        }

        $sede = 'bitritto';
        foreach ($ids as $staff_id) {
            $user = get_userdata($staff_id);
            if ($user && !empty($user->display_name)) {
                $name = strtolower($user->display_name);
                if (strpos($name, 'barletta') !== false) {
                    $sede = 'barletta';
                    break;
                } elseif (strpos($name, 'bitritto') !== false) {
                    $sede = 'bitritto';
                    break;
                } else {
                    // Per altre sedi, usa il nome pulito
                    $sede = sanitize_title($user->display_name);
                    break;
                }
            }
        }

        if ($sede === 'bitritto') {
            return 'totemsport-archive';
        }

        return 'totemsport-archive-' . $sede;
    }

    /**
     * Crea la struttura di cartelle per l'archiviazione
     * @param int $appointment_id ID dell'appuntamento confermato
     * @return string Path della cartella creata
     */
    public static function create_patient_folder($appointment_id)
    {
        $id_app = get_post_meta($appointment_id, 'appointment_id', true);
        if (!$id_app) {
            return false;
        }

        $appuntamento = AppointmentHelper::get_app_data($id_app);
        if (!$appuntamento) {
            return false;
        }

        // Ottieni data appuntamento
        $year = date('Y');
        $month = date('m') . '-' . date('F');
        $day = date('d');

        // Crea nome cartella paziente: NOME-COGNOME-CODICEFISCALE
        $nome = strtoupper(sanitize_file_name($appuntamento['nome'] ?? 'SCONOSCIUTO'));
        $cognome = strtoupper(sanitize_file_name($appuntamento['cognome'] ?? 'SCONOSCIUTO'));
        $cf = strtoupper(sanitize_file_name($appuntamento['cf'] ?? 'SCONOSCIUTO'));
        $folder_name = "{$nome}-{$cognome}-{$cf}";

        // Path completo: wp-content/uploads/totemsport-archive(-sede)/YYYY/MM-Month/DD/NOME-COGNOME-CF/
        $upload_dir = wp_upload_dir();
        $master_folder = self::get_master_folder($appointment_id);
        $base_path = $upload_dir['basedir'] . '/' . $master_folder;
        $patient_path = "{$base_path}/{$year}/{$month}/{$day}/{$folder_name}";

        // Crea le cartelle se non esistono
        if (!file_exists($patient_path)) {
            wp_mkdir_p($patient_path);
        }

        return $patient_path;
    }

    /**
     * Salva un documento PDF nella cartella del paziente (locale e Google Drive)
     * @param int $appointment_id ID dell'appuntamento
     * @param string $type Tipo documento (anamnesi, consenso, referto)
     * @param string $html_content Contenuto HTML da convertire in PDF
     * @return string|false Path del file salvato o false
     */
    public static function save_document($appointment_id, $type, $html_content)
    {
        $folder_path = self::create_patient_folder($appointment_id);
        if (!$folder_path) {
            return false;
        }

        // Nome file fisso per tipo: sovrascrive la versione precedente
        $safe_type = sanitize_file_name($type);
        $filename = $safe_type . '.pdf';
        $file_path = $folder_path . '/' . $filename;

        // Rimuovi eventuali vecchie versioni dello stesso tipo (anche quelle con timestamp)
        foreach (glob($folder_path . '/' . $safe_type . '*.pdf') as $old) {
            @unlink($old);
        }

        // Usa PdfHelper per generare il PDF
        try {
            $pdf_content = PdfHelper::generate_pdf_from_html($html_content);
            file_put_contents($file_path, $pdf_content);
            
            // Carica anche su Google Drive
            self::upload_to_google_drive($appointment_id, $file_path, $filename);
            
            return $file_path;
        } catch (Exception $e) {
            error_log("Errore salvataggio PDF: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Carica un file su Google Drive nella struttura gerarchica
     * @param int $appointment_id ID dell'appuntamento
     * @param string $file_path Path locale del file
     * @param string $filename Nome del file
     * @return bool Successo dell'operazione
     */
    private static function upload_to_google_drive($appointment_id, $file_path, $filename)
    {
        // Verifica se GoogleDriveHelper è disponibile
        if (!class_exists('GoogleDriveHelper') || !GoogleDriveHelper::is_configured()) {
            error_log('TotemSport: Google Drive non configurato, skip upload');
            return false;
        }
        
        if (!GoogleDriveHelper::ensure_access_token()) {
            error_log('TotemSport: Google Drive token non valido, upload saltato');
            return false;
        }
        
        $id_app = get_post_meta($appointment_id, 'appointment_id', true);
        if (!$id_app) {
            return false;
        }
        
        $appuntamento = AppointmentHelper::get_app_data($id_app);
        if (!$appuntamento) {
            return false;
        }
        
        // Ottieni parametri per la struttura
        $year = date('Y');
        $date = date('d-m-Y'); // Formato: 09-12-2025 (GG-MM-YYYY)

        // Nome cartella paziente: NOME-COGNOME-CF
        $nome = strtoupper(sanitize_file_name($appuntamento['nome'] ?? 'SCONOSCIUTO'));
        $cognome = strtoupper(sanitize_file_name($appuntamento['cognome'] ?? 'SCONOSCIUTO'));
        $cf = strtoupper(sanitize_file_name($appuntamento['cf'] ?? 'SCONOSCIUTO'));
        $folder_name = "{$nome}-{$cognome}-{$cf}";

        // Determina la cartella master (es. totemsport-archive-barletta)
        $master_folder_name = self::get_master_folder($appointment_id);

        // Crea la struttura delle cartelle su Google Drive SOLO con GG-MM-YYYY
        $gdrive_folder_id = GoogleDriveHelper::create_folder_structure($year, $date, $folder_name, $master_folder_name);
        
        if (!$gdrive_folder_id) {
            error_log('TotemSport: Impossibile creare struttura cartelle su Google Drive');
            return false;
        }
        
        // Carica il file nella cartella del paziente
        $file_id = GoogleDriveHelper::upload_file($file_path, $filename, $gdrive_folder_id);
        
        if ($file_id) {
            error_log("TotemSport: File {$filename} caricato su Google Drive con ID: {$file_id}");
            return true;
        }
        
        return false;
    }

    /**
     * Archivia tutti i documenti di un appuntamento completato
     * @param int $appointment_id ID dell'appuntamento
     * @return array Array con i path dei file salvati
     */
    public static function archive_appointment($appointment_id)
    {
        $saved_files = [];
        error_log("TotemSport: Inizio archiviazione per appointment_id: {$appointment_id}");

        // Salva anamnesi
        error_log("TotemSport: Cerco anamnesi...");
        $anamnesi_html = self::get_document_html($appointment_id, 'anamnesi');
        if ($anamnesi_html) {
            error_log("TotemSport: Anamnesi trovata, salvo documento...");
            $file = self::save_document($appointment_id, 'anamnesi', $anamnesi_html);
            if ($file) {
                $saved_files['anamnesi'] = $file;
                error_log("TotemSport: Anamnesi salvata: {$file}");
            } else {
                error_log("TotemSport: Errore salvataggio anamnesi");
            }
        } else {
            error_log("TotemSport: Anamnesi NON trovata");
        }

        // Salva consenso
        error_log("TotemSport: Cerco consenso...");
        $consenso_html = self::get_document_html($appointment_id, 'consenso');
        if ($consenso_html) {
            error_log("TotemSport: Consenso trovato, salvo documento...");
            $file = self::save_document($appointment_id, 'consenso', $consenso_html);
            if ($file) {
                $saved_files['consenso'] = $file;
                error_log("TotemSport: Consenso salvato: {$file}");
            } else {
                error_log("TotemSport: Errore salvataggio consenso");
            }
        } else {
            error_log("TotemSport: Consenso NON trovato");
        }

        // Salva form infermiere (se esiste)
        error_log("TotemSport: Cerco form infermiere...");
        $nurse_html = self::get_nurse_form_html($appointment_id);
        if ($nurse_html) {
            error_log("TotemSport: Form infermiere trovato, salvo documento...");
            $file = self::save_document($appointment_id, 'form_infermiere', $nurse_html);
            if ($file) {
                $saved_files['nurse_form'] = $file;
                error_log("TotemSport: Form infermiere salvato: {$file}");
            } else {
                error_log("TotemSport: Errore salvataggio form infermiere");
            }
        } else {
            error_log("TotemSport: Form infermiere NON trovato");
        }

        error_log("TotemSport: Archiviazione completata. File salvati: " . count($saved_files));
        // Salva form medico (se esiste)
        error_log("TotemSport: Cerco form medico...");
        $med_html = self::get_med_form_html($appointment_id);
        if ($med_html) {
            error_log("TotemSport: Form medico trovato, salvo documento...");
            $file = self::save_document($appointment_id, 'form_medico', $med_html);
            if ($file) {
                $saved_files['med_form'] = $file;
                error_log("TotemSport: Form medico salvato: {$file}");
            } else {
                error_log("TotemSport: Errore salvataggio form medico");
            }
        } else {
            error_log("TotemSport: Form medico NON trovato");
        }

        // Trigger Event-Driven Sync: schedula un controllo sync tra 1 minuto (se non già schedulato)
        if (!wp_next_scheduled('totemsport_auto_sync')) {
            wp_schedule_single_event(time() + 60, 'totemsport_auto_sync');
            error_log("TotemSport: Sync event-driven schedulato tra 60s");
        }
        
        return $saved_files;
    }
    /**
     * Ottieni HTML del form medico
     */
    private static function get_med_form_html($appointment_id)
    {
        $id_app = get_post_meta($appointment_id, 'appointment_id', true);
        if (!$id_app) return false;

        $appuntamento = AppointmentHelper::get_app_data($id_app);
        if (!$appuntamento) return false;

        $form_id_med = 10; // ID form medico
        $key = 56; // Campo CF nel form medico

        $form = GFAPI::get_form($form_id_med);
        $entries = AppointmentHelper::get_form_data($appuntamento['cf'], $key, $form_id_med, true);
        if (empty($entries)) return false;

        $entry = $entries[0];
        return HtmlHelper::show_html_anamnesi($form, $entry);
    }

    /**
     * Ottieni HTML di un documento
     */
    private static function get_document_html($appointment_id, $type)
    {
        $id_app = get_post_meta($appointment_id, 'appointment_id', true);
        if (!$id_app) return false;

        $appuntamento = AppointmentHelper::get_app_data($id_app);
        if (!$appuntamento) return false;

        if ($type == 'anamnesi') {
            // Anamnesi è sempre form 2; campo CF id 80
                $form_id = 2;
                $form = GFAPI::get_form($form_id);
                // Prima cerca con appointment_id (post confermato)
                $entries = AppointmentHelper::get_form_data($appuntamento['cf'], 80, $form_id, true, $appointment_id);
                // Se non trova, cerca con id_app (post originale)
                if (empty($entries)) {
                    $entries = AppointmentHelper::get_form_data($appuntamento['cf'], 80, $form_id, true, $id_app);
                }
                // Se ancora non trova, cerca solo per codice fiscale
                if (empty($entries)) {
                    $entries = AppointmentHelper::get_form_data($appuntamento['cf'], 80, $form_id, true);
                }
                if (empty($entries)) return false;
                $entry = $entries[0];
        } else if ($type == 'consenso') {
            // Consenso: il form_id è memorizzato nell'appuntamento
            $form_id = intval($appuntamento['form_id'] ?? 4);
            
            // Verifica che il form sia uno dei form consenso (4, 5, 6, 7)
            if (!in_array($form_id, [4, 5, 6, 7])) {
                $form_id = 4; // Default
            }
            
            $form = GFAPI::get_form($form_id);
            
            // Cerca l'entry per CF (campo id 3 nei form consenso)
            $entries = AppointmentHelper::get_form_data($appuntamento['cf'], 3, $form_id, true);
            
            if (empty($entries)) return false;
            $entry = $entries[0];
        } else {
            return false;
        }

        return HtmlHelper::show_html_anamnesi($form, $entry);
    }

    /**
     * Ottieni HTML del form infermiere
     */
    private static function get_nurse_form_html($appointment_id)
    {
        $id_app = get_post_meta($appointment_id, 'appointment_id', true);
        if (!$id_app) return false;

        $appuntamento = AppointmentHelper::get_app_data($id_app);
        if (!$appuntamento) return false;

        $form_id_nurse = 9; // ID form infermiere
        $key = 56; // Campo CF nel form infermiere

        $form = GFAPI::get_form($form_id_nurse);

        // Prima prova con id appuntamento (campo hidden appointment_id, se presente)
        $entries = AppointmentHelper::get_form_data($appointment_id, 'appointment_id', $form_id_nurse, true);
        if (empty($entries)) {
            // Se non trovato, prova con codice fiscale come fallback
            $entries = AppointmentHelper::get_form_data($appuntamento['cf'], $key, $form_id_nurse, true);
        }
        if (empty($entries)) return false;

        $entry = $entries[0];
        return HtmlHelper::show_html_anamnesi($form, $entry);
    }

    /**
     * Ottieni la lista dei file archiviati per un paziente
     * @param int $appointment_id
     * @return array Lista di file con nome e path
     */
    public static function get_archived_files($appointment_id)
    {
        $folder_path = self::create_patient_folder($appointment_id);
        if (!$folder_path || !file_exists($folder_path)) {
            return [];
        }

        $files = [];
        $scan = scandir($folder_path);
        $consenso_files = [];
        $anamnesi_files = [];
        foreach ($scan as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'pdf') {
                if (stripos($file, 'consenso') === 0) {
                    $consenso_files[] = $file;
                } else if (stripos($file, 'anamnesi') === 0) {
                    $anamnesi_files[] = $file;
                } else {
                    $files[] = [
                        'name' => $file,
                        'path' => $folder_path . '/' . $file,
                        'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $folder_path . '/' . $file)
                    ];
                }
            }
        }

        // Se ci sono più consensi, prendi solo il più recente (in base a filemtime)
        if (count($consenso_files) > 0) {
            $latest = $consenso_files[0];
            $latest_time = filemtime($folder_path . '/' . $latest);
            foreach ($consenso_files as $cf) {
                $cf_time = filemtime($folder_path . '/' . $cf);
                if ($cf_time > $latest_time) {
                    $latest = $cf;
                    $latest_time = $cf_time;
                }
            }
            $files[] = [
                'name' => $latest,
                'path' => $folder_path . '/' . $latest,
                'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $folder_path . '/' . $latest)
            ];
        }

        // Se ci sono più anamnesi, prendi solo la più recente (in base a filemtime)
        if (count($anamnesi_files) > 0) {
            $latest = $anamnesi_files[0];
            $latest_time = filemtime($folder_path . '/' . $latest);
            foreach ($anamnesi_files as $af) {
                $af_time = filemtime($folder_path . '/' . $af);
                if ($af_time > $latest_time) {
                    $latest = $af;
                    $latest_time = $af_time;
                }
            }
            $files[] = [
                'name' => $latest,
                'path' => $folder_path . '/' . $latest,
                'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $folder_path . '/' . $latest)
            ];
        }

        return $files;
    }

    /**
     * Ottieni tutte le cartelle archiviate con informazioni
     * @return array Lista di cartelle con dati paziente e file
     */
    public static function get_all_archived_folders()
    {
        $upload_dir = wp_upload_dir();
        $base_folders = glob($upload_dir['basedir'] . '/totemsport-archive*', GLOB_ONLYDIR);
        
        if (empty($base_folders)) {
            return [];
        }

        $folders = [];
        
        foreach ($base_folders as $base_path) {
            // Naviga: YYYY/MM-Month/DD/NOME-COGNOME-CF/
            foreach (glob($base_path . '/*', GLOB_ONLYDIR) as $year_path) {
                foreach (glob($year_path . '/*', GLOB_ONLYDIR) as $month_path) {
                    foreach (glob($month_path . '/*', GLOB_ONLYDIR) as $day_path) {
                        foreach (glob($day_path . '/*', GLOB_ONLYDIR) as $patient_path) {
                            $patient_name = basename($patient_path);
                            $parts = explode('-', $patient_name);
                            
                            $count = count($parts);
                            $cf = '';
                            $nome = '';
                            $cognome = '';

                            if ($count >= 3) {
                                // Assumi che l'ultimo sia il CF
                                $cf = $parts[$count - 1];
                                // Il penultimo sia il Cognome
                                $cognome = $parts[$count - 2];
                                // Tutto il resto sia il Nome (può essere composto da più parti)
                                $nome_parts = array_slice($parts, 0, $count - 2);
                                $nome = implode(' ', $nome_parts);
                            } elseif ($count == 2) {
                                $nome = $parts[0];
                                $cf = $parts[1];
                            } else {
                                $nome = $patient_name;
                            }
                            
                            // Conta i file PDF nella cartella
                            $pdf_files = glob($patient_path . '/*.pdf');
                            
                            $folders[] = [
                                'patient_name' => $patient_name,
                                'nome' => $nome,
                                'cognome' => $cognome,
                                'cf' => $cf,
                                'date' => basename($day_path) . '/' . basename($month_path) . '/' . basename($year_path),
                                'path' => function_exists('wp_normalize_path') ? wp_normalize_path($patient_path) : $patient_path,
                                'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $patient_path),
                                'file_count' => count($pdf_files),
                                'files' => array_map(function($file) use ($upload_dir) {
                                    return [
                                        'name' => basename($file),
                                        'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file),
                                        'size' => filesize($file)
                                    ];
                                }, $pdf_files)
                            ];
                        }
                    }
                }
            }
        
            // Ordina per data (più recenti prima)
            usort($folders, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
            
            return $folders;
        }
    }

    /**
     * Sposta una cartella archivio nel cestino (non elimina subito)
     * @param string $folder_path
     * @return bool
     */
    public static function move_to_trash($folder_path)
    {
        $upload_dir = wp_upload_dir();
        $trash_base = $upload_dir['basedir'] . '/totemsport-archive-trash';
        if (!file_exists($trash_base)) {
            wp_mkdir_p($trash_base);
        }
        if (!file_exists($folder_path) || !is_dir($folder_path)) {
            return false;
        }
        $folder_name = basename($folder_path);
        $parent = basename(dirname($folder_path));
        $trash_folder = $trash_base . '/' . $parent . '-' . $folder_name . '-' . time();
        // Sposta la cartella
        if (@rename($folder_path, $trash_folder)) {
            // Crea file .trashed con timestamp
            file_put_contents($trash_folder . '/.trashed', time());
            // Salva la path originale per il ripristino
            file_put_contents($trash_folder . '/.origpath', $folder_path);
            return true;
        }
        return false;
    }

    /**
     * Svuota il cestino eliminando cartelle più vecchie di 30 giorni
     */
    public static function empty_trash()
    {
        $upload_dir = wp_upload_dir();
        $trash_base = $upload_dir['basedir'] . '/totemsport-archive-trash';
        if (!file_exists($trash_base)) return;
        $now = time();
        foreach (glob($trash_base . '/*', GLOB_ONLYDIR) as $folder) {
            $trashed_file = $folder . '/.trashed';
            $trashed_time = file_exists($trashed_file) ? intval(file_get_contents($trashed_file)) : filemtime($folder);
            if ($now - $trashed_time > 30 * 24 * 3600) {
                self::rrmdir($folder);
            }
        }
    }

    /**
     * Restituisce le cartelle presenti nel cestino
     * @return array
     */
    public static function get_trash_folders()
    {
        $upload_dir = wp_upload_dir();
        $trash_base = $upload_dir['basedir'] . '/totemsport-archive-trash';
        error_log('TotemSport cestino PATH: ' . $trash_base . ' (exists: ' . (file_exists($trash_base) ? 'yes' : 'no') . ')');
        if (!file_exists($trash_base)) return [];
        $folders = [];
        foreach (glob($trash_base . '/*', GLOB_ONLYDIR) as $folder) {
            $trashed_file = $folder . '/.trashed';
            $trashed_time = file_exists($trashed_file) ? intval(file_get_contents($trashed_file)) : filemtime($folder);
            $folders[] = [
                'name' => basename($folder),
                'path' => $folder,
                'trashed_time' => $trashed_time,
                'files' => array_map('basename', glob($folder . '/*.pdf'))
            ];
        }
        return $folders;
    }

    /**
     * Ripristina una cartella dal cestino all'archivio
     * @param string $trash_folder_path
     * @return bool
     */
    public static function restore_from_trash($trash_folder_path)
    {
        $upload_dir = wp_upload_dir();
        $archive_base = $upload_dir['basedir'] . '/totemsport-archive';
        error_log('TotemSport ripristino DEBUG: archive_base=' . $archive_base);
        if (!file_exists($trash_folder_path) || !is_dir($trash_folder_path)) {
            error_log('TotemSport ripristino DEBUG: trash_folder_path non esiste o non è dir: ' . $trash_folder_path);
            return false;
        }
        // Se esiste il file .origpath, usalo come destinazione
        $origpath_file = $trash_folder_path . '/.origpath';
        if (file_exists($origpath_file)) {
            $target = trim(file_get_contents($origpath_file));
            error_log('TotemSport ripristino: uso path originale da .origpath: ' . $target);
        } else {
            $parts = explode('-', basename($trash_folder_path));
            error_log('TotemSport ripristino DEBUG: parts=' . print_r($parts, true));
            if (count($parts) < 3) {
                error_log('TotemSport ripristino DEBUG: parts < 3');
                return false;
            }
            $parent = $parts[0];
            $folder_name = $parts[1];
            error_log('TotemSport ripristino DEBUG: parent=' . $parent . ' folder_name=' . $folder_name);
            $target = $archive_base . '/' . $parent . '/' . $folder_name;
            $i = 1;
            while (file_exists($target)) {
                error_log('TotemSport ripristino DEBUG: target exists ' . $target);
                $target = $archive_base . '/' . $parent . '/' . $folder_name . '-' . $i;
                $i++;
            }
            error_log('TotemSport ripristino DEBUG: final target=' . $target);
        }

        // Check if source exists
        if (!file_exists($trash_folder_path)) {
            error_log('TotemSport ripristino ERROR: sorgente non esiste: ' . $trash_folder_path);
            return false;
        }
        if (!is_dir($trash_folder_path)) {
            error_log('TotemSport ripristino ERROR: sorgente non è una cartella: ' . $trash_folder_path);
            return false;
        }
        // Check if target already exists
        if (file_exists($target)) {
            error_log('TotemSport ripristino ERROR: target già esistente: ' . $target);
            return false;
        }
        // Check if parent dir exists, try to create if not
        $target_parent = dirname($target);
        if (!is_dir($target_parent)) {
            error_log('TotemSport ripristino: target parent non esiste, provo a crearlo: ' . $target_parent);
            if (!mkdir($target_parent, 0777, true)) {
                error_log('TotemSport ripristino ERROR: impossibile creare la cartella padre: ' . $target_parent);
                return false;
            }
        }
        // Check permissions
        if (!is_writable($target_parent)) {
            error_log('TotemSport ripristino ERROR: target parent non scrivibile: ' . $target_parent);
            return false;
        }
        if (!is_writable(dirname($trash_folder_path))) {
            error_log('TotemSport ripristino ERROR: sorgente non scrivibile: ' . dirname($trash_folder_path));
            return false;
        }
        // Try rename
        $result = @rename($trash_folder_path, $target);
        if ($result) {
            error_log('TotemSport ripristino DEBUG: rename OK');
            return true;
        } else {
            error_log('TotemSport ripristino DEBUG: rename FAIL');
            // Extra diagnostics
            error_log('TotemSport ripristino DEBUG: sorgente esiste? ' . (file_exists($trash_folder_path) ? 'SI' : 'NO'));
            error_log('TotemSport ripristino DEBUG: target esiste? ' . (file_exists($target) ? 'SI' : 'NO'));
            error_log('TotemSport ripristino DEBUG: permessi sorgente: ' . substr(sprintf('%o', fileperms($trash_folder_path)), -4));
            error_log('TotemSport ripristino DEBUG: permessi target parent: ' . substr(sprintf('%o', fileperms($target_parent)), -4));
        }
        return false;
    }

    /**
     * Ricorsivamente elimina una cartella
     */
    private static function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    $path = $dir . "/" . $object;
                    if (is_dir($path))
                        self::rrmdir($path);
                    else
                        @unlink($path);
                }
            }
            @rmdir($dir);
        }
    }
}
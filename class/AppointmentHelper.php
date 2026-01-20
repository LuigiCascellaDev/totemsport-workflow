<?php
Class AppointmentHelper {

    public static function get_app_data($idappuntamento)
    {
        $appuntamento = get_post($idappuntamento);
        if (!$appuntamento) return null;

        $prodotto = wc_get_product(get_post_meta($appuntamento->ID, '_appointment_product_id', true));

        $priorita = false;
        $order = wc_get_order($appuntamento->post_parent);

        if ($order) {
            foreach ($order->get_items() as $item) {
                foreach ($item->get_meta_data() as $meta) {

                    // normalizza dash e minuscole
                    $key = strtolower(str_replace(['–','—'], '-', $meta->key));

                    // controllo solo se contiene "accesso prioritario"
                    if (strpos($key, 'accesso prioritario') !== false) {
                        $priorita = true;
                        break 2; // esce dai due foreach
                    }
                }
            }
        }

        $user_id = get_post_meta($appuntamento->ID, '_appointment_customer_id', true);
        $user = get_user_by('id', $user_id);
        
        // Inizializza variabili
        $nome = '';
        $cognome = '';
        $cf = '';
        $dataNascita = '';
        $luogoNascita = '';
        $tipoDoc = '';
        $numDoc = '';
        $is_guest = false;

        if ($user) {
            $nome = get_user_meta($user->ID, 'billing_first_name', true);
            $cognome = get_user_meta($user->ID, 'billing_last_name', true);
            $cf = get_user_meta($user->ID, 'billing_codicefiscale', true);
            $dataNascita = get_user_meta($user->ID, 'billing_datadinascita', true);
            $luogoNascita = get_user_meta($user->ID, 'billing_luogonascita', true);
            $tipoDoc = get_user_meta($user->ID, 'billing_tipodocumento', true);
            $numDoc = get_user_meta($user->ID, 'billing_numerodocumento', true);
        } else {
            // Gestione Guest (recupero da ordine)
            $is_guest = true;
            if ($order) {
                $nome = $order->get_billing_first_name();
                $cognome = $order->get_billing_last_name();
                $cf = $order->get_meta('billing_codicefiscale');
                $dataNascita = $order->get_meta('billing_datadinascita');
                $luogoNascita = $order->get_meta('billing_luogonascita');
                $tipoDoc = $order->get_meta('billing_tipodocumento');
                $numDoc = $order->get_meta('billing_numerodocumento');
            }
        }

        $tipologia = $prodotto ? $prodotto->get_name() : '';
        $data_ora = get_post_meta($appuntamento->ID, '_appointment_start', true);

        $sport = get_post_meta($idappuntamento, 'sport', true);
        if (!$sport && $user) {
            $sport = get_user_meta($user->ID, 'billing_sportpraticato', true);
        }
        if (!$sport && $order) {
            // Cerca tra i meta degli item dell'ordine
            foreach ($order->get_items() as $item) {
                $meta_sport = $item->get_meta('Sport Praticato');
                if ($meta_sport) {
                    $sport = $meta_sport;
                    break;
                }
            }
        }

        $societa = get_post_meta($idappuntamento, 'societa', true);
        if (!$societa && $user) {
            $societa = get_user_meta($user->ID, 'billing_societaappartenza', true);
        }
        if (!$societa && $order) {
            $societa = $order->get_meta('billing_societaappartenza');
        }

        $staff_ids = get_post_meta($appuntamento->ID, '_appointment_staff_ids', true) ?: [];
        if (!is_array($staff_ids)) $staff_ids = [$staff_ids];

        $totale = get_post_meta($idappuntamento, '_custom_totale', true);
        $totaleClean = 0;

        $order_status = '';
        if ($order) {
            $order_status = $order->get_status();
            $totaleClean = $order->get_total();
            $totale = get_post_meta($idappuntamento, '_custom_totale', true) ?? $order->get_total();
        }

        $dati = [
            'nome' => $nome,
            'cognome' => $cognome,
            'cf' => $cf,
            'data' => date("d/m/Y", strtotime($data_ora)),
            'ora' => date("H:i", strtotime($data_ora)),
            'tipologia' => $tipologia,
            'pagato' => $order_status === 'completed',
            'totale' => $totale,
            'totaleClean' => $totaleClean,
            'urine' => get_post_meta($idappuntamento, 'urine', true),
            'minore' => get_post_meta($idappuntamento, 'minore', true),
            'dataNascita' => $dataNascita,
            'luogoNascita' => $luogoNascita,
            'tipoDoc' => $tipoDoc,
            'numDoc' => $numDoc,
            'priorita' => $priorita,
            'sport' => $sport,
            'societa' => $societa,
            'staff_ids' => $staff_ids,
            'is_guest' => $is_guest,
            'first_name' => $nome,
            'last_name' => $cognome,
        ];

        $dati['form_id'] = self::get_form_id($dati);

        return $dati;
    }

    public static function get_all_staff() {
    global $wpdb;

    // Recupera tutti i valori unici dai meta degli appuntamenti
    $staff_values = $wpdb->get_col("
        SELECT DISTINCT meta_value 
        FROM {$wpdb->postmeta} 
        WHERE meta_key IN ('_appointment_staff_id', '_appointment_staff_ids')
    ");

    $all_staff = [];

    foreach ($staff_values as $value) {
        if (empty($value)) continue;

        // Se il meta contiene più ID separati da virgola/serialized, li esplodo
        $ids = maybe_unserialize($value);
        if (!is_array($ids)) $ids = [$ids];

        foreach ($ids as $id) {
            $id = intval($id);
            if ($id && !isset($all_staff[$id])) {
                $user = get_userdata($id);
                if ($user) {
                    $all_staff[$id] = $user->display_name;
                }
            }
        }
    }

    // Ordino alfabeticamente
    asort($all_staff);

    return $all_staff;
}

     private static function get_form_id($appuntamento)
    {
        $form_id = null;

        // normalizza tipologia per rilevare agonistica / non agonistica e under/over
        $tipologia_raw = $appuntamento['tipologia'] ?? '';
        $tipologia = strtolower(trim($tipologia_raw));

        $is_non_agonistica = strpos($tipologia, 'non agon') !== false;
        $is_agonistica = (!$is_non_agonistica && strpos($tipologia, 'agon') !== false);
        
        // Rileva "minore" dal flag
        $is_minor = ($appuntamento['minore'] === 'si');

        if ($is_non_agonistica) {
            $form_id = $is_minor ? 7 : 4; // minore non agonistica / over non agonistica
        } elseif ($is_agonistica) {
            $form_id = $is_minor ? 6 : 5; // minore agonistica / over agonistica
        } elseif (strpos($tipologia, 'concorso') !== false) {
            // Per concorsi
            $form_id = 5; // default agonistica over
        } 

        return $form_id;
    }

    public static function get_form_data($codice_fiscale, $key, $form_id, $only_values = false, $appointment_id = null)
    {

        // Recupera le entries che contengono il CF specificato

        $field_filters = [
            [
                'key' => $key, // <-- ID del campo "Codice Fiscale" nel form
                'value' => $codice_fiscale
            ]
        ];
        // Se viene passato appointment_id, aggiungi filtro
        if ($appointment_id !== null) {
            // Sostituisci 1000 con l'ID effettivo del campo hidden appointment_id nel form Gravity
            $field_filters[] = [
                'key' => 1000, // <-- Cambia se il campo ha un altro ID
                'value' => $appointment_id
            ];
        }
        $search_criteria = [
            'field_filters' => $field_filters
        ];

        // Verifica che GFAPI sia disponibile
        /*  if (!class_exists('GFAPI')) {
        return '<p style="color:red">Errore: Gravity Forms non è disponibile.</p>';
    }*/

        $entries = GFAPI::get_entries($form_id, $search_criteria);

        if ($only_values) {
            return $entries;
        }

        if (!empty($entries) && $codice_fiscale != '') {
            $entry = $entries[0];
            $form = GFAPI::get_form($form_id);
            $values = [];

            foreach ($form['fields'] as &$field) {
                $field_id = $field->id;
                if (isset($entry[$field_id])) {
                    $values = $entry[$field_id];
                }
            }


            return  gravity_form($form, false, false, false, $values, true);
        }
    }

    public static function get_certificato_data($appointment_id) {
        $dati = [];

        // 1. Dati da post appuntamento
        $dati['nome'] = get_post_meta($appointment_id, 'nome', true);
        $dati['cognome'] = get_post_meta($appointment_id, 'cognome', true);
        $dati['cf'] = get_post_meta($appointment_id, 'cf', true);
        $dati['luogo_nascita'] = get_post_meta($appointment_id, 'luogo_nascita', true);
        $dati['data_nascita'] = get_post_meta($appointment_id, 'data_nascita', true);
        $dati['residenza'] = get_post_meta($appointment_id, 'residenza', true);
        $dati['provincia'] = get_post_meta($appointment_id, 'provincia', true);
        $dati['documento'] = get_post_meta($appointment_id, 'documento', true);
        $dati['numero_documento'] = get_post_meta($appointment_id, 'numero_documento', true);

        // 2. Dati da utente collegato
        $user_id = get_post_meta($appointment_id, '_appointment_customer_id', true);
        if ($user_id) {
            $dati['nome'] = $dati['nome'] ?: get_user_meta($user_id, 'billing_first_name', true) ?: get_user_meta($user_id, 'first_name', true);
            $dati['cognome'] = $dati['cognome'] ?: get_user_meta($user_id, 'billing_last_name', true) ?: get_user_meta($user_id, 'last_name', true);
            $dati['cf'] = $dati['cf'] ?: get_user_meta($user_id, 'billing_codicefiscale', true);
            $dati['luogo_nascita'] = $dati['luogo_nascita'] ?: get_user_meta($user_id, 'billing_luogonascita', true) ?: get_user_meta($user_id, 'billing_place_of_birth', true);
            $dati['data_nascita'] = $dati['data_nascita'] ?: get_user_meta($user_id, 'billing_datadinascita', true) ?: get_user_meta($user_id, 'billing_date_of_birth', true);
            $dati['residenza'] = $dati['residenza'] ?: (get_user_meta($user_id, 'billing_address_1', true) . ' - ' . get_user_meta($user_id, 'billing_city', true));
            $dati['provincia'] = $dati['provincia'] ?: get_user_meta($user_id, 'billing_state', true);
            $dati['documento'] = $dati['documento'] ?: get_user_meta($user_id, 'billing_tipodocumento', true) ?: get_user_meta($user_id, 'billing_document_type', true);
            $dati['numero_documento'] = $dati['numero_documento'] ?: get_user_meta($user_id, 'billing_numerodocumento', true) ?: get_user_meta($user_id, 'billing_document_number', true);
        }

        // 3. Dati da ordine (per ospiti)
        $order_item_id = get_post_meta($appointment_id, '_appointment_order_item_id', true);
        if ($order_item_id) {
            global $wpdb;
            $order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d",
                $order_item_id
            ));
            if ($order_id) {
                $dati['nome'] = $dati['nome'] ?: get_post_meta($order_id, '_billing_first_name', true);
                $dati['cognome'] = $dati['cognome'] ?: get_post_meta($order_id, '_billing_last_name', true);
                $dati['cf'] = $dati['cf'] ?: get_post_meta($order_id, 'billing_codicefiscale', true);
                $dati['luogo_nascita'] = $dati['luogo_nascita'] ?: get_post_meta($order_id, 'billing_luogonascita', true) ?: get_post_meta($order_id, 'billing_place_of_birth', true);
                $dati['data_nascita'] = $dati['data_nascita'] ?: get_post_meta($order_id, 'billing_datadinascita', true) ?: get_post_meta($order_id, 'billing_date_of_birth', true);
                $dati['residenza'] = $dati['residenza'] ?: (get_post_meta($order_id, 'billing_address_1', true) . ' ' . get_post_meta($order_id, 'billing_city', true));
                $dati['provincia'] = $dati['provincia'] ?: get_post_meta($order_id, 'billing_state', true);
                $dati['documento'] = $dati['documento'] ?: get_post_meta($order_id, 'billing_tipodocumento', true) ?: get_post_meta($order_id, 'billing_document_type', true);
                $dati['numero_documento'] = $dati['numero_documento'] ?: get_post_meta($order_id, 'billing_numerodocumento', true) ?: get_post_meta($order_id, 'billing_document_number', true);
            }
        }

        $prodotto = wc_get_product(get_post_meta($appointment_id, '_appointment_product_id', true));
        $tipologia = $prodotto ? $prodotto->get_name() : '';
        $dati['tipologia'] = $tipologia;

        error_log('DEBUG_CERTIFICATO: dati recuperati = ' . print_r($dati, true));
        return $dati;
    }

}

<?php
/**
 * GoogleDriveSyncHelper - Sincronizzazione bidirezionale Archivio↔Drive
 */

class GoogleDriveSyncHelper {
    
    /**
     * Sincronizzazione completa bidirezionale
     */
    public static function manual_sync() {
        if (!GoogleDriveHelper::is_configured()) {
            return [
                'success' => false,
                'message' => 'Google Drive non configurato'
            ];
        }
        
        if (!GoogleDriveHelper::ensure_access_token()) {
            return [
                'success' => false,
                'message' => 'Google Drive non autorizzato o token non valido. Riautorizza l\'accesso.',
                'uploaded' => 0,
                'downloaded' => 0,
                'errors' => ['Access token non disponibile: riautorizza Google Drive']
            ];
        }
        
        $uploaded = 0;
        $downloaded = 0;
        $errors = [];
        
        try {
            // 1. Sincronizza Archivio → Drive (upload file mancanti)
            // Cerca tutte le cartelle master locali (totemsport-archive*)
            $upload_base = wp_upload_dir()['basedir'];
            $archive_folders = glob($upload_base . '/totemsport-archive*', GLOB_ONLYDIR);

            foreach ($archive_folders as $folder_path) {
                $master_name = basename($folder_path);
                $sync_result = self::sync_local_to_drive($errors, $folder_path, $master_name);
                $uploaded += $sync_result['uploaded'];
            }
            
            // 2. Sincronizza Drive → Archivio (download file mancanti)
            // $downloaded = self::sync_drive_to_local();
            
            $success = empty($errors);
            $message = $success
                ? "Sincronizzazione completata: $uploaded file caricati su Drive."
                : "Sincronizzazione completata con errori: $uploaded file caricati. Prima anomalia: " . $errors[0];
            
            return [
                'success' => $success,
                'message' => $message,
                'uploaded' => $uploaded,
                'downloaded' => $downloaded,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }
    
    /**
     * Sincronizza Archivio locale → Google Drive
     * Carica i file che esistono in locale ma non su Drive
     */
    private static function sync_local_to_drive(&$errors, $archive_base = null, $master_folder_name = 'totemsport-archive') {
        if ($archive_base === null) {
            $archive_base = wp_upload_dir()['basedir'] . '/totemsport-archive';
        }

        if (!is_dir($archive_base)) {
            return ['uploaded' => 0, 'errors' => $errors];
        }
        $uploaded = 0;
        // Cache temporanea per evitare duplicati
        $day_folders_cache = [];
        $patient_folders_cache = [];

        // 1. Trova o crea la cartella master su Drive (idempotente)
        $master_folder_id = GoogleDriveHelper::find_or_create_folder($master_folder_name, null);
        
        if (!$master_folder_id) {
            $errors[] = "Impossibile trovare o creare la cartella master su Drive ($master_folder_name)";
            return ['uploaded' => 0, 'errors' => $errors];
        }

        // Scansiona la struttura: YYYY/MM-Month/DD/NOME-COGNOME-CF/
        $years = glob($archive_base . '/*', GLOB_ONLYDIR);
        foreach ($years as $year_path) {
            $year = basename($year_path);
            // Crea o trova la cartella anno su Drive
            $year_folder_id = GoogleDriveHelper::find_or_create_folder($year, $master_folder_id);
            if (!$year_folder_id) {
                $errors[] = "Impossibile trovare o creare la cartella anno su Drive ($year)";
                continue;
            }
            $months = glob($year_path . '/*', GLOB_ONLYDIR);
            foreach ($months as $month_path) {
                $month_name = basename($month_path); // es: "12-December"
                $month_number = substr($month_name, 0, 2); // Estrae "12"
                $days = glob($month_path . '/*', GLOB_ONLYDIR);
                foreach ($days as $day_path) {
                    $day = basename($day_path); // es: "09"
                    $date_formatted = $day . '-' . $month_number . '-' . $year; // es: "09-12-2025"
                    // Cache per cartelle giorno
                    $day_cache_key = $year_folder_id . '|' . $date_formatted;
                    if (isset($day_folders_cache[$day_cache_key])) {
                        $day_folder_id = $day_folders_cache[$day_cache_key];
                    } else {
                        $day_folder_id = GoogleDriveHelper::find_or_create_folder($date_formatted, $year_folder_id);
                        if ($day_folder_id) {
                            $day_folders_cache[$day_cache_key] = $day_folder_id;
                        }
                    }
                    if (!$day_folder_id) {
                        $errors[] = "Impossibile trovare o creare la cartella giorno su Drive ($date_formatted)";
                        continue;
                    }
                    $patient_folders = glob($day_path . '/*', GLOB_ONLYDIR);
                    foreach ($patient_folders as $patient_path) {
                        $folder_name = basename($patient_path);
                        // Cache per cartelle paziente
                        $patient_cache_key = $day_folder_id . '|' . $folder_name;
                        if (isset($patient_folders_cache[$patient_cache_key])) {
                            $patient_folder_id = $patient_folders_cache[$patient_cache_key];
                        } else {
                            $patient_folder_id = GoogleDriveHelper::find_or_create_folder($folder_name, $day_folder_id);
                            if ($patient_folder_id) {
                                $patient_folders_cache[$patient_cache_key] = $patient_folder_id;
                            }
                        }
                        if (!$patient_folder_id) {
                            $errors[] = "Impossibile trovare o creare cartella paziente su Drive ($folder_name)";
                            continue;
                        }
                        // Carica tutti i file della cartella paziente
                        $files = glob($patient_path . '/*.{pdf,jpg,png,jpeg}', GLOB_BRACE);
                        foreach ($files as $file_path) {
                            $filename = basename($file_path);
                            // Verifica se il file esiste già su Drive
                            if (self::file_exists_on_drive($patient_folder_id, $filename)) {
                                continue; // Skip se già presente
                            }
                            // Carica il file
                            $file_id = GoogleDriveHelper::upload_file($file_path, $filename, $patient_folder_id);
                            if ($file_id) {
                                $uploaded++;
                                error_log("TotemSport: Caricato $filename su Drive");
                            } else {
                                error_log("TotemSport: Errore caricamento $filename");
                                $errors[] = "Errore caricamento $filename";
                            }
                        }
                    }
                }
            }
        }
        return ['uploaded' => $uploaded, 'errors' => $errors];
    }
    
    /**
     * Verifica se un file esiste già su Google Drive
     */
    private static function file_exists_on_drive($folder_id, $filename) {
        // Cerca tutti i file nella cartella e confronta i nomi in modo robusto
        $query = "'" . $folder_id . "' in parents and trashed=false";
        $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&spaces=drive&fields=files(name,id)';

        $token = GoogleDriveHelper::get_access_token_public();
        if (!$token) {
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            $result = json_decode($response, true);
            if (!empty($result['files'])) {
                $filename_norm = strtolower(trim($filename));
                foreach ($result['files'] as $file) {
                    $drive_name_norm = strtolower(trim($file['name']));
                    if ($filename_norm === $drive_name_norm) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    /**
     * Gestisce i webhook da Google Drive
     */
    public static function handle_webhook($headers) {
        // Valida l'intestazione X-Goog-Channel-ID
        $channel_id = isset($headers['x-goog-channel-id']) ? $headers['x-goog-channel-id'][0] : null;
        
        if (!$channel_id) {
            return false;
        }
        
        // Nel nostro caso, non implementiamo i webhook completi
        // Solo log dell'evento
        error_log('TotemSport: Webhook ricevuto dal Drive');
        
        return true;
    }
    
    /**
     * Registra il webhook (stub - non implementato completamente)
     */
    public static function watch_folder($folder_id) {
        // Questo richiederebbe una implementazione complessa con cURL
        // Per ora è un stub
        error_log('TotemSport: watch_folder called per ' . $folder_id);
        return true;
    }
    
    /**
     * Ferma il monitoraggio
     */
    public static function stop_watch() {
        error_log('TotemSport: stop_watch called');
        return true;
    }
}
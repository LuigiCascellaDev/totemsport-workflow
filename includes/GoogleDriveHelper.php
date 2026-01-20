<?php
/**
 * GoogleDriveHelper - Versione OAuth2 (più semplice)
 * L'utente autorizza l'app una sola volta, niente Service Account
 */

class GoogleDriveHelper {
    
    private static $access_token = null;
    private static $token_expiry = null;
    
    /**
     * Carica il token salvato
     */
    private static function load_token() {
        $token_path = dirname(__FILE__) . '/../credentials/google-token.json';
        
        if (!file_exists($token_path)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($token_path), true);
        if (!$data || !isset($data['access_token'])) {
            return null;
        }
        
        self::$access_token = $data['access_token'];
        self::$token_expiry = $data['expires_at'] ?? 0;
        
        return $data;
    }
    
    /**
     * Salva il token
     */
    private static function save_token($token_data) {
        $token_path = dirname(__FILE__) . '/../credentials/google-token.json';
        
        $data = [
            'access_token' => $token_data['access_token'],
            'refresh_token' => $token_data['refresh_token'] ?? null,
            'expires_at' => time() + ($token_data['expires_in'] ?? 3600)
        ];
        
        file_put_contents($token_path, json_encode($data, JSON_PRETTY_PRINT));
        
        self::$access_token = $data['access_token'];
        self::$token_expiry = $data['expires_at'];
    }
    
    /**
     * Ottiene le credenziali OAuth2
     */
    private static function get_credentials() {
        $cred_path = dirname(__FILE__) . '/../credentials/google-credentials.json';
        
        if (!file_exists($cred_path)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($cred_path), true);
        
        // Supporta sia "installed" che "web"
        if (isset($data['installed'])) {
            return $data['installed'];
        } elseif (isset($data['web'])) {
            return $data['web'];
        }
        
        return $data;
    }
    
    /**
     * Genera URL di autorizzazione
     */
    public static function get_auth_url() {
        $creds = self::get_credentials();
        if (!$creds) {
            return null;
        }
        
        $params = [
            'client_id' => $creds['client_id'],
            'redirect_uri' => admin_url('admin.php?page=totemsport-google-drive'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Scambia il codice di autorizzazione per un access token
     */
    public static function exchange_code($code) {
        $creds = self::get_credentials();
        if (!$creds) {
            return false;
        }
        
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'code' => $code,
                'client_id' => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'redirect_uri' => admin_url('admin.php?page=totemsport-google-drive'),
                'grant_type' => 'authorization_code'
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['access_token'])) {
            self::save_token($result);
            return true;
        }
        
        error_log('TotemSport OAuth error: ' . $response);
        return false;
    }
    
    /**
     * Rinnova l'access token usando il refresh token
     */
    private static function refresh_token() {
        $token_data = self::load_token();
        if (!$token_data || !isset($token_data['refresh_token'])) {
            error_log('TotemSport: refresh_token fallito - token mancante o senza refresh_token');
            return false;
        }
        
        $creds = self::get_credentials();
        if (!$creds) {
            error_log('TotemSport: refresh_token fallito - credenziali mancanti');
            return false;
        }
        
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'refresh_token' => $token_data['refresh_token'],
                'grant_type' => 'refresh_token'
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($err) {
            error_log('TotemSport: refresh_token cURL error: ' . $err);
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['access_token'])) {
            $result['refresh_token'] = $token_data['refresh_token']; // Mantieni il refresh token
            self::save_token($result);
            return true;
        }
        error_log('TotemSport: refresh_token fallito - risposta (' . $code . '): ' . $response);
        
        return false;
    }
    
    /**
     * Ottiene access token valido
     */
    private static function get_access_token() {
        // Carica token se non in memoria
        if (!self::$access_token) {
            self::load_token();
        }
        
        // Se il token è ancora valido, usalo
        if (self::$access_token && self::$token_expiry && time() < self::$token_expiry - 300) {
            return self::$access_token;
        }
        
        // Prova a rinnovare
        if (self::refresh_token()) {
            return self::$access_token;
        }
        error_log('TotemSport: impossibile ottenere access token (nessun token valido/refresh fallito)');
        
        return null;
    }
    
    /**
     * Assicura la presenza di un access token valido (effettua il refresh se serve)
     */
    public static function ensure_access_token() {
        return (bool) self::get_access_token();
    }
    
    /**
     * Ottiene l'access token per uso esterno (sincronizzazione)
     */
    public static function get_access_token_public() {
        return self::get_access_token();
    }
    
    /**
     * Chiama l'API di Google Drive
     */
    private static function api_call($method, $url, $data = null) {
        $token = self::get_access_token();
        if (!$token) {
            return null;
        }
        
        $ch = curl_init($url);
        $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
        
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => $method
        ];
        
        if ($data) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($ch, $opts);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) {
            error_log('TotemSport cURL: ' . $err);
            return null;
        }
        
        if ($code < 200 || $code >= 300) {
            error_log("TotemSport API ($code): $response");
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Cerca una cartella o la crea
     */
    public static function find_or_create_folder($name, $parent_id = null) {
        $query = "name='" . addslashes($name) . "' and trashed=false and mimeType='application/vnd.google-apps.folder'";
        if ($parent_id) {
            $query .= " and '$parent_id' in parents";
        }
        
        $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&spaces=drive&pageSize=1&fields=files(id)';
        $res = self::api_call('GET', $url);
        
        if ($res && !empty($res['files'])) {
            return $res['files'][0]['id'];
        }
        
        // Crea
        $data = ['name' => $name, 'mimeType' => 'application/vnd.google-apps.folder'];
        if ($parent_id) {
            $data['parents'] = [$parent_id];
        }
        
        $res = self::api_call('POST', 'https://www.googleapis.com/drive/v3/files?fields=id', $data);
        return $res['id'] ?? null;
    }
    
    /**
     * Crea la struttura di cartelle (YYYY/DD-MM-YYYY/NOME-COGNOME-CF)
     */
    public static function create_folder_structure($year, $date, $folder_name, $master_folder_name = 'totemsport-archive') {
        try {
            $root = self::find_or_create_folder($master_folder_name);
            if (!$root) return null;
            
            $year_folder = self::find_or_create_folder($year, $root);
            if (!$year_folder) return null;
            
            $date_folder = self::find_or_create_folder($date, $year_folder);
            if (!$date_folder) return null;
            
            $patient = self::find_or_create_folder($folder_name, $date_folder);
            return $patient;
        } catch (Exception $e) {
            error_log('TotemSport create_folder_structure: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Carica un file
     */
    public static function upload_file($filepath, $filename, $folder_id) {
        if (!file_exists($filepath)) {
            return null;
        }
        
        $content = file_get_contents($filepath);
        if (!$content) {
            return null;
        }
        
        $token = self::get_access_token();
        if (!$token) {
            return null;
        }
        
        $boundary = '===============' . md5(rand()) . '==';
        $metadata = ['name' => $filename, 'parents' => [$folder_id]];
        
        $body = "--$boundary\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= json_encode($metadata) . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= $content . "\r\n";
        $body .= "--$boundary--";
        
        $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: multipart/related; boundary="' . $boundary . '"'
            ],
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code < 200 || $code >= 300) {
            error_log("TotemSport upload ($code): $response");
            return null;
        }
        
        $result = json_decode($response, true);
        return $result['id'] ?? null;
    }
    
    /**
     * Verifica se è configurato (credenziali + token OAuth)
     */
    public static function is_configured() {
        $cred_path = dirname(__FILE__) . '/../credentials/google-credentials.json';
        $token_path = dirname(__FILE__) . '/../credentials/google-token.json';
        
        if (!file_exists($cred_path) || !file_exists($token_path)) {
            return false;
        }
        
        $token_data = json_decode(file_get_contents($token_path), true);
        if (!$token_data || empty($token_data['refresh_token'])) {
            return false;
        }
        
        return true;
    }
}

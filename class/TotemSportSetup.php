<?php
/**
 * Setup Wizard per TotemSport
 * Installa tutto automaticamente senza interventi manuali
 */

class TotemSportSetup {
    
    private static $setup_lock = 'totemsport_setup_lock';
    private static $setup_status = 'totemsport_setup_status';
    
    /**
     * Inizia il setup quando il plugin viene attivato
     */
    public static function init_setup() {
        // Evita esecuzioni multiple
        if (get_transient(self::$setup_lock)) {
            return;
        }
        
        set_transient(self::$setup_lock, true, 300); // 5 minuti
        
        // Avvia lo setup in background
        self::async_setup();
    }
    
    /**
     * Esegue lo setup in background
     */
    public static function async_setup() {
        $status = [
            'step' => 'starting',
            'messages' => [],
            'errors' => [],
            'completed' => false
        ];
        
        try {
            // Step 1: Verifica e installa Composer
            $status['step'] = 'composer_check';
            $status['messages'][] = 'Verifica Composer...';
            
            if (!self::check_and_install_composer()) {
                $status['errors'][] = 'Impossibile verificare Composer, ma il plugin continuerà a funzionare.';
            } else {
                $status['messages'][] = '✓ Composer trovato/installato';
            }
            
            // Step 2: Installa dipendenze
            $status['step'] = 'install_dependencies';
            $status['messages'][] = 'Installazione dipendenze...';
            
            if (self::install_dependencies()) {
                $status['messages'][] = '✓ Dipendenze installate';
            } else {
                $status['messages'][] = '⚠ Dipendenze non installate, ma il plugin funzionerà senza Google Drive';
            }
            
            // Step 3: Crea cartelle necessarie
            $status['step'] = 'create_folders';
            $status['messages'][] = 'Creazione cartelle...';
            self::create_required_folders();
            $status['messages'][] = '✓ Cartelle create';
            
            // Step 4: Verifica permessi
            $status['step'] = 'check_permissions';
            $status['messages'][] = 'Verifica permessi...';
            self::verify_permissions();
            $status['messages'][] = '✓ Permessi verificati';
            
            $status['completed'] = true;
            $status['step'] = 'completed';
            $status['messages'][] = '✓ Setup completato con successo!';
            
        } catch (Exception $e) {
            $status['errors'][] = $e->getMessage();
        }
        
        // Salva lo stato
        update_option(self::$setup_status, $status);
        
        // Log
        error_log('TotemSport Setup: ' . wp_json_encode($status));
    }
    
    /**
     * Verifica e installa Composer se necessario
     */
    private static function check_and_install_composer() {
        $composer_path = self::find_composer();
        
        if ($composer_path) {
            error_log('TotemSport: Composer found at ' . $composer_path);
            return true;
        }
        
        // Prova ad installare composer
        return self::install_composer();
    }
    
    /**
     * Trova Composer nel sistema
     */
    private static function find_composer() {
        $possible_paths = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            '/opt/cpanel/composer/bin/composer',
            getenv('HOME') . '/.composer/vendor/bin/composer',
        ];
        
        // Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $possible_paths = array_merge($possible_paths, [
                'C:\\ProgramData\\ComposerSetup\\bin\\composer.bat',
                'C:\\tools\\composer\\composer.bat',
            ]);
        }
        
        foreach ($possible_paths as $path) {
            if (@file_exists($path) && @is_executable($path)) {
                return $path;
            }
        }
        
        // Cerca nel PATH
        $output = @shell_exec(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 
            'where composer.bat 2>&1' : 'which composer 2>&1');
        
        if ($output && trim($output)) {
            return trim($output);
        }
        
        return false;
    }
    
    /**
     * Installa Composer
     */
    private static function install_composer() {
        $plugin_dir = dirname(dirname(__FILE__));
        $composer_phar = $plugin_dir . '/composer.phar';
        
        // Scarica composer.phar se non esiste
        if (!file_exists($composer_phar)) {
            $result = @copy(
                'https://getcomposer.org/composer.phar',
                $composer_phar
            );
            
            if (!$result) {
                error_log('TotemSport: Could not download composer.phar');
                return false;
            }
        }
        
        // Imposta permessi di esecuzione
        @chmod($composer_phar, 0755);
        
        return file_exists($composer_phar);
    }
    
    /**
     * Installa le dipendenze Composer
     */
    private static function install_dependencies() {
        $plugin_dir = dirname(dirname(__FILE__));
        $composer_json = $plugin_dir . '/composer.json';
        $vendor_autoload = $plugin_dir . '/vendor/autoload.php';
        
        // Se già installate, skip
        if (file_exists($vendor_autoload)) {
            return true;
        }
        
        if (!file_exists($composer_json)) {
            error_log('TotemSport: composer.json not found');
            return false;
        }
        
        $composer_path = self::find_composer();
        
        // Fallback a composer.phar se Composer non trovato
        if (!$composer_path) {
            $composer_phar = $plugin_dir . '/composer.phar';
            if (file_exists($composer_phar)) {
                $composer_path = 'php ' . escapeshellarg($composer_phar);
            }
        }
        
        if (!$composer_path) {
            error_log('TotemSport: No composer found to execute');
            return false;
        }
        
        // Costruisci il comando
        $command = sprintf(
            'cd %s && %s install --no-dev --optimize-autoloader --no-interaction 2>&1',
            escapeshellarg($plugin_dir),
            $composer_path
        );
        
        // Esegui composer install
        $output = @shell_exec($command);
        
        if ($output) {
            error_log('TotemSport Composer: ' . $output);
        }
        
        // Verifica se è andato a buon fine
        $success = file_exists($vendor_autoload);
        
        if ($success) {
            error_log('TotemSport: Dependencies installed successfully');
        } else {
            error_log('TotemSport: Failed to install dependencies');
        }
        
        return $success;
    }
    
    /**
     * Crea le cartelle necessarie
     */
    private static function create_required_folders() {
        $plugin_dir = dirname(dirname(__FILE__));
        
        $folders = [
            $plugin_dir . '/credentials',
            $plugin_dir . '/includes',
            wp_upload_dir()['basedir'] . '/totemsport-archive',
        ];
        
        foreach ($folders as $folder) {
            if (!file_exists($folder)) {
                @mkdir($folder, 0755, true);
            }
        }
    }
    
    /**
     * Verifica i permessi delle cartelle
     */
    private static function verify_permissions() {
        $plugin_dir = dirname(dirname(__FILE__));
        
        $folders = [
            $plugin_dir . '/credentials' => 0755,
            $plugin_dir . '/includes' => 0755,
        ];
        
        foreach ($folders as $folder => $perms) {
            if (file_exists($folder)) {
                @chmod($folder, $perms);
            }
        }
    }
    
    /**
     * Mostra lo stato dello setup nel pannello admin
     */
    public static function admin_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $status = get_option(self::$setup_status);
        
        if (!$status) {
            return;
        }
        
        $class = $status['completed'] ? 'notice-success' : 'notice-warning';
        $icon = $status['completed'] ? '✓' : '⚠';
        
        ?>
        <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
            <p>
                <strong><?php echo esc_html($icon . ' TotemSport Setup:'); ?></strong><br>
                <?php 
                foreach ($status['messages'] as $msg) {
                    echo esc_html($msg) . '<br>';
                }
                foreach ($status['errors'] as $error) {
                    echo '<span style="color: red;">✗ ' . esc_html($error) . '</span><br>';
                }
                ?>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=totemsport-google-drive')); ?>" class="button button-primary">
                    Verifica Stato
                </a>
            </p>
        </div>
        <?php
        
        // Mostra una sola volta
        delete_option(self::$setup_status);
    }
}

// Hook di attivazione
add_action('totemsport_activate', ['TotemSportSetup', 'init_setup']);

// Mostra avviso admin
add_action('admin_notices', ['TotemSportSetup', 'admin_notice']);

<?php

/**
 * Plugin Name: TotemSport
 * Plugin URI:  https://example.com/totemsport
 * Description: Fornisce un'area per tutti gli utenti e un'area riservata all'amministratore.
 * Version:     1.0.0
 * Author:      Autore
 * Text Domain: totemsport
 * Domain Path: /languages
 */
session_start();

// Includi lo script di attivazione e setup
require_once plugin_dir_path(__FILE__) . 'activation.php';
require_once plugin_dir_path(__FILE__) . 'class/TotemSportSetup.php';
require_once plugin_dir_path(__FILE__) . 'class/SearchHelper.php';
require_once plugin_dir_path(__FILE__) . 'class/AppointmentHelper.php';
require_once plugin_dir_path(__FILE__) . 'class/HtmlHelper.php';
require_once plugin_dir_path(__FILE__) . 'class/PdfHelper.php';
require_once plugin_dir_path(__FILE__) . 'class/ArchiveHelper.php';
require_once plugin_dir_path(__FILE__) . 'includes/GoogleDriveHelper.php';
require_once plugin_dir_path(__FILE__) . 'includes/GoogleDriveSyncHelper.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TotemSport {
            
        // --- AJAX: stato attuale stampa ECR POS Nexi ---
        public function totemsport_pos_get_ecr_print_status() {
            //verifico se l'utente ha permessi amministratore
            if (!current_user_can('manage_options')) {
                header('Location: ' . home_url());
                exit;
            }
            // Comando Nexi: codice operazione 'Q' (ipotetico, da confermare con manuale Nexi)
            // Se il protocollo non prevede un comando di query, si può memorizzare lo stato localmente dopo ogni set.
            // Qui si mostra un esempio che tenta la query, ma se il POS non supporta, restituire errore.
            $terminal_id = str_pad('1', 8, '0', STR_PAD_LEFT);
            $reserved1 = '0';
            $msg_code = 'Q'; // Q = Query stato stampa (ipotetico, da confermare)
            $appmsg = $terminal_id . $reserved1 . $msg_code;
            $response = $this->send_nexi_pos_command($appmsg, true);
            if (is_wp_error($response)) {
                wp_send_json_error(['message' => $response->get_error_message()]);
            }
            // Decodifica risposta: ipotizziamo che il flag sia in posizione 10 (dopo header)
            $flag = null;
            if (strlen($response) > 10) {
                $flag = substr($response, 10, 1);
            }
            $abilitata = ($flag === '1');
            wp_send_json_success([
                'enabled' => $abilitata,
                'raw_response' => bin2hex($response),
                'flag' => $flag,
            ]);
        }
    // AJAX: archivia l'anamnesi se confermata da review
    public function totemsport_archive_anamnesi() {
        if (!isset($_POST['appointment_id']) || !is_numeric($_POST['appointment_id'])) {
            wp_send_json_error(['msg' => 'ID appuntamento mancante']);
        }
        $appointment_id = intval($_POST['appointment_id']);
        require_once plugin_dir_path(__FILE__) . 'class/ArchiveHelper.php';
        $result = ArchiveHelper::archive_appointment($appointment_id);
        if ($result !== false) {
            wp_send_json_success(['msg' => 'Anamnesi archiviata', 'files' => $result]);
        } else {
            wp_send_json_error(['msg' => 'Errore archiviazione']);
        }
        wp_die();
    }

    private $pos_bridge_url;
    private $pos_config_option;
    private $cashmatic_base_url;
    private $cashmatic_username;
    private $cashmatic_password;
    private $cashmatic_token_option;
    private $cashmatic_config_option;
    private $f24_api_key;
    private $f24_api_key_option;
    private $f24_endpoint;
    

    public function __construct() {

        $this->pos_config_option = 'totemsport_pos_config';
        $this->pos_bridge_url = apply_filters('totemsport_pos_bridge_url', 'http://185.198.119.196:65113');
        // Cashmatic config (LAN endpoint)
        $this->cashmatic_base_url = 'https://192.168.1.175:50301/api/';
        $this->cashmatic_username = 'LuigiC';
        $this->cashmatic_password = 'Alfa4c56t#';
        $this->cashmatic_token_option = 'totemsport_cashmatic_token';
        $this->cashmatic_config_option = 'totemsport_cashmatic_config';
        
        $this->f24_api_key = '28MadNJ4soazbvclYmkxmIiiu8CMgqKB';
        $this->f24_api_key_option = 'totemsport_f24_api_key';
        $this->f24_endpoint = 'https://www.app.fattura24.com/api/v0.3/SaveDocument';

        // Sovrascrivi con configurazione salvata
        $cashmatic_cfg = get_option($this->cashmatic_config_option, array());
        if (!empty($cashmatic_cfg['base_url'])) {
            $this->cashmatic_base_url = trailingslashit($cashmatic_cfg['base_url']);
        }
        if (!empty($cashmatic_cfg['username'])) {
            $this->cashmatic_username = $cashmatic_cfg['username'];
        }
        if (!empty($cashmatic_cfg['password'])) {
            $this->cashmatic_password = $cashmatic_cfg['password'];
        }

        $saved_f24 = get_option($this->f24_api_key_option, '');
        if (!empty($saved_f24)) {
            $this->f24_api_key = $saved_f24;
        }

        // Override POS bridge URL da opzioni, se presenti
        $pos_cfg = get_option($this->pos_config_option, array());
        if (!empty($pos_cfg['base_url'])) {
            $this->pos_bridge_url = $pos_cfg['base_url'];
        }

        add_action('init', array($this, 'register_shortcodes'));
        add_action('admin_menu', array($this, 'admin_menu'));
        //   add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'public_assets'));
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
        add_action('wp_ajax_nopriv_totemsport_cf_search', array($this, 'totemsport_cf_search'));
        add_action('wp_ajax_totemsport_cf_search', array($this, 'totemsport_cf_search'));

        add_action('wp_ajax_nopriv_totemsport_get_app_data', array($this, 'totemsport_get_app_data'));
        add_action('wp_ajax_totemsport_get_app_data', array($this, 'totemsport_get_app_data'));

        //creo un custom post type per gli appuntamenti confermati
        add_action('init', array($this, 'registra_custom_post_type'));
        add_filter("gform_pre_render_anamnesi", array($this, 'popola_gform'), 10, 2);
        add_action('gform_after_submission_2', array($this, 'aggiorna_entry'));

        add_action('wp_ajax_totemsport_get_app_bo', array($this, 'totemsport_get_app_bo'));
        add_action('wp_ajax_totemsport_get_doc_bo', array($this, 'totemsport_get_doc_bo'));
        add_action('wp_ajax_totemsport_admin_close', array($this, 'totemsport_admin_close'));
        
        // Archive endpoints
        add_action('wp_ajax_totemsport_archive_appointment', array($this, 'totemsport_archive_appointment'));
        add_action('wp_ajax_totemsport_archive_anamnesi', array($this, 'totemsport_archive_anamnesi'));
        add_action('wp_ajax_totemsport_get_archived_folders', array($this, 'totemsport_get_archived_folders'));
        add_action('wp_ajax_totemsport_delete_archive_folder', array($this, 'totemsport_delete_archive_folder'));
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        add_action('wp_ajax_totemsport_trash_archive_folder', function() {
            check_ajax_referer('totemsport_delete_nonce', 'nonce');
            $path = isset($_POST['path']) ? $_POST['path'] : '';
            if (!$path || !file_exists($path)) {
                wp_send_json_error(['message' => 'Cartella non trovata']);
            }
            require_once plugin_dir_path(__FILE__) . 'class/ArchiveHelper.php';
            if (ArchiveHelper::move_to_trash($path)) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Errore spostamento nel cestino']);
            }
        });
        add_action('wp_ajax_totemsport_get_trash_folders', function() {
            require_once plugin_dir_path(__FILE__) . 'class/ArchiveHelper.php';
            $folders = ArchiveHelper::get_trash_folders();
            error_log('TotemSport cestino AJAX: ' . print_r($folders, true));
            wp_send_json_success(['folders' => $folders]);
        });
        add_action('wp_ajax_totemsport_restore_trash_folder', function() {
            check_ajax_referer('totemsport_delete_nonce', 'nonce');
            $path = isset($_POST['path']) ? $_POST['path'] : '';
            error_log('TotemSport ripristino richiesto: ' . $path);
            if (!$path || !file_exists($path)) {
                error_log('TotemSport ripristino ERRORE: cartella non trovata');
                wp_send_json_error(['message' => 'Cartella non trovata']);
            }
            require_once plugin_dir_path(__FILE__) . 'class/ArchiveHelper.php';
            $result = ArchiveHelper::restore_from_trash($path);
            error_log('TotemSport ripristino risultato: ' . var_export($result, true));
            if ($result) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Errore ripristino']);
            }
        });
        add_action('wp_ajax_totemsport_delete_trash_folder', function() {
            check_ajax_referer('totemsport_delete_nonce', 'nonce');
            $path = isset($_POST['path']) ? $_POST['path'] : '';
            if (!$path || !file_exists($path)) {
                wp_send_json_error(['message' => 'Cartella non trovata']);
            }
            require_once plugin_dir_path(__FILE__) . 'class/ArchiveHelper.php';
            // Usa la funzione helper pubblica deleteDirectory della classe TotemSport
            if (method_exists('TotemSport', 'deleteDirectory')) {
                TotemSport::deleteDirectory($path);
            } else {
                $deleteDir = function($dir) {
                    if (!is_dir($dir)) return false;
                    $files = scandir($dir);
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..') {
                            $path = $dir . '/' . $file;
                            if (is_dir($path)) {
                                call_user_func(__FUNCTION__, $path);
                            } else {
                                @unlink($path);
                            }
                        }
                    }
                    return @rmdir($dir);
                };
                $deleteDir($path);
            }
            wp_send_json_success();
        });
        
        // Nurse area endpoints
        add_action('wp_ajax_totemsport_get_nurse_apps', array($this, 'totemsport_get_nurse_apps'));
        add_action('wp_ajax_totemsport_nurse_complete', array($this, 'totemsport_nurse_complete'));

        // Hook AJAX per salvataggio totale custom
        add_action('wp_ajax_totemsport_save_custom_total', array($this, 'totemsport_save_custom_total'));
        
        // Sincronizzazione automatica Google Drive
        add_action('totemsport_auto_sync', array($this, 'auto_sync_drive'));
        
        // Schedula sync automatico se non esiste
        // Schedula sync automatico se non esiste
        /* Rimosso per ottimizzazione event-driven
        $next_auto_sync = wp_next_scheduled('totemsport_auto_sync');
        // Se non programmato o la prossima esecuzione è oltre 30 secondi, riprogramma a 30 secondi
        if (!$next_auto_sync || ($next_auto_sync - time()) > 90) {
            wp_clear_scheduled_hook('totemsport_auto_sync');
            wp_schedule_event(time(), 'totemsport_90sec', 'totemsport_auto_sync');
        }
        */

        add_action('wp_ajax_totemsport_delete_appointment', array($this, 'totemsport_delete_appointment'));

        // Med area endpoints
        add_action('wp_ajax_totemsport_get_med_apps', array($this, 'totemsport_get_med_apps'));
        add_action('wp_ajax_totemsport_get_nurse_form', array($this, 'totemsport_get_nurse_form'));
        add_action('wp_ajax_totemsport_med_complete', array($this, 'totemsport_med_complete'));
        add_action('wp_ajax_totemsport_get_certificato_med', array($this, 'totemsport_get_certificato_med'));

        // Admin close actions (modal)
        add_action('wp_ajax_totemsport_get_close_actions', array($this, 'totemsport_get_close_actions'));
        add_action('wp_ajax_totemsport_pos_payment', array($this, 'totemsport_pos_payment'));
        add_action('wp_ajax_totemsport_cashmatic_active', array($this, 'totemsport_cashmatic_active'));
        add_action('wp_ajax_totemsport_cashmatic_last', array($this, 'totemsport_cashmatic_last'));
        add_action('wp_ajax_totemsport_cashmatic_cancel', array($this, 'totemsport_cashmatic_cancel'));
        add_action('wp_ajax_totemsport_f24_receipt', array($this, 'totemsport_f24_receipt'));
        add_action('wp_ajax_totemsport_generate_certificato', array($this, 'totemsport_generate_certificato'));
        add_action('init', callback: array($this, 'print_label_handler'));

        // Endpoint AJAX per generazione PDF report giornata
        add_action('wp_ajax_totemsport_generate_report_giornata_pdf', array($this, 'totemsport_generate_report_giornata_pdf'));
        add_action('wp_ajax_totemsport_generate_report_mese_pdf', array($this, 'totemsport_generate_report_mese_pdf'));

        // Endpoint AJAX per salvataggio orario di apertura istanza
        add_action('wp_ajax_totemsport_save_open_time', array($this, 'totemsport_save_open_time'));
        
        // POS Nexi actions
        add_action('wp_ajax_totemsport_pos_card_verification', array($this, 'totemsport_pos_card_verification'));
        add_action('wp_ajax_totemsport_pos_get_ecr_print_status', array($this, 'totemsport_pos_get_ecr_print_status'));
        add_action('wp_ajax_nopriv_totemsport_archive_anamnesi', array($this, 'totemsport_archive_anamnesi'));

        // Mostra il link di autorizzazione Google Drive in admin (solo per admin)
        add_action('admin_notices', function() {
            if (!current_user_can('manage_options')) return;
            if (!class_exists('GoogleDriveHelper')) return;
            $auth_url = call_user_func(['GoogleDriveHelper', 'get_auth_url']);
            if ($auth_url) {
                echo '<div class="notice notice-info"><p><strong>Google Drive:</strong> <a href="' . esc_url($auth_url) . '" target="_blank">Clicca qui per autorizzare l\'accesso a Google Drive</a></p></div>';
            }
        });

    }


    public function totemsport_get_close_actions()
    {
    {
        // Solo admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }

        $conf_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        if (!$conf_id) {
            wp_send_json_error('ID appuntamento mancante');
            wp_die();
        }

        // ID appuntamento originale (wc appointment) per la stampa
        $app_id = intval(get_post_meta($conf_id, 'appointment_id', true));
        if (!$app_id) {
            $app_id = $conf_id; // fallback
        }

        $app_data = AppointmentHelper::get_app_data($app_id);
        $cert_data = AppointmentHelper::get_certificato_data($app_id);

        $amount_total = isset($app_data['totale']) ? floatval($app_data['totaleClean']) : 0;

        // Consenti importo sovrascritto dalla modale (input admin)
        $amount_override_raw = isset($_POST['amount']) ? str_replace(',', '.', $_POST['amount']) : '';
        if ($amount_override_raw !== '') {
            $amount_total = floatval($amount_override_raw);
        }
        $amount_attr = number_format($amount_total, 2, '.', '');
        $amount_display = '€' . number_format($amount_total, 2, ',', '.');
        $pagato_raw = !empty($app_data['pagato']);

        // Urine flag: controlla su più fonti (post confermato, post appuntamento, meta confermato)
        $urine_raw = get_post_meta($conf_id, 'urine', true);
        if ($urine_raw === '' || $urine_raw === null) {
            $urine_raw = get_post_meta($conf_id, 'urine_radio', true);
        }
        if ($urine_raw === '' || $urine_raw === null) {
            $urine_raw = get_post_meta($app_id, 'urine', true);
        }
        if ($urine_raw === '' || $urine_raw === null) {
            $urine_raw = get_post_meta($app_id, 'urine_radio', true);
        }
        if ($urine_raw === '' || $urine_raw === null) {
            $meta_conf = SearchHelper::get_confirmed_meta($conf_id);
            if (is_array($meta_conf) && isset($meta_conf['urine'])) {
                $urine_raw = $meta_conf['urine'];
            }
        }

        // Normalizza il flag (case-insensitive, accetta 1/true/sì)
        $urine_flag = false;
        if (!empty($urine_raw)) {
            $val = strtolower(trim((string)$urine_raw));
            $urine_flag = in_array($val, array('si', 'sì', '1', 'yes', 'true'), true);
        }

        // Build HTML con stile moderno (inserito inline per evitare dipendenze)
        $html = '<style id="totemsport-close-styles">'
            . '#modal-close-actions .modal-content{border-radius:14px;border:none;box-shadow:0 14px 36px rgba(15,23,42,.22);background:linear-gradient(135deg,#f8fafc 0%,#e8ecf3 100%);}'
            . '.close-actions-content{padding:6px 4px;color:#0f172a;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}'
            . '.payment-section{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;box-shadow:0 8px 22px rgba(15,23,42,.08);margin-top:8px;}'
            . '.payment-amount{font-size:15px;margin-bottom:10px;color:#334155;}'
            . '.payment-cards{display:flex;gap:12px;flex-wrap:wrap;}'
            . '.payment-radio{display:none;}'
            . '.payment-card{flex:1;min-width:180px;border:1px solid #cbd5e1;border-radius:10px;padding:12px 14px;display:flex;align-items:center;gap:10px;cursor:pointer;transition:all .15s ease;background:#f8fafc;box-shadow:0 6px 14px rgba(15,23,42,.04);}'
            . '.payment-card:hover{border-color:#0ea5e9;box-shadow:0 10px 24px rgba(14,165,233,.16);}'
            . '.payment-radio:checked + label.payment-card{border-color:#0ea5e9;box-shadow:0 12px 28px rgba(14,165,233,.22);background:#e0f2fe;}'
            . '.payment-card .pill{padding:3px 10px;border-radius:999px;background:#e2e8f0;font-size:12px;font-weight:600;color:#0f172a;}'
            . '.payment-card strong{font-size:15px;}'
            . '.section-title{display:flex;align-items:center;gap:8px;margin-bottom:12px;font-weight:700;color:#0f172a;}'
            . '.section-title .dot{width:10px;height:10px;border-radius:50%;background:#0ea5e9;box-shadow:0 0 0 6px rgba(14,165,233,.15);}'
            . '.pay-block{border:1px dashed #cbd5e1;border-radius:10px;padding:12px 14px;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,.06);}'
            . '.btn-pair{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}'
            . '.modern-btn{border:none;border-radius:10px;padding:10px 14px;font-weight:700;letter-spacing:.1px;color:#fff;box-shadow:0 10px 24px rgba(14,165,233,.2);transition:transform .1s ease,box-shadow .15s ease;}'
            . '.modern-btn.success{background:linear-gradient(135deg,#0ea5e9 0%,#0284c7 100%);}'
            . '.modern-btn.neutral{background:linear-gradient(135deg,#475569 0%,#1f2937 100%);}'
            . '.modern-btn.danger{background:linear-gradient(135deg,#ef4444 0%,#b91c1c 100%);}'
            . '.modern-btn:hover{transform:translateY(-1px);box-shadow:0 14px 28px rgba(14,165,233,.26);}'
            . '.modern-btn:disabled{opacity:.8;box-shadow:none;transform:none;}'
            . '.status-line{min-height:18px;}'
            . '#close-actions-amount-reset{background:none;border:none;padding:0;margin:0;color:#0f172a;text-decoration:underline;font-size:13px;box-shadow:none;outline:none;cursor:pointer;}'
            . '#close-actions-amount-reset:hover,#close-actions-amount-reset:active,#close-actions-amount-reset:focus{color:#0f172a;text-decoration:underline;box-shadow:none;outline:none;background:none;}'
            . '</style>';

        $html .= '<div class="close-actions-content modern-close-modal">';

        $has_urine = $urine_flag;
        $has_payment = !$pagato_raw;
        $has_certificato = true; // Sempre disponibile per ora
        
        $societa_default = $app_data['societa'] ?? '';
        $sport_default = $app_data['sport'] ?? '';
        $nome_default = $app_data['nome'] ?? '';
        $cognome_default = $app_data['cognome'] ?? '';
        $luogo_nascita_default = $cert_data['luogo_nascita'] ?? '';
        $data_nascita_default = $cert_data['data_nascita'] ?? '';
        $residenza_default = $cert_data['residenza'] ?? '';
        $provincia_default = $cert_data['provincia'] ?? '';
        $cf_default = $cert_data['cf'] ?? '';
        $documento_default = $cert_data['documento'] ?? '';
        $numero_documento_default = $cert_data['numero_documento'] ?? '';

        $tipologia = strtolower($app_data['tipologia'] ?? '');

        // Calcolo booleani per tipologia (evita conflitti tra "agonistica" e "non agonistica")
        $is_non_agonistica = (strpos($tipologia, 'non agon') !== false);
        $is_agonistica = (!$is_non_agonistica && strpos($tipologia, 'agon') !== false);
        $is_concorso  = (strpos($tipologia, 'concorso') !== false);

        // Print button (solo se urine = si)
        if ($has_urine) {
            $html .= '<div class="print-button-section mb-4">';
            $html .= '<h6 class="mb-3"><i class="bi bi-droplet-half"></i> <strong>Stampa Etichetta Urine</strong></h6>';
            $html .= '<button type="button" class="btn btn-primary btn-sm" style="background: linear-gradient(135deg,rgba(214, 153, 21, 1) 0%, rgba(222, 194, 16, 1) 92%); padding: 0.5rem 0.8rem; border: none;" id="btn-print-etichetta" data-print-id="' . intval($app_id) . '">';
            $html .= '<i class="bi bi-printer"></i> Stampa';
            $html .= '</button>';
            $html .= '</div>';
        }

        // Print button (solo se è disponibile il certificato)
        if ($has_certificato) {

            $html .= '<div class="print-cert-section mb-4">';
            $html .= '<h6 class="mb-3"><i class="bi bi-patch-check"></i> <strong>Stampa Certificato</strong></h6>';

            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-nome">Nome</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-nome" name="input-nome" value="' . esc_attr($nome_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-cognome">Cognome</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-cognome" name="input-cognome" value="' . esc_attr($cognome_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-luogo-nascita">Luogo di nascita</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-luogo-nascita" name="input-luogo-nascita" value="' . esc_attr($luogo_nascita_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-data-nascita">Data di nascita</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-data-nascita" name="input-data-nascita" value="' . esc_attr($data_nascita_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-residenza">Residenza</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-residenza" name="input-residenza" value="' . esc_attr($residenza_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-residenza">Provincia di Residenza</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-provincia" name="input-provincia" value="' . esc_attr($provincia_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-cf">CF</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-cf" name="input-cf" value="' . esc_attr($cf_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-documento">Documento</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-documento" name="input-documento" value="' . esc_attr($documento_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-numero-documento">Numero Documento</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-numero-documento" name="input-numero-documento" value="' . esc_attr($numero_documento_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';

            if ($is_agonistica || $is_concorso) {
                // (solo per agonistica e concorso) campi aggiuntivi
                $html .= '<div class="form-group mb-2 mt-2">';
                $html .= '<label for="input-societa">Società/Associazione Sportiva</label>';
                $html .= '<input type="text" class="form-control mb-2" id="input-societa" name="input-societa" value="' . esc_attr($societa_default) . '" />';
                $html .= '</div>';
                $html .= '<div class="form-group mb-2 mt-2">';
                $html .= '<label for="input-sport">Sport praticato</label>';
                $html .= '<input type="text" class="form-control mb-2" id="input-sport" name="input-sport" value="' . esc_attr($sport_default) . '" />';
                $html .= '</div>';
                $html .= '<div class="form-group mb-2 mt-2">';

                // Checkbox Sì/No per lenti a contatto
                $html .= '<div class="form-group mb-2 mt-2">';
                $html .= '<label for="input-lenti">Obbligo Lenti a Contatto?</label><br>';
                $html .= '<p class="text-muted">Attenzione: Se la casella "SI" non viene spuntata sul certificato verrà apposto "NO"</p>';
                $html .= '<input class="mb-2" type="checkbox" id="input-lenti" name="input-lenti" value="si"> <label for="input-lenti">Sì</label>';
                $html .= '</div>';

                // Select per gruppo sanguigno
                $html .= '<div class="form-group mb-2 mt-2">';
                $html .= '<label for="input-sangue">Gruppo Sanguigno</label>';
                $html .= '<select class="form-control mb-2" id="input-sangue" name="input-sangue">';
                $html .= '<option value="">Seleziona...</option>';
                $html .= '<option value="0">0</option>';
                $html .= '<option value="A">A</option>';
                $html .= '<option value="B">B</option>';
                $html .= '<option value="AB">AB</option>';
                $html .= '</select>';
                $html .= '</div>';

                // Select per fattore RH
                $html .= '<div class="form-group mb-2 mt-2">';
                $html .= '<label for="input-rh">Fattore RH</label>';
                $html .= '<select class="form-control mb-2" id="input-rh" name="input-rh">';
                $html .= '<option value="">Seleziona...</option>';
                $html .= '<option value="+">Positivo (+)</option>';
                $html .= '<option value="-">Negativo (-)</option>';
                $html .= '</select>';
                $html .= '</div>';
            }
            
            $html .= '<button type="button" class="btn btn-print btn-sm mt-2 mb-2" style="background: linear-gradient(135deg,rgba(214, 36, 152, 1) 0%, rgba(181, 98, 163, 1) 92%); padding: 0.5rem 0.8rem; border: none;" id="stampa-certificato-btn">';
            $html .= '<span id="stampa-certificato-btn-admin-text"><i class="bi bi-printer"></i> Stampa Certificato</span>';
            $html .= '<div id="certificato-spinner" style="display:none;text-align:center;padding:1em;">
                <span class="spinner-border spinner-border-sm"></span> Generazione certificato...
            </div>';
            $html .= '</button>';
            $html .= '</div>';
        }

        // Payment selector (solo se non già pagato)
        if ($has_payment) {
            $html .= '<div class="payment-section">';
            $html .= '<div class="section-title"><i class="bi bi-piggy-bank"> </i><span class="dot"></span><span>Metodo di Pagamento</span></div>';
            $html .= '<div class="payment-amount">';
            $html .= '<label for="close-actions-amount" style="font-weight:700;color:#0f172a;">Importo</label>';
            $html .= '<div class="d-flex flex-wrap align-items-center" style="gap:10px;margin-top:6px;">';
            $html .= '<div class="input-group" style="max-width:200px;">';
            $html .= '<div class="input-group-prepend"><span class="input-group-text">€</span></div>';
            $html .= '<input type="number" min="0" step="0.01" class="form-control" id="close-actions-amount" data-original="' . esc_attr($amount_attr) . '" value="' . esc_attr($amount_attr) . '" />';
            $html .= '</div>';
            $html .= '<span id="close-actions-amount-display" class="font-weight-bold" style="color:#0f172a;">' . esc_html($amount_display) . '</span>';
            $html .= '<button type="button" id="close-actions-amount-reset" style="background:none; border:none; color:#0f172a; cursor:pointer;">Ripristina importo</button>';
            $html .= '</div>';
            $html .= '<div class="small text-muted mt-1">L\'importo modificato viene inviato a Cashmatic, F24 e POS.</div>';
            $html .= '</div>';
            $html .= '<div class="payment-cards">';
            $html .= '<input class="payment-radio" type="radio" name="payment_method" id="payment_contanti" value="contanti" checked>';
            $html .= '<label class="payment-card" for="payment_contanti"><div class="pill">Cashmatic</div><div><strong>Contanti</strong><div class="text-muted" style="font-size:12px;">Incasso automatico</div></div></label>';
            $html .= '<input class="payment-radio" type="radio" name="payment_method" id="payment_pos" value="pos">';
            $html .= '<label class="payment-card" for="payment_pos"><div class="pill">POS</div><div><strong>Carta / Bancomat</strong><div class="text-muted" style="font-size:12px;">Invio a terminale</div></div></label>';
            $html .= '</div>'; // chiusura payment-cards
        }

        // Se non c'è né pagamento né stampa urine, mostra messaggio
        if (!$has_urine && !$has_payment && !$has_certificato) {
            $html .= '<div class="alert alert-info text-center my-4" style="font-size:1.1em;">Nulla da segnalare nella pre chiusura</div>';
        }

        // Blocco Cashmatic/POS solo se c'è pagamento
        if ($has_payment) {
            // Blocco Cashmatic contanti
            $html .= '<div id="cashmatic-payment-block" class="mt-3 pay-block" style="display:block;" data-amount="' . esc_attr($amount_attr) . '">';
            $html .= '<div class="btn-pair">';
            $html .= '<button type="button" class="modern-btn success btn-sm" id="cashmatic-pay-btn"><i class="bi bi-cash-coin"></i> Avvia incasso contanti</button>';
            $html .= '<button type="button" class="modern-btn danger btn-sm" id="cashmatic-cancel-btn" style="display:none;">Annulla pagamento</button>';
            $html .= '<button type="button" class="modern-btn neutral btn-sm" id="f24-receipt-btn-cash"><i class="bi bi-receipt"></i> Genera ricevuta</button>';
            $html .= '</div>';
            $html .= '<div id="cashmatic-status" class="mt-2 small text-muted status-line"></div>';
            $html .= '</div>';

            // Blocco POS (bridge legacy o altro)
            $html .= '<div id="pos-payment-block" class="mt-3 pay-block" style="display:none;" data-amount="' . esc_attr($amount_attr) . '">';
            $html .= '<div class="btn-pair">';
            $html .= '<button type="button" class="modern-btn success btn-sm" id="pos-pay-btn"><i class="bi bi-credit-card"></i> Avvia pagamento POS</button>';
            $html .= '<button type="button" class="modern-btn neutral btn-sm" id="f24-receipt-btn"><i class="bi bi-receipt"></i> Genera ricevuta</button>';
            $html .= '</div>';
            $html .= '<div id="pos-status" class="mt-2 small text-muted status-line"></div>';
            $html .= '</div>';
        }
        }

    $html .= '</div>';

    wp_send_json_success(array('html' => $html));
    wp_die();
    }

    public function totemsport_get_certificato_med()
    {
        // Solo medico o admin
        $user = wp_get_current_user();
        if (!in_array('medico', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            wp_send_json_error(['message' => 'Accesso negato']);
            wp_die();
        }

        $conf_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        if (!$conf_id) {
            wp_send_json_error(['message' => 'ID appuntamento mancante']);
            wp_die();
        }

        // ID appuntamento originale (wc appointment) per la stampa
        $app_id = intval(get_post_meta($conf_id, 'appointment_id', true));
        if (!$app_id) {
            $app_id = $conf_id; // fallback
        }
        
        $app_data = AppointmentHelper::get_app_data($app_id);
        $cert_data = AppointmentHelper::get_certificato_data($app_id);

        if (!$app_data || !is_array($app_data)) {
            wp_send_json_error(['message' => 'Dati appuntamento non trovati']);
            wp_die();
        }
        $html = '';

        $has_certificato = true; // Sempre disponibile per ora

        $societa_default = $app_data['societa'] ?? '';
        $sport_default = $app_data['sport'] ?? '';
        $nome_default = $app_data['nome'] ?? '';
        $cognome_default = $app_data['cognome'] ?? '';
        $luogo_nascita_default = $cert_data['luogo_nascita'] ?? '';
        $data_nascita_default = $cert_data['data_nascita'] ?? '';
        $residenza_default = $cert_data['residenza'] ?? '';
        $provincia_default = $cert_data['provincia'] ?? '';
        $cf_default = $cert_data['cf'] ?? '';
        $documento_default = $cert_data['documento'] ?? '';
        $numero_documento_default = $cert_data['numero_documento'] ?? '';

        $tipologia = strtolower($app_data['tipologia'] ?? '');

        // Calcolo booleani per tipologia (evita conflitti tra "agonistica" e "non agonistica")
        $is_non_agonistica = (strpos($tipologia, 'non agon') !== false);
        $is_agonistica = (!$is_non_agonistica && strpos($tipologia, 'agon') !== false);
        $is_concorso  = (strpos($tipologia, 'concorso') !== false);


        if ($has_certificato) {
            $html .= '<div class="print-cert-section mb-4">';
            $html .= '<h6 class="mb-3"><i class="bi bi-patch-check"></i> <strong>Stampa Certificato</strong></h6>';

            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-nome">Nome</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-nome" name="input-nome" value="' . esc_attr($nome_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-cognome">Cognome</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-cognome" name="input-cognome" value="' . esc_attr($cognome_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-luogo-nascita">Luogo di nascita</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-luogo-nascita" name="input-luogo-nascita" value="' . esc_attr($luogo_nascita_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-data-nascita">Data di nascita</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-data-nascita" name="input-data-nascita" value="' . esc_attr($data_nascita_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-residenza">Residenza</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-residenza" name="input-residenza" value="' . esc_attr($residenza_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-provincia">Provincia di Residenza</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-provincia" name="input-provincia" value="' . esc_attr($provincia_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-cf">CF</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-cf" name="input-cf" value="' . esc_attr($cf_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-documento">Documento</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-documento" name="input-documento" value="' . esc_attr($documento_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-numero-documento">Numero Documento</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-numero-documento" name="input-numero-documento" value="' . esc_attr($numero_documento_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            
            if ($is_agonistica || $is_concorso) {
                $html .= '<label for="input-societa">Società/Associazione Sportiva</label>';
                $html .= '<input type="text" class="form-control mb-2" id="input-societa" name="input-societa" value="' . esc_attr($societa_default) . '" />';
                $html .= '</div>';
                $html .= '<div class="form-group mb-2 mt-2">';
                $html .= '<label for="input-sport">Sport praticato</label>';
                $html .= '<input type="text" class="form-control mb-2" id="input-sport" name="input-sport" value="' . esc_attr($sport_default) . '" />';
                $html .= '</div>';
                $html .= '<div class="form-group mb-2 mt-2">';
                $html .= '<label for="input-lenti">Obbligo Lenti a Contatto?</label><br>';
                $html .= '<p class="text-muted">Attenzione: Se la casella "SI" non viene spuntata sul certificato verrà apposto "NO"</p>';
                $html .= '<input class="mb-2" type="checkbox" id="input-lenti" name="input-lenti" value="si"> <label for="input-lenti">Sì</label>';
                $html .= '</div>';
                $html .= '<div class="form-group mb-2 mt-2">';
                $html .= '<label for="input-sangue">Gruppo Sanguigno</label>';
                $html .= '<select class="form-control mb-2" id="input-sangue" name="input-sangue">';
                $html .= '<option value="">Seleziona...</option>';
                $html .= '<option value="0">0</option>';
                $html .= '<option value="A">A</option>';
                $html .= '<option value="B">B</option>';
                $html .= '<option value="AB">AB</option>';
                $html .= '</select>';
                $html .= '</div>';

                // Select per fattore RH
                $html .= '<div class="form-group mb-2 mt-2">';
                $html .= '<label for="input-rh">Fattore RH</label>';
                $html .= '<select class="form-control mb-2" id="input-rh" name="input-rh">';
                $html .= '<option value="">Seleziona...</option>';
                $html .= '<option value="+">Positivo (+)</option>';
                $html .= '<option value="-">Negativo (-)</option>';
                $html .= '</select>';
                $html .= '</div>';
            }
            $html .= '<button type="button" class="btn btn-print btn-sm mt-2 mb-2" style="background: linear-gradient(135deg,rgba(214, 36, 152, 1) 0%, rgba(181, 98, 163, 1) 92%); padding: 0.5rem 0.8rem; border: none; color: white;" id="stampa-certificato-btn-med">';
            $html .= '<span id="stampa-certificato-btn-med-text"><i class="bi bi-printer"></i> Stampa Certificato</span>';
            $html .= '<div id="certificato-spinner" style="display:none;text-align:center;padding:1em;">';
            $html .= '<span class="spinner-border spinner-border-sm"></span> Generazione certificato...';
            $html .= '</div>';
            $html .= '</button>';
            $html .= '</div>';

            // Stampa sospensione
            $html .= '<div class="print-sospensione-section mb-4">';
            $html .= '<h6 class="mb-3"><i class="bi bi-file-earmark-medical"></i> <strong>Stampa Sospensione</strong></h6>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-sport">Sport praticato</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-sport" name="input-sport" value="' . esc_attr($sport_default) . '" />';
            $html .= '</div>';
            $html .= '<label for="input-quesito-diagnostico">Quesito Diagnostico:</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-quesito-diagnostico" name="input-quesito-diagnostico" />';
            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-print btn-sm mt-2 mb-2" style="background: linear-gradient(135deg,rgba(214, 36, 152, 1) 0%, rgba(181, 98, 163, 1) 92%); padding: 0.5rem 0.8rem; border: none; color: white;" id="stampa-sospensione-btn-med">';
            $html .= '<span id="stampa-sospensione-btn-med-text"><i class="bi bi-printer"></i> Stampa Sospensione</span>';
            $html .= '<div id="sospensione-spinner" style="display:none;text-align:center;padding:1em;">';
            $html .= '<span class="spinner-border spinner-border-sm"></span> Generazione sospensione...';
            $html .= '</div>';
            $html .= '</button>';
            $html .= '</div>';

            // Stampa consigli
            $html .= '<div class="print-consigli-section mb-4">';
            $html .= '<h6 class="mb-3"><i class="bi bi-journal-medical"></i> <strong>Stampa Consigli</strong></h6>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-sport">Sport praticato</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-sport" name="input-sport" value="' . esc_attr($sport_default) . '" />';
            $html .= '</div>';
            $html .= '<div class="form-group mb-2 mt-2">';
            $html .= '<label for="input-consigli">Consigli:</label>';
            $html .= '<input type="text" class="form-control mb-2" id="input-consigli" name="input-consigli" />';
            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-print btn-sm mt-2 mb-2" style="background: linear-gradient(135deg,rgba(214, 36, 152, 1) 0%, rgba(181, 98, 163, 1) 92%); padding: 0.5rem 0.8rem; border: none; color: white;" id="stampa-consigli-btn-med">';
            $html .= '<span id="stampa-consigli-btn-med-text"><i class="bi bi-printer"></i> Stampa Consigli</span>';
            $html .= '<div id="consigli-spinner" style="display:none;text-align:center;padding:1em;">';
            $html .= '<span class="spinner-border spinner-border-sm"></span> Generazione consigli...';
            $html .= '</div>';
            $html .= '</button>';
            $html .= '</div>';
        }

        wp_send_json_success(['html' => $html]);
        wp_die();
    }


    public function add_cron_schedules($schedules)
    {
        // Intervallo custom ogni 90 secondi per la sync su Google Drive
        $schedules['totemsport_90sec'] = array(
            'interval' => 90,
            'display' => __('Ogni 90 secondi', 'totemsport'),
        );
        return $schedules;
    }

    // Annulla pagamento attivo su Cashmatic
    public function totemsport_cashmatic_cancel() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }
        $token = $this->cashmatic_get_token();
        if (is_wp_error($token)) {
            wp_send_json_error(['message' => $token->get_error_message()]);
        }
        $response = $this->cashmatic_request('/transaction/CancelPayment', [], [
            'token' => $token,
            'method' => 'POST',
        ]);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }
        $code = $response['code'] ?? null;
        $message = $response['message'] ?? 'Errore annullamento Cashmatic';
        if ($code === 0) {
            wp_send_json_success(['message' => $message, 'result' => $response]);
        } else {
            wp_send_json_error(['message' => $message, 'result' => $response]);
        }
    }

    public function totemsport_pos_payment()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }

        $amount_cents = isset($_POST['amount_cents']) ? intval($_POST['amount_cents']) : 0;
        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        $order_id = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : '';
        $channel = isset($_POST['channel']) ? sanitize_text_field($_POST['channel']) : 'pos';
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : 'pos';

        if ($amount_cents <= 0) {
            wp_send_json_error(array('message' => 'Importo non valido'));
        }

        if ($payment_method === 'contanti') {
            // Cashmatic
            $token = $this->cashmatic_get_token();
            if (is_wp_error($token)) {
                wp_send_json_error(array('message' => $token->get_error_message()));
            }
            $payload = array(
                'amount' => $amount_cents,
                'reference' => $order_id ?: ('app_' . $appointment_id . '_' . $channel),
                'allowExternalCancel' => true,
            );
            $response = $this->cashmatic_request('/transaction/StartPayment', $payload, array(
                'token' => $token,
            ));
            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => $response->get_error_message()));
            }
            $code = $response['code'] ?? null;
            $message = $response['message'] ?? 'Risposta Cashmatic sconosciuta';
            if ($code === 0) {
                $result = array(
                    'status' => 'OK',
                    'code' => $code,
                    'message' => $message,
                    'data' => $response['data'] ?? $response,
                );
                wp_send_json_success(array('result' => $result));
            }
            wp_send_json_error(array('error_message' => $message, 'result' => $response));
        } else if ($payment_method === 'pos') {
            // POS Nexi LAN Integration - Protocollo ufficiale
            if ($payment_method === 'contanti') {
                // Cashmatic
                $token = $this->cashmatic_get_token();
                if (is_wp_error($token)) {
                    wp_send_json_error(array('message' => $token->get_error_message()));
                }
                $payload = array(
                    'amount' => $amount_cents,
                    'reference' => $order_id ?: ('app_' . $appointment_id . '_' . $channel),
                    'allowExternalCancel' => true,
                );
                $response = $this->cashmatic_request('/transaction/StartPayment', $payload, array(
                    'token' => $token,
                ));
                if (is_wp_error($response)) {
                    wp_send_json_error(array('message' => $response->get_error_message()));
                }
                $code = $response['code'] ?? null;
                $message = $response['message'] ?? 'Errore Cashmatic';
                if ($code === 0) {
                    $result = array(
                        'status' => 'OK',
                        'success_message' => 'Pagamento Cashmatic eseguito',
                        'code' => $code,
                        'message' => $message,
                        'data' => $response['data'] ?? $response,
                    );
                    wp_send_json_success(array('result' => $result));
                }
                wp_send_json_error(array('error_message' => $message, 'result' => $response));
            } else if ($payment_method === 'pos') {
                // POS Nexi LAN Integration - Protocollo ufficiale
                $terminal_id = str_pad('1', 8, '0', STR_PAD_LEFT); // Personalizza se necessario
                $cashreg_id = str_pad('1', 8, '0', STR_PAD_LEFT); // Personalizza se necessario
                $reserved1 = '0';
                $msg_code = 'P';
                $add_data = '0'; // Nessun dato addizionale
                $reserved2 = '00';
                $card_present = '0';
                $pay_type = '0'; // 0=auto
                $amount_field = str_pad($amount_cents, 8, '0', STR_PAD_LEFT);
                // Prepara additional data: id, nome, cognome, tipologia, cf
                $app_data = null;
                if ($appointment_id) {
                    $app_data = AppointmentHelper::get_app_data($appointment_id);
                }
                $nome = $app_data['nome'] ?? '';
                $cognome = $app_data['cognome'] ?? '';
                $tipologia = $app_data['tipologia'] ?? '';
                $cf = $app_data['cf'] ?? '';
                $additional = [];
                if ($appointment_id) $additional[] = 'ID:' . $appointment_id;
                if ($nome || $cognome) $additional[] = 'Paz:' . trim($nome . ' ' . $cognome);
                if ($tipologia) $additional[] = 'Prestazione:' . $tipologia;
                if ($cf) $additional[] = 'CF:' . $cf;
                $print_text_val = implode(' | ', $additional);
                // Trunca a 128 caratteri e riempi con spazi
                $print_text = str_pad(substr($print_text_val, 0, 128), 128, ' ', STR_PAD_RIGHT);
                $reserved3 = str_pad('0', 8, '0');
                // Componi il messaggio applicativo
                $appmsg = $terminal_id
                    . $reserved1
                    . $msg_code
                    . $cashreg_id
                    . $add_data
                    . $reserved2
                    . $card_present
                    . $pay_type
                    . $amount_field
                    . $print_text
                    . $reserved3;
                $response = $this->send_nexi_pos_command($appmsg, true);
                if (is_wp_error($response)) {
                    wp_send_json_error(array('message' => $response->get_error_message()));
                }
                // Decodifica risposta: estrai campi principali
                $fields = [];
                if (strlen($response) > 12) {
                    $fields['terminal_id'] = substr($response, 1, 8);
                    $fields['msg_code'] = substr($response, 10, 1);
                    $fields['result'] = substr($response, 11, 2);
                    $fields['raw'] = bin2hex($response);
                }
                $ok = ($fields['result'] ?? null) === '00';
                if ($ok) {
                    wp_send_json_success(array('result' => array(
                        'status' => 'OK',
                        'message' => 'Pagamento eseguito',
                        'fields' => $fields,
                        'raw_response' => bin2hex($response),
                    )));
                } else {
                    wp_send_json_error(array(
                        'message' => 'Pagamento negato',
                        'fields' => $fields,
                        'raw_response' => bin2hex($response),
                    ));
                }
            } else {
                wp_send_json_error(array('message' => 'Metodo pagamento non supportato'));
            }
        }
    }

    public function totemsport_f24_receipt()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        
        // Se arriva l'ID dell'accettazione (appunt_conf), risali all'ID dell'appuntamento originale
        if ($appointment_id) {
            $maybe_conf = get_post($appointment_id);
            if ($maybe_conf && $maybe_conf->post_type === 'appunt_conf') {
                $original_id = intval(get_post_meta($maybe_conf->ID, 'appointment_id', true));
                if ($original_id) {
                    $appointment_id = $original_id;
                }
            }
        }

        if (!$appointment_id) {
            wp_send_json_error(array('message' => 'ID appuntamento mancante'));
        }

        $app_data = AppointmentHelper::get_app_data($appointment_id);
        if (!$app_data) {
            wp_send_json_error(array('message' => 'Dati appuntamento non trovati'));
        }

        // Usa il totale custom se presente
        $custom_total = get_post_meta($appointment_id, '_custom_total', true);
        if ($custom_total !== '' && is_numeric($custom_total)) {
            $amount_total = floatval($custom_total);
        } else {
            $amount_total = isset($app_data['totale']) ? floatval($app_data['totale']) : 0;
        }

        if ($amount_total <= 0) {
            wp_send_json_error(array('message' => 'Importo non valido per la ricevuta'));
        }

        $nome = trim(($app_data['nome'] ?? '') . ' ' . ($app_data['cognome'] ?? ''));
        $cf = $app_data['cf'] ?? '';
        $tipologia = $app_data['tipologia'] ?? '';
        $urine_flag = false;
        if (!empty($app_data['urine'])) {
            $urine_val = strtolower(trim((string)$app_data['urine']));
            $urine_flag = in_array($urine_val, array('si', 'sì', '1', 'yes', 'true'), true);
        }
        $staff_ids = $app_data['staff_ids'] ?? array();
        $staff_name = '';
        if (!empty($staff_ids)) {
            $staff_user = get_userdata(intval($staff_ids[0]));
            if ($staff_user) {
                $staff_name = $staff_user->display_name;
            }
        }
        $email = '';
        $addr_street = '';
        $addr_city = '';
        $addr_postcode = '';
        $addr_state = '';
        $addr_country = 'IT';
        // prova a recuperare email dal billing user
        $app_post = get_post($appointment_id);
        if ($app_post) {
            $user_id = get_post_meta($app_post->ID, '_appointment_customer_id', true);
            if ($user_id) {
                $email = get_user_meta($user_id, 'billing_email', true);
                $addr_street = trim(get_user_meta($user_id, 'billing_address_1', true) . ' ' . get_user_meta($user_id, 'billing_address_2', true));
                $addr_city = get_user_meta($user_id, 'billing_city', true);
                $addr_postcode = get_user_meta($user_id, 'billing_postcode', true);
                $addr_state = get_user_meta($user_id, 'billing_state', true);
                $addr_country = get_user_meta($user_id, 'billing_country', true) ?: 'IT';
            }
        }

        // IVA a 0%: imponibile = totale, IVA = 0
        $gross = round($amount_total, 2);

        $net = $gross;
        $vat = 0;

        $mapping = $this->map_tipologia_to_code_product($tipologia);
        $mapped_code = $mapping['code'] ?? null;
        $mapped_product = $mapping['product'] ?? null;

        $xml = $this->build_f24_xml_receipt(array(
            'customer_name' => $nome,
            'customer_cf' => $cf,
            'customer_email' => $email,
            'customer_address' => $addr_street,
            'customer_city' => $addr_city,
            'customer_postcode' => $addr_postcode,
            'customer_province' => $addr_state,
            'customer_country' => $addr_country,
            'description' => 'Prestazione ambulatoriale - Gestionale Online' . esc_html($tipologia),
            'tipologia' => $tipologia,
            'center' => $staff_name,
            'urine_flag' => $urine_flag,
            'sku' => $mapped_code,
            'product_name' => $mapped_product,
            'gross' => $gross,
            'net' => $net,
            'vat' => $vat,
        ));

        $response = wp_remote_post($this->f24_endpoint, array(
            'timeout' => 30,
            'body' => array(
                'apiKey' => $this->f24_api_key,
                'xml' => $xml,
            ),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Errore connessione Fattura24: ' . $response->get_error_message()));
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        // Fattura24 può rispondere JSON o testo
        if (is_array($decoded)) {
            $success = false;
            $msg = '';
            $doc_id = null;
            if (isset($decoded['success'])) {
                $success = (bool)$decoded['success'];
            }
            if (isset($decoded['result']) && is_array($decoded['result']) && isset($decoded['result']['success'])) {
                $success = (bool)$decoded['result']['success'];
            }
            $msg = $decoded['message'] ?? ($decoded['result']['message'] ?? '');
            $doc_id = $decoded['docId'] ?? ($decoded['result']['docId'] ?? null);

            if ($success) {
                $pdf_base64 = null;
                if ($doc_id) {
                    $pdf_base64 = $this->f24_get_pdf_base64($doc_id);
                }
                // Salva la ricevuta come PDF nell'archivio del paziente
                $pdf_url = null;
                if ($pdf_base64 && $appointment_id) {
                    $pdf_content = base64_decode($pdf_base64);
                    if ($pdf_content) {
                        $folder_path = ArchiveHelper::create_patient_folder($appointment_id);
                        if ($folder_path) {
                            $file_path = $folder_path . '/ricevuta_fattura24.pdf';
                            file_put_contents($file_path, $pdf_content);
                            // Calcola l'URL pubblico del PDF
                            $upload_dir = wp_upload_dir();
                            $pdf_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file_path);
                        }
                    }
                }
                wp_send_json_success(array(
                    'message' => $msg ?: 'Ricevuta creata su Fattura24',
                    'raw' => $decoded,
                    'pdf_base64' => $pdf_base64,
                    'doc_id' => $doc_id,
                    'pdf_url' => $pdf_url
                ));
            }

            wp_send_json_error(array('message' => $msg ?: 'Errore Fattura24', 'raw' => $decoded));
        }

        // Prova parsing XML: alcuni endpoint restituiscono <result><success>true</success>...</result>
        libxml_use_internal_errors(true);
        $xml_obj = simplexml_load_string($body);
        if ($xml_obj !== false) {
            $xml_success = false;
            $xml_msg = '';
            $xml_doc_id = null;

            // Check common fields
            if (isset($xml_obj->success)) {
                $xml_success = (strtolower((string)$xml_obj->success) === 'true');
            }
            if (isset($xml_obj->result->success)) {
                $xml_success = (strtolower((string)$xml_obj->result->success) === 'true');
            }
            if (isset($xml_obj->message)) {
                $xml_msg = (string)$xml_obj->message;
            }
            if (isset($xml_obj->result->message)) {
                $xml_msg = (string)$xml_obj->result->message;
            }
            if (isset($xml_obj->docId)) {
                $xml_doc_id = (string)$xml_obj->docId;
            }
            if (isset($xml_obj->result->docId)) {
                $xml_doc_id = (string)$xml_obj->result->docId;
            }

            if ($xml_success) {
                $pdf_base64 = null;
                if ($xml_doc_id) {
                    $pdf_base64 = $this->f24_get_pdf_base64($xml_doc_id);
                }
                wp_send_json_success(array('message' => $xml_msg ?: 'Ricevuta creata su Fattura24', 'raw' => $body, 'pdf_base64' => $pdf_base64, 'doc_id' => $xml_doc_id));
            }
        }

        $body_trim = trim($body);

        // fallback: se contiene "OK" lo consideriamo successo
        if ($body_trim !== '' && stripos($body_trim, 'ok') !== false) {
            wp_send_json_success(array('message' => 'Ricevuta creata su Fattura24', 'raw' => $body_trim)); 
        }

        // ulteriore fallback: se la risposta non contiene parole di errore, assumiamo successo ma mostriamo lo snippet
        $has_error_word = (stripos($body_trim, 'error') !== false) || (stripos($body_trim, 'errore') !== false) || (stripos($body_trim, 'fail') !== false);
        if ($body_trim !== '' && !$has_error_word) {
            $snippet = substr($body_trim, 0, 400);
            wp_send_json_success(array('message' => 'Ricevuta creata su Fattura24', 'raw' => $snippet));
        }

        // Se arriva qualcosa, restituiamo il dettaglio per debug
        if ($body_trim !== '') {
            $snippet = substr($body_trim, 0, 400);
            wp_send_json_error(array('message' => 'Risposta Fattura24 non riconosciuta', 'raw' => $snippet));
        }

        wp_send_json_error(array('message' => 'Risposta Fattura24 vuota o non riconosciuta'));
    }


    private function build_f24_xml_receipt($data)
    {
        $customer_name = $data['customer_name'] ?: 'Cliente';
        $customer_cf = $data['customer_cf'] ?: '';
        $customer_email = $data['customer_email'] ?: '';
        $customer_address = $data['customer_address'] ?: '';
        $customer_city = $data['customer_city'] ?: '';
        $customer_postcode = $data['customer_postcode'] ?: '';
        $customer_province = $data['customer_province'] ?: '';
        $customer_country = $data['customer_country'] ?: 'IT';
        $description = $data['description'] ?: 'Prestazione';
        $gross = number_format($data['gross'], 2, '.', '');
        $net = number_format($data['net'], 2, '.', '');
        $vat = number_format($data['vat'], 2, '.', '');
        $tipologia = strtolower($data['tipologia'] ?? '');
        $center = trim($data['center'] ?? '');
        $urine_flag = !empty($data['urine_flag']);
        $product_name = !empty($data['product_name']) ? $data['product_name'] : trim($data['tipologia'] ?? 'Prestazione');
        $code_main = !empty($data['sku']) ? $data['sku'] : 'APP-' . substr(md5($product_name), 0, 6);
        $analysis_map = $this->map_analysis_code_product($data['tipologia'] ?? '');
        $code_analysis = $analysis_map['code'];
        $product_analysis = $analysis_map['product'];

        $is_non_agonistica = (strpos($tipologia, 'non agon') !== false);
        $is_agonistica = (!$is_non_agonistica && strpos($tipologia, 'agon') !== false);

        // Righe: base sempre 1; aggiungi analisi solo per agonistica con urine in sede
        $rows = array();
        $row_desc_base = $description;
        if (!empty($center)) {
            $row_desc_base .= ' — Centro: ' . $center;
        }

        $rows[] = array(
            'description' => $row_desc_base,
            'qty' => 1,
            'price' => $gross, // Usa sempre il custom total (gross) come prezzo riga
            'vat_code' => '0',
            'vat_desc' => '0% - Esente Art.10 DPR 633/72',
            'code' => $code_main,
            'product' => $product_name,
            'revenue_center' => $center,
        );

        if ($is_agonistica && $urine_flag) {
            $rows[] = array(
                'description' => 'Analisi qualitativa - ' . ($center ?: 'Centro'),
                'qty' => 1,
                'price' => number_format(0, 2, '.', ''),
                'vat_code' => '0',
                'vat_desc' => '0% - Esente Art.10 DPR 633/72',
                'code' => $code_analysis,
                'product' => $product_analysis,
                'revenue_center' => $center,
            );
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Fattura24><Document>';
        $xml .= '<DocumentType>R</DocumentType>'; // Ricevuta
        $xml .= '<CustomerName><![CDATA[' . $customer_name . ']]></CustomerName>';
        $xml .= '<CustomerAddress><![CDATA[' . $customer_address . ']]></CustomerAddress>';
        $xml .= '<CustomerPostcode>' . esc_html($customer_postcode) . '</CustomerPostcode>';
        $xml .= '<CustomerCity><![CDATA[' . $customer_city . ']]></CustomerCity>';
        $xml .= '<CustomerProvince>' . esc_html($customer_province) . '</CustomerProvince>';
        $xml .= '<CustomerCountry>' . esc_html($customer_country) . '</CustomerCountry>';
        $xml .= '<CustomerFiscalCode>' . esc_html($customer_cf) . '</CustomerFiscalCode>';
        $xml .= '<CustomerVatCode></CustomerVatCode>';
        $xml .= '<CustomerEmail>' . esc_html($customer_email) . '</CustomerEmail>';
        $xml .= '<Object><![CDATA[Ricevuta appuntamento '. $customer_name .' - Gestionale Online]]></Object>';
        $xml .= '<FeCustomerPec></FeCustomerPec>';
        $xml .= '<FeDestinationCode>0000000</FeDestinationCode>';
        $xml .= '<FePaymentCode>MP08</FePaymentCode>'; // POS
        $xml .= '<TotalWithoutTax>' . $net . '</TotalWithoutTax>';
        $xml .= '<VatAmount>' . $vat . '</VatAmount>';
        $xml .= '<Total>' . $gross . '</Total>';
        $xml .= '<SendEmail>false</SendEmail>';
        $xml .= '<Rows>';

        foreach ($rows as $r) {
            $xml .= '<Row>';
            if (!empty($r['code'])) {
                $xml .= '<Code>' . esc_html($r['code']) . '</Code>';
            }
            if (!empty($r['product'])) {
                $xml .= '<ProductName><![CDATA[' . $r['product'] . ']]></ProductName>';
            }
            if (!empty($r['revenue_center'])) {
                $xml .= '<RevenueCenter><![CDATA[' . $r['revenue_center'] . ']]></RevenueCenter>';
            }
            $xml .= '<Description><![CDATA[' . $r['description'] . ']]></Description>';
            $xml .= '<Qty>' . $r['qty'] . '</Qty>';
            $xml .= '<Price>' . $r['price'] . '</Price>';
            $xml .= '<VatCode>' . $r['vat_code'] . '</VatCode>';
            $xml .= '<VatDescription>' . $r['vat_desc'] . '</VatDescription>';
            $xml .= '</Row>';
        }

        $xml .= '</Rows>';
        
        // Blocco pagamenti saldato secondo specifica Fattura24
        $today = date('Y-m-d');
        $xml .= '<Payments>';
        $xml .= '<Payment>';
        $xml .= '<Date>' . $today . '</Date>';
        $xml .= '<Amount>' . $gross . '</Amount>';
        $xml .= '<Paid>true</Paid>';
        $xml .= '</Payment>';
        $xml .= '</Payments>';
        $xml .= '</Document></Fattura24>';

        return $xml;
    }


    private function map_tipologia_to_code_product($tipologia)
    {
        $key = strtolower(trim((string)$tipologia));
        $map = $this->get_product_mapping_table();
        return $map[$key] ?? null;
    }


    private function map_analysis_code_product($tipologia)
    {
        $tip_lower = strtolower((string)$tipologia);

        if (strpos($tip_lower, 'barletta') !== false) {
            return array('code' => 'AQ_BT', 'product' => 'Analisi qualitativa');
        }

        if (strpos($tip_lower, 'bitritto') !== false) {
            return array('code' => 'AQ_BI', 'product' => 'Analisi qualitativa');
        }

        return array('code' => 'ANALISI', 'product' => 'Analisi qualitativa');
    }


    private function get_product_mapping_table()
    {
        // Chiave: tipologia prodotto WooCommerce (case-insensitive)
        return array(
            'analisi qualitativa - bitritto' => array('code' => 'AQ_BI', 'product' => 'Analisi qualitativa'),
            'analisi qualitativa - barletta' => array('code' => 'AQ_BT', 'product' => 'Analisi qualitativa'),
            'attività agonistica under - bitritto' => array('code' => 'AU_BI', 'product' => 'Attività Agonistica Under - Bitritto'),
            'attività agonistica over - bitritto' => array('code' => 'AO_BI', 'product' => 'Attività Agonistica Over - Bitritto'),
            'attività agonistica under - barletta' => array('code' => 'AU_BA', 'product' => 'Attività Agonistica Under - Barletta'),
            'attività agonistica over - barletta' => array('code' => 'AO_BA', 'product' => 'Attività Agonistica Over - Barletta'),
            'attività agonistica over - est_mo' => array('code' => 'AO_MO', 'product' => 'Attività Agonistica Over - EST_MO'),
            'attività agonistica under - est_mo' => array('code' => 'AU_MO', 'product' => 'Attività Agonistica Under - EST_MO'),
            'attività agonistica under - est_molf' => array('code' => 'AU_MOLF', 'product' => 'Attività Agonistica Under - EST_MOLF'),
            'attività agonistica over - est_molf' => array('code' => 'AO_MOLF', 'product' => 'Attività Agonistica Over - EST_MOLF'),
            'attività agonistica over - est_melior' => array('code' => 'AO_MELIOR', 'product' => 'Attività Agonistica Over - EST_MELIOR'),
            'attività agonistica under - est_melior' => array('code' => 'AU_MELIOR', 'product' => 'Attività Agonistica Under - EST_MELIOR'),
            'attività non agonistica - bitritto' => array('code' => 'NA_BI', 'product' => 'Attività non Agonistica - Bitritto'),
            'attività non agonistica- barletta' => array('code' => 'NA_BA', 'product' => 'Attività non Agonistica- Barletta'),
            'attività non agonistica - esterna 1' => array('code' => 'EST_1', 'product' => 'Attività non Agonistica'),
            'attività non agonistica - esterna 2' => array('code' => 'EST_2', 'product' => 'Attività non Agonistica'),
            'attività non agonistica  - est_mo' => array('code' => 'NA_MO', 'product' => 'Attività Non Agonistica  - EST_MO'),
            'attività non agonistica  - est_molf' => array('code' => 'NA_MOLF', 'product' => 'Attività Non Agonistica  - EST_MOLF'),
            'attività non agonistica  - est_melior' => array('code' => 'NA_MELIOR', 'product' => 'Attività Non Agonistica  - EST_MELIOR'),
            'audiometria' => array('code' => 'AUDIO', 'product' => 'Audiometria'),
            'commissioni bancarie' => array('code' => 'COMM_BANC', 'product' => 'COMMISSIONI BANCARIE'),
            'ecostress' => array('code' => 'ESS', 'product' => 'ECOSTRESS'),
            'esami ematochimici' => array('code' => 'ESEM_MEDL', 'product' => 'Esami Ematochimici'),
            'esami strumentali' => array('code' => 'ES', 'product' => 'Esami strumentali'),
            'idoneità' => array('code' => 'ID_NA', 'product' => 'Idoneità'),
            'idoneità aggiuntiva - bitritto' => array('code' => 'ID_BI', 'product' => 'Idoneità aggiuntiva'),
            'idoneità aggiuntiva - barletta' => array('code' => 'ID_BA', 'product' => 'Idoneità aggiuntiva'),
            'monitoraggio biologico' => array('code' => 'UR_MEDL', 'product' => 'Monitoraggio Biologico'),
            'nomina annuale medico competente 2024 + n. 5 visite' => array('code' => 'NM_VIS_MEDL', 'product' => 'Nomina Annuale Medico Competente 2024 + n. 5 Visite'),
            'nomina annuale medico competente' => array('code' => 'NM_MEDL', 'product' => 'Nomina Annuale Medico Competente'),
            'pa - agonistiche under' => array('code' => 'PA_AU', 'product' => 'PA - Agonistiche Under'),
            'pa - agonistiche over' => array('code' => 'PA_AO', 'product' => 'PA - Agonistiche Over'),
            'pa - non agonistiche' => array('code' => 'PA_NA', 'product' => 'PA - Non Agonistiche'),
            'pacchetto ecg e certificati' => array('code' => 'PAC_FARM', 'product' => 'Pacchetto ECG e CERTIFICATI'),
            'refertazione ecg' => array('code' => 'REF_ECG', 'product' => 'REFERTAZIONE ECG'),
            'ricevuta annullata per errata fatturazione' => array('code' => 'ERR_1', 'product' => 'Ricevuta annullata per errata fatturazione'),
            'ricevuta annullata ( visita non eseguita )' => array('code' => 'ERR_2', 'product' => 'Ricevuta annullata ( visita non eseguita )'),
            'spirometria' => array('code' => 'SPIRO_MEDL', 'product' => 'Spirometria'),
            'visita concorso militare - bitritto' => array('code' => 'CM_BI', 'product' => 'Visita Concorso Militare - Bitritto'),
            'visita concorso militare - barletta' => array('code' => 'CM_BA', 'product' => 'Visita Concorso Militare - Barletta'),
            'visita concorso militare - est_mo' => array('code' => 'CM_MO', 'product' => 'Visita Concorso Militare - EST_MO'),
            'visita concorso militare - est_molf' => array('code' => 'CM_MOLF', 'product' => 'Visita Concorso Militare - EST_MOLF'),
            'visita concorso militare - est_melior' => array('code' => 'CM_MELIOR', 'product' => 'Visita Concorso Militare - EST_MELIOR'),
            'visita medica' => array('code' => 'VS_MEDL', 'product' => 'Visita Medica'),
            'visita specialistica - bitritto' => array('code' => 'VS_BI', 'product' => 'Visita specialistica'),
            'visita specialistica' => array('code' => 'VIS_SPEC', 'product' => 'Visita specialistica'),
            'visite medicina dello sport c/o vostra struttura' => array('code' => 'VIS_MO', 'product' => 'VISITE MEDICINA DELLO SPORT C/O VOSTRA STRUTTURA'),
            'imposta di bollo' => array('code' => 'BO', 'product' => 'IMPOSTA DI BOLLO'),
            'eco carotidi' => array('code' => 'ECO_CAROT_CARD', 'product' => 'Eco carotidi'),
            'ecocardiogramma' => array('code' => 'ECOC_CARD', 'product' => 'Ecocardiogramma'),
            'ecocolordoppler' => array('code' => 'ECO2D_CARD', 'product' => 'Ecocolordoppler'),
            'elettrocardiogramma' => array('code' => 'ECG', 'product' => 'Elettrocardiogramma'),
            'holter cardiaco' => array('code' => 'H_CARD_24H', 'product' => 'Holter cardiaco'),
            'holter pressorio' => array('code' => 'H_PRESS', 'product' => 'Holter pressorio'),
            'holter pressorio settimanale' => array('code' => 'H_CARD_7GG', 'product' => 'Holter pressorio settimanale'),
            'prova da sforzo' => array('code' => 'PR_SF_CARD', 'product' => 'Prova da sforzo'),
            'visita cardiologica' => array('code' => 'VIS_CARD', 'product' => 'Visita cardiologica'),
            'ecografia' => array('code' => 'ECOGR', 'product' => 'Ecografia'),
            'visita neurologica' => array('code' => 'VIS_NEUR', 'product' => 'Visita neurologica'),
            'elettroencefalogramma' => array('code' => 'EEG', 'product' => 'Elettroencefalogramma'),
            'prestazione specialistica ortopedica ( infiltrazione )' => array('code' => 'ORTOP_INF', 'product' => 'Prestazione specialistica ortopedica ( infiltrazione )'),
            'prestazione specialistica ortopedica ( prp )' => array('code' => 'ORTP_PRP', 'product' => 'Prestazione specialistica ortopedica ( PRP )'),
            'rimborso per anticipo pagamento ticket asl giusta fattura nr.' => array('code' => 'TICKET_ASL', 'product' => 'Rimborso per anticipo pagamento ticket ASL giusta fattura nr.'),
            'visita ortopedica' => array('code' => 'VIS_ORT', 'product' => 'Visita ortopedica'),
            'acconto pacchetto prepagato visite mediche sportive' => array('code' => 'PAC', 'product' => 'ACCONTO PACCHETTO PREPAGATO VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 10 visite mediche sportive' => array('code' => 'PAC10', 'product' => 'PACCHETTO PREPAGATO N. 10 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 18 visite mediche sportive' => array('code' => 'PAC18', 'product' => 'PACCHETTO PREPAGATO N. 18 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 8 visite mediche sportive' => array('code' => 'PAC8', 'product' => 'PACCHETTO PREPAGATO N. 8 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 5 visite mediche sportive' => array('code' => 'PAC5', 'product' => 'PACCHETTO PREPAGATO N. 5 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 26 visite mediche sportive' => array('code' => 'PAC26', 'product' => 'PACCHETTO PREPAGATO N. 26 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 30 visite mediche sportive' => array('code' => 'PAC30', 'product' => 'PACCHETTO PREPAGATO N. 30 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 16 visite mediche sportive' => array('code' => 'PAC16', 'product' => 'PACCHETTO PREPAGATO N. 16 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 20 visite mediche sportive' => array('code' => 'PAC20', 'product' => 'PACCHETTO PREPAGATO N. 20 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 40 visite mediche sportive' => array('code' => 'PAC40', 'product' => 'PACCHETTO PREPAGATO N. 40 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 15 visite mediche sportive' => array('code' => 'PAC15', 'product' => 'PACCHETTO PREPAGATO N. 15 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 22 visite mediche sportive' => array('code' => 'PAC22', 'product' => 'PACCHETTO PREPAGATO N. 22 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 80 visite mediche sportive' => array('code' => 'PAC80', 'product' => 'PACCHETTO PREPAGATO N. 80 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 27 visite mediche sportive' => array('code' => 'PAC27', 'product' => 'PACCHETTO PREPAGATO N. 27 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 12 visite mediche sportive' => array('code' => 'PAC12', 'product' => 'PACCHETTO PREPAGATO N. 12 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 25 visite mediche sportive' => array('code' => 'PAC25', 'product' => 'PACCHETTO PREPAGATO N. 25 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 55 visite mediche sportive' => array('code' => 'PAC55', 'product' => 'PACCHETTO PREPAGATO N. 55 VISITE MEDICHE SPORTIVE'),
            'pacchetto prepagato n. 50 visite mediche sportive' => array('code' => 'PAC50', 'product' => 'PACCHETTO PREPAGATO N. 50 VISITE MEDICHE SPORTIVE'),
            'saldo pacchetto prepagato visite mediche sportive' => array('code' => 'PAC', 'product' => 'SALDO PACCHETTO PREPAGATO VISITE MEDICHE SPORTIVE'),
        );
    }


    private function f24_get_pdf_base64($doc_id)
    {
        if (!$doc_id) {
            return null;
        }

        $response = wp_remote_post('https://www.app.fattura24.com/api/v0.3/GetFile', array(
            'timeout' => 30,
            'body' => array(
                'apiKey' => $this->f24_api_key,
                'docId' => $doc_id,
            ),
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return null;
        }

        // Se arriva JSON con result base64
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (!empty($decoded['file'])) {
                return $decoded['file'];
            }
            if (!empty($decoded['result']['file'])) {
                return $decoded['result']['file'];
            }
        }

        // Altrimenti assumiamo sia binario PDF
        return base64_encode($body);
    }


    public function totemsport_cashmatic_active()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }

        $token = $this->cashmatic_get_token();
        if (is_wp_error($token)) {
            wp_send_json_error(array('message' => $token->get_error_message()));
        }

        $response = $this->cashmatic_request('/device/ActiveTransaction', array(), array(
            'token' => $token,
            'method' => 'GET',
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = $response['code'] ?? null;
        if ($code !== 0) {
            $message = $response['message'] ?? 'Errore ActiveTransaction Cashmatic';
            wp_send_json_error(array('message' => $message, 'result' => $response));
        }

        wp_send_json_success(array('result' => $response));
    }


    public function totemsport_cashmatic_last()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }

        $token = $this->cashmatic_get_token();
        if (is_wp_error($token)) {
            wp_send_json_error(array('message' => $token->get_error_message()));
        }

        $response = $this->cashmatic_request('/device/LastTransaction', array(), array(
            'token' => $token,
            'method' => 'GET',
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = $response['code'] ?? null;
        if ($code !== 0) {
            $message = $response['message'] ?? 'Errore LastTransaction Cashmatic';
            wp_send_json_error(array('message' => $message, 'result' => $response));
        }

        wp_send_json_success(array('result' => $response));
    }


    private function cashmatic_request($endpoint, $body = array(), $args = array())
    {
        $defaults = array(
            'method' => 'POST',
            'token' => '',
            'auth' => true,
            'timeout' => 30,
        );
        $args = wp_parse_args($args, $defaults);

        $headers = array('Content-Type' => 'application/json');
        if ($args['auth'] && !empty($args['token'])) {
            $headers['Authorization'] = 'Bearer ' . $args['token'];
        } elseif ($args['auth'] && empty($args['token'])) {
            $token = $this->cashmatic_get_token();
            if (is_wp_error($token)) {
                return $token;
            }
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $url = rtrim($this->cashmatic_base_url, '/') . '/' . ltrim($endpoint, '/');

        $response = wp_remote_request($url, array(
            'method' => $args['method'],
            'timeout' => $args['timeout'],
            'headers' => $headers,
            'body' => !empty($body) ? wp_json_encode($body) : '',
            'sslverify' => false, // Cashmatic usa spesso certificati self-signed in LAN
        ));

        if (is_wp_error($response)) {
            return new WP_Error('cashmatic_http_error', 'Errore connessione Cashmatic: ' . $response->get_error_message());
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($decoded)) {
            return new WP_Error('cashmatic_invalid_response', 'Risposta non valida da Cashmatic');
        }

        return $decoded;
    }


    private function cashmatic_get_token()
    {
        $cached = get_option($this->cashmatic_token_option);

        // Se ho un token valido, provo a rinnovarlo come best practice (RenewToken); se fallisce, faccio login
        if (is_array($cached) && !empty($cached['token'])) {
            $renew = $this->cashmatic_renew_token($cached['token']);
            if (!is_wp_error($renew)) {
                return $renew; // token rinnovato
            }
        }

        // Nessun token o rinnovo fallito → login
        return $this->cashmatic_login();
    }


    private function cashmatic_login()
    {
        $payload = array(
            'username' => $this->cashmatic_username,
            'password' => $this->cashmatic_password,
        );

        $response = $this->cashmatic_request('/user/Login', $payload, array(
            'auth' => false,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = $response['code'] ?? null;
        $message = $response['message'] ?? 'Login Cashmatic fallito';

        if ($code !== 0) {
            return new WP_Error('cashmatic_login_failed', $message);
        }

        $token = '';
        if (!empty($response['token']) && is_string($response['token'])) {
            $token = $response['token'];
        } elseif (!empty($response['data']['token']) && is_string($response['data']['token'])) {
            $token = $response['data']['token'];
        } elseif (!empty($response['result']['token']) && is_string($response['result']['token'])) {
            $token = $response['result']['token'];
        }

        if (empty($token)) {
            return new WP_Error('cashmatic_token_missing', 'Token non restituito dal login Cashmatic');
        }

        $this->cashmatic_save_token($token);

        return $token;
    }


    private function cashmatic_renew_token($current_token)
    {
        if (empty($current_token)) {
            return new WP_Error('cashmatic_token_missing', 'Token mancante per il rinnovo Cashmatic');
        }

        $response = $this->cashmatic_request('/user/RenewToken', array(), array(
            'token' => $current_token,
            'auth' => true,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = $response['code'] ?? null;
        $message = $response['message'] ?? 'RenewToken Cashmatic fallito';

        if ($code !== 0) {
            return new WP_Error('cashmatic_renew_failed', $message);
        }

        $token = '';
        if (!empty($response['token']) && is_string($response['token'])) {
            $token = $response['token'];
        } elseif (!empty($response['data']['token']) && is_string($response['data']['token'])) {
            $token = $response['data']['token'];
        } elseif (!empty($response['result']['token']) && is_string($response['result']['token'])) {
            $token = $response['result']['token'];
        }

        if (empty($token)) {
            return new WP_Error('cashmatic_token_missing', 'Token non restituito dal rinnovo Cashmatic');
        }

        $this->cashmatic_save_token($token);

        return $token;
    }


    private function cashmatic_save_token($token)
    {
        $cache_payload = array(
            'token' => $token,
            // durata 15 minuti → lo rinnoviamo sempre all'uso, ma imposto comunque una scadenza corta
            'expires_at' => time() + (12 * 60),
        );
        update_option($this->cashmatic_token_option, $cache_payload, false);
    }


    public function print_label_handler()
    {

        if (isset($_GET['action']) && $_GET['action'] === 'totemsport_print_label' && isset($_GET['id'])) {
            $user = wp_get_current_user();
            if (!current_user_can('manage_options')) {
                header('Location: ' . home_url());
                exit; // prevent direct access
            }
            $id_app = intval($_GET['id']);
            $appuntamento = AppointmentHelper::get_app_data($id_app);
            PdfHelper::generate_label_html($appuntamento);
            exit();
        }
    }

    public function aggiorna_entry($new_entry)
    {

        // Recupera i nuovi dati
        $updated_entry = GFAPI::get_entry($new_entry['id']);
        $entry_id = $_SESSION['entry_id'] ?? null;
        if (is_wp_error($updated_entry))
            return;

        // Unisci dati nuovi e ID esistente
        $updated_entry['id'] = $entry_id;

        $original_entry = GFAPI::get_entry($entry_id);
        if (is_wp_error($original_entry))
            return;

        // --- duplico la firma --- 
        foreach ($updated_entry as $key => $value) {
            if ($key === 'id')
                continue;

            // Controlla se il campo è una firma (tipicamente URL / path)
            if (!empty($value) && is_string($value) && strpos($value, '.png') !== false) {
                // Copia fisicamente il file
                $upload_dir = wp_upload_dir();

                $file_url = $upload_dir['basedir'] . '/gravity_forms/signatures/' . $value;
                if (file_exists($file_url)) {

                    //rinomino il file per evitare conflitti
                    $new_filename = uniqid('gf_signature_') . '.png';
                    $new_file_path = $upload_dir['basedir'] . '/gravity_forms/signatures/' . $new_filename;
                    copy($file_url, $new_file_path);
                    $updated_entry[$key] = $new_filename;
                }
            }
        }



        // Aggiorna la entry originale
        GFAPI::update_entry($updated_entry);

        // Cancella la nuova (duplicata)
        GFAPI::delete_entry($new_entry['id']);

        unset($_SESSION['entry_id']);

        // Redirect o messaggio
        wp_safe_redirect('/totem/?step=2&idappuntamento=' . $_GET['idappuntamento']);
        exit;
    }


    public function popola_gform($form, $entry_globale)
    {


        if (empty($entry_globale)) {
            return $form; // nessuna entry -> form vuoto
        }

        foreach ($form['fields'] as &$field) {
            // Gestione campi composti
            if (!empty($field->inputs) && is_array($field->inputs)) {
                foreach ($field->inputs as &$input) {
                    $input_id = (string) $input['id'];
                    $entry_key = $input_id;
                    if (isset($entry_globale[$entry_key])) {
                        $input['defaultValue'] = $entry_globale[$entry_key];
                    }
                }
            } else {
                $entry_key = $field->id;
                if (isset($entry_globale[$entry_key])) {
                    $field->defaultValue = $entry_globale[$entry_key];
                }
            }
        }

        return $form;
    }
    public function registra_custom_post_type()
    {
        $labels = array(
            'name' => _x('Accettazioni Confermate', 'post type general name', 'totemsport'),
            'singular_name' => _x('Accettazione Confermata', 'post type singular name', 'totemsport'),
            'menu_name' => _x('Accettazioni Confermate', 'admin menu', 'totemsport'),
            'name_admin_bar' => _x('Accettazione Confermata', 'add new on admin bar', 'totemsport'),
            'add_new' => _x('Aggiungi Nuovo', 'accettazione confermata', 'totemsport'),
            'add_new_item' => __('Aggiungi Nuova Accettazione Confermata', 'totemsport'),
            'new_item' => __('Nuova Accettazione Confermata', 'totemsport'),
            'edit_item' => __('Modifica Accettazione Confermata', 'totemsport'),
            'view_item' => __('Visualizza Accettazione Confermata', 'totemsport'),
            'all_items' => __('Tutte Le Accettazioni Confermate', 'totemsport'),
            'search_items' => __('Cerca Accettazioni Confermate', 'totemsport'),
            'parent_item_colon' => __('Accettazioni Confermate Genitore:', 'totemsport'),
            'not_found' => __('Nessuna accettazione confermata trovata.', 'totemsport'),
            'not_found_in_trash' => __('Nessuna accettazione confermata trovata nel cestino.', 'totemsport')
        );

        $args = array(
            'labels' => $labels,
            // Non pubblico in frontend ma visibile nella dashboard
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            // mostrare il post type come sottomenu della pagina con slug 'totemsport'
            'show_in_menu' => 'totemsport',
            'query_var' => true,
            // slug corto: WP limita il post_type a ~20 caratteri
            //  'rewrite'            => array( 'slug' => 'appunt_conf' ),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => array('title', 'editor'),
        );
        // usare uno slug interno corto per il post type (<=20 chars)
        register_post_type('appunt_conf', $args);
    }




    public function totemsport_cf_search()
    {

        $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        $results = SearchHelper::search_from_cf($term);
        wp_send_json($results);
    }



    public function register_shortcodes()
    {
        add_shortcode('totemsport_public_area', array($this, 'render_public_area'));
        add_shortcode('totemsport_private_area', array($this, 'render_admin_area'));
        add_shortcode('totemsport_nurse_area', array($this, 'render_nurse_area'));
        add_shortcode('totemsport_med_area', array($this,'render_med_area'));
    }

    public function render_public_area($atts)
    {

        include plugin_dir_path(__FILE__) . 'public-area.php';
    }

    public function render_nurse_area($atts)
    {

        include plugin_dir_path(__FILE__) . 'nurse-area.php';
    }

    public function render_med_area()
    {
        include plugin_dir_path(__FILE__) . 'med-area.php';
    }


    public function render_admin_area()
    {
        include plugin_dir_path(__FILE__) . 'admin-area.php';
    }

    public function admin_menu()
    {
        add_menu_page(
            __('TotemSport', 'totemsport'),
            __('TotemSport', 'totemsport'),
            'manage_options',
            'totemsport',
            array($this, 'admin_page'),
            'dashicons-admin-site',
            20
        );

        add_submenu_page(
            'totemsport',
            __('Archivio Documenti', 'totemsport'),
            __('Archivio', 'totemsport'),
            'manage_options',
            'totemsport-archive',
            array($this, 'archive_page')
        );
        
        add_submenu_page(
            'totemsport',
            __('Configurazione Google Drive', 'totemsport'),
            __('Google Drive', 'totemsport'),
            'manage_options',
            'totemsport-google-drive',
            array($this, 'google_drive_settings_page')
        );

        add_submenu_page(
            'totemsport',
            __('Report', 'totemsport'),
            __('Report', 'totemsport'),
            'manage_options',
            'totemsport-report',
            array($this, 'report_page'),
        );

        add_submenu_page(
            'totemsport',
            __('Configurazione Cashmatic', 'totemsport'),
            __('Cashmatic', 'totemsport'),
            'manage_options',
            'totemsport-cashmatic',
            array($this, 'cashmatic_settings_page')
        );

        add_submenu_page(
            'totemsport',
            __('Configurazione POS', 'totemsport'),
            __('POS', 'totemsport'),
            'manage_options',
            'totemsport-pos',
            array($this, 'pos_settings_page')
        );
    }

    public function admin_assets($hook)
    {
        // Only load our admin CSS/JS on our plugin page
        if (strpos($hook, 'totemsport') === false) {
            return;
        }
        wp_enqueue_style('totemsport-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', array(), '1.0.0');
        
        wp_enqueue_script('totemsport-nurse', plugin_dir_url(__FILE__) . 'assets/nurse.js', array(), '1.0.0');

        wp_enqueue_script('totemsport-med', plugin_dir_url(__FILE__) . 'assets/med.js', array(), '1.0.0');
    }

    public function cashmatic_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Accesso negato', 'totemsport'));
        }

        $saved = get_option($this->cashmatic_config_option, array());
        $base_url = isset($saved['base_url']) ? esc_attr($saved['base_url']) : $this->cashmatic_base_url;
        $username = isset($saved['username']) ? esc_attr($saved['username']) : $this->cashmatic_username;
        $password = isset($saved['password']) ? esc_attr($saved['password']) : $this->cashmatic_password;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('totemsport_cashmatic_save', 'totemsport_cashmatic_nonce')) {
            $base_url = isset($_POST['cashmatic_base_url']) ? esc_url_raw(trim($_POST['cashmatic_base_url'])) : '';
            $username = isset($_POST['cashmatic_username']) ? sanitize_text_field($_POST['cashmatic_username']) : '';
            $password = isset($_POST['cashmatic_password']) ? sanitize_text_field($_POST['cashmatic_password']) : '';

            $cfg = array(
                'base_url' => $base_url,
                'username' => $username,
                'password' => $password,
            );
            update_option($this->cashmatic_config_option, $cfg, false);

            echo '<div class="updated notice"><p>Configurazione Cashmatic salvata.</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>Configurazione Cashmatic</h1>
            <form method="post">
                <?php wp_nonce_field('totemsport_cashmatic_save', 'totemsport_cashmatic_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="cashmatic_base_url">Endpoint Base</label></th>
                        <td><input type="text" name="cashmatic_base_url" id="cashmatic_base_url" value="<?php echo esc_attr($base_url); ?>" class="regular-text" placeholder="https://192.168.1.175:50301/api/" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cashmatic_username">Username</label></th>
                        <td><input type="text" name="cashmatic_username" id="cashmatic_username" value="<?php echo esc_attr($username); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cashmatic_password">Password</label></th>
                        <td><input type="password" name="cashmatic_password" id="cashmatic_password" value="<?php echo esc_attr($password); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button('Salva Configurazione'); ?>
            </form>
            <p class="description">Imposta l'endpoint Cashmatic SelfPay (includere /api/), le credenziali e salva. Le chiamate di pagamento useranno questi valori.</p>
        </div>
        <?php
    }

    public function report_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Accesso negato', 'totemsport'));
        }
        
        echo '<div class="wrap"><h1>Report appuntamenti</h1>';
        // Form giornaliero
        echo '<form id="totemsport-report-form" style="margin-bottom:20px;display:flex;align-items:center;gap:12px;">';
        echo '<label for="totemsport-report-date" style="font-size:16px;font-weight:500;">Data:</label>';
        echo '<input type="date" id="totemsport-report-date" name="data" value="'.date('Y-m-d').'" style="font-size:16px;padding:4px 8px;">';
        echo '<button id="totemsport-download-report-btn" type="submit" class="button button-primary" style="font-size:18px;padding:10px 24px;">Scarica PDF giornata</button>';
        echo '</form>';
        // Form mensile
        echo '<form id="totemsport-report-mese-form" style="margin-bottom:20px;display:flex;align-items:center;gap:12px;">';
        echo '<label for="totemsport-report-mese-anno" style="font-size:16px;font-weight:500;">Anno:</label>';
        echo '<input type="number" id="totemsport-report-mese-anno" name="anno" min="2020" max="2100" value="'.date('Y').'" style="font-size:16px;width:90px;padding:4px 8px;">';
        echo '<label for="totemsport-report-mese-mese" style="font-size:16px;font-weight:500;">Mese:</label>';
        echo '<select id="totemsport-report-mese-mese" name="mese" style="font-size:16px;padding:4px 8px;">';
        for($m=1;$m<=12;$m++){
            $sel = ($m==date('n')) ? 'selected' : '';
            echo '<option value="'.$m.'" '.$sel.'>'.sprintf('%02d',$m).'</option>';
        }
        echo '</select>';
        echo '<button id="totemsport-download-report-mese-btn" type="submit" class="button button-primary" style="font-size:18px;padding:10px 24px;">Scarica PDF mese</button>';
        echo '</form>';
        echo '<div id="totemsport-report-result" style="margin-top:20px;"></div>';
        echo '</div>';
        ?>
        <script>
        jQuery(document).ready(function($){
            // Giornaliero
            $('#totemsport-report-form').on('submit', function(e){
                e.preventDefault();
                var btn = $('#totemsport-download-report-btn');
                var data = $('#totemsport-report-date').val();
                btn.prop('disabled', true).text('Generazione in corso...');
                $('#totemsport-report-result').html('');
                $.post(ajaxurl, {action: 'totemsport_generate_report_giornata_pdf', data: data}, function(resp){
                    btn.prop('disabled', false).text('Scarica PDF giornata');
                    if(resp.success && resp.data && resp.data.url){
                        $('#totemsport-report-result').html('<a href="'+resp.data.url+'" target="_blank" class="button button-success" style="font-size:16px;">Download PDF</a>');
                    }else{
                        $('#totemsport-report-result').html('<span style="color:red;">Errore: '+(resp.data && resp.data.msg ? resp.data.msg : 'Errore generico')+'</span>');
                    }
                });
            });
            // Mensile
            $('#totemsport-report-mese-form').on('submit', function(e){
                e.preventDefault();
                var btn = $('#totemsport-download-report-mese-btn');
                var anno = $('#totemsport-report-mese-anno').val();
                var mese = $('#totemsport-report-mese-mese').val();
                btn.prop('disabled', true).text('Generazione in corso...');
                $('#totemsport-report-result').html('');
                $.post(ajaxurl, {action: 'totemsport_generate_report_mese_pdf', anno: anno, mese: mese}, function(resp){
                    btn.prop('disabled', false).text('Scarica PDF mese');
                    if(resp.success && resp.data && resp.data.url){
                        $('#totemsport-report-result').html('<a href="'+resp.data.url+'" target="_blank" class="button button-success" style="font-size:16px;">Download PDF mese</a>');
                    }else{
                        $('#totemsport-report-result').html('<span style="color:red;">Errore: '+(resp.data && resp.data.msg ? resp.data.msg : 'Errore generico')+'</span>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function pos_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Accesso negato', 'totemsport'));
        }

        $saved = get_option($this->pos_config_option, array());
        $base_url = isset($saved['base_url']) ? esc_attr($saved['base_url']) : $this->pos_bridge_url;
        $terminal_id = isset($saved['terminal_id']) ? esc_attr($saved['terminal_id']) : '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('totemsport_pos_save', 'totemsport_pos_nonce')) {
            $base_url = isset($_POST['pos_base_url']) ? esc_url_raw(trim($_POST['pos_base_url'])) : '';
            $terminal_id = isset($_POST['pos_terminal_id']) ? sanitize_text_field($_POST['pos_terminal_id']) : '';

            $cfg = array(
                'base_url' => $base_url,
                'terminal_id' => $terminal_id,
            );
            update_option($this->pos_config_option, $cfg, false);

            // Aggiorna la URL usata runtime
            if (!empty($base_url)) {
                $this->pos_bridge_url = $base_url;
            }

            echo '<div class="updated notice"><p>Configurazione POS salvata.</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>Configurazione POS Nexi</h1>
            <div id="ecr-print-status-block" style="margin:10px 0; font-weight:bold;">Stato stampa ECR: <span id="ecr-print-status">Caricamento...</span></div>
            <script>
            jQuery(function() {
                function updateEcrPrintStatus() {
                    jQuery.post(ajaxurl, {action: 'totemsport_pos_get_ecr_print_status'}, function(resp) {
                        var el = document.getElementById('ecr-print-status');
                        if (resp.success) {
                            el.textContent = resp.data.enabled ? 'ABILITATA' : 'DISABILITATA';
                            el.style.color = resp.data.enabled ? 'green' : 'red';
                        } else {
                            el.textContent = 'Errore: ' + (resp.data && resp.data.message ? resp.data.message : '');
                            el.style.color = 'gray';
                        }
                    });
                }
                updateEcrPrintStatus();
                // Aggiorna stato dopo click su abilita/disabilita
                jQuery(document).on('click', '#btn-ecr-print-enable, #btn-ecr-print-disable', function() {
                    setTimeout(updateEcrPrintStatus, 1200);
                });
            });
            </script>
            <form method="post" style="margin-bottom:32px;">
                <?php wp_nonce_field('totemsport_pos_save', 'totemsport_pos_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="pos_base_url">Endpoint POS Bridge <span style="color:#888;font-weight:normal;">(es: http://192.168.1.100:65113)</span></label></th>
                        <td>
                            <input type="text" name="pos_base_url" id="pos_base_url" value="<?php echo esc_attr($base_url); ?>" class="regular-text" placeholder="http://192.168.1.100:65113" />
                            <p class="description">Inserisci l'indirizzo IP e la porta del terminale Nexi in rete locale. Esempio: <code>http://192.168.1.100:65113</code> (dove <b>192.168.1.100</b> è l'IP del POS e <b>65113</b> la porta configurata sul terminale).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pos_terminal_id">Terminal ID</label></th>
                        <td><input type="text" name="pos_terminal_id" id="pos_terminal_id" value="<?php echo esc_attr($terminal_id); ?>" class="regular-text" placeholder="00000001" /></td>
                    </tr>
                </table>
                <?php submit_button('Salva Configurazione'); ?>
            </form>
            <p class="description">Imposta l'endpoint del bridge POS Nexi (solo IP:porta in LAN) e il Terminal ID. Le chiamate al POS useranno questi valori.</p>

            <hr style="margin:32px 0;">
            <h2>Test Funzionalità POS Nexi</h2>
            <div style="margin-bottom:16px;">
                <button id="btn-close-session" class="button button-secondary">Chiudi sessione POS (Close Session)</button>
                <button id="btn-ecr-print-enable" class="button button-secondary">Abilita stampa ricevuta su ECR</button>
                <button id="btn-card-verification" class="button button-secondary">Test Card Verification</button>
                <span id="card-verification-result" style="margin-left:16px;font-weight:bold;"></span>
            </div>
            <script>
            jQuery(function() {
                jQuery('#btn-card-verification').on('click', function() {
                    var $btn = jQuery(this);
                    var $res = jQuery('#card-verification-result');
                    $btn.prop('disabled', true);
                    $res.text('Verifica in corso...').css('color', 'gray');
                    jQuery.post(ajaxurl, {action: 'totemsport_pos_card_verification'}, function(resp) {
                        $btn.prop('disabled', false);
                        if (resp.success && resp.data && resp.data.result === 'OK') {
                            $res.text('Carta valida (verifica OK)').css('color', 'green');
                        } else if (resp.data && resp.data.result === 'KO') {
                            $res.text('Verifica negata o carta non valida').css('color', 'red');
                        } else {
                            $res.text('Errore: ' + (resp.data && resp.data.message ? resp.data.message : '')); $res.css('color', 'red');
                        }
                    }).fail(function() {
                        $btn.prop('disabled', false);
                        $res.text('Errore di comunicazione').css('color', 'red');
                    });
                });
            });
            </script>
            <div style="margin-bottom:16px;">
                <strong>Nota:</strong> Questi comandi inviano richieste reali al terminale POS Nexi configurato.
            </div>
            <script src="<?php echo plugins_url('assets/pos.js', __FILE__); ?>"></script>
        </div>
        <?php
    }

    public function public_assets()
    {
        wp_enqueue_style('totemsport-public', plugin_dir_url(__FILE__) . 'assets/public.css', array(), '1.0.0');
        wp_enqueue_script('totemsport-public', plugin_dir_url(__FILE__) . 'assets/public.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('totemsport-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('totemsport-nurse', plugin_dir_url(__FILE__) . 'assets/nurse.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('totemsport-med', plugin_dir_url(__FILE__) . 'assets/med.js', array('jquery'), '1.0.0', true);
        wp_localize_script('totemsport-public', 'totemsportAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'siteUrl' => get_bloginfo('url')
        ));
        wp_localize_script('totemsport-nurse', 'totemsportAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'siteUrl' => get_bloginfo('url')
        ));
        wp_localize_script('totemsport-med', 'totemsportAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'siteUrl' => get_bloginfo('url')
        ));
    }

    public function totemsport_get_app_data()
    {

        $idappuntamento = isset($_GET['idappuntamento']) ? intval($_GET['idappuntamento']) : 0;
        $dati = AppointmentHelper::get_app_data($idappuntamento);
        $tabella = HtmlHelper::format_app_data_as_table($dati);
        $result = ['html' => $tabella, 'success' => 'true', 'dati' => $dati];
        wp_send_json($result);
    }

    public function totemsport_get_app_bo()
    {
        try {
            $html = '';
            $buttons = '';
            $processed_appointments = []; // Traccia gli ID degli appuntamenti già mostrati

            // Recupera TUTTI gli staff una volta sola (riusa la logica esistente)
            $all_staff = AppointmentHelper::get_all_staff();

        $posts = get_posts([
            'post_type' => 'appunt_conf',
            'posts_per_page' => -1,
            'post_status' => ['publish'],
            'date_query' => [
                [
                    'year' => date('Y'),
                    'month' => date('m'),
                    'day' => date('d'),
                ],
            ],
        ]);

        if (!empty($posts)) {

            foreach ($posts as $p) {

                $id_app = get_post_meta($p->ID, 'appointment_id', true);
                if (!$id_app || !get_post($id_app))
                    continue;

                // Skip se questo appuntamento è già stato mostrato
                if (in_array($id_app, $processed_appointments))
                    continue;

                $processed_appointments[] = $id_app;

                $appuntamento = AppointmentHelper::get_app_data($id_app);
                if (!$appuntamento)
                    continue;

                $conf = SearchHelper::get_confirmed_meta($p->ID);

                // --- RECUPERO STAFF: gestisce sia _appointment_staff_ids che _appointment_staff_id ---
                $staff_ids = [];
                $staff_meta_plural = get_post_meta($id_app, '_appointment_staff_ids', true);
                $staff_meta_singular = get_post_meta($id_app, '_appointment_staff_id', true);

                // Prova prima il plurale
                if (!empty($staff_meta_plural)) {
                    $unserialized = maybe_unserialize($staff_meta_plural);
                    if (is_array($unserialized)) {
                        $staff_ids = array_filter(array_map('intval', $unserialized));
                    } elseif (is_numeric($unserialized)) {
                        $staff_ids = [intval($unserialized)];
                    }
                }
                // Se il plurale è vuoto, prova il singolare
                elseif (!empty($staff_meta_singular)) {
                    if (is_numeric($staff_meta_singular)) {
                        $staff_ids = [intval($staff_meta_singular)];
                    }
                }

                // --- DATI PER LA TABELLA ---
                $nome = $appuntamento['nome'] ?? '';
                $cognome = $appuntamento['cognome'] ?? '';
                $tipovisita = $appuntamento['tipologia'] ?? '';
                $pagato_raw = $appuntamento['pagato'] ?? false;
                $totale = $appuntamento['totale'] ?? 0;
                $ora = $appuntamento['ora'] ?? '';
                $cf = $appuntamento['cf'] ?? '';

                $urine_raw = isset($conf['urine']) ? strtolower($conf['urine']) : '';

                $urine_label = '<div class="urine-cell">' .
                    '<div class="urine-status">' .
                    (($urine_raw === 'si')
                        ? '<span class="urine-badge no">✗ No</span>'
                        : '<span class="urine-badge yes">✓ Sì</span>') .
                    '</div>' .
                    '</div>';

                $pagato_label = '<div class="payment-cell">' .
                    '<div class="payment-status">' .
                    ($pagato_raw
                        ? '<span class="payment-badge yes">✓ Sì</span>'
                        : '<span class="payment-badge no">✗ No</span>') .
                    '</div>' .
                    '</div>';

                $nonce = wp_create_nonce('totemsport_toggle_status_' . $p->ID);
                $toggle_url = add_query_arg([
                    'toggle_status' => $p->ID,
                    '_wpnonce' => $nonce
                ], get_the_permalink(get_the_ID()));

                $priorita_html = ($appuntamento['priorita'] ?? false)
                    ? '<div class="priority-indicator" title="Accettazione prioritaria">!</div>'
                    : '';

                // Riga tabella — ora con staff_ids corretti
                $html .= '<tr data-staff="' . esc_attr(implode(',', $staff_ids)) . '" data-cf="' . esc_attr($cf) . '">';
                $html .= '<td style="text-align:center">' . $priorita_html . '</td>';
                $html .= '<td>' . esc_html($p->ID) . '</td>';
                $html .= '<td>' . esc_html($nome) . '</td>';
                $html .= '<td>' . esc_html($cognome) . '</td>';
                $html .= '<td>' . esc_html($ora) . '</td>';
                $html .= '<td>' . esc_html($tipovisita) . '</td>';
                $html .= '<td>' . $urine_label . '</td>';
                $html .= '<td>' . $pagato_label . '</td>';
                $html .= '<td><a class="btn-action btn-preview show_modal" data-type="anamnesi" data-id="' . esc_attr($p->ID) . '" href="#" title="Visualizza anamnesi"><i class="bi bi-eye"></i> <span>Visualizza</span></a></td>';
                $html .= '<td><a class="btn-action btn-preview show_modal" data-type="consenso" data-id="' . esc_attr($p->ID) . '" href="#" title="Visualizza consenso"><i class="bi bi-eye"></i> <span>Visualizza</span></a></td>';
                $html .= '<td>';
                $html .= '<div class="action-buttons">';
                $html .= '<button class="btn-action btn-toggle btn-close-appointment" data-id="' . esc_attr($p->ID) . '" title="Invia all\"infermiere"><i class="bi bi-check-circle"></i> <span>Chiudi</span></button>';
                $html .= '</div>';
                $html .= '</td>';
                $html .= '</tr>';
            }

            // --- GENERAZIONE FILTRI STAFF ---
            $buttons = '<button class="btn btn-primary filter-staff" data-staff="all">Tutti</button>';
            foreach ($all_staff as $sid => $name) {
                if (empty(trim($name)))
                    continue;
                $buttons .= ' <button class="btn btn-primary filter-staff" data-staff="' . esc_attr($sid) . '">' . esc_html($name) . '</button>';
            }

        } else {
            $html = '<tr><td colspan="11">Nessuna Accettazione trovata.</td></tr>';
        }

        // Recupera gli appuntamenti completati/in lavorazione post-infermiere (draft, private, pending)
        $posts_completed = get_posts([
            'post_type' => 'appunt_conf',
            'posts_per_page' => -1,
            'post_status' => ['draft', 'private', 'pending'],
            'date_query' => [
                [
                    'year' => date('Y'),
                    'month' => date('m'),
                    'day' => date('d'),
                ],
            ],
        ]);

        $html_completed = '';
        if (!empty($posts_completed)) {
            foreach ($posts_completed as $p) {
                $id_app = get_post_meta($p->ID, 'appointment_id', true);
                if (!$id_app || !get_post($id_app))
                    continue;

                // Skip se questo appuntamento è già stato mostrato nella tabella "da processare"
                if (in_array($id_app, $processed_appointments))
                    continue;

                $appuntamento = AppointmentHelper::get_app_data($id_app);
                if (!$appuntamento)
                    continue;

                // --- RECUPERO STAFF: gestisce sia _appointment_staff_ids che _appointment_staff_id ---
                $staff_ids = [];
                $staff_meta_plural = get_post_meta($id_app, '_appointment_staff_ids', true);
                $staff_meta_singular = get_post_meta($id_app, '_appointment_staff_id', true);

                // Prova prima il plurale
                if (!empty($staff_meta_plural)) {
                    $unserialized = maybe_unserialize($staff_meta_plural);
                    if (is_array($unserialized)) {
                        $staff_ids = array_filter(array_map('intval', $unserialized));
                    } elseif (is_numeric($unserialized)) {
                        $staff_ids = [intval($unserialized)];
                    }
                }
                // Se il plurale è vuoto, prova il singolare
                elseif (!empty($staff_meta_singular)) {
                    if (is_numeric($staff_meta_singular)) {
                        $staff_ids = [intval($staff_meta_singular)];
                    }
                }

                $nome = $appuntamento['nome'] ?? '';
                $cognome = $appuntamento['cognome'] ?? '';
                $tipologia = $appuntamento['tipologia'] ?? '';
                $ora = $appuntamento['ora'] ?? '';

                $html_completed .= '<tr data-staff="' . esc_attr(implode(',', $staff_ids)) . '">';
                $html_completed .= '<td>' . esc_html($p->ID) . '</td>';
                $html_completed .= '<td>' . esc_html($nome) . '</td>';
                $html_completed .= '<td>' . esc_html($cognome) . '</td>';
                $html_completed .= '<td>' . esc_html($ora) . '</td>';
                $html_completed .= '<td>' . esc_html($tipologia) . '</td>';
                $html_completed .= '<td><a class="btn-action btn-preview show_modal" data-type="anamnesi" data-id="' . esc_attr($p->ID) . '" href="#" title="Visualizza anamnesi"><i class="bi bi-eye"></i> <span>Visualizza</span></a></td>';
                $html_completed .= '<td><a class="btn-action btn-preview show_modal" data-type="consenso" data-id="' . esc_attr($p->ID) . '" href="#" title="Visualizza consenso"><i class="bi bi-eye"></i> <span>Visualizza</span></a></td>';
                $html_completed .= '<td><div class="action-buttons" style="gap: 0.3rem;">' .
                    '<button class="btn-action btn-close-appointment" data-id="' . esc_attr($p->ID) . '" title="Azioni di chiusura" style="background: linear-gradient(135deg, #28a745 0%, #20873a 100%); color: white;"><i class="bi bi-check-circle"></i> <span>Azioni</span></button>' .
                    '<button class="btn-action btn-delete-appointment" data-id="' . esc_attr($p->ID) . '" title="Elimina Accettazione" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;"><i class="bi bi-trash"></i> <span>Elimina</span></button>' .
                    '</div></td>';
                $html_completed .= '</tr>';
            }
        } else {
            $html_completed = '<tr><td colspan="7">Nessuna accettazione completata.</td></tr>';
        }

            wp_send_json([
                'success' => true,
                'html' => $html,
                'html_completed' => $html_completed,
                'filters' => $buttons
            ]);
        } catch (Exception $e) {
            wp_send_json([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage()
            ]);
        }
    }




    function totemsport_get_doc_bo()
    {

        //prendo il valore type da POST
        //prendo il valore id da POST
        $type = isset($_POST['type']) ? $_POST['type'] : '';
        $id_appuntamento_confermato = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $id = get_post_meta($id_appuntamento_confermato, 'appointment_id', true);



        $appuntamento = AppointmentHelper::get_app_data($id);
        if ($type == 'anamnesi') {
            $form_id = 2; // ID Anamnesi
            $key = 80;
        } else {

            $key = 3;
            $form_id = $appuntamento['form_id'];

        }

        $form = GFAPI::get_form($form_id);
        $entries = AppointmentHelper::get_form_data($appuntamento['cf'], $key, $form_id, true);
        $entry = $entries[0];
        $html = HtmlHelper::show_html_anamnesi($form, $entry);
        $html .= '<a class="print" target="_blank" href="?gf_page=print-entry&fid=' . $form_id . '&lid=' . $entry['id'] . '&notes=1">Salva in PDF o Stampa</a>';
        $result = ['html' => base64_encode($html), 'success' => 'true'];
        header('Content-Type: application/json; charset=utf-8');
        wp_send_json($result);
        exit();
    }

    // Endpoint per chiudere un appuntamento dall'admin e inviarlo all'infermiere
    function totemsport_admin_close()
    {
        $user = wp_get_current_user();
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        
        if (!$appointment_id) {
            wp_send_json(['success' => false, 'message' => 'ID accettazione mancante']);
            exit();
        }

        // Lock anti-doppio invio: accetta solo la prima richiesta entro 10 secondi
        $lock_key = 'admin_close_lock';
        $now = time();
        $lock = get_post_meta($appointment_id, $lock_key, true);
        if ($lock && ($now - intval($lock)) < 10) {
            wp_send_json(['success' => false, 'message' => 'Operazione già in corso o appena eseguita. Attendi qualche secondo e aggiorna la pagina.']);
            exit();
        }
        update_post_meta($appointment_id, $lock_key, $now);

        // Controllo stato: solo appuntamenti ancora "publish" possono essere chiusi
        $current_status = get_post_status($appointment_id);
        if ($current_status !== 'publish') {
            wp_send_json(['success' => false, 'message' => 'Appuntamento già chiuso o in lavorazione.']);
            exit();
        }

        // Cambia lo status da publish a draft (invia all'infermiere)
        $result = wp_update_post([
            'ID' => $appointment_id,
            'post_status' => 'draft'
        ]);

        if ($result) {
            // Salva l'utente admin che effettua la chiusura
            update_post_meta($appointment_id, 'admin_user', $user->ID);
            update_post_meta($appointment_id, 'admin_close_time', current_time('mysql'));
            // Archiviazione automatica all'atto della chiusura
            ArchiveHelper::archive_appointment($appointment_id);
            wp_send_json(['success' => true, 'message' => 'Accettazione inviata all\'infermiere e archiviata']);
        } else {
            wp_send_json(['success' => false, 'message' => 'Errore durante l\'aggiornamento']);
        }
        exit();
    }

    // Endpoint per recuperare appuntamenti per l'infermiere (status = draft)
    function totemsport_get_nurse_apps()
    {
        $user = wp_get_current_user();
        if (!in_array('infermiere', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            wp_send_json(['success' => false, 'message' => 'Accesso negato']);
            exit();
        }

        $all_staff = AppointmentHelper::get_all_staff();
        $filter_staff = isset($_GET['filter_staff']) ? $_GET['filter_staff'] : 'all';
        $filter_nome = isset($_GET['filter_nome']) ? sanitize_text_field($_GET['filter_nome']) : '';
        $filter_cognome = isset($_GET['filter_cognome']) ? sanitize_text_field($_GET['filter_cognome']) : '';
        $filter_cf = isset($_GET['filter_cf']) ? sanitize_text_field($_GET['filter_cf']) : '';

        $html = '';
        $processed_appointments = [];

        $today = date('d/m/Y', current_time('timestamp'));

        // --- ACCETTAZIONI DA FARE (status draft) ---
        $posts = get_posts([
            'post_type' => 'appunt_conf',
            'post_status' => array('draft'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
            'date_query' => [
                [
                    'year'  => date('Y', current_time('timestamp')),
                    'month' => date('m', current_time('timestamp')),
                    'day'   => date('d', current_time('timestamp')),
                ],
            ],
        ]);

        foreach ($posts as $p) {
            $id_app = get_post_meta($p->ID, 'appointment_id', true);
            if (!$id_app || !get_post($id_app)) continue;

            $appuntamento = AppointmentHelper::get_app_data($id_app);
            if (!$appuntamento) continue;

            // Solo appuntamenti di oggi
            $app_date = $appuntamento['data'] ?? '';
            if ($app_date !== $today) continue;

            $nome = $appuntamento['nome'] ?? '';
            $cognome = $appuntamento['cognome'] ?? '';
            $cf = $appuntamento['cf'] ?? '';
            $tipologia = $appuntamento['tipologia'] ?? '';
            $ora = $appuntamento['ora'] ?? '';

            // --- RECUPERO STAFF ---
            $staff_ids = [];
            $staff_meta_plural = get_post_meta($id_app, '_appointment_staff_ids', true);
            $staff_meta_singular = get_post_meta($id_app, '_appointment_staff_id', true);
            if (!empty($staff_meta_plural)) {
                $unserialized = maybe_unserialize($staff_meta_plural);
                if (is_array($unserialized)) {
                    $staff_ids = array_filter(array_map('intval', $unserialized));
                } elseif (is_numeric($unserialized)) {
                    $staff_ids = [intval($unserialized)];
                }
            } elseif (!empty($staff_meta_singular)) {
                if (is_numeric($staff_meta_singular)) {
                    $staff_ids = [intval($staff_meta_singular)];
                }
            }

            // --- FILTRI ---
            if ($filter_staff !== 'all') {
                if (!in_array(intval($filter_staff), $staff_ids)) continue;
            }
            if ($filter_nome && stripos($nome, $filter_nome) === false) continue;
            if ($filter_cognome && stripos($cognome, $filter_cognome) === false) continue;
            if ($filter_cf && stripos($cf, $filter_cf) === false) continue;

            $processed_appointments[] = $id_app;

            $html .= '<tr data-staff="' . esc_attr(implode(',', $staff_ids)) . '" data-nome="' . esc_attr(strtolower($nome)) . '" data-cognome="' . esc_attr(strtolower($cognome)) . '" data-cf="' . esc_attr(strtolower($cf)) . '">';
            $html .= '<td>' . esc_html($p->ID) . '</td>';
            $html .= '<td>' . esc_html($nome) . '</td>';
            $html .= '<td>' . esc_html($cognome) . '</td>';
            $html .= '<td>' . esc_html($ora) . '</td>';
            $html .= '<td>' . esc_html($tipologia) . '</td>';
            $html .= '<td><button class="btn-action btn-preview nurse-view-btn" data-id="' . esc_attr($p->ID) . '" title="Visualizza documenti"><i class="bi bi-eye"></i> <span>Visualizza</span></button></td>';
            $html .= '</tr>';
        }

        if ($html === '') {
            $html = '<tr><td colspan="6" class="text-center">Nessuna accettazione di oggi</td></tr>';
        }

        // --- ACCETTAZIONI COMPLETATE (status private o pending) ---
        $posts_completed = get_posts([
            'post_type' => 'appunt_conf',
            'posts_per_page' => -1,
            'post_status' => ['private', 'pending'],
            'orderby' => 'date',
            'order' => 'ASC',
            'date_query' => [
                [
                    'year'  => date('Y', current_time('timestamp')),
                    'month' => date('m', current_time('timestamp')),
                    'day'   => date('d', current_time('timestamp')),
                ],
            ],
        ]);

        $html_completed = '';
            // --- PAGINAZIONE ---
            $page = isset($_GET['page_completed']) ? max(1, intval($_GET['page_completed'])) : 1;
            $per_page = 10;
            $rows = array();
            foreach ($posts_completed as $p) {
                $id_app = get_post_meta($p->ID, 'appointment_id', true);
                if (!$id_app || !get_post($id_app)) continue;
                if (in_array($id_app, $processed_appointments)) continue;

            $appuntamento = AppointmentHelper::get_app_data($id_app);
            if (!$appuntamento) continue;

            // Solo appuntamenti di oggi
            $app_date = $appuntamento['data'] ?? '';
            if ($app_date !== $today) continue;

            $nome = $appuntamento['nome'] ?? '';
            $cognome = $appuntamento['cognome'] ?? '';
            $cf = $appuntamento['cf'] ?? '';
            $tipologia = $appuntamento['tipologia'] ?? '';
            $ora = $appuntamento['ora'] ?? '';

            // --- RECUPERO STAFF ---
            $staff_ids = [];
            $staff_meta_plural = get_post_meta($id_app, '_appointment_staff_ids', true);
            $staff_meta_singular = get_post_meta($id_app, '_appointment_staff_id', true);
            if (!empty($staff_meta_plural)) {
                $unserialized = maybe_unserialize($staff_meta_plural);
                if (is_array($unserialized)) {
                    $staff_ids = array_filter(array_map('intval', $unserialized));
                } elseif (is_numeric($unserialized)) {
                    $staff_ids = [intval($unserialized)];
                }
            } elseif (!empty($staff_meta_singular)) {
                if (is_numeric($staff_meta_singular)) {
                    $staff_ids = [intval($staff_meta_singular)];
                }
            }

            // --- FILTRI ---
            if ($filter_staff !== 'all') {
                if (!in_array(intval($filter_staff), $staff_ids)) continue;
            }
            if ($filter_nome && stripos($nome, $filter_nome) === false) continue;
            if ($filter_cognome && stripos($cognome, $filter_cognome) === false) continue;
            if ($filter_cf && stripos($cf, $filter_cf) === false) continue;

            // Indicatori Admin
            $priorita_html = ($appuntamento['priorita'] ?? false)
                ? '<div class="priority-indicator" title="Accettazione prioritaria">!</div>'
                : '';

            $rows[] = '<tr data-staff="' . esc_attr(implode(',', $staff_ids)) . '" data-nome="' . esc_attr(strtolower($nome)) . '" data-cognome="' . esc_attr(strtolower($cognome)) . '" data-cf="' . esc_attr(strtolower($cf)) . '">' .
                '<td style="text-align:center">' . $priorita_html . '</td>' .
                '<td>' . esc_html($p->ID) . '</td>' .
                '<td>' . esc_html($nome) . '</td>' .
                '<td>' . esc_html($cognome) . '</td>' .
                '<td>' . esc_html($ora) . '</td>' .
                '<td><button class="btn-action btn-preview nurse-edit-btn" data-id="' . esc_attr($p->ID) . '" title="Modifica"><i class="bi bi-pencil"></i> <span>Modifica</span></button></td>' .
                '</tr>';
        }

        $page = isset($_GET['page_completed']) ? max(1, intval($_GET['page_completed'])) : 1;
        $per_page = 10;
        $total = count($rows);
        $total_pages = $total > 0 ? ceil($total / $per_page) : 1;
        $start = ($page - 1) * $per_page;
        $html_completed = '';
        if ($total > 0) {
            $html_completed = implode('', array_slice($rows, $start, $per_page));
        } else {
            $html_completed = '<tr><td colspan="9" class="text-center">Nessuna visita completata trovata</td></tr>';
        }
        
        $pagination_completed = '';
        if ($total_pages > 1) {
            $pagination_completed .= '<div class="pagination" style="margin:10px 0;text-align:center;">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $active = ($i == $page) ? ' style="font-weight:bold;text-decoration:underline;"' : '';
                $pagination_completed .= '<a href="#" class="page-link-completed" data-page="' . $i . '"' . $active . '>' . $i . '</a> ';
            }
            $pagination_completed .= '</div>';
        }

        // --- GENERAZIONE FILTRI STAFF ---
        $buttons = '<button class="filter-staff active" data-staff="all">Tutti</button>';
        foreach ($all_staff as $sid => $name) {
            if (empty(trim($name))) continue;
            $buttons .= ' <button class="filter-staff" data-staff="' . esc_attr($sid) . '">' . esc_html($name) . '</button>';
        }

        wp_send_json([
            'success' => true, 
            'html' => $html, 
            'html_completed' => $html_completed, 
            'pagination_completed' => $pagination_completed,
            'filters' => $buttons
        ]);
        exit();
    }

    // Endpoint per completare un appuntamento dall'infermiere
    function totemsport_nurse_complete()
    {

        // Evita output indesiderato che rompe il JSON
        if (ob_get_length()) {
            while (ob_get_level()) {
                ob_end_clean();
            }
        }

        header('Content-Type: application/json; charset=utf-8');

        $user = wp_get_current_user();
        if (!in_array('infermiere', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            wp_send_json(['success' => false, 'message' => 'Accesso negato']);
            exit();
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        
        if (!$appointment_id) {
            wp_send_json(['success' => false, 'message' => 'ID accettazione mancante']);
            exit();
        }

        // Salva il form infermiere (Gravity Forms ID 9) bypassando il submit nascosto
        // Prendiamo tutti i campi input_* inviati
        $posted_inputs = array();
        foreach ($_POST as $k => $v) {
            if (strpos($k, 'input_') === 0) {
                $posted_inputs[$k] = $v;
            }
        }

        // Forza sempre la creazione di un entry GF anche se il form è vuoto
        if (!class_exists('GFAPI')) {
            wp_send_json(['success' => false, 'message' => 'Gravity Forms non disponibile (GFAPI mancante).']);
            exit();
        }

        $form_id = 9; // ID form infermiere
        $form_obj = GFAPI::get_form($form_id);

        if ($form_obj && !is_wp_error($form_obj)) {
            $entry = array(
                'form_id'    => $form_id,
                'created_by' => $user->ID,
            );

            // Mappa i campi inviati nell'entry
            foreach ($posted_inputs as $key => $val) {
                // Esempio key: input_1 oppure input_3_2
                $parts = explode('_', $key);
                if (count($parts) >= 2) {
                    // input, fieldId, [subId]
                    $field_id = $parts[1];
                    if (isset($parts[2])) {
                        // sub input -> fieldId.subId
                        $entry_key = $field_id . '.' . $parts[2];
                    } else {
                        $entry_key = $field_id;
                    }
                    $entry[$entry_key] = maybe_serialize($val);
                }
            }

            // Recupera appointment_id dal parametro della funzione
            $id_app = get_post_meta($appointment_id, 'appointment_id', true);

            // Recupera il CF dell'appuntamento
            $appuntamento = AppointmentHelper::get_app_data($id_app);
            $cf_app = $appuntamento['cf'];

            // Trova il CF inserito nel form (campo 56 o 56.*)
            $cf_form = '';
            foreach ($entry as $k => $v) {
                if ((string)$k === '56' || strpos($k, '56.') === 0) {
                    $cf_form = maybe_unserialize($v);
                    break;
                }
            }

            // Se non presente, prova a prenderlo da POST['cf']
            if (!$cf_form && isset($_POST['cf'])) {
                $cf_form = sanitize_text_field($_POST['cf']);
            }

            // Se ancora non presente, precompila con quello dell'appuntamento
            if (!$cf_form && $cf_app) {
                $cf_form = $cf_app;
                $entry['56'] = $cf_app;
            }

            // Controllo: il CF inserito deve essere uguale a quello dell'appuntamento
            if ($cf_app && $cf_form && strtolower(trim($cf_app)) !== strtolower(trim($cf_form))) {
                error_log('TotemSport DEBUG: BLOCCO! cf_app=' . var_export($cf_app, true) . ' | cf_form=' . var_export($cf_form, true));
                wp_send_json(['success' => false, 'message' => 'Il codice fiscale inserito non corrisponde a quello dell\'appuntamento. Riprova.']);
                exit();
            }

            // Colleghiamo l'accettazione per riferimento (campo custom, sempre presente)
            $entry['appointment_id'] = $appointment_id;

            $add_result = GFAPI::add_entry($entry);

            if (!is_wp_error($add_result)) {
                // Salva come meta il firmatario del form infermiere
                update_post_meta($appointment_id, 'nurse_form_user', $user->ID);
                update_post_meta($appointment_id, 'nurse_form_submit_time', current_time('mysql'));
            }

            if (is_wp_error($add_result)) {
                wp_send_json(['success' => false, 'message' => 'Errore salvataggio form infermiere: ' . $add_result->get_error_message()]);
                exit();
            }
        }

        // Garantisce all'infermiere il permesso di aggiornare questo post specifico
        $cap_cb = function ($allcaps, $caps, $args) use ($appointment_id) {
            if (!empty($args) && in_array($args[0], array('edit_post', 'edit_appunt_conf', 'edit_posts'), true)) {
                $post_id = isset($args[2]) ? intval($args[2]) : 0;
                if ($post_id === $appointment_id) {
                    $allcaps['edit_post'] = true;
                    $allcaps['edit_appunt_conf'] = true;
                    $allcaps['edit_posts'] = true;
                }
            }
            return $allcaps;
        };
        add_filter('user_has_cap', $cap_cb, 10, 3);

        // Cambia lo status da draft a private (completato dall'infermiere, passa al medico)
        $result = wp_update_post([
            'ID' => $appointment_id,
            'post_status' => 'private' // completato dall'infermiere, passa al medico
        ], true);

        remove_filter('user_has_cap', $cap_cb, 10);

        if (is_wp_error($result)) {
            wp_send_json(['success' => false, 'message' => 'Errore durante l\'aggiornamento: ' . $result->get_error_message()]);
        }

        if ($result) {
            // Salva l'utente infermiere che effettua la chiusura
            update_post_meta($appointment_id, 'infermiere_user', $user->ID);

            // Salva la data appuntamento come meta 'data' (Y-m-d)
            $original_id = get_post_meta($appointment_id, 'appointment_id', true);
            if ($original_id) {
                $data_ora = get_post_meta($original_id, '_appointment_start', true);
                if ($data_ora) {
                    update_post_meta($appointment_id, 'data', date('Y-m-d', strtotime($data_ora)));
                }
            }

            // Aggiorna automaticamente l'archivio con il nuovo form infermiere
            error_log('TotemSport: Infermiere ha completato accettazione ' . $appointment_id . '. Aggiornamento archivio...');
            ArchiveHelper::archive_appointment($appointment_id);
            error_log('TotemSport: Archivio aggiornato per accettazione ' . $appointment_id);
            wp_send_json(['success' => true, 'message' => 'Accettazione completata e archivio aggiornato']);
        } else {
            wp_send_json(['success' => false, 'message' => 'Errore durante l\'aggiornamento']);
        }
        exit();
    }

    // Endpoint per recuperare appuntamenti per il medico (status = private, completati dall'infermiere)
    function totemsport_get_med_apps()
    {
        $user = wp_get_current_user();
        if (!in_array('medico', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            wp_send_json(['success' => false, 'message' => 'Accesso negato']);
            exit();
        }

        $all_staff = AppointmentHelper::get_all_staff();
        $filter_staff = isset($_GET['filter_staff']) ? $_GET['filter_staff'] : 'all';
        $filter_nome = isset($_GET['filter_nome']) ? sanitize_text_field($_GET['filter_nome']) : '';
        $filter_cognome = isset($_GET['filter_cognome']) ? sanitize_text_field($_GET['filter_cognome']) : '';
        $filter_cf = isset($_GET['filter_cf']) ? sanitize_text_field($_GET['filter_cf']) : '';

        $oggi = date('d/m/Y', current_time('timestamp'));
        $html = '';
        $html_completed = '';
        $processed_appointments = [];

        // --- VISITE DA FARE (meta medico_completed non impostato o 0) ---
        $posts = get_posts([
            'post_type' => 'appunt_conf',
            'posts_per_page' => -1,
            'post_status' => 'private',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'medico_completed',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => 'medico_completed',
                    'value' => '0',
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'ASC',
        ]);

        foreach ($posts as $p) {
            $id_app = get_post_meta($p->ID, 'appointment_id', true);
            if (!$id_app || !get_post($id_app)) continue;

            $appuntamento = AppointmentHelper::get_app_data($id_app);
            if (!$appuntamento) continue;

            // Solo appuntamenti di oggi
            $data_app = $appuntamento['data'] ?? '';
            if ($data_app !== $oggi) continue;

            $nome = $appuntamento['nome'] ?? '';
            $cognome = $appuntamento['cognome'] ?? '';
            $tipologia = $appuntamento['tipologia'] ?? '';
            $ora = $appuntamento['ora'] ?? '';
            $cf = $appuntamento['cf'] ?? '';

            // --- RECUPERO STAFF ---
            $staff_ids = [];
            $staff_meta_plural = get_post_meta($id_app, '_appointment_staff_ids', true);
            $staff_meta_singular = get_post_meta($id_app, '_appointment_staff_id', true);
            if (!empty($staff_meta_plural)) {
                $unserialized = maybe_unserialize($staff_meta_plural);
                if (is_array($unserialized)) {
                    $staff_ids = array_filter(array_map('intval', $unserialized));
                } elseif (is_numeric($unserialized)) {
                    $staff_ids = [intval($unserialized)];
                }
            } elseif (!empty($staff_meta_singular)) {
                if (is_numeric($staff_meta_singular)) {
                    $staff_ids = [intval($staff_meta_singular)];
                }
            }

            // --- FILTRI ---
            if ($filter_staff !== 'all') {
                if (!in_array(intval($filter_staff), $staff_ids)) continue;
            }
            if ($filter_nome && stripos($nome, $filter_nome) === false) continue;
            if ($filter_cognome && stripos($cognome, $filter_cognome) === false) continue;
            if ($filter_cf && stripos($cf, $filter_cf) === false) continue;

            $html .= '<tr>';
            $html .= '<td>' . esc_html($p->ID) . '</td>';
            $html .= '<td>' . esc_html($nome) . '</td>';
            $html .= '<td>' . esc_html($cognome) . '</td>';
            $html .= '<td>' . esc_html($ora) . '</td>';
            $html .= '<td>' . esc_html($tipologia) . '</td>';
            $html .= '<td>
                <button class="btn-action btn-preview med-view-btn" data-id="' . esc_attr($p->ID) . '" title="Visualizza documenti"><i class="bi bi-eye"></i> <span>Visualizza</span></button>
                <button class="btn-action btn-preview btn-print-certificato-med" style="margin-left: 0.5rem;" data-id="' . esc_attr($p->ID) . '" title="Stampa Certificato"><i class="bi bi-printer"></i> <span>Stampa Certificato</span></button></td>
                ';
            $html .= '</tr>';

            $processed_appointments[] = $id_app;
        }
        if ($html === '') {
            $html = '<tr><td colspan="6" class="text-center">Nessuna Accettazione da processare</td></tr>';
        }

        // --- VISITE COMPLETATE (meta medico_completed = 1) ---
        $posts_completed = get_posts([
            'post_type' => 'appunt_conf',
            'post_status' => 'pending',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'medico_completed',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'ASC',
        ]);

        foreach ($posts_completed as $p) {
            $id_app = get_post_meta($p->ID, 'appointment_id', true);
            if (!$id_app || !get_post($id_app)) continue;
            if (in_array($id_app, $processed_appointments)) continue;

            $appuntamento = AppointmentHelper::get_app_data($id_app);
            if (!$appuntamento) continue;

            // Solo appuntamenti di oggi
            $data_app = $appuntamento['data'] ?? '';
            if ($data_app !== $oggi) continue;

            $nome = $appuntamento['nome'] ?? '';
            $cognome = $appuntamento['cognome'] ?? '';
            $tipologia = $appuntamento['tipologia'] ?? '';
            $ora = $appuntamento['ora'] ?? '';
            $cf = $appuntamento['cf'] ?? '';

            // --- RECUPERO STAFF ---
            $staff_ids = [];
            $staff_meta_plural = get_post_meta($id_app, '_appointment_staff_ids', true);
            $staff_meta_singular = get_post_meta($id_app, '_appointment_staff_id', true);
            if (!empty($staff_meta_plural)) {
                $unserialized = maybe_unserialize($staff_meta_plural);
                if (is_array($unserialized)) {
                    $staff_ids = array_filter(array_map('intval', $unserialized));
                } elseif (is_numeric($unserialized)) {
                    $staff_ids = [intval($unserialized)];
                }
            } elseif (!empty($staff_meta_singular)) {
                if (is_numeric($staff_meta_singular)) {
                    $staff_ids = [intval($staff_meta_singular)];
                }
            }
            
            // --- FILTRI ---
            if ($filter_staff !== 'all') {
                if (!in_array(intval($filter_staff), $staff_ids)) continue;
            }
            if ($filter_nome && stripos($nome, $filter_nome) === false) continue;
            if ($filter_cognome && stripos($cognome, $filter_cognome) === false) continue;
            if ($filter_cf && stripos($cf, $filter_cf) === false) continue;

            $html_completed .= '<tr>';
            $html_completed .= '<td>' . esc_html($p->ID) . '</td>';
            $html_completed .= '<td>' . esc_html($nome) . '</td>';
            $html_completed .= '<td>' . esc_html($cognome) . '</td>';
            $html_completed .= '<td>' . esc_html($ora) . '</td>';
            $html_completed .= '<td>' . esc_html($tipologia) . '</td>';
            $html_completed .= '<td>
                <button class="btn-action btn-preview med-view-btn" data-id="' . esc_attr($p->ID) . '" title="Modifica"><i class="bi bi-pencil"></i> <span>Modifica</span></button>
                <button class="btn-action btn-preview btn-print-certificato-med" style="margin-left: 0.5rem;" data-id="' . esc_attr($p->ID) . '" title="Stampa Certificato"><i class="bi bi-printer"></i> <span>Stampa Certificato</span></button></td>
                ';
                            $html_completed .= '</tr>';
        }
        if ($html_completed === '') {
            $html_completed = '<tr><td colspan="6" class="text-center">Nessuna visita completata trovata</td></tr>';
        }

        // --- GENERAZIONE FILTRI STAFF ---
        $buttons = '<button class="filter-staff active" data-staff="all">Tutti</button>';
        foreach ($all_staff as $sid => $name) {
            if (empty(trim($name))) continue;
            $buttons .= ' <button class="filter-staff" data-staff="' . esc_attr($sid) . '">' . esc_html($name) . '</button>';
        }

        wp_send_json(['success' => true, 'html' => $html, 'html_completed' => $html_completed, 'filters' => $buttons]);
        exit();
    }

    // Endpoint per recuperare il form compilato dall'infermiere
    function totemsport_get_nurse_form()
    {
        $user = wp_get_current_user();
        if (!in_array('medico', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            wp_send_json(['success' => false, 'message' => 'Accesso negato']);
            exit();
        }

        $id_appuntamento_confermato = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $id = get_post_meta($id_appuntamento_confermato, 'appointment_id', true);
        
        $appuntamento = AppointmentHelper::get_app_data($id);
        
        $form_id_nurse = 9; 
        $key = 56; // Campo CF nel form infermiere
        
        $form = GFAPI::get_form($form_id_nurse);
        // Associazione tramite codice fiscale E appointment_id
        $search_criteria = array(
            'field_filters' => array(
                array('key' => '56', 'value' => $appuntamento['cf']), // CF
                array('key' => 'appointment_id', 'value' => $id_appuntamento_confermato), // appointment_id
            )
        );
        $entries = GFAPI::get_entries($form_id_nurse, $search_criteria);
        if (!empty($entries) && !is_wp_error($entries)) {
            $entry = $entries[0];
            $html = HtmlHelper::show_html_anamnesi($form, $entry);
        } else {
            $html = '<p>Nessun modulo infermiere trovato per questo appuntamento.</p>';
        }

        $result = ['html' => base64_encode($html), 'success' => 'true'];
        header('Content-Type: application/json; charset=utf-8');
        wp_send_json($result);
        exit();
    }

    // Endpoint per completare un appuntamento dal medico
    function totemsport_med_complete()
    {

        // Evita output indesiderato che rompe il JSON
        if (ob_get_length()) {
            while (ob_get_level()) {
                ob_end_clean();
            }
        }

        header('Content-Type: application/json; charset=utf-8');

        $user = wp_get_current_user();
        if (!in_array('medico', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            wp_send_json(['success' => false, 'message' => 'Accesso negato']);
            exit();
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        if (!$appointment_id) {
            wp_send_json(['success' => false, 'message' => 'ID accettazione mancante']);
            exit();
        }

        // Salva il form medico (Gravity Forms ID 10) bypassando il submit nascosto
        // Prendiamo tutti i campi input_* inviati
        $posted_inputs = array();
        foreach ($_POST as $k => $v) {
            if (strpos($k, 'input_') === 0) {
                $posted_inputs[$k] = $v;
            }
        }

        // Forza sempre la creazione di un entry GF anche se il form è vuoto
        if (!class_exists('GFAPI')) {
            wp_send_json(['success' => false, 'message' => 'Gravity Forms non disponibile (GFAPI mancante).']);
            exit();
        }

        $form_id = 10; // ID form medico
        $form_obj = GFAPI::get_form($form_id);

        if ($form_obj && !is_wp_error($form_obj)) {
            $entry = array(
                'form_id'    => $form_id,
                'created_by' => $user->ID,
            );

            // Mappa i campi inviati nell'entry
            foreach ($posted_inputs as $key => $val) {
                // Esempio key: input_1 oppure input_3_2
                $parts = explode('_', $key);
                if (count($parts) >= 2) {
                    // input, fieldId, [subId]
                    $field_id = $parts[1];
                    if (isset($parts[2])) {
                        // sub input -> fieldId.subId
                        $entry_key = $field_id . '.' . $parts[2];
                    } else {
                        $entry_key = $field_id;
                    }
                    $entry[$entry_key] = maybe_serialize($val);
                }
            }

            // Recupera appointment_id dal parametro della funzione
            $id_app = get_post_meta($appointment_id, 'appointment_id', true);

            // Recupera il CF dell'appuntamento
            $appuntamento = AppointmentHelper::get_app_data($id_app);
            $cf_app = $appuntamento['cf'];

            // Trova il CF inserito nel form (campo 56 o 56.*)
            $cf_form = '';
            foreach ($entry as $k => $v) {
                if ((string)$k === '56' || strpos($k, '56.') === 0) {
                    $cf_form = maybe_unserialize($v);
                    break;
                }
            }

            // Se non presente, prova a prenderlo da POST['cf']
            if (!$cf_form && isset($_POST['cf'])) {
                $cf_form = sanitize_text_field($_POST['cf']);
            }

            // Se ancora non presente, precompila con quello dell'appuntamento
            if (!$cf_form && $cf_app) {
                $cf_form = $cf_app;
                $entry['56'] = $cf_app;
            }

            // Controllo: il CF inserito deve essere uguale a quello dell'appuntamento
            if ($cf_app && $cf_form && strtolower(trim($cf_app)) !== strtolower(trim($cf_form))) {
                error_log('TotemSport DEBUG: BLOCCO! cf_app=' . var_export($cf_app, true) . ' | cf_form=' . var_export($cf_form, true));
                wp_send_json(['success' => false, 'message' => 'Il codice fiscale inserito non corrisponde a quello dell\'appuntamento. Riprova.']);
                exit();
            }

            // Colleghiamo l'accettazione per riferimento (campo custom, sempre presente)
            $entry['appointment_id'] = $appointment_id;

            $add_result = GFAPI::add_entry($entry);

            if (!is_wp_error($add_result)) {
                // Salva come meta il firmatario del form medico
                update_post_meta($appointment_id, 'medico_form_user', $user->ID);
                update_post_meta($appointment_id, 'medico_form_submit_time', current_time('mysql'));
            }

            if (is_wp_error($add_result)) {
                wp_send_json(['success' => false, 'message' => 'Errore salvataggio form medico: ' . $add_result->get_error_message()]);
                exit();
            }
        }

        // Cambia lo status da private a pending (o custom status "completed" se lo registri)
        // pending = completato dal medico, fine del flusso
        $result = wp_update_post([
            'ID' => $appointment_id,
            'post_status' => 'pending' // completato dal medico
        ]);

        if ($result) {
            update_post_meta($appointment_id, 'medico_user', $user->ID);
            update_post_meta($appointment_id, 'medico_completed', 1);

            // Salva la data appuntamento come meta 'data' (Y-m-d)
            $original_id = get_post_meta($appointment_id, 'appointment_id', true);
            if ($original_id) {
                $data_ora = get_post_meta($original_id, '_appointment_start', true);
                if ($data_ora) {
                    update_post_meta($appointment_id, 'data', date('Y-m-d', strtotime($data_ora)));
                }
                if (!class_exists('AppointmentHelper')) {
                    require_once plugin_dir_path(__FILE__) . 'class/AppointmentHelper.php';
                }
                $dati = AppointmentHelper::get_app_data($original_id);
                if ($dati) {
                    $meta_keys = ['nome','cognome','cf','ora','tipologia','pagato','totale'];
                    foreach ($meta_keys as $k) {
                        if (isset($dati[$k])) {
                            update_post_meta($appointment_id, $k, $dati[$k]);
                        }
                    }
                }
            }

            // Aggiorna automaticamente l'archivio con il nuovo form medico
            error_log('TotemSport: Medico ha completato accettazione ' . $appointment_id . '. Aggiornamento archivio...');
            ArchiveHelper::archive_appointment($appointment_id);
            error_log('TotemSport: Archivio aggiornato per accettazione ' . $appointment_id);
            wp_send_json(['success' => true, 'message' => 'Accettazione completato e archivio aggiornato']);
        } else {
            wp_send_json(['success' => false, 'message' => 'Errore durante l\'aggiornamento']);
        }
        exit();
    }

    // Endpoint per archiviare i documenti di un appuntamento
    function totemsport_archive_appointment()
    {
        $user = wp_get_current_user();

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        
        if (!$appointment_id) {
            wp_send_json(['success' => false, 'message' => 'ID Accettazione mancante']);
            exit();
        }

        // Archivia i documenti
        $saved_files = ArchiveHelper::archive_appointment($appointment_id);

        if (!empty($saved_files)) {
            wp_send_json([
                'success' => true, 
                'message' => 'Documenti archiviati con successo',
                'files' => $saved_files
            ]);
        } else {
            wp_send_json(['success' => false, 'message' => 'Nessun documento da archiviare']);
        }
        exit();
    }

    function totemsport_get_archived_folders()
    {
        $user = wp_get_current_user();

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }

        $folders = ArchiveHelper::get_all_archived_folders();
        wp_send_json(['success' => true, 'folders' => $folders]);
        exit();
    }

    // Endpoint per eliminare una cartella di archivio (solo admin)
    function totemsport_delete_archive_folder()
    {
        // Verifica nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'totemsport_delete_nonce')) {
            wp_send_json(['success' => false, 'message' => 'Nonce non valido']);
            exit();
        }
        
        $user = wp_get_current_user();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }

        $folder_path = isset($_POST['path']) ? wp_unslash($_POST['path']) : '';
        
        if (empty($folder_path)) {
            wp_send_json(['success' => false, 'message' => 'Percorso archivio mancante']);
            exit();
        }

        $upload_dir = wp_upload_dir();
        $target_path_real = realpath($folder_path);
        $target_path = wp_normalize_path($target_path_real ? $target_path_real : $folder_path);
        $target_norm = rtrim(strtolower($target_path), '/\\') . '/';

        // Sicurezza: il path deve stare sotto una delle cartelle master consentite
        $is_valid_base = false;
        $base_folders = glob($upload_dir['basedir'] . '/totemsport-archive*', GLOB_ONLYDIR);
        foreach ($base_folders as $bf) {
            $base_norm = rtrim(strtolower(wp_normalize_path($bf)), '/\\') . '/';
            if (strpos($target_norm, $base_norm) === 0) {
                $is_valid_base = true;
                break;
            }
        }

        if (!$is_valid_base || !is_dir($target_path)) {
            wp_send_json(['success' => false, 'message' => 'Percorso non valido']);
            exit();
        }

        self::deleteDirectory($target_path);
        wp_send_json(['success' => true, 'message' => 'Archivio eliminato con successo']);
        exit();
    }

    function totemsport_get_nurse_tables() {
        // Solo infermiere o admin
        $user = wp_get_current_user();
        if (!in_array('infermiere', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            wp_send_json(['success' => false, 'message' => 'Accesso negato']);
            exit();
        }

        // Da processare
        $args_da_processare = [
            'post_type'      => 'appuntamento',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_nurse_completed',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key'     => '_nurse_completed',
                    'value'   => '0',
                    'compare' => '='
                ]
            ]
        ];
        
        $query_da_processare = new WP_Query($args_da_processare);
        $tbody = '';
        if ($query_da_processare->have_posts()) {
            while ($query_da_processare->have_posts()) {
                $query_da_processare->the_post();
                $id = get_the_ID();
                $nome = get_post_meta($id, 'nome', true);
                $cognome = get_post_meta($id, 'cognome', true);
                $orario = get_post_meta($id, 'orario', true);
                $tipologia = get_post_meta($id, 'tipologia', true);
                $tbody .= '<tr>
                    <td>'.esc_html($id).'</td>
                    <td>'.esc_html($nome).'</td>
                    <td>'.esc_html($cognome).'</td>
                    <td>'.esc_html($orario).'</td>
                    <td>'.esc_html($tipologia).'</td>
                    <td><button class="btn btn-action btn-preview nurse-view-btn" data-id="'.esc_attr($id).'"><i class="bi bi-eye"></i> Apri</button></td>
                </tr>';
            }
            wp_reset_postdata();
        } else {
            $tbody = '<tr><td colspan="6" class="text-center">Nessuna accettazione da processare</td></tr>';
        }

        // Completati
        $args_completati = [
            'post_type'      => 'appuntamento',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_nurse_completed',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ];
        $query_completati = new WP_Query($args_completati);
        $tbody_completed = '';
        if ($query_completati->have_posts()) {
            while ($query_completati->have_posts()) {
                $query_completati->the_post();
                $id = get_the_ID();
                $nome = get_post_meta($id, 'nome', true);
                $cognome = get_post_meta($id, 'cognome', true);
                $orario = get_post_meta($id, 'orario', true);
                $tipologia = get_post_meta($id, 'tipologia', true);
                $tbody_completed .= '<tr>
                    <td>'.esc_html($id).'</td>
                    <td>'.esc_html($nome).'</td>
                    <td>'.esc_html($cognome).'</td>
                    <td>'.esc_html($orario).'</td>
                    <td>'.esc_html($tipologia).'</td>
                    <td><span class="badge badge-success">Completato</span></td>
                    <td><button class="btn btn-action btn-preview nurse-edit-btn" data-id="'.esc_attr($id).'"><i class="bi bi-pencil"></i> Modifica</button></td>
                </tr>';
            }
            wp_reset_postdata();
        } else {
            $tbody_completed = '<tr><td colspan="7" class="text-center">Nessuna visita completata trovata</td></tr>';
        }

        wp_send_json([
            'success' => true,
            'tbody' => $tbody,
            'tbody_completed' => $tbody_completed
        ]);
        exit();
    }

    // Endpoint per eliminare un appuntamento completato e la sua cartella di archivio
    function totemsport_delete_appointment()
    {
        $user = wp_get_current_user();
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        
        if (!$appointment_id) {
            wp_send_json(['success' => false, 'message' => 'ID Accettazione mancante']);
            exit();
        }

        // Recupera dati appuntamento PRIMA di eliminare il post
        $id_app = get_post_meta($appointment_id, 'appointment_id', true);
        $appuntamento = null;
        if ($id_app) {
            $appuntamento = AppointmentHelper::get_app_data($id_app);
        }

        // Elimina il post
        $result = wp_delete_post($appointment_id, true); // true = bypass trash, true delete

        if (!$result) {
            wp_send_json(['success' => false, 'message' => 'Errore durante l\'eliminazione dell\'Accettazione']);
            exit();
        }

        // Sposta le cartelle di archivio associate al paziente nel cestino invece di eliminarle
        if ($appuntamento) {
            $upload_dir = wp_upload_dir();
            $master_folder = ArchiveHelper::get_master_folder($appointment_id);
            $base_path = $upload_dir['basedir'] . '/' . $master_folder;

            $nome = strtoupper(sanitize_file_name($appuntamento['nome'] ?? 'SCONOSCIUTO'));
            $cognome = strtoupper(sanitize_file_name($appuntamento['cognome'] ?? 'SCONOSCIUTO'));
            $cf = strtoupper(sanitize_file_name($appuntamento['cf'] ?? 'SCONOSCIUTO'));
            $folder_name = "{$nome}-{$cognome}-{$cf}";

            // Cerca ricorsivamente in tutta la struttura: YYYY/MM-Month/DD/NOME-COGNOME-CF
            if (file_exists($base_path)) {
                foreach (glob($base_path . '/*', GLOB_ONLYDIR) as $year_dir) {
                    foreach (glob($year_dir . '/*', GLOB_ONLYDIR) as $month_dir) {
                        foreach (glob($month_dir . '/*', GLOB_ONLYDIR) as $day_dir) {
                            foreach (glob($day_dir . '/*', GLOB_ONLYDIR) as $patient_dir) {
                                if (basename($patient_dir) === $folder_name) {
                                    if (class_exists('ArchiveHelper')) {
                                        ArchiveHelper::move_to_trash($patient_dir);
                                    } else {
                                        require_once plugin_dir_path(__FILE__) . 'class/ArchiveHelper.php';
                                        ArchiveHelper::move_to_trash($patient_dir);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        wp_send_json(['success' => true, 'message' => 'Accettazione e archivio eliminati con successo']);
        exit();
    }

    // Funzione helper per eliminare una directory ricorsivamente
    private static function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    self::deleteDirectory($path);
                } else {
                    @unlink($path);
                }
            }
        }
        return @rmdir($dir);
    }

    function archive_page()
    {
        include plugin_dir_path(__FILE__) . 'archive-area.php';
    }
    
    function google_drive_settings_page()
    {
        include plugin_dir_path(__FILE__) . 'google-drive-settings.php';
    }
    
    /**
     * Registra endpoint REST API per webhook Google Drive
     */
    public function register_rest_endpoints()
    {
        register_rest_route('totemsport/v1', '/drive-webhook', [
            'methods' => ['POST', 'GET'],
            'callback' => array($this, 'handle_drive_webhook'),
            'permission_callback' => '__return_true' // Google Drive webhook non richiede auth
        ]);
        
        register_rest_route('totemsport/v1', '/sync-drive', [
            'methods' => 'POST',
            'callback' => array($this, 'handle_manual_sync'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    /**
     * Gestisce il webhook da Google Drive
     */
    public function handle_drive_webhook($request)
    {
        $headers = $request->get_headers();
        
        if (!class_exists('GoogleDriveSyncHelper')) {
            return new WP_REST_Response(['error' => 'Sync not configured'], 503);
        }
        
        GoogleDriveSyncHelper::handle_webhook($headers);
        
        return new WP_REST_Response(['status' => 'ok'], 200);
    }
    
    /**
     * Gestisce la sincronizzazione manuale
     */
    public function handle_manual_sync($request)
    {
        error_log('TotemSport: Manual sync request received');
        
        if (!class_exists('GoogleDriveSyncHelper')) {
            error_log('TotemSport: GoogleDriveSyncHelper class not found');
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Helper di sincronizzazione non disponibile'
            ], 503);
        }
        
        if (!class_exists('GoogleDriveHelper')) {
            error_log('TotemSport: GoogleDriveHelper class not found');
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Google Drive helper non disponibile'
            ], 503);
        }
        
        try {
            error_log('TotemSport: Starting GoogleDriveSyncHelper::manual_sync()');
            $result = GoogleDriveSyncHelper::manual_sync();
            error_log('TotemSport: Sync result: ' . json_encode($result));
            
            if ($result['success']) {
                return new WP_REST_Response($result, 200);
            } else {
                return new WP_REST_Response($result, 200); // Return 200 anche per fallimenti logici
            }
        } catch (Exception $e) {
            error_log('TotemSport: Exception during sync: ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage()
            ], 200);
        }
    }
    
    /**
     * Sincronizzazione automatica (eseguita da WP Cron)
     */
    public function auto_sync_drive()
    {
        if (!class_exists('GoogleDriveSyncHelper') || !GoogleDriveHelper::is_configured()) {
            error_log('TotemSport: Auto-sync skipped - Google Drive not configured');
            return;
        }
        
        error_log('TotemSport: Starting automatic sync...');
        $result = GoogleDriveSyncHelper::manual_sync();
        
        if ($result['success']) {
            error_log('TotemSport: Auto-sync completed - ' . $result['message']);
        } else {
            error_log('TotemSport: Auto-sync failed - ' . $result['message']);
        }
    }

    public function add_hidden_appointment_id_field($form) {
        
        if ($form['id'] != 9 || $form['id'] != 10) return $form;

        // Verifica se il campo esiste già
        $field_exists = false;
        foreach ($form['fields'] as $field) {
            if ($field->type == 'hidden' && $field->inputName == 'appointment_id') {
                $field_exists = true;
                break;
            }
        }
        if (!$field_exists) {
            // Crea nuovo campo hidden
            $field = new GF_Field_Hidden();
            $field->label = 'Appointment ID';
            $field->inputName = 'appointment_id';
            $field->id = 1001; // id arbitrario alto per evitare conflitti
            $form['fields'][] = $field;
        }
        return $form;
    }

    public function populate_hidden_appointment_id($value) {
        // Recupera appointment_id da GET o SESSION
        if (isset($_GET['idappuntamento'])) {
            return intval($_GET['idappuntamento']);
        } elseif (isset($_SESSION['idappuntamento'])) {
            return intval($_SESSION['idappuntamento']);
        }
        return '';
    }

    public function totemsport_generate_certificato() {
        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;

        $maybe_conf = get_post($appointment_id);
        if ($maybe_conf && $maybe_conf->post_type === 'appunt_conf') {
            $original_id = intval(get_post_meta($maybe_conf->ID, 'appointment_id', true));
            if ($original_id) {
                $appointment_id = $original_id;
            }
        }

        $dati = [];

        // Recupera dati dall'appuntamento
        if ($appointment_id) {
            require_once plugin_dir_path(__FILE__) . 'class/AppointmentHelper.php';
            $dati = AppointmentHelper::get_certificato_data($appointment_id);
        }

        $dati['nome'] = $_POST['nome'] ?? '';
        $dati['cognome'] = $_POST['cognome'] ?? '';
        $dati['luogo_nascita'] = $_POST['luogo_nascita'] ?? '';
        $dati['data_nascita'] = $_POST['data_nascita'] ?? '';
        $dati['residenza'] = $_POST['residenza'] ?? '';
        $dati['cf'] = $_POST['cf'] ?? '';
        $dati['tipo_certificato'] = $_POST['tipo_certificato'] ?? '';
        $dati['documento'] = $_POST['documento'] ?? '';
        $dati['numero_documento'] = $_POST['numero_documento'] ?? '';
        
        $dati['società'] = $_POST['società'] ?? '';
        $dati['scadenza'] = $_POST['scadenza'] ?? '';
        $dati['rilascio'] = $_POST['rilascio'] ?? '';
        $dati['societa'] = $_POST['societa'] ?? '';
        $dati['sport'] = $_POST['sport'] ?? '';
        $dati['lenti'] = $_POST['lenti'] ?? '';
        $dati['sangue'] = $_POST['sangue'] ?? '';
        $dati['rh'] = $_POST['rh'] ?? '';

        $dati['quesito_diagnostico'] = $_POST['quesito_diagnostico'] ?? '';
        $dati['consigli'] = $_POST['consigli'] ?? '';


        $tipo_certificato = !empty($_POST['tipo_certificato']) ? sanitize_text_field(trim($_POST['tipo_certificato'])) : 'certificato';
        $dati['tipo_certificato'] = $tipo_certificato;

        $required = ['nome', 'cognome', 'cf', 'tipo_certificato'];
        $missing = [];
        
        foreach ($required as $field) {
            if (empty($dati[$field])) $missing[] = $field;
        }

        if ($missing) {
            wp_send_json_error(['missing_fields' => $missing, 'message' => 'Dati anagrafici mancanti: ' . implode(', ', $missing)]);
        }

        require_once plugin_dir_path(__FILE__) . 'class/CertificatoHelper.php';
        
        // Passa sempre da genera_certificato_pdf, che gestisce il tipo
        $filepath = CertificatoHelper::genera_certificato_pdf($dati, $tipo_certificato);
        $doc_type = $tipo_certificato;

        $upload_dir = wp_upload_dir();
        $url = $upload_dir['baseurl'] . '/certificati/' . basename($filepath);
        
        if ($appointment_id) {
            update_post_meta($appointment_id, '_certificato_pdf_url', $url);
            update_post_meta($appointment_id, '_certificato_pdf_path', $filepath);

            // ARCHIVIA IL DOCUMENTO
            require_once plugin_dir_path(__FILE__) . 'class/ArchiveHelper.php';
            ArchiveHelper::save_document($appointment_id, $doc_type, file_get_contents($filepath));
        }

        wp_send_json_success(['url' => $url]);
    }

    // --- Configurazione POS Nexi ---

    // --- AJAX: Card Verification POS Nexi ---
    public function totemsport_pos_card_verification() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }
        // Comando Nexi: codice operazione 'V' (Card Verification)
        $terminal_id = str_pad('1', 8, '0', STR_PAD_LEFT); // Personalizza se necessario
        $cashreg_id = str_pad('1', 8, '0', STR_PAD_LEFT); // Personalizza se necessario
        $reserved1 = '0';
        $msg_code = 'V'; // V = Card Verification
        $add_data = '0';
        $reserved2 = '00';
        $card_present = '0';
        $pay_type = '0';
        $amount_field = str_pad('0', 8, '0', STR_PAD_LEFT); // Importo zero
        $print_text = str_pad('', 128, ' ', STR_PAD_LEFT);
        $reserved3 = str_pad('0', 8, '0');
        $appmsg = $terminal_id
            . $reserved1
            . $msg_code
            . $cashreg_id
            . $add_data
            . $reserved2
            . $card_present
            . $pay_type
            . $amount_field
            . $print_text
            . $reserved3;
        $response = $this->send_nexi_pos_command($appmsg, true);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }
        // Decodifica risposta: estrai campi principali
        $fields = [];
        if (strlen($response) > 12) {
            $fields['terminal_id'] = substr($response, 1, 8);
            $fields['msg_code'] = substr($response, 10, 1);
            $fields['result'] = substr($response, 11, 2);
            $fields['raw'] = bin2hex($response);
        }
        $ok = ($fields['result'] ?? null) === '00';
        wp_send_json_success([
            'result' => $ok ? 'OK' : 'KO',
            'fields' => $fields,
            'raw_response' => bin2hex($response),
        ]);
    }

    // --- Invio comando generico a POS Nexi (STX/ETX/LRC, risposta opzionale) ---
    private function send_nexi_pos_command($appmsg, $expect_response = true) {
        $pos_ip = '';
        $pos_port = 0;
        $ssl = false;
        // Rileva se serve SSL (https:// o ssl://)
        if (stripos($this->pos_bridge_url, 'https://') === 0 || stripos($this->pos_bridge_url, 'ssl://') === 0) {
            $ssl = true;
        }
        if (preg_match('#^(?:https?://|ssl://)?([\d\.]+):(\d+)#', $this->pos_bridge_url, $m)) {
            $pos_ip = $m[1];
            $pos_port = intval($m[2]);
        }
        if (!$pos_ip || !$pos_port) {
            return new WP_Error('pos_config', 'Configurazione POS non valida');
        }
        $stx = chr(0x02);
        $etx = chr(0x03);
        $msg = $stx . $appmsg . $etx;
        $lrc = 0x7F;
        for ($i = 0; $i < strlen($msg); $i++) {
            $lrc ^= ord($msg[$i]);
        }
        $msg .= chr($lrc);
        $timeout = 30;
        $target = $ssl ? ("ssl://" . $pos_ip) : $pos_ip;
        $fp = @fsockopen($target, $pos_port, $errno, $errstr, $timeout);
        if (!$fp) {
            return new WP_Error('pos_conn', "Errore connessione POS: $errstr ($errno)");
        }
        stream_set_timeout($fp, $timeout);
        fwrite($fp, $msg);
        if (!$expect_response) {
            fclose($fp);
            return true;
        }
        $raw = '';
        $timed_out = false;
        while (!feof($fp)) {
            $buf = fread($fp, 4096);
            if ($buf === false || $buf === '') break;
            $raw .= $buf;
            $info = stream_get_meta_data($fp);
            if (!empty($info['timed_out'])) {
                $timed_out = true;
                break;
            }
        }
        fclose($fp);
        $response = '';
        $stx_pos = strpos($raw, $stx);
        $etx_pos = strpos($raw, $etx);
        if ($stx_pos !== false && $etx_pos !== false && $etx_pos > $stx_pos) {
            // Estrai tra STX e ETX (inclusi)
            $response = substr($raw, $stx_pos, $etx_pos - $stx_pos + 2); // +2 per includere ETX e LRC
            // Se c'è un byte dopo ETX, includilo come LRC
            if (strlen($raw) > $etx_pos + 1) {
                $response .= $raw[$etx_pos + 1];
            }
        } elseif (!empty($raw)) {
            // Nessun framing, ma qualcosa ricevuto (es. ACK/NACK)
            $response = $raw;
        }
        
        if ($timed_out) {
            error_log('[NEXI POS] Timeout di lettura dalla socket POS');
            return new WP_Error('pos_timeout', 'Timeout di risposta dal POS');
        }
        if (empty($response)) {
            return new WP_Error('pos_noresp', 'Nessuna risposta dal POS');
        }
        return $response;
    }

    // --- AJAX: chiusura sessione POS Nexi ---
    public function totemsport_pos_close_session() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }
        $terminal_id = str_pad('1', 8, '0', STR_PAD_LEFT); // Personalizza se necessario
        $cashreg_id = str_pad('1', 8, '0', STR_PAD_LEFT); // Personalizza se necessario
        $reserved1 = '0';
        $msg_code = 'C';
        $add_data = '0';
        $reserved2 = str_pad('0', 7, '0');
        $appmsg = $terminal_id . $reserved1 . $msg_code . $cashreg_id . $add_data . $reserved2;
        $response = $this->send_nexi_pos_command($appmsg, true);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }
        // Decodifica risposta
        $fields = [];
        if (strlen($response) > 12) {
            $fields['terminal_id'] = substr($response, 1, 8);
            $fields['msg_code'] = substr($response, 10, 1);
            $fields['result'] = substr($response, 11, 2);
            $fields['raw'] = bin2hex($response);
        }
        $ok = ($fields['result'] ?? null) === '00';
        wp_send_json_success([
            'result' => $ok ? 'OK' : 'KO',
            'fields' => $fields,
            'raw_response' => bin2hex($response),
        ]);
    }

    // --- AJAX: abilita/disabilita stampa ricevuta su ECR ---
    public function totemsport_pos_set_ecr_print() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }
        $enable = isset($_POST['enable']) ? intval($_POST['enable']) : 1; // 1=abilita, 0=disabilita
        $terminal_id = str_pad('1', 8, '0', STR_PAD_LEFT); // Personalizza se necessario
        $reserved1 = '0';
        $msg_code = 'E';
        $flag = $enable ? '1' : '0';
        $appmsg = $terminal_id . $reserved1 . $msg_code . $flag;
        $result = $this->send_nexi_pos_command($appmsg, false); // Solo ACK atteso
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success(['result' => 'OK', 'message' => $enable ? 'Stampa su ECR abilitata' : 'Stampa su ECR disabilitata']);
    }
        
    // --- Endpoint AJAX: genera PDF report giornata corrente ---
    public function totemsport_generate_report_giornata_pdf() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }
        $data = null;
        if (isset($_POST['data'])) {
            $data = sanitize_text_field($_POST['data']);
        } elseif (isset($_GET['data'])) {
            $data = sanitize_text_field($_GET['data']);
        }
        require_once plugin_dir_path(__FILE__) . 'class/ReportHelper.php';
        $filepath = ReportHelper::genera_report_giornata_pdf($data);
        if (!$filepath || !file_exists($filepath)) {
            wp_send_json_error(['msg' => 'Errore generazione PDF']);
        }
        $upload_dir = wp_upload_dir();
        $url = $upload_dir['baseurl'] . '/report/' . basename($filepath);
        wp_send_json_success(['url' => $url]);
    }

    // Salva il totale modificato per un appuntamento
    public function totemsport_save_custom_total() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }
        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        $custom_total = isset($_POST['custom_total']) ? floatval($_POST['custom_total']) : null;
        if (!$appointment_id || $custom_total === null) {
            wp_send_json_error(array('message' => 'Dati mancanti'));
        }
        // Se l'ID è di tipo appunt_conf, risali all'ID principale
        $maybe_conf = get_post($appointment_id);
        if ($maybe_conf && $maybe_conf->post_type === 'appunt_conf') {
            $original_id = intval(get_post_meta($maybe_conf->ID, 'appointment_id', true));
            if ($original_id) {
                $appointment_id = $original_id;
            }
        }
        update_post_meta($appointment_id, '_custom_total', $custom_total);
        wp_send_json_success(array('message' => 'Totale aggiornato', 'custom_total' => $custom_total, 'saved_on_id' => $appointment_id));
    }

    // --- Endpoint AJAX: genera PDF report mensile ---
    public function totemsport_generate_report_mese_pdf() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
            wp_die();
        }
        $anno = isset($_POST['anno']) ? intval($_POST['anno']) : null;
        $mese = isset($_POST['mese']) ? intval($_POST['mese']) : null;
        if (!$anno || !$mese) {
            wp_send_json_error(['msg' => 'Anno o mese non validi']);
        }
        require_once plugin_dir_path(__FILE__) . 'class/ReportHelper.php';
        $filepath = ReportHelper::genera_report_mese_pdf($anno, $mese);
        if (!$filepath || !file_exists($filepath)) {
            wp_send_json_error(['msg' => 'Errore generazione PDF']);
        }
        $upload_dir = wp_upload_dir();
        $url = $upload_dir['baseurl'] . '/report/' . basename($filepath);
        wp_send_json_success(['url' => $url]);
    }

    // --- Endpoint AJAX: salva orario di apertura form ---
    public function totemsport_save_open_time() {
        
        if (!is_user_logged_in()) {
            wp_send_json(['success' => false, 'message' => 'Non autenticato']);
            exit();
        }

        $user = wp_get_current_user();
        if (!in_array('medico', (array) $user->roles) && !in_array('infermiere', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            wp_send_json(['success' => false, 'message' => 'Accesso negato']);
            exit();
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        $tipo = isset($_POST['tipo']) ? sanitize_key($_POST['tipo']) : '';
        if (!$appointment_id || !in_array($tipo, ['med', 'nurse', 'admin'])) {
            wp_send_json(['success' => false, 'message' => 'Parametri mancanti']);
            exit();
        }
        $meta_key = $tipo . '_form_open_time';
        if (!get_post_meta($appointment_id, $meta_key, true)) {
            update_post_meta($appointment_id, $meta_key, current_time('mysql'));
        }
        wp_send_json(['success' => true]);
        exit();
    }

}

// Hook di disattivazione per pulire il cron
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('totemsport_auto_sync');
});

new TotemSport();

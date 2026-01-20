<?php
/**
 * Pagina di configurazione Google Drive per TotemSport
 */

// Verifica permessi amministratore
if (!current_user_can('manage_options')) {
    wp_die('Non hai i permessi per accedere a questa pagina.');
}

// Debug
$helper_exists = class_exists('GoogleDriveHelper');
$credentials_path = plugin_dir_path(__FILE__) . 'credentials/google-credentials.json';
$credentials_exists = file_exists($credentials_path);
$token_path = plugin_dir_path(__FILE__) . 'credentials/google-token.json';
$token_exists = file_exists($token_path);

// La libreria Google API è integrata nel plugin, ma manteniamo un flag per evitare notice
$vendor_exists = true; // non usiamo vendor esterno, resta true

$is_configured = $helper_exists && GoogleDriveHelper::is_configured();

// Debug: forza il log
error_log('TotemSport Debug: GoogleDriveHelper exists: ' . ($helper_exists ? 'YES' : 'NO'));
error_log('TotemSport Debug: Credentials exists: ' . ($credentials_exists ? 'YES' : 'NO'));
error_log('TotemSport Debug: Token exists: ' . ($token_exists ? 'YES' : 'NO'));
error_log('TotemSport Debug: is_configured: ' . ($is_configured ? 'YES' : 'NO'));
error_log('TotemSport Debug: Credentials path: ' . $credentials_path);

?>

<div class="wrap">
    <h1><i class="dashicons dashicons-cloud"></i> Configurazione Google Drive</h1>
    
    <!-- Debug info -->
    <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 11px;">
        <strong>Debug Info:</strong><br>
        GoogleDriveHelper class exists: <?php echo $helper_exists ? '✓ YES' : '✗ NO'; ?><br>
        Credentials file path: <?php echo esc_html($credentials_path); ?><br>
        Credentials exists: <?php echo $credentials_exists ? '✓ YES' : '✗ NO'; ?><br>
        Token file path: <?php echo esc_html($token_path); ?><br>
        Token exists: <?php echo $token_exists ? '✓ YES' : '✗ NO'; ?><br>
        is_configured: <?php echo $is_configured ? '✓ YES' : '✗ NO'; ?><br>
        <br>
        <strong>Logica branch:</strong><br>
        if ($is_configured) → <span style="color: <?php echo $is_configured ? 'green' : 'red'; ?>;"><?php echo $is_configured ? 'TRUE (Mostra sezione Sync)' : 'FALSE (prosegui)'; ?></span><br>
        elseif ($credentials_exists && !$token_exists) → <span style="color: <?php echo ($credentials_exists && !$token_exists) ? 'green' : 'red'; ?>;"><?php echo ($credentials_exists && !$token_exists) ? 'TRUE (Mostra banner Token mancante)' : 'FALSE (prosegui)'; ?></span><br>
        elseif ($credentials_exists) → <span style="color: <?php echo ($credentials_exists && !($credentials_exists && !$token_exists) && !$is_configured) ? 'green' : 'red'; ?>;"><?php echo ($credentials_exists && !($credentials_exists && !$token_exists) && !$is_configured) ? 'TRUE (Mostra banner Ultimo step)' : 'FALSE (prosegui)'; ?></span><br>
        else → <span style="color: <?php echo (!$credentials_exists) ? 'green' : 'red'; ?>;"><?php echo (!$credentials_exists) ? 'TRUE (Mostra banner Credenziali mancanti)' : 'FALSE'; ?></span>
    </div>
    
    <div class="card" style="max-width: 900px; margin-top: 20px;">
        <h2>Stato Configurazione</h2>
        
        <table class="widefat" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th style="width: 40%;">Componente</th>
                    <th style="width: 15%; text-align: center;">Stato</th>
                    <th style="width: 45%;">Note</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Libreria Google API</strong></td>
                    <td style="text-align: center;">
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 24px;"></span>
                    </td>
                    <td>
                        <span style="color: #46b450;">✓ Integrata nativamente (nessuna dipendenza esterna)</span>
                    </td>
                </tr>
                
                <tr>
                    <td><strong>Credenziali Google Drive</strong></td>
                    <td style="text-align: center;">
                        <?php if ($credentials_exists): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 24px;"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss" style="color: #dc3232; font-size: 24px;"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($credentials_exists): ?>
                            <span style="color: #46b450;">✓ File trovato: <code>credentials/google-credentials.json</code></span>
                        <?php else: ?>
                            <span style="color: #dc3232;">✗ File non trovato. Caricare le credenziali in <code>credentials/google-credentials.json</code></span>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <td><strong>Integrazione Google Drive</strong></td>
                    <td style="text-align: center;">
                        <?php if ($is_configured): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 24px;"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss" style="color: #dc3232; font-size: 24px;"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($is_configured): ?>
                            <span style="color: #46b450;">✓ Attiva - I documenti verranno caricati su Google Drive</span>
                        <?php else: ?>
                            <span style="color: #dc3232;">✗ Non attiva - I documenti verranno salvati solo localmente</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php 
        // Gestisci il callback OAuth2
        if (isset($_GET['code']) && !$is_configured) {
            if (GoogleDriveHelper::exchange_code($_GET['code'])) {
                echo '<div class="notice notice-success"><p><strong>✓ Autorizzazione completata!</strong> Google Drive è ora configurato.</p></div>';
                $is_configured = true;
            } else {
                echo '<div class="notice notice-error"><p><strong>✗ Errore</strong> durante l\'autorizzazione. Riprova.</p></div>';
            }
        }
        
        // Debug condizioni
        error_log('TotemSport Debug branch: is_configured=' . ($is_configured ? 'true' : 'false') . ', credentials_exists=' . ($credentials_exists ? 'true' : 'false') . ', token_exists=' . ($token_exists ? 'true' : 'false'));
        
        if ($is_configured): ?>
            <div class="notice notice-success inline" style="margin: 20px 0;">
                <p><strong>✓ Google Drive è configurato e funzionante!</strong></p>
                <p>I documenti archiviati vengono salvati automaticamente sul TUO Google Drive personale in:</p>
                <ul style="margin-left: 20px;">
                    <li><code>TotemSport Archive/2025/09-12-2025/NOME-COGNOME-CF/</code></li>
                </ul>
                <p style="margin-top: 10px;">
                    <a href="https://drive.google.com" target="_blank" class="button">Apri Google Drive</a>
                </p>
            </div>
            
            <div class="card" style="max-width: 900px; margin-top: 20px;">
                <h2>🔄 Sincronizzazione Bidirezionale</h2>
                
                <div style="display: flex; gap: 20px; margin: 20px 0;">
                    <div style="flex: 1; padding: 15px; background: #f0f6ff; border-left: 4px solid #0073aa;">
                        <h3 style="margin-top: 0;">📤 Archivio → Drive</h3>
                        <p>Carica su Google Drive i file che esistono solo in locale.</p>
                    </div>
                    
                    <div style="flex: 1; padding: 15px; background: #f0fff4; border-left: 4px solid #46b450;">
                        <h3 style="margin-top: 0;">📥 Drive → Archivio</h3>
                        <p>Scarica in locale i file che esistono solo su Drive.</p>
                    </div>
                </div>
                
                <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 20px;">
                    <h3 style="margin-top: 0;">⚡ Sincronizzazione Automatica</h3>
                    <p><strong>Attiva:</strong> La sincronizzazione viene eseguita automaticamente ogni ora in background.</p>
                    <p style="font-size: 13px; color: #666; margin: 10px 0 0 0;">
                        WordPress Cron si occupa di mantenere allineati archivio locale e Google Drive senza intervento manuale.
                    </p>
                </div>
                
                <p><strong>Sincronizza manualmente:</strong></p>
                <button type="button" id="sync-drive-now" class="button button-primary button-large">
                    <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Sincronizza Ora
                </button>
                <span id="sync-status" style="margin-left: 15px; font-weight: bold;"></span>
                
                <p style="margin-top: 15px; font-size: 13px; color: #666;">
                    <strong>📌 Nota:</strong> La sincronizzazione mantiene allineati archivio locale e Google Drive. 
                    I file presenti in entrambe le posizioni non vengono duplicati.
                </p>
            </div>
        <?php elseif ($credentials_exists && !$token_exists): 
            $auth_url = GoogleDriveHelper::get_auth_url();
            ?>
            <div class="notice notice-warning inline" style="margin: 20px 0;">
                <p><strong>⚠️ Token mancante - Completa l'autorizzazione</strong></p>
                <p>Il token è scaduto o è stato cancellato. Clicca il pulsante per autorizzare di nuovo:</p>
                <p style="margin-top: 15px;">
                    <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary button-hero">
                        <span class="dashicons dashicons-google" style="margin-top: 3px;"></span>
                        Autorizza con Google
                    </a>
                </p>
                <p style="font-size: 12px; color: #666; margin-top: 10px;">
                    Ti verrà chiesto di accedere con il tuo account Google e autorizzare l'accesso ai file.
                </p>
            </div>
        <?php elseif ($credentials_exists): 
            $auth_url = GoogleDriveHelper::get_auth_url();
            ?>
            <div class="notice notice-info inline" style="margin: 20px 0;">
                <p><strong>📋 Un ultimo step!</strong></p>
                <p>Clicca il pulsante qui sotto per autorizzare TotemSport ad accedere al tuo Google Drive:</p>
                <p style="margin-top: 15px;">
                    <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary button-hero">
                        <span class="dashicons dashicons-google" style="margin-top: 3px;"></span>
                        Autorizza con Google
                    </a>
                </p>
                <p style="font-size: 12px; color: #666; margin-top: 10px;">
                    Ti verrà chiesto di accedere con il tuo account Google e autorizzare l'accesso ai file. 
                    <strong>Nessuna condivisione manuale</strong> di cartelle richiesta!
                </p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning inline" style="margin: 20px 0;">
                <p><strong>⚠️ Credenziali mancanti</strong></p>
                <p>Segui la guida qui sotto per configurare Google Drive.</p>
            </div>
        <?php endif; ?>
        
        <!-- FALLBACK: Se non è configurato e ha credenziali, mostra sempre il pulsante di autorizzazione -->
        <?php if (!$is_configured && $credentials_exists): 
            $auth_url = GoogleDriveHelper::get_auth_url();
            ?>
            <div style="background: #fff3cd; padding: 20px; margin: 20px 0; border: 3px solid #ffc107; border-radius: 5px;">
                <h3 style="margin-top: 0; color: #856404;">🔴 FALLBACK: Pulsante di Autorizzazione Forzato</h3>
                <p><strong>Se non vedi il pulsante sopra, clicca qui:</strong></p>
                <p style="margin: 20px 0;">
                    <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary button-hero" style="padding: 15px 40px; font-size: 16px;">
                        <span class="dashicons dashicons-google" style="margin-top: 3px;"></span>
                        🔐 Autorizza con Google
                    </a>
                </p>
                <p style="font-size: 12px; color: #666;">URL: <code><?php echo esc_html($auth_url); ?></code></p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="card" style="max-width: 900px; margin-top: 20px;">
        <h2>📘 Come Funziona Google Drive Integration</h2>
        
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: white;">🚀 Autenticazione OAuth2</h3>
        </div>
        <h3>✨ Funzionalità Automatiche</h3>
        <table class="widefat" style="margin-bottom: 20px;">
            <tbody>
                <tr>
                    <td style="width: 50px;"><span class="dashicons dashicons-cloud-upload" style="font-size: 24px; color: #0073aa;"></span></td>
                    <td>
                        <strong>Upload Automatico su Archiviazione</strong><br>
                        <span style="color: #666; font-size: 13px;">Quando archivi un appuntamento, i PDF vengono caricati immediatamente su Google Drive</span>
                    </td>
                </tr>
                <tr style="background: #f9f9f9;">
                    <td><span class="dashicons dashicons-update" style="font-size: 24px; color: #46b450;"></span></td>
                    <td>
                        <strong>Sincronizzazione Automatica Ogni Ora</strong><br>
                        <span style="color: #666; font-size: 13px;">WordPress Cron sincronizza automaticamente Archivio ↔ Drive in background</span>
                    </td>
                </tr>
                <tr>
                    <td><span class="dashicons dashicons-networking" style="font-size: 24px; color: #ffc107;"></span></td>
                    <td>
                        <strong>Sincronizzazione Bidirezionale</strong><br>
                        <span style="color: #666; font-size: 13px;">📤 Locale → Drive: Carica file mancanti | 📥 Drive → Locale: Scarica file aggiunti manualmente</span>
                    </td>
                </tr>
                <tr style="background: #f9f9f9;">
                    <td><span class="dashicons dashicons-category" style="font-size: 24px; color: #9b59b6;"></span></td>
                    <td>
                        <strong>Struttura Cartelle Automatica</strong><br>
                        <span style="color: #666; font-size: 13px;"><code>TotemSport Archive/2025/09-12-2025/NOME-COGNOME-CF/</code></span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="card" style="max-width: 900px; margin-top: 20px;">
        <h2>Sicurezza</h2>
        <div class="notice notice-info inline">
            <p><strong>⚠️ Importante:</strong></p>
            <ul style="margin-left: 20px;">
                <li>Non committare mai il file <code>google-credentials.json</code> su repository pubblici</li>
                <li>Il file è già escluso dal <code>.gitignore</code></li>
                <li>Mantieni le credenziali al sicuro e non condividerle</li>
            </ul>
        </div>
    </div>
    
    <?php if (!$vendor_exists || !$credentials_exists): ?>
    <div class="card" style="max-width: 900px; margin-top: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
        <h2 style="color: #856404;">⚠️ Azione Richiesta</h2>
        <p><strong>Per attivare Google Drive completa i seguenti passaggi:</strong></p>
        <ul style="margin-left: 20px;">
            <?php if (!$vendor_exists): ?>
            <li>Eseguire <code>composer install</code> nella cartella del plugin</li>
            <?php endif; ?>
            <?php if (!$credentials_exists): ?>
            <li>Caricare il file <code>google-credentials.json</code> nella cartella <code>credentials/</code></li>
            <?php endif; ?>
        </ul>
        <p>Una volta completati, ricarica questa pagina per verificare lo stato.</p>
    </div>
    <?php endif; ?>
    
</div>

<style>
    .card h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #ddd;
    }
    
    .card h3 {
        margin-top: 25px;
        margin-bottom: 10px;
        color: #2271b1;
    }
    
    .card ol, .card ul {
        line-height: 1.8;
    }
    
    .card code {
        background: #f0f0f1;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 13px;
    }
    
    .widefat td {
        padding: 12px;
    }
    
    .widefat th {
        padding: 12px;
    }
</style>

<script>
// Debug frontend
console.log('=== TotemSport Google Drive Config Debug ===');
console.log('Credentials exists:', <?php echo json_encode($credentials_exists); ?>);
console.log('Token exists:', <?php echo json_encode($token_exists); ?>);
console.log('is_configured:', <?php echo json_encode($is_configured); ?>);
console.log('Branch logic:');
console.log('  - is_configured:', <?php echo json_encode($is_configured); ?>, '→ Mostra Sync');
console.log('  - credentials_exists && !token_exists:', <?php echo json_encode($credentials_exists && !$token_exists); ?>, '→ Mostra "Token mancante"');
console.log('  - credentials_exists:', <?php echo json_encode($credentials_exists); ?>, '→ Mostra "Ultimo step"');
console.log('  - else:', <?php echo json_encode(!$credentials_exists); ?>, '→ Mostra "Credenziali mancanti"');

// Alert se dovrebbe mostrare il banner Token mancante
if (<?php echo json_encode($credentials_exists && !$token_exists); ?>) {
    console.warn('⚠️ BANNER "Token mancante" DOVREBBE ESSERE VISIBILE SULLA PAGINA');
}

jQuery(document).ready(function($) {
    $('#sync-drive-now').on('click', function() {
        var $button = $(this);
        var $status = $('#sync-status');
        
        console.log('[Sync] Button clicked');
        
        $button.prop('disabled', true);
        $status.html('<span style="color: #0073aa;">⏳ Sincronizzazione in corso...</span>');
        
        $.ajax({
            url: '<?php echo rest_url('totemsport/v1/sync-drive'); ?>',
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            },
            success: function(response) {
                console.log('[Sync] Success response:', response);
                if (response && response.success) {
                    $status.html('<span style="color: #46b450;">✓ ' + (response.message || 'Sincronizzazione completata') + '</span>');
                } else {
                    $status.html('<span style="color: #dc3232;">✗ ' + (response?.message || 'Errore durante la sincronizzazione') + '</span>');
                }
                $button.prop('disabled', false);
                
                setTimeout(function() {
                    $status.fadeOut(function() {
                        $(this).html('').show();
                    });
                }, 5000);
            },
            error: function(xhr, status, error) {
                console.error('[Sync] Error:', xhr.responseJSON, status, error);
                var message = 'Errore durante la sincronizzazione';
                try {
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                } catch (e) {}
                $status.html('<span style="color: #dc3232;">✗ ' + message + '</span>');
                $button.prop('disabled', false);
            }
        });
    });
});
</script>

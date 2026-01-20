<?php
    // Prevent direct access to this file
    if (!defined('ABSPATH')) {
        exit;
    }

    // Recupero l'id dell'appuntamento dalla query string
    $idappuntamento = isset($_GET['idappuntamento']) ? intval($_GET['idappuntamento']) : 0;

    if ($idappuntamento) {
        $app = AppointmentHelper::get_app_data($idappuntamento);
        $urine = isset($_GET['urine']) ? sanitize_text_field($_GET['urine']) : 'Non specificato';
        
        // Verifica se esiste già un appuntamento confermato per questo ID appuntamento
        $existing = get_posts([
            'post_type' => 'appunt_conf',
            'post_status' => 'any',
            'meta_query' => [
                [
                    'key' => 'appointment_id',
                    'value' => $idappuntamento,
                    'compare' => '='
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => 1
        ]);

        if (empty($existing)) {
            $content = 'Urine: ' . $urine . "\n";
            $content .= 'Pagamento confermato: ' . ($app['pagato'] ? 'Sì' : 'No') . "\n";

            //salvo l'appuntamento come confermato nel custom post type
            $confirmed_appointment = array(
                'post_title' => 'Appuntamento Confermato - ' . $app['nome'] . ' ' . $app['cognome'],
                'post_content' => $content,
                'post_status' => 'publish',
                'post_type' => 'appunt_conf',
            );
            $appointment = wp_insert_post($confirmed_appointment, true);

            if (is_wp_error($appointment)) {
                // Gestione dell'errore
                echo 'Errore nel salvataggio dell\'appuntamento confermato: ' . $appointment->get_error_message();
            } else {
                update_post_meta($appointment, 'appointment_id', $idappuntamento);
                update_post_meta($appointment, 'urine', $urine);
                update_post_meta($appointment, 'minore', $_SESSION['minor']);
            }
        }
    }

    $ok_icon = '<i class="fas fa-check-circle" style="font-size:20px; padding:4px; color: #28a745;"></i>';
    $ko_icon = '<i class="fas fa-times" style="font-size: 20px; padding:4px; color: #cf1111ff;"></i>';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <div class="success-icon mb-4">
                <i class="fas fa-check-circle" style="font-size: 80px; color: #28a745;"></i>
            </div>
            <h2 class="mb-4">Grazie per aver completato la procedura!</h2>
            <div class="card p-4 shadow-sm">
                <p class="lead">La tua richiesta è stata inviata con successo.</p>
                <h2>Riepilogo appuntamento</h2>
                <p><span class="pagato">Pagato</span><?php echo $app['pagato'] ? $ok_icon : $ko_icon; ?></p>
                <p><span class="pagato">Urine</span>
                    <?php
                    // Mostra X solo se urine è "si"
                    echo (strtolower($urine) === 'si') ? $ko_icon : $ok_icon;
                    ?>
                </p>

                <hr class="my-4">
                <a href="<?php echo home_url(); ?>/totem" class="btn btn-primary">
                    Torna alla Home
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Ensure FontAwesome is loaded
    if (!document.querySelector('link[href*="font-awesome"]')) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
        document.head.appendChild(link);
    }

// Ripulisce la scelta minorenne per questo appuntamento dopo la conferma finale
(function clearMinorChoiceOnceDone() {
    var params = new URLSearchParams(window.location.search);
    var idapp = params.get('idappuntamento');
    if (!idapp) return;
    var key = 'totemsport_minor_' + idapp;
    try {
        sessionStorage.removeItem(key);
    } catch (e) {
        // ignore storage errors
    }
})();
</script>

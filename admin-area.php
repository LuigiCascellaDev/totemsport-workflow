<?php
// Admin page for TotemSport plugin: list custom posts 'appunt_conf' with fields
// Assumptions: meta keys used are 'nome', 'cognome', 'urine', 'pagato'.
// Action: ?toggle_status=POST_ID&_wpnonce=NONCE will toggle between 'publish' and 'draft'.

//verifico se l'utente ha permessi amministratore
if (!current_user_can('manage_options')) {
    header('Location: ' . home_url());
    exit;
}

// Handle toggle action
if (isset($_GET['toggle_status']) && isset($_GET['_wpnonce'])) {
    $post_id = intval($_GET['toggle_status']);
    $nonce = sanitize_text_field($_GET['_wpnonce']);

    if (wp_verify_nonce($nonce, 'totemsport_toggle_status_' . $post_id)) {
        if (current_user_can('edit_post', $post_id)) {
            $post_obj = get_post($post_id);
            if ($post_obj) {
                $new_status = ($post_obj->post_status === 'publish') ? 'draft' : 'publish';
                wp_update_post(array('ID' => $post_id, 'post_status' => $new_status));
            }
        }
    }

    // Redirect back to the page without query args
    $redirect = remove_query_arg(array('toggle_status', '_wpnonce'));
    wp_safe_redirect($redirect);
    exit;
}



?>

<!doctype html>
    <html>

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
        <style>
            :root {
                --primary-color: #0066cc;
                --admin-color: #0066cc;
            }

            body {
                background-color: #f8f9fa;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }

            .admin-header {
                background: linear-gradient(135deg, #0066cc 0%, #004c99 100%);
                color: white;
                padding: 2rem 0;
                margin-bottom: 2rem;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .admin-header h1 {
                margin: 0;
                font-weight: 600;
                font-size: 2rem;
                color: white;
            }

            .admin-header p {
                margin: 0.5rem 0 0 0;
                opacity: 0.95;
            }

            .table-wrapper {
                background: white;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                overflow: hidden;
                padding: 0.5rem 0 1rem;
            }

            .table-responsive-wrapper {
                padding: 0 1rem 1rem;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            #app-bo-table {
                margin-bottom: 0;
                width: 100% !important;
            }

            #app-bo-table thead {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-bottom: 2px solid #dee2e6;
            }

            #app-bo-table thead th {
                font-weight: 600;
                color: #2d3748;
                padding: 0.75rem 0.9rem;
                white-space: nowrap;
                font-size: 0.9rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            #app-bo-table tbody td {
                padding: 0.7rem 0.85rem;
                vertical-align: middle;
                border-bottom: 1px solid #e9ecef;
            }

            #app-bo-table tbody tr:hover {
                background-color: #f8f9fa;
            }

            .modal-header {
                background: linear-gradient(135deg, #0066cc 0%, #004c99 100%);
                color: white;
            }

            .modal-title {
                font-weight: 600;
            }

            .modal-header .close {
                color: white;
                opacity: 0.8;
            }

            /* Fade rapido per le modali */
            .modal.fade {
                transition: opacity 0.15s linear;
            }

            .modal.fade .modal-dialog {
                transition: transform 0.15s ease-out;
            }

            .btn-action {
                padding: 0.5rem 1rem;
                border-radius: 8px;
                font-size: 0.9rem;
                font-weight: 500;
                border: none;
                cursor: pointer;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
            }

            .btn-preview {
                background: linear-gradient(135deg, #0066cc 0%, #004c99 100%);
                color: white;
            }

            .btn-preview:hover {
                background: linear-gradient(135deg, #0066cc 0%, #004c99 100%);
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3);
            }

            .btn-action i {
                font-size: 1rem;
            }

            .modal-body {
                padding: 2rem;
            }

            .modal-body table {
                margin-bottom: 0;
            }

            /* Responsive tablet: abilita scroll orizzontale e min-width per colonne */
            @media (max-width: 1100px) {
                .table-responsive-wrapper {
                    margin: 0 -0.5rem;
                }
                #app-bo-table,
                #app-bo-table-completed {
                    min-width: 900px;
                }
            }

            .modal-body table td,
            .modal-body table th {
                padding: 0.75rem 1rem;
            }

            .modal-body h6 {
                font-weight: 600;
                color: #2d3748;
                margin-bottom: 1rem;
                font-size: 1.1rem;
            }

            .modal-footer {
                padding: 1rem 2rem;
                border-top: 1px solid #dee2e6;
            }

            .modal-footer .btn {
                padding: 0.6rem 1.5rem;
                border-radius: 8px;
                font-weight: 500;
                border: none;
                transition: all 0.2s ease;
            }

            .modal-footer .btn-secondary {
                background: #6c757d;
                color: white;
            }

            .modal-footer .btn-secondary:hover {
                background: #5a6268;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
            }

            .btn-print {
                background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
                color: white;
            }

            .btn-print:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
            }

            .btn-toggle {
                background: linear-gradient(135deg, #28a745 0%, #20873a 100%);
                color: white;
            }

            .btn-toggle:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            }

            .action-buttons {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }

            .filter-staff {
                margin: 0.25rem;
                padding: 0.5rem 1rem;
                border-radius: 8px;
                font-size: 0.9rem;
                font-weight: 500;
                border: 2px solid #dee2e6;
                background: white;
                color: #495057;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .filter-staff:hover {
                border-color: #0066cc;
                color: white;
                background: linear-gradient(135deg, #0066cc 0%, #004c99 100%);
            }

            .filter-staff.active {
                background: linear-gradient(135deg, #0066cc 0%, #004c99 100%);
                color: white;
                border-color: #0066cc;
            }

            #staff-filters-container {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            #app-bo-table th,
            #app-bo-table td {
                font-size: 0.85rem;
                padding: 0.75rem 0.5rem !important;
            }

            .priority-indicator {
                display: inline-block;
                width: 20px;
                height: 20px;
                line-height: 20px;
                text-align: center;
                border-radius: 50%;
                background: #ffc107;
                color: white;
                font-weight: bold;
                font-size: 0.75rem;
            }

            #bo-controls {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                align-items: center;
            }

            #bo-search-input {
                min-width: 260px;
            }

            #app-bo-pagination {
                display: flex;
                flex-wrap: wrap;
                gap: 0.35rem;
                justify-content: center;
                padding: 0.75rem 1rem 0.25rem;
            }

            #app-bo-pagination .page-btn.active {
                background: linear-gradient(135deg, #0066cc 0%, #004c99 100%);
                color: white;
                border-color: #0066cc;
            }

            .sortable-header {
                cursor: pointer;
                user-select: none;
                display: flex;
                align-items: center;
                gap: 0.4rem;
                white-space: nowrap;
            }

            .sortable-header:hover {
                color: #0066cc;
            }

            .sort-icon {
                font-size: 0.75rem;
                opacity: 0.6;
                transition: opacity 0.2s;
            }

            .sortable-header.active .sort-icon {
                opacity: 1;
                color: #0066cc;
            }

            .payment-cell {
                display: flex;
                flex-direction: column;
                gap: 0.4rem;
            }

            #pos-payment-block {
                display: none;
            }

            .urine-cell {
                display: flex;
                flex-direction: column;
                gap: 0.4rem;
            }

            .payment-label {
                font-size: 0.75rem;
                font-weight: 600;
                color: #495057;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .urine-status,
            .payment-status {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
            }

            .payment-badge,
            .urine-badge {
                padding: 0.35rem 0.75rem;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
                color: white;
                text-transform: uppercase;
            }

            .payment-badge.yes {
                background: linear-gradient(135deg, #28a745 0%, #20873a 100%);
            }

            .payment-badge.no {
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            }

            .urine-badge.yes {
                background: linear-gradient(135deg, #28a745 0%, #20873a 100%);
            }

            .urine-badge.no {
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            }

            .payment-price {
                font-size: 0.8rem;
                font-weight: 600;
                color: #2d3748;
            }
        </style>
    </head>

    <body>
        <div class="admin-header">
            <div class="container" >
                <h1 style="color:white;"><i class="bi bi-calendar-check" style="color:white;"></i> Gestione Accettazioni</h1>
                <p>Visualizza e gestisci tutte le accettazioni confermate</p>
            </div>
        </div>

        <div class="container">
            <?php
                // Recupera tutti gli staff dagli appuntamenti
                $all_staff = AppointmentHelper::get_all_staff();
            ?>

            <div id="bo-controls" class="mb-4">
                <div class="d-flex align-items-center" style="gap: 0.5rem;">
                    <label class="mb-0" style="font-weight: 600; color: #2d3748;">Sede</label>
                    <div id="staff-filters-container">
                        <!-- I filtri verranno generati interamente da JS -->
                    </div>
                </div>
                <div class="ml-auto d-flex align-items-center" style="gap: 0.5rem;">
                    <label for="bo-search-input" class="mb-0" style="font-weight: 600; color: #2d3748;">Cerca</label>
                    <input id="bo-search-input" type="text" class="form-control form-control-sm" placeholder="Nome, cognome o CF">
                </div>
            </div>

            <div class="table-wrapper">
                <h5 class="px-3 pt-3 mb-2" style="color: #2d3748; font-weight: 600;"><i class="bi bi-hourglass-split"></i>Accettazioni da Processare</h5>
                <div class="table-responsive-wrapper">
                    <table id="app-bo-table" class="table mt-4">
                        
                        <thead>
                            <tr>
                                <th style="width: 30px; text-align: center;">P</th>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 90px;">Nome</th>
                                <th style="width: 90px;">Cognome</th>
                                                                <th style="width: 55px;" class="sortable-header" id="sort-ora" data-sort="ora" data-direction="asc">
                                  <span>Ora</span>
                                  <i class="bi bi-arrow-down sort-icon"></i>
                                </th>
                                <th style="width: 120px;">Tipologia</th>
                                                                <th style="width: 80px; text-align:center;">Urine</th>
                                <th style="width: 70px;">Pagato</th>
                                <th style="width: 90px;">Anamnesi</th>
                                <th style="width: 90px;">Consenso</th>
                                <th style="width: 160px;">Azioni</th>
                            </tr>
                        </thead>

                        <tbody id="app-bo-tbody">
                        </tbody>

                    </table>
                    <div id="app-bo-pagination"></div>
                </div>
            </div>

            <div class="table-wrapper mt-4">
                <h5 class="px-3 pt-3 mb-2" style="color: #28a745; font-weight: 600;"><i class="bi bi-check-circle"></i> Accettazioni Completate</h5>
                <div class="table-responsive-wrapper">
                    <table id="app-bo-table-completed" class="table mt-4">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th style="width: 100px;">Nome</th>
                                <th style="width: 100px;">Cognome</th>
                                <th style="width: 80px;">Orario</th>
                                <th style="width: 150px;">Tipologia</th>
                                <th style="width: 100px;">Anamnesi</th>
                                <th style="width: 100px;">Consenso</th>
                                <th style="width: 120px;">Azioni</th>
                            </tr>
                        </thead>
                        <tbody id="app-bo-tbody-completed">
                        </tbody>
                    </table>
                    <div id="app-bo-pagination-completed"></div>
                </div>
            </div>

        </div>
        <!-- </div> -->

        <!-- Modale azioni pre-chiusura -->
        <div class="modal fade" id="modal-close-actions" tabindex="-1" role="dialog" aria-labelledby="closeActionsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" style="color:white" id="closeActionsModalLabel"><?php esc_html_e('Azioni Pre-Chiusura', 'totemsport'); ?></h5>
                    </div>
                    <div class="modal-body" id="modal-close-actions-body">
                        <!-- Contenuto inserito via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php esc_html_e('Annulla', 'totemsport'); ?></button>
                        <button type="button" class="btn btn-primary" style="background:linear-gradient(135deg, #0066cc 0%, #004c99 100%)" id="confirm-close-btn">
                            <span class="btn-text"><?php esc_html_e('Conferma Chiusura', 'totemsport'); ?></span>
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" style="vertical-align:middle;"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!--inserisco il modale in bootstrap-->
        <div class="modal fade" id="modal-preview" tabindex="-1" role="dialog" aria-labelledby="appointmentModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="appointmentModalLabel" style="color: white;">
                            <?php esc_html_e('Dettagli Accettazione', 'totemsport'); ?></h5>
                    </div>
                    <div class="modal-body" id="modal-preview-body">
                        <!-- I dettagli dell'accettazione verranno inseriti qui dinamicamente -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal"><?php esc_html_e('Chiudi', 'totemsport'); ?></button>

                    </div>
                </div>
            </div>
        </div>

    </body>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <!-- Config AJAX -->
    <script>
        var totemsportAjax = {
            ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
            siteUrl: '<?php echo get_bloginfo('url'); ?>'
        };
    </script>
    <!-- Custom Admin JS -->
    <script src="<?php echo plugins_url('assets/admin.js', __FILE__); ?>"></script>

</html>

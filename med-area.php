<?php
    // Med page for TotemSport plugin

    //verifico se l'utente ha ruolo medico o administrator
    $user = wp_get_current_user();

    if (!in_array('medico', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
        header('Location: ' . home_url());
        exit; // prevent direct access
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
                --primary-color: #17a2b8;
                --info-color: #17a2b8;
            }

            body {
                background-color: #f8f9fa;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }

            .admin-header {
                background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
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
                overflow-x: hidden;
            }

            #med-table {
                margin-bottom: 0;
                width: 100% !important;
            }

            #med-table thead {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-bottom: 2px solid #dee2e6;
            }

            #med-table thead th {
                font-weight: 600;
                color: #2d3748;
                padding: 1rem 1.1rem;
                white-space: nowrap;
                font-size: 0.9rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            #med-table tbody td {
                padding: 1rem 1.1rem;
                vertical-align: middle;
                border-bottom: 1px solid #e9ecef;
            }

            #med-table tbody tr:hover {
                background-color: #f8f9fa;
            }

            .modal-header {
                background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
                color: white;
            }

            .modal-title {
                font-weight: 600;
            }

            .modal-header .close {
                color: white;
                opacity: 0.8;
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
                background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
                color: white;
            }

            .btn-preview:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
            }

            .btn-action i {
                font-size: 1rem;
            }

            .modal-body {
                padding: 2rem;
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

            .modal-footer .btn-success {
                background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
                color: white;
            }

            .modal-footer .btn-success:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
            }
            
            /* Nasconde il submit Gravity per il medico: invio solo con bottone dedicato */
            #med-form-content .gform_footer,
            #med-form-content .gform_page_footer,
            #med-form-content input[type="submit"],
            #med-form-content button[type="submit"] {
                display: none !important;
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
                border-color: #17a2b8;
                color: white;
                background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            }

            .filter-staff.active {
                background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
                color: white;
                border-color: #17a2b8;
            }

            #staff-filters-container {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            #bo-controls {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                align-items: center;
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

        </style>
    </head>

    <body>
        <div class="admin-header">
            <div class="container">
                <h1><i class="bi bi-clipboard2-pulse"></i> Area Medico</h1>
                <p>Visite da completare</p>
            </div>
        </div>

        <div class="container">
            <div id="bo-controls" class="mb-4 mt-4">
                <div class="d-flex align-items-center" style="gap: 0.5rem;">
                    <label class="mb-0" style="font-weight: 600; color: #2d3748;">Sede</label>
                    <div id="staff-filters-container">
                        <!-- I filtri verranno generati interamente da JS -->
                    </div>
                </div>
            </div>
            <div class="table-wrapper mt-4">
                <div class="d-flex justify-content-between align-items-center px-3 pt-3">
                    <h5 class="mb-0"><i class="bi bi-bandaid"></i> Visite Da Completare</h5>
                    <input type="text" id="med-completed-search" class="form-control form-control-sm ml-3" style="max-width: 260px;" placeholder="Filtra per nome, cognome, CF...">
                </div>
                <div class="table-responsive-wrapper">
                    <table id="med-table" class="table mt-2">
                        <thead class="thead-light">
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Cognome</th>
                                <th>Orario</th>
                                <th>Tipologia</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody id="med-tbody">
                            <!-- Popolato via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabella visite completate -->
            <div class="table-wrapper mt-4">
                <div class="d-flex justify-content-between align-items-center px-3 pt-3">
                    <h5 class="mb-0"><i class="bi bi-check2-circle"></i> Visite completate (modificabili)</h5>
                    <input type="text" id="med-completed-search" class="form-control form-control-sm ml-3" style="max-width: 260px;" placeholder="Filtra per nome, cognome, CF...">
                </div>
                <div class="table-responsive-wrapper">
                    <table id="med-table-completed" class="table table-sm mt-2">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Cognome</th>
                                <th>Orario</th>
                                <th>Tipologia</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody id="med-tbody-completed">
                            <!-- Popolato via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modale Stampa Certificato -->
        <div class="modal fade" id="medModalCert" tabindex="-1" role="dialog" aria-labelledby="closeActionsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" style="color:white" id="closeActionsModalLabel"><?php esc_html_e('Stampa Certificato', 'totemsport'); ?></h5>
                    </div>
                    <div class="modal-body" id="medModalCertBody">
                        <!-- Contenuto inserito via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php esc_html_e('Annulla', 'totemsport'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modale Documenti -->
        <div class="modal fade" id="medModal" tabindex="-1" role="dialog" aria-labelledby="medModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="medModalLabel" style="color: white;">Documenti Paziente</h5>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <!-- Colonna sinistra: Documenti Paziente -->
                            <div class="col-md-6" style="border-right: 2px solid #dee2e6; max-height: 70vh; overflow-y: auto;">
                                <div id="med-docs-anamnesi">
                                    <h6>Anamnesi</h6>
                                    <div id="med-anamnesi-loading" style="display:none;text-align:center;padding:1em;">
                                        <span class="spinner-border spinner-border-sm" style="color:#17a2b8"></span> Caricamento anamnesi...
                                    </div>
                                    <div id="med-anamnesi-content"></div>
                                </div>
                                <div id="med-docs-consenso" class="mt-4">
                                    <h6>Consenso</h6>
                                    <div id="med-consenso-loading" style="display:none;text-align:center;padding:1em;">
                                        <span class="spinner-border spinner-border-sm" style="color:#17a2b8"></span> Caricamento consenso...
                                    </div>
                                    <div id="med-consenso-content"></div>
                                </div>
                                <div id="med-docs-nurse" class="mt-4">
                                    <h6>Form Infermiere</h6>
                                    <div id="med-nurse-loading" style="display:none;text-align:center;padding:1em;">
                                        <span class="spinner-border spinner-border-sm" style="color:#17a2b8"></span> Caricamento form infermiere...
                                    </div>
                                    <div id="med-nurse-content"></div>
                                </div>
                            </div>
                            <!-- Colonna destra: Form Medico -->
                            <div class="col-md-6" style="max-height: 70vh; overflow-y: auto;">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6>Form Medico</h6>
                                </div>
                                <div id="med-form-content">
                                    <?php echo do_shortcode('[gravityform id="10" title="false" description="false" ajax="true"]'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Chiudi</button>
                        <button type="button" class="btn btn-success" id="med-complete-btn">Completa Visita</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
        <!-- Bootstrap JS -->
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
        <!-- Custom Med JS -->
        <script src="<?php echo plugins_url('assets/med.js', __FILE__); ?>"></script>
        <script>
            var totemsportAjax = {
                ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
                siteUrl: '<?php echo get_bloginfo('url'); ?>'
            };
        </script>
    </body>

</html>

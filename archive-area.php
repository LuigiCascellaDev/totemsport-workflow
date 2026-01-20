<?php
// Archive page for TotemSport plugin: visualizza cartelle archiviate

//verifico se l'utente ha permessi amministratore
if (!current_user_can('manage_options')) {
    header('Location: ' . home_url());
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
            --archive-color: #17a2b8;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .archive-header {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .archive-header h1 {
            margin: 0;
            font-weight: 600;
            font-size: 2rem;
            color: white;
        }

        .archive-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.95;
        }

        .search-box {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .search-box input {
            border-radius: 8px;
            border: 2px solid #dee2e6;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
        }

        .search-box input:focus {
            border-color: #17a2b8;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
        }

        .folder-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .folder-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .folder-card.expanded {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }

        .folder-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .folder-info {
            flex: 1;
        }

        .folder-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }

        .folder-meta {
            font-size: 0.9rem;
            color: #718096;
        }

        .folder-badge {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            margin-left: 1rem;
        }

        .folder-files {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid #e9ecef;
            display: none;
        }

        .folder-card.expanded .folder-files {
            display: block;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }

        .file-item:hover {
            background: #e9ecef;
        }

        .file-icon {
            font-size: 1.5rem;
            color: #dc3545;
            margin-right: 1rem;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 500;
            color: #2d3748;
        }

        .file-size {
            font-size: 0.85rem;
            color: #718096;
        }

        .btn-download {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
            color: white;
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: #718096;
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .loading {
            text-align: center;
            padding: 3rem;
        }

        .spinner-border {
            color: #17a2b8;
        }

        .date-section {
            margin-bottom: 2.5rem;
        }

        .date-header {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 3px solid #17a2b8;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .date-header i {
            color: #17a2b8;
        }

        .date-header .badge {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
            background: #17a2b8;
        }

        #filter-date-from,
        #filter-date-to {
            border-radius: 8px;
            border: 2px solid #dee2e6;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
        }

        #filter-date-from:focus,
        #filter-date-to:focus {
            border-color: #17a2b8;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
        }
    </style>
</head>

<body>
    <div class="archive-header">
        <div class="container">
            <h1 style="color:white;"><i class="bi bi-archive" style="color:white;"></i> Archivio Documenti</h1>
            <p>Visualizza e scarica i documenti archiviati dei pazienti</p>
        </div>
    </div>

    <div class="container">
        <div class="search-box">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" id="search-archive" class="form-control"
                        placeholder="Cerca per nome, cognome o codice fiscale...">
                </div>
                <div class="col-md-3">
                    <input type="date" id="filter-date-from" class="form-control" placeholder="Da data">
                </div>
                <div class="col-md-3">
                    <input type="date" id="filter-date-to" class="form-control" placeholder="A data">
                </div>
            </div>
        </div>

        <div id="archive-list" class="loading">
            <div class="spinner-border" role="status">
                <span class="sr-only">Caricamento...</span>
            </div>
            <p class="mt-3">Caricamento archivi...</p>
        </div>

        <div id="archive-pagination" class="d-flex justify-content-center mt-4"></div>
        
        <hr class="my-5" />
        <div id="trash-section" class="mt-5">
            <h3><i class="bi bi-trash"></i> Cestino</h3>
            <div id="trash-list" class="loading">
                <div class="spinner-border" role="status">
                    <span class="sr-only">Caricamento...</span>
                </div>
                <p class="mt-3">Caricamento cestino...</p>
            </div>
            <div id="trash-pagination" class="d-flex justify-content-center mt-4"></div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

    <script>
        var totemsportAjax = {
            ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('totemsport_delete_nonce'); ?>'
        };

        let allFolders = [];
        let trashFolders = [];
        let currentArchivePage = 1;
        let currentTrashPage = 1;
        const DAYS_PER_PAGE = 5; // Numero massimo di giorni da mostrare per pagina
        const TRASH_PER_PAGE = 5; // Numero massimo di elementi nel cestino per pagina

        $(document).ready(function () {
            loadArchive();
            loadTrash();

            // Search functionality
            $('#search-archive').on('input', function () {
                applyFilters();
            });

            // Date filters
            $('#filter-date-from, #filter-date-to').on('change', function () {
                applyFilters();
            });

            // Toggle folder expansion
            $(document).on('click', '.folder-card', function (e) {
                if (!$(e.target).hasClass('btn-download')) {
                    $(this).toggleClass('expanded');
                }
            });

            // Elimina (sposta nel cestino)
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.btn-delete-archive');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                const path = btn.getAttribute('data-path');
                const label = btn.getAttribute('data-label');
                if (!path) return;
                if (!confirm(`Spostare l'archivio di ${label} nel cestino?`)) return;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
                fetch(totemsportAjax.ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'totemsport_trash_archive_folder',
                        path: path,
                        nonce: totemsportAjax.nonce
                    })
                })
                    .then(resp => resp.json())
                    .then(response => {
                        if (response && response.success) {
                            alert('Archivio spostato nel cestino!');
                            loadArchive();
                            loadTrash();
                        } else {
                            const message = response && response.message ? response.message : 'Errore durante lo spostamento nel cestino';
                            alert(message);
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-trash"></i> Elimina';
                        }
                    })
                    .catch(error => {
                        alert('Errore durante lo spostamento nel cestino: ' + error);
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-trash"></i> Elimina';
                    });
            }, true);

            // Ripristina dal cestino
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.btn-restore-trash');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                const path = btn.getAttribute('data-path');
                if (!path) return;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
                fetch(totemsportAjax.ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'totemsport_restore_trash_folder',
                        path: path,
                        nonce: totemsportAjax.nonce
                    })
                })
                    .then(resp => resp.json())
                    .then(response => {
                        if (response && response.success) {
                            alert('Archivio ripristinato!');
                            loadArchive();
                            loadTrash();
                        } else {
                            alert('Errore durante il ripristino');
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Ripristina';
                        }
                    })
                    .catch(error => {
                        alert('Errore durante il ripristino: ' + error);
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Ripristina';
                    });
            }, true);

            // Elimina definitivamente dal cestino
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.btn-delete-trash');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                const path = btn.getAttribute('data-path');
                if (!path) return;
                if (!confirm('Eliminare definitivamente questa cartella dal cestino?')) return;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
                fetch(totemsportAjax.ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'totemsport_delete_trash_folder',
                        path: path,
                        nonce: totemsportAjax.nonce
                    })
                })
                    .then(resp => resp.json())
                    .then(response => {
                        if (response && response.success) {
                            alert('Cartella eliminata definitivamente!');
                            loadTrash();
                        } else {
                            alert('Errore durante la cancellazione');
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-x-circle"></i> Elimina';
                        }
                    })
                    .catch(error => {
                        alert('Errore durante la cancellazione: ' + error);
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-x-circle"></i> Elimina';
                    });
            }, true);
        });
        function loadTrash() {
            $.ajax({
                url: totemsportAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'totemsport_get_trash_folders'
                },
                success: function (response) {
                    console.log('AJAX cestino response:', response);
                    if (response && response.success && response.data && Array.isArray(response.data.folders)) {
                        trashFolders = response.data.folders;
                        renderTrash(trashFolders);
                    } else {
                        $('#trash-list').html('<div class="no-results"><i class="bi bi-exclamation-circle"></i><p>Errore nel caricamento del cestino</p></div>');
                    }
                },
                error: function (xhr, status, error) {
                    $('#trash-list').html('<div class="no-results"><i class="bi bi-exclamation-circle"></i><p>Errore di connessione</p></div>');
                }
            });
        }

        function renderTrash(folders) {
            if (!Array.isArray(folders) || folders.length === 0) {
                $('#trash-list').html('<div class="no-results"><i class="bi bi-inbox"></i><p>Cestino vuoto</p></div>');
                $('#trash-pagination').empty();
                return;
            }

            // CALCOLO PAGINAZIONE CESTINO
            const totalPages = Math.ceil(folders.length / TRASH_PER_PAGE);
            if (currentTrashPage > totalPages) currentTrashPage = totalPages || 1;
            
            const startIndex = (currentTrashPage - 1) * TRASH_PER_PAGE;
            const endIndex = startIndex + TRASH_PER_PAGE;
            const trashToShow = folders.slice(startIndex, endIndex);

            let html = '';
            trashToShow.forEach(function (folder) {
                const date = new Date(folder.trashed_time * 1000);
                const dateStr = date.toLocaleDateString();
                html += `
                    <div class="folder-card">
                        <div class="folder-header">
                            <div class="folder-info">
                                <div class="folder-name"><i class="bi bi-folder-x"></i> ${folder.name}</div>
                                <div class="folder-meta"><i class="bi bi-clock-history"></i> Eliminato il: ${dateStr}</div>
                            </div>
                            <div class="d-flex align-items-center" style="gap: 0.5rem;">
                                <button class="btn btn-sm btn-success btn-restore-trash" data-path="${folder.path}"><i class="bi bi-arrow-counterclockwise"></i> Ripristina</button>
                                <button class="btn btn-sm btn-danger btn-delete-trash" data-path="${folder.path}"><i class="bi bi-x-circle"></i> Elimina</button>
                            </div>
                        </div>
                        <div class="folder-files">
                            ${folder.files && folder.files.length > 0 ? folder.files.map(f => `<div class='file-item'><i class='bi bi-file-earmark-pdf file-icon'></i> <span class='file-name'>${f}</span></div>`).join('') : '<span class="text-muted">Nessun file</span>'}
                        </div>
                    </div>
                `;
            });
            $('#trash-list').html(html);
            renderTrashPagination(totalPages);
        }

        function renderTrashPagination(totalPages) {
            if (totalPages <= 1) {
                $('#trash-pagination').empty();
                return;
            }

            let html = '<nav aria-label="Trash navigation"><ul class="pagination">';
            html += `<li class="page-item ${currentTrashPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="changeTrashPage(${currentTrashPage - 1}); return false;">&laquo; Precedenti</a></li>`;
            for (let i = 1; i <= totalPages; i++) {
                html += `<li class="page-item ${currentTrashPage === i ? 'active' : ''}"><a class="page-link" href="#" onclick="changeTrashPage(${i}); return false;">${i}</a></li>`;
            }
            html += `<li class="page-item ${currentTrashPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" onclick="changeTrashPage(${currentTrashPage + 1}); return false;">Successivi &raquo;</a></li>`;
            html += '</ul></nav>';
            $('#trash-pagination').html(html);
        }

        window.changeTrashPage = function(page) {
            currentTrashPage = page;
            renderTrash(trashFolders);
            $('html, body').animate({ scrollTop: $("#trash-section").offset().top - 50 }, 500);
        };

        function loadArchive() {
            $.ajax({
                url: totemsportAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'totemsport_get_archived_folders'
                },
                success: function (response) {
                    if (response && response.success) {
                        allFolders = response.folders;
                        renderFolders(allFolders);
                    } else {
                        $('#archive-list').html('<div class="no-results"><i class="bi bi-exclamation-circle"></i><p>Errore nel caricamento degli archivi</p></div>');
                    }
                },
                error: function (xhr, status, error) {
                    $('#archive-list').html('<div class="no-results"><i class="bi bi-exclamation-circle"></i><p>Errore di connessione</p></div>');
                }
            });
        }

        function parseDate(dateStr) {
            // dateStr formato: "DD/MM-Month/YYYY"
            const parts = dateStr.split('/');
            if (parts.length !== 3) return null;

            const day = parts[0];
            const monthPart = parts[1].split('-')[0]; // "MM"
            const year = parts[2];

            return new Date(year, parseInt(monthPart) - 1, parseInt(day));
        }

        function renderFolders(folders) {
            if (folders.length === 0) {
                $('#archive-list').html('<div class="no-results"><i class="bi bi-inbox"></i><p>Nessun documento archiviato</p></div>');
                $('#archive-pagination').empty();
                return;
            }

            // Raggruppa per data
            const foldersByDate = {};
            folders.forEach(function (folder) {
                const dateKey = folder.date;
                if (!foldersByDate[dateKey]) {
                    foldersByDate[dateKey] = [];
                }
                foldersByDate[dateKey].push(folder);
            });

            // ORDINAMENTO CORRETTO: Converte le chiavi in oggetti Date per il confronto
            const sortedDates = Object.keys(foldersByDate).sort(function (a, b) {
                const dateA = parseDate(a);
                const dateB = parseDate(b);
                if (!dateA) return 1;
                if (!dateB) return -1;
                return dateB - dateA; // Ordine Decrescente
            });

            // CALCOLO PAGINAZIONE
            const totalPages = Math.ceil(sortedDates.length / DAYS_PER_PAGE);
            if (currentArchivePage > totalPages) currentArchivePage = totalPages || 1;
            
            const startIndex = (currentArchivePage - 1) * DAYS_PER_PAGE;
            const endIndex = startIndex + DAYS_PER_PAGE;
            const datesToShow = sortedDates.slice(startIndex, endIndex);

            let html = '';
            datesToShow.forEach(function (dateKey) {
                const dateFolders = foldersByDate[dateKey];

                html += `
                <div class="date-section">
                    <h5 class="date-header">
                        <i class="bi bi-calendar-event"></i> ${dateKey} 
                        ${dateKey === new Date().toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit' }) + '-' + new Date().toLocaleDateString('it-IT', { month: 'long' }) + '/' + new Date().getFullYear() ? '<span class="badge badge-success ml-2">OGGI</span>' : ''}
                        <span class="badge badge-secondary ml-2">${dateFolders.length} ${dateFolders.length === 1 ? 'paziente' : 'pazienti'}</span>
                    </h5>
            `;

                dateFolders.forEach(function (folder) {
                    html += `
                        <div class="folder-card" data-nome="${folder.nome.toLowerCase()}" data-cognome="${folder.cognome.toLowerCase()}" data-cf="${folder.cf.toLowerCase()}" data-date="${dateKey}">
                            <div class="folder-header">
                                <div class="folder-info">
                                    <div class="folder-name">
                                        <i class="bi bi-folder2"></i> ${folder.nome} ${folder.cognome}
                                    </div>
                                    <div class="folder-meta">
                                        <i class="bi bi-person-vcard"></i> ${folder.cf}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center" style="gap: 0.5rem;">
                                    <div class="folder-badge">
                                        <i class="bi bi-file-earmark-pdf"></i> ${folder.file_count} ${folder.file_count === 1 ? 'file' : 'files'}
                                    </div>
                                    <button class="btn btn-sm btn-danger btn-delete-archive" data-path="${folder.path}" data-label="${folder.nome} ${folder.cognome}" onclick="event.stopPropagation();">
                                        <i class="bi bi-trash"></i> Elimina
                                    </button>
                                </div>
                            </div>
                            <div class="folder-files">
                                ${renderFiles(folder.files)}
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
            });

            $('#archive-list').html(html);
            renderArchivePagination(totalPages);
        }

        function renderArchivePagination(totalPages) {
            if (totalPages <= 1) {
                $('#archive-pagination').empty();
                return;
            }

            let html = '<nav aria-label="Page navigation"><ul class="pagination">';
            
            // Bottone Precedente
            html += `
                <li class="page-item ${currentArchivePage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changeArchivePage(${currentArchivePage - 1}); return false;" aria-label="Previous">
                        <span aria-hidden="true">&laquo; Precedenti</span>
                    </a>
                </li>
            `;

            // Numeri di pagina
            for (let i = 1; i <= totalPages; i++) {
                html += `
                    <li class="page-item ${currentArchivePage === i ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="changeArchivePage(${i}); return false;">${i}</a>
                    </li>
                `;
            }

            // Bottone Successive
            html += `
                <li class="page-item ${currentArchivePage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changeArchivePage(${currentArchivePage + 1}); return false;" aria-label="Next">
                        <span aria-hidden="true">Successivi &raquo;</span>
                    </a>
                </li>
            `;

            html += '</ul></nav>';
            $('#archive-pagination').html(html);
        }

        window.changeArchivePage = function(page) {
            currentArchivePage = page;
            renderFolders(applyFilters(true)); // Riapplica i filtri ma ritorna l'array invece di renderizzare
            // Scroll to top of list
            $('html, body').animate({
                scrollTop: $("#archive-list").offset().top - 100
            }, 500);
        };

        function renderFiles(files) {
            if (files.length === 0) {
                return '<p class="text-muted">Nessun file disponibile</p>';
            }

            let html = '';
            files.forEach(function (file) {
                const sizeKB = Math.round(file.size / 1024);
                html += `
                    <div class="file-item">
                        <i class="bi bi-file-earmark-pdf file-icon"></i>
                        <div class="file-info">
                            <div class="file-name">${file.name}</div>
                            <div class="file-size">${sizeKB} KB</div>
                        </div>
                        <a href="${file.url}" target="_blank" class="btn-download" onclick="event.stopPropagation()">
                            <i class="bi bi-download"></i> Scarica
                        </a>
                    </div>
                `;
            });

            return html;
        }

        function applyFilters(returnArray = false) {
            const searchQuery = $('#search-archive').val().toLowerCase();
            const dateFromStr = $('#filter-date-from').val();
            const dateToStr = $('#filter-date-to').val();

            // Funzione interna per leggere l'input date YYYY-MM-DD come ora locale
            const parseFormDate = (dStr) => {
                if (!dStr) return null;
                const p = dStr.split('-');
                return new Date(p[0], p[1] - 1, p[2]); // Forza mezzanotte locale
            };

            const fromDate = parseFormDate(dateFromStr);
            const toDate = parseFormDate(dateToStr);

            let filtered = allFolders;

            // Filtro range date
            if (fromDate || toDate) {
                filtered = filtered.filter(function (folder) {
                    const folderDate = parseDate(folder.date); // Mezzanotte locale
                    if (!folderDate) return true;

                    // Confronto pulito tra date locali
                    if (fromDate && folderDate < fromDate) return false;
                    if (toDate && folderDate > toDate) return false;
                    return true;
                });
            }

            // Filtro ricerca
            if (searchQuery !== '') {
                filtered = filtered.filter(function (folder) {
                    return folder.nome.toLowerCase().includes(searchQuery) ||
                        folder.cognome.toLowerCase().includes(searchQuery) ||
                        folder.cf.toLowerCase().includes(searchQuery);
                });
            }

            if (returnArray) return filtered;

            currentArchivePage = 1; // Resetta alla prima pagina quando si filtra
            renderFolders(filtered);
        }
    </script>

</body>

</html>

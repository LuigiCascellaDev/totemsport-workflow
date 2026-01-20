<html>
    <head>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    </head>

    <body>
        <div class="container mt-5">
            <?php
                $step = isset($_GET['step']) ? intval($_GET['step']) : 1;
                include plugin_dir_path(__FILE__) . 'step'.$step.'.php';
            ?>
        </div>
    </body>
</html>

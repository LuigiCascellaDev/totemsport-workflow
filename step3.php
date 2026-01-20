<?php

//recupero l'appuntamentamento
$idappuntamento = isset($_GET['idappuntamento']) ? intval($_GET['idappuntamento']) : 0;
$minore = $_SESSION['minor'];
$dati = AppointmentHelper::get_app_data($idappuntamento);
$codice_fiscale = $dati['cf'];
$nome = $dati['nome'];
$cognome = $dati['cognome'];
$key = 3;
$tipologia = $dati['tipologia'];

$form_id = $dati['form_id'];


$entries = AppointmentHelper::get_form_data($codice_fiscale, $key, $form_id, true);

//se i dati sono presenti passo allo step successivo
if (!empty($entries)) {
    // Archivia subito l'anamnesi se già presente (non serve attendere step successivi)
    if (file_exists(__DIR__ . '/class/ArchiveHelper.php')) {
        require_once __DIR__ . '/class/ArchiveHelper.php';
        ArchiveHelper::archive_appointment($idappuntamento);
    }
    ob_start();
    header('Location: ?page=totemsport&step=4&idappuntamento=' . $idappuntamento . '&cf=' . $codice_fiscale);
    exit();
}

echo do_shortcode('[gravityform id="' . $form_id . '" title="true"]');


?>
<script>
    //popolo il form tramite js --
    document.addEventListener('DOMContentLoaded', function () {


        var cf = '<?php echo $codice_fiscale; ?>';
        var nome = '<?php echo $nome; ?>';
        var cognome = '<?php echo $cognome; ?>';
        var formid = '<?php echo $form_id; ?>';
        var dataNascita = '<?php echo $dati['dataNascita']; ?>';
        var luogoNascita = '<?php echo $dati['luogoNascita']; ?>';
        var tipoDoc = "<?php echo $dati['tipoDoc']; ?>";
        var numDoc = '<?php echo $dati['numDoc']; ?>';

        const cfField = document.getElementById('input_' + formid + '_3');

        const nomeField = document.getElementById('input_' + formid + '_1_3');
        const cognomeField = document.getElementById('input_' + formid + '_1_6');
        const dataVisitaField = document.getElementById('input_' + formid + '_9');

        const dataNascitaField = document.getElementById('input_' + formid + '_5');
        const luogoNascitaField = document.getElementById('input_' + formid + '_4');
        const tipoDocField = document.getElementById('input_' + formid + '_21');
        const numDocField = document.getElementById('input_' + formid + '_8');

        cfField.value = cf;
        nomeField.value = nome;
        cognomeField.value = cognome;
        dataNascitaField.value = dataNascita;
        luogoNascitaField.value = luogoNascita;
        tipoDocField.value = tipoDoc;

        numDocField.value = numDoc;

        var d = new Date();
        dataVisitaField.value = d.getUTCDate() + '/' + (d.getMonth() + 1) + '/' + d.getFullYear()

    })
</script>

<?php

//importo una libreria per la generazione di pdf da html


// Ensure Dompdf autoload is available
if (!class_exists('\\Dompdf\\Dompdf')) {
    $vendorAutoload = dirname(__DIR__) . '/lib/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    }
}


class PdfHelper
{
    public static function generate_label_html($appuntamento_data)
    {
        $nome = esc_html($appuntamento_data['nome']);
        $cognome = esc_html($appuntamento_data['cognome']);

        $data = esc_html($appuntamento_data['dataNascita']);


        $html = "<style>
@page { margin:10px 20px; }
</style><div style='font-family: Arial, sans-serif; font-size: 12px;'>
            <p><strong>NOME:</strong> {$nome}</p>
            <p><strong>COGNOME:</strong> {$cognome}</p>            
            <p><strong>DATA DI NASCITA:</strong> {$data}</p></div>";




        $pdf = new \Dompdf\Dompdf();

        $pdf->loadHtml($html);
        $pdf->setPaper(array(0, 0, 9 * 28.3465, 3 * 28.3465), 'portrait'); // Imposta la dimensione della carta a 9cm x 3cm
        $pdf->render();

        header("Content-Type: application/pdf");
        header("Content-Disposition: inline; filename=etichetta.pdf");

        echo $pdf->output();
        exit;
    }

    /**
     * Genera contenuto PDF da HTML (per archiviazione)
     * @param string $html Contenuto HTML
     * @return string Contenuto binario del PDF
     */
    public static function generate_pdf_from_html($html)
    {
        $pdf = new \Dompdf\Dompdf();
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        return $pdf->output();
    }
}

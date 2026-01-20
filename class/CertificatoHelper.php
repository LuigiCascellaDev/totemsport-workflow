<?php

class CertificatoHelper
{
    /**
     * Genera il certificato PDF e restituisce il path temporaneo.
     * @param array $dati Array associativo con i dati del certificato.
     * @param string $tipo_certificato Tipo di certificato/documento da generare.
     * @return string Path del file PDF generato.
     */
    public static function genera_certificato_pdf($dati, $tipo_certificato)
    {
        // Includi Dompdf
        if (!class_exists('\Dompdf\Dompdf')) {
            require_once plugin_dir_path(__FILE__) . '../lib/vendor/dompdf/src/Dompdf.php';
            require_once plugin_dir_path(__FILE__) . '../lib/vendor/dompdf/src/Options.php';
            if (file_exists(plugin_dir_path(__FILE__) . '../lib/vendor/autoload.php')) {
                require_once plugin_dir_path(__FILE__) . '../lib/vendor/autoload.php';
            }
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVuSans');
        $dompdf = new \Dompdf\Dompdf($options);

        // Rimuovo gli slash (escape) dai dati per evitare "Carta d\'identità"
        $dati = wp_unslash($dati);


        // Numero certificato: deve essere popolato PRIMA della generazione HTML
        $numero_certificato = get_option('totemsport_certificato_numero', 999); 
        $numero_certificato++;
        update_option('totemsport_certificato_numero', $numero_certificato);
        $dati['certificato_numero'] = $numero_certificato;

        $tipo = strtolower($dati['tipologia'] ?? '');
        // Header grafico (immagine)
        $header_img_url = plugins_url('assets/images/cert-header.png', dirname(__FILE__));

        // Normalizzazione tipo per evitare problemi di case-sensitivity o spazi
        $current_type = strtolower(trim($tipo_certificato ?: ($dati['tipo_certificato'] ?? '')));

        // Genera HTML in base al tipo_certificato richiesto
        if (strpos($current_type, 'sospensioni') !== false || strpos($current_type, 'sospensione') !== false || strpos($current_type, 'sospesi') !== false) {
            $html = self::html_modulo_sospesi($dati, $header_img_url);
        } elseif (strpos($current_type, 'consigli') !== false || strpos($current_type, 'consiglio') !== false) {
            $html = self::html_modulo_consigli($dati, $header_img_url);
        } else {
            if (strpos($tipo, 'non agonistica') !== false) {
                $html = self::html_certificato_non_agonistica($dati, $header_img_url);
            } elseif (strpos($tipo, 'agonistica') !== false || strpos($tipo, 'concorso') !== false) {
                $html = self::html_certificato_agonistica($dati, $header_img_url);
            } else {
                $html = self::html_certificato_generico($dati, $header_img_url);
            }
        }

        // Margini minimi
        $dompdf->setPaper('A4', 'portrait');

        $dompdf->loadHtml($html);
        $dompdf->render();

        // Salva PDF in una cartella temporanea
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/certificati/';
        if (!file_exists($cert_dir)) {
            mkdir($cert_dir, 0755, true);
        }
        $filename = 'certificato_' . ($dati['cf'] ?? 'sconosciuto') . '_' . date('YmdHis') . '.pdf';
        $filepath = $cert_dir . $filename;

        file_put_contents($filepath, $dompdf->output());

        return $filepath;
    }

    private static function html_certificato_non_agonistica($dati, $header_img_url) {
        // ...HTML specifico per NON AGONISTICA...
        return '
            <style>
                @page { margin: 10px 20px 10px 20px; }
                body { margin: 0; padding: 0; font-size: 22px; }
                .cert-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
                .cert-table td { padding: 12px 8px; font-size: 22px; }
                .cert-label { font-weight: bold; width: 100%; }
                .cert-value { width: 28%; }
            </style>
            <div style="width:100%;text-align:center;">
                <img src="' . esc_url($header_img_url) . '" style="width:100%;max-width:1000px;margin-bottom:24px;">
            </div>
            <h1 style="text-align:center; font-size:28px; width:100%;">CERTIFICATO DI IDONEITÀ<br>ALL’ATTIVITÀ SPORTIVA NON AGONISTICA</h1>
            <h4 style="text-align:center; font-size:28px; margin-bottom:24px; width:100%;">(D.M. 08/08/2014; Linee Guida)</h4>
            <table class="cert-table">
                <tr>
                    <td class="cert-label">Cognome:</td>
                    <td class="cert-value">' . esc_html($dati['cognome']) . '</td>
                    <td class="cert-label">Nome:</td>
                    <td class="cert-value">' . esc_html($dati['nome']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">Nato a</td>
                    <td class="cert-value">' . esc_html($dati['luogo_nascita']) . '</td>
                    <td class="cert-label">il</td>
                    <td class="cert-value">' . esc_html($dati['data_nascita']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">Residente in</td>
                    <td>' . esc_html($dati['residenza']) . '</td>
                    <td class="cert-label">PROV.</td>
                    <td class="cert-value">' . esc_html($dati['provincia']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">Codice fiscale:</td>
                    <td class="cert-value">' . esc_html($dati['cf']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label" style="font-style:italic;">Documento di identità tipo:</td>
                    <td class="cert-value">' . esc_html($dati['documento']) . '</td>
                    <td class="cert-label" style="font-style:italic;">Numero:</td>
                    <td class="cert-value">' . esc_html($dati['numero_documento']) . '</td>

            </table>
            <p style="font-size:22px; text-align:justify; width:100%; margin-top:24px; margin-bottom:30px;">
                Il soggetto di cui sopra, sulla base della visita medica, dei valori di pressione arteriosa rilevati, nonché del referto del tracciato ECG eseguito in data odierna, contestualmente alla visita, non presenta controindicazioni in atto alla pratica di attività sportiva non agonistica.
            </p>
            <div style="font-size:22px; width:100%; margin-bottom:18px;">
                <span style="font-style:italic;">Il presente certificato ha validità annuale e scadrà il:</span><b> ' . esc_html($dati['scadenza']) . '</b><br>
                <span style="font-style:italic; margin-top:18px;">Rilasciato il:</span><b> ' . esc_html($dati['rilascio']) . ' </b>
            </div>
            <div style="text-align:right; margin-top:10px; margin-right:150px; font-size:22px;">
                <b>Il Medico</b>
            </div>
        ';
    }

    private static function html_certificato_agonistica($dati, $header_img_url) {
        // ...HTML specifico per AGONISTICA...
        return '
            <style>
                @page { margin: 10px 20px 10px 20px; }
                body { margin: 0; padding: 0; font-size: 22px; }
                .cert-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
                .cert-table td { padding: 12px 8px; font-size: 18px; }
                .cert-label { font-weight: bold; }
                .cert-value { width: 100%; }
            </style>
            <div style="width:100%;text-align:center;">
                <img src="' . esc_url($header_img_url) . '" style="width:100%;max-width:1000px;margin-bottom:24px;">
            </div>
            <h1 style="text-align:center; font-size:25px; width:100%;">CERTIFICATO DI IDONEITÀ<br>ALL’ATTIVITÀ SPORTIVA AGONISTICA</h1>
            <h6 style="text-align:center; margin-bottom:24px; margin-top:2px; width:100%;">(Art. 5 - D.M. 18/02/82 Come allegato C modello standard Regione Puglia)</h6>
            <table class="cert-table">
                <tr>
                    <td class="cert-label">CERTIFICATO N.</td>
                    <td class="cert-value">' . esc_html($dati['certificato_numero']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">L&rsquo;atleta:</td>
                    <td class="cert-value">' . esc_html($dati['cognome']) . ' ' . esc_html($dati['nome']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">Nato a</td>
                    <td class="cert-value">' . esc_html($dati['luogo_nascita']) . '</td>
                    <td class="cert-label">il</td>
                    <td class="cert-value">' . esc_html($dati['data_nascita']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">Residente in</td>
                    <td class="cert-value">' . esc_html($dati['residenza']) . '</td>
                    <td class="cert-label">PROV.</td>
                    <td class="cert-value">' . esc_html($dati['provincia']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">Codice fiscale:</td>
                    <td class="cert-value">' . esc_html($dati['cf']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label" style="font-style:italic;">Documento di identità tipo:</td>
                    <td class="cert-value">' . esc_html($dati['documento']) . '</td>
                    <td class="cert-label" style="font-style:italic;">Numero:</td>
                    <td class="cert-value">' . esc_html($dati['numero_documento']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">della Società/Associazione Sportiva:</td>
                    <td class="cert-value" colspan="3">' . esc_html($dati['societa']) . '</td>
                </tr>
            </table>
            <div style="font-size:13px; text-align:center; width:100%; margin-top:24px; margin-bottom:30px;"> 
                <p>
                    SULLA BASE DELLA VISITA MEDICA E DEI RELATIVI ACCERTAMENTI, L&rsquo;ATLETA NON PRESENTA CONTROINDICAZIONI ALLA PRATICA DELL&rsquo;ATTIVITÀ SPORTIVA AGONISTICA DELLO SPORT:
                </p>
                <b style="font-size:25px;">' . esc_html($dati['sport']) . '</b> 
            </div>
            <p style="font-size:16px; text-align:justify; width:100%; margin-top:10px; margin-bottom:30px;">
                L’ATLETA HA L’OBBLIGO DI LENTI CORRETTIVE (a contatto): <b style="font-size:25px">' . esc_html($dati['lenti']) . '</b> <br> GRUPPO SANGUIGNO: <b style="font-size:25px">' . esc_html($dati['sangue']) . '</b> FATT. RH: <b style="font-size:25px">' . esc_html($dati['rh']) . '</b>
            </p>
            <div style="font-size:16px; width:100%; margin-bottom:18px;">
                <span style="font-style:italic;">Il presente certificato ha validità annuale e scadrà il:</span> <b>' . esc_html($dati['scadenza']) . ' </b><br>
                <span style="font-style:italic; margin-top:18px;">Rilasciato il:</span> <b>' . esc_html($dati['rilascio']) . ' </b>
            </div>
            <div style="font-size:16px; width:100%; margin-bottom:18px;">
                <span style="font-style:italic; margin-top:18px;">Data</span> <b>' . esc_html($dati['rilascio']) . '</b>
            </div>
            <div style="text-align:right; margin-top:10px; margin-right:130px; font-size:18px;">
                <b>Il Medico</b>
            </div>
        ';
    }

    private static function html_certificato_generico($dati, $header_img_url) {
        // ...HTML generico...
        return '<div>Certificato GENERICO</div>';
    }

    private static function html_modulo_sospesi($dati, $header_img_url) {
        // ...HTML generico...
        return '
            <style>
                @page { margin: 10px 20px 10px 20px; }
                body { margin: 0; padding: 0; font-size: 22px; }
                .cert-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
                .cert-table td { padding: 12px 8px; font-size: 18px; }
                .cert-label { font-weight: bold; }
                .cert-value { width: 100%; }
            </style>
            <div style="width:100%;text-align:center;">
                <img src="' . esc_url($header_img_url) . '" style="width:100%;max-width:1000px;margin-bottom:24px;">
            </div>
            <h1 style="text-align:center; font-size:25px; width:100%;">RICHIESTA DI ULTERIORI ESAMI</h1>
            <table class="cert-table">
                <tr>
                    <td class="cert-label">L&rsquo;atleta:</td>
                    <td class="cert-value">' . esc_html($dati['cognome']) . ' ' . esc_html($dati['nome']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">Nato a</td>
                    <td class="cert-value">' . esc_html($dati['luogo_nascita']) . '</td>
                    <td class="cert-label">il</td>
                    <td class="cert-value">' . esc_html($dati['data_nascita']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">Residente/domiciliato in</td>
                    <td class="cert-value">' . esc_html($dati['residenza']) . '</td>
                    <td class="cert-label">PROV.</td>
                    <td class="cert-value">' . esc_html($dati['provincia']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">Codice fiscale:</td>
                    <td class="cert-value">' . esc_html($dati['cf']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label" style="font-style:italic;">sottoposto in data:</td>
                    <td class="cert-value">' . esc_html($dati['rilascio']) . '</td>
            </table>
            <div style="font-size:13px; text-align:center; width:100%; margin-top:24px; margin-bottom:30px;"> 
                <p>
                    <span style="font-style:italic;">a visita medica per l’ottenimento di idoneità alla alla pratica sportiva necessita dei seguenti esami specialistici integrativi:</span>
                    <b style="font-style:italic"> Quesito Diagnostico: ' . esc_html($dati['quesito_diagnostico']) . ' </b> 
                </p>
                <p>
                    <b style="font-style:italic; font-size:18px;"> L’Atleta, in attesa del giudizio definitivo, risulta sospeso dall’attività sportiva. </b>
                </p>
            </div>
            <div style="font-size:16px; width:100%; margin-bottom:18px;">
                <span style="font-style:italic; margin-top:18px;">Data</span> <b>' . esc_html($dati['rilascio']) . '</b>
            </div>
            <table style="width:100%; margin-top:20px;">
                <tr>
                    <td style="text-align:left; font-size:18px;">
                        <b>L&rsquo;Atleta o chi esercita la patria podestà</b>
                    </td>
                    <td style="text-align:right; font-size:18px; margin-right:20px">
                        <b>Il Medico</b>
                    </td>
                </tr>
            </table>
            <div style="margin-top:50px; font-size:18px; text-align:justify; width:100%;">
                <p>I referti degli esami aggiuntivi richiesti dal medico dovranno essere inviati nel più breve tempo possibile al seguente indirizzo e-mail: <b>bitritto.medicinasport@gmail.com</b>, inserendo nell&rsquo;oggetto "esami aggiuntivi - NOME COGNOME" oppure tramite WhatsApp al numero <b>380 346 7768</b>, con le stesse modalità. L&rsquo;utente riceverà una risposta entro 5 giorni dall invio dei referti.</p>
            </div>
        ';
    }

    private static function html_modulo_consigli($dati, $header_img_url) {
        // ...HTML generico...
        return '
            <style>
                @page { margin: 10px 20px 10px 20px; }
                body { margin: 0; padding: 0; font-size: 22px; }
                .cert-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
                .cert-table td { padding: 12px 8px; font-size: 18px; }
                .cert-label { font-weight: bold; }
                .cert-value { width: 100%; }
            </style>
            <div style="width:100%;text-align:center;">
                <img src="' . esc_url($header_img_url) . '" style="width:100%;max-width:1000px;margin-bottom:24px;">
            </div>
            <table class="cert-table">
                <tr>
                    <td class="cert-label">L&rsquo;atleta:</td>
                    <td class="cert-value">' . esc_html($dati['cognome']) . ' ' . esc_html($dati['nome']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">Nato a</td>
                    <td class="cert-value">' . esc_html($dati['luogo_nascita']) . '</td>
                    <td class="cert-label">il</td>
                    <td class="cert-value">' . esc_html($dati['data_nascita']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">Residente/domiciliato in</td>
                    <td class="cert-value">' . esc_html($dati['residenza']) . '</td>
                    <td class="cert-label">PROV.</td>
                    <td class="cert-value">' . esc_html($dati['provincia']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label">Codice fiscale:</td>
                    <td class="cert-value">' . esc_html($dati['cf']) . '</td>
                </tr>
                <tr>
                    <td class="cert-label" style="font-style:italic;">sottoposto in data:</td>
                    <td class="cert-value">' . esc_html($dati['rilascio']) . '</td>
                </tr>
            </table>
            <div style="font-size:13px; text-align:center; width:100%; margin-top:24px; margin-bottom:30px;"> 
                <span style="font-style:italic;">a visita medica per l’ottenimento di idoneità alla dello sport.</td>
                <span class="cert-value">' . esc_html($dati['sport']) . '</span>
                <p>
                    <b style="font-style:italic"> ' . esc_html($dati['consigli']) . ' </b> 
                </p>
            </div>
            <div style="font-size:16px; width:100%; margin-bottom:18px;">
                <span style="font-style:italic; margin-top:18px;">Data</span> <b>' . esc_html($dati['rilascio']) . '</b>
            </div>
            <div style="text-align:right; margin-top:10px; margin-right:130px; font-size:18px;">
                <b>Il Medico</b>
            </div>
        ';
    }

}
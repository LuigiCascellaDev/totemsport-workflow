<div class="totemsport-wrapper">
  <div class="totemsport-layout">
    <div class="totemsport-public-area">
      <div class="ts-hero">
        <div class="ts-hero-text">
          <p class="ts-kicker">Benvenuto</p>
          <h2><?php echo esc_html__('Accettazione online', 'totemsport'); ?></h2>
          <p class="ts-sub">Inserisci il Codice Fiscale per continuare</p>
        </div>
      </div>

      <!-- Campo ricerca CF -->
      <div class="totemsport-card">
        <div class="totemsport-field">
          <label for="totemsport-cf-search"><?php esc_html_e('Cerca per Codice Fiscale', 'totemsport'); ?></label>
          <input type="text" id="totemsport-cf-search" name="totemsport_cf_search" autocomplete="off" autofocus />
          <div id="totemsport-cf-suggestions"></div>
        </div>

        <button id="totemsport-next" class="totemsport-btn totemsport-next"><?php esc_html_e('Prossimo', 'totemsport'); ?></button>
      </div>
    </div>

    <!-- Lista appuntamenti -->
    <div class="totemsport-no-appointments" id="prenota_ora" style="display:none;">
      <p class="ts-table-intro"><strong>
          <?php echo esc_html__('Consulta la tabella per verificare se il tuo appuntamento esiste, oppure', 'totemsport'); ?><br>
          <?php echo esc_html__('rivolgiti in segreteria per verificare se è possibile prenotare un nuovo appuntamento oggi', 'totemsport'); ?></strong>
      </p>

      <table class="totemsport-table">
        <thead>
          <tr>
            <th><?php echo esc_html__('Paziente', 'totemsport'); ?></th>
            <th><?php echo esc_html__('Azione', 'totemsport'); ?></th>
          </tr>
        </thead>
        <tbody id="corpo_tabella">
          <?php
          $appointments = $appointments ?? [];
          if (!empty($appointments)):
            foreach ($appointments as $app): ?>
            <tr>
              <td data-label="<?php echo esc_attr__('Paziente', 'totemsport'); ?>">
                <?php
                // Separa CF e nome dalla stringa "CF - Nome"
                $label = $app['label'] ?? $app['nome_paziente'] ?? '';
                $cf = '';
                $nome = $label;

                if (strpos($label, ' - ') !== false) {
                  [$cf, $nome] = array_map('trim', explode(' - ', $label, 2));
                }
                ?>
                <div class="totemsport-paziente-nome"><?php echo esc_html($nome); ?></div>
                <?php if ($cf): ?>
                  <div class="totemsport-paziente-cf"><?php echo esc_html(strtoupper($cf)); ?></div>
                <?php endif; ?>
              </td>

              </td>
              <td data-label="<?php echo esc_attr__('Azione', 'totemsport'); ?>">
                <a href="#" class="action-btn" data-id="<?php echo esc_attr($app['id']); ?>">
                  <?php esc_html_e('Visualizza', 'totemsport'); ?>
                </a>
              </td>
            </tr>
          <?php
            endforeach;
          else: ?>
            <tr><td colspan="2"><?php esc_html_e('Nessun appuntamento disponibile', 'totemsport'); ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content app-modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="appointmentModalLabel" style="color:white;"><?php esc_html_e('Dettagli Appuntamento', 'totemsport'); ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body" id="appointmentModalBody">
        <!-- Qui generiamo la tabella dei dati tramite PHP -->
        <?php
        $dati_appuntamento = $dati_appuntamento ?? null;
        $form_anamnesi = $form_anamnesi ?? null;
        $entry_anamnesi = $entry_anamnesi ?? null;

        // Visualizzazione verticale responsive
        if ($dati_appuntamento) {
          echo '<div class="ts-app-details-vertical">';
          $is_guest = !empty($dati_appuntamento['is_guest']);
          $nome     = $is_guest ? ($dati_appuntamento['first_name'] ?? '-') : ($dati_appuntamento['nome'] ?? '-');
          $cognome  = $is_guest ? ($dati_appuntamento['last_name'] ?? '-')  : ($dati_appuntamento['cognome'] ?? '-');
          $cf       = $dati_appuntamento['cf'] ?? '-';
          $data     = $dati_appuntamento['data'] ?? '';
          $ora      = $dati_appuntamento['ora'] ?? '';
          echo '<div class="ts-app-row"><div class="ts-app-label">Nome</div><div class="ts-app-value">' . esc_html($nome) . '</div></div>';
          echo '<div class="ts-app-row"><div class="ts-app-label">Cognome</div><div class="ts-app-value">' . esc_html($cognome) . '</div></div>';
          echo '<div class="ts-app-row"><div class="ts-app-label">CF</div><div class="ts-app-value">' . esc_html($cf) . '</div></div>';
          echo '<div class="ts-app-row"><div class="ts-app-label">Data App</div><div class="ts-app-value">' . esc_html($data) . '</div></div>';
          echo '<div class="ts-app-row"><div class="ts-app-label">Ora App</div><div class="ts-app-value">' . esc_html($ora) . '</div></div>';
          echo '<div class="ts-app-row"><div class="ts-app-label">Appuntamento figlio minorenne?</div><div class="ts-app-value ts-app-minor-radio">'
            . '<label style="margin-right:12px;"><input type="radio" name="is_minor" value="si"> Si</label>'
            . '<label><input type="radio" name="is_minor" value="no"> No</label>'
            . '</div></div>';
          echo '</div>';
        }

        if ($form_anamnesi && $entry_anamnesi) {
          echo HtmlHelper::show_html_anamnesi($form_anamnesi, $entry_anamnesi);
        }
        ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary"
          id="conferma_appuntamento"><?php esc_html_e('Conferma', 'totemsport'); ?></button>
      </div>
    </div>
  </div>
</div>

<style>
  .totemsport-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: flex-start;
      /* Responsive verticale per dettagli appuntamento in modale */
    justify-content: center;
    background: radial-gradient(circle at 20% 20%, #f1f7ff 0, #f7fbff 40%, #ffffff 75%);
    padding: 24px 20px 32px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  }

  .totemsport-layout {
    width: min(1200px, 100%);
    display: grid;
    grid-template-columns: minmax(360px, 44%) minmax(0, 1fr);
    gap: 44px;
    align-items: flex-start;
  }

  .totemsport-public-area {
    width: 100%;
  }

  .ts-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
  }

  .ts-hero-text h2 {
    margin: 0;
    font-weight: 700;
    color: #0f172a;
  }

  .ts-kicker {
    margin: 0;
    color: #0ea5e9;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    font-size: 13px;
  }

  .ts-sub {
    margin: 6px 0 0 0;
    color: #475569;
    font-size: 14px;
  }

  .totemsport-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
    padding: 18px 18px 16px;
  }

  .totemsport-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: #0f172a;
  }

  #totemsport-cf-search {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 16px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
  }

  #totemsport-cf-search:focus {
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.18);
    outline: none;
  }

  .totemsport-btn {
    width: 100%;
    margin-top: 12px;
    background: linear-gradient(135deg, #0d7cc7 0%, #0a66a6 100%);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 15px;
    font-size: 17px;
    font-weight: 700;
    text-align: center;
    cursor: pointer;
    box-shadow: 0 12px 30px rgba(13, 124, 199, 0.3);
    transition: transform 0.1s ease, box-shadow 0.15s ease;
  }

  .totemsport-btn:active {
    transform: translateY(1px);
    box-shadow: 0 8px 18px rgba(13, 124, 199, 0.25);
  }

  #totemsport-cf-suggestions {
    margin-top: 4px;
  }

  .totemsport-no-appointments {
    width: 100%;
    max-height: 420px;
    overflow-y: auto;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    background: #fff;
  }

  .totemsport-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 320px;
  }

  .totemsport-table th,
  .totemsport-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
    font-size: 14px;
  }

  .totemsport-table th {
    color: #0f172a;
    font-weight: 700;
    background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%);
  }

  .totemsport-table .action-btn,
  .totemsport-table .action-btn:visited {
    display: inline-block;
    padding: 10px 18px;
    background: #0d7cc7;
    color: #fff;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    box-shadow: 0 8px 20px rgba(13, 124, 199, 0.28);
  }

  .ts-table-intro {
    margin: 0 0 10px 0;
    color: #0f172a;
    line-height: 1.45;
  }

  .totemsport-layout > *:first-child,
  .totemsport-layout > *:last-child {
    align-self: flex-start;
  }

  /* Modal header restyle */
  .app-modal-content .modal-header {
    background: linear-gradient(135deg, #0d7cc7 0%, #0a66a6 100%);
    color: #fff;
    border-bottom: none;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    padding: 14px 18px;
  }

  .app-modal-content .modal-title {
    margin: 0;
    font-weight: 700;
    font-size: 18px;
  }

  .app-modal-content .btn-close {
    filter: invert(100%);
    opacity: 0.9;
    width: 28px;
    height: 28px;
  }

  .app-modal-content .btn-close:focus {
    box-shadow: none;
  }

  /* Responsive verticale per dettagli appuntamento in modale */
  .ts-app-details-vertical {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 10px;
  }
  .ts-app-row {
    display: flex;
    flex-direction: column;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 8px;
    margin-bottom: 4px;
  }
  .ts-app-label {
    font-weight: 600;
    color: #0f172a;
    font-size: 15px;
    margin-bottom: 2px;
  }
  .ts-app-value {
    font-size: 15px;
    color: #334155;
    word-break: break-word;
  }
  .ts-app-minor-radio label {
    font-weight: 400;
    margin-right: 18px;
    font-size: 15px;
  }
  @media (max-width: 600px) {
    .ts-app-details-vertical {
      gap: 6px;
    }
    .ts-app-label, .ts-app-value {
      font-size: 14px;
    }
    .ts-app-row {
      padding-bottom: 6px;
    }
  }

  @media (max-width: 1024px) {
    .totemsport-layout {
      grid-template-columns: 1fr;
      gap: 28px;
    }
    .totemsport-wrapper { padding: 20px 14px 28px; }
  }

  @media (max-width: 640px) {
    .totemsport-card { padding: 14px; }
    .totemsport-btn { font-size: 16px; padding: 13px; }
  }
</style>

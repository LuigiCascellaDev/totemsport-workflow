<?php

  $idappuntamento = isset($_GET['idappuntamento']) ? intval($_GET['idappuntamento']) : 0;
  $dati = AppointmentHelper::get_app_data($idappuntamento);
  $codice_fiscale = $dati['cf'];

  $form_id = 2; // ID Anamnesi
    $key = '80'; // campo CF
    $entries = AppointmentHelper::get_form_data($codice_fiscale, $key, $form_id, true);
  // Recupera le entries che contengono il CF specificato

  $mode = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : '';

  if (!empty($entries) && $codice_fiscale != '') {
    $form = GFAPI::get_form($form_id);
    $entries = AppointmentHelper::get_form_data($codice_fiscale, $key, $form_id, true);
    $entry = $entries[0];



    if ($mode === 'edit') {

      $_SESSION['entry_id'] = $entry['id'];

      apply_filters("gform_pre_render_anamnesi", $form, $entry);
      echo do_shortcode('[gravityform id="' . $form_id . '" title="true"]');

    } else if (count($_POST) > 0 && !empty($entries)) {

      header("Location: /totem/?step=2&idappuntamento=" . intval($_GET['idappuntamento']));
      exit();

    } else {

      $html = '<div class="totem-data-review">';
      $html .= '<div class="review-header">';
      $html .= '<i class="bi bi-clipboard-check"></i>';
      $html .= '<h3>Verifica i tuoi dati</h3>';
      $html .= '<p class="text-muted">Controlla che le informazioni siano corrette prima di confermare</p>';
      $html .= '</div>';
      
      $html .= '<div class="data-container">';
      $html .= HtmlHelper::show_html_anamnesi($form, $entry);
      $html .= '</div>';
      
      $html .= '<div class="action-section">';
      $html .= '<p class="info-text"><i class="bi bi-info-circle"></i> Se i dati sono corretti, puoi procedere con la conferma dell\'appuntamento.</p>';
      
      $minor = isset($_GET['minor']) ? sanitize_text_field($_GET['minor']) : 'no';
      $_SESSION['minor'] = $minor;
      
      $html .= '<div class="button-group">';
      $html .= '<a id="confirm_anamnesi" href="#" data-idappuntamento="' . $idappuntamento . '" class="totem-btn totem-btn-confirm">';
      $html .= '<i class="bi bi-check-circle"></i> Conferma Anamnesi';
      $html .= '</a>';
      
      $html .= '<a id="modify_anamnesi" href="/totem/?step=2&idappuntamento=' . $idappuntamento . '&mode=edit" class="totem-btn totem-btn-edit">';
      $html .= '<i class="bi bi-pencil"></i> Modifica Dati';
      $html .= '</a>';
      $html .= '</div>';
      
      $html .= '</div>';
      $html .= '</div>';
      
      $html .= '<style>
        .totem-data-review {
            max-width: 900px;
            margin: 2rem auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .review-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        
        .review-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .review-header h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            color: white;
        }
        
        .review-header .text-muted {
            font-size: 1.1rem;
            opacity: 0.9;
            color: white !important;
        }
        
        .data-container {
            padding: 2rem;
            background: #f8f9fa;
        }
        
        .data-container table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .data-container th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: #2d3748;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        
        .data-container td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            color: #4a5568;
        }
        
        .data-container tr:last-child td {
            border-bottom: none;
        }
        
        .data-container tr:hover {
            background: #f8f9fa;
        }
        
        .action-section {
            padding: 2rem;
            background: white;
        }
        
        .info-text {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: 8px;
            color: #1976d2;
            font-size: 1.05rem;
        }
        
        .info-text i {
            margin-right: 0.5rem;
            font-size: 1.2rem;
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .totem-btn {
            flex: 1;
            min-width: 250px;
            padding: 1.5rem 2rem;
            font-size: 1.3rem;
            font-weight: 600;
            border-radius: 12px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }
        
        .totem-btn i {
            font-size: 1.5rem;
        }
        
        .totem-btn-confirm {
            background: linear-gradient(135deg, #28a745 0%, #20873a 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .totem-btn-confirm:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
            color: white;
        }
        
        .totem-btn-edit {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .totem-btn-edit:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
            color: white;
        }
        
        @media (max-width: 768px) {
            .totem-data-review {
                margin: 1rem;
                border-radius: 12px;
            }
            
            .review-header {
                padding: 2rem 1.5rem;
            }
            
            .review-header i {
                font-size: 2.5rem;
            }
            
            .review-header h3 {
                font-size: 1.5rem;
            }
            
            .data-container,
            .action-section {
                padding: 1.5rem;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .totem-btn {
                width: 100%;
                min-width: auto;
            }
        }
      </style>';
      
      $html .= '<script>
  document.addEventListener("DOMContentLoaded", function() {
    var btn = document.getElementById("confirm_anamnesi");
    if(btn) {
      btn.addEventListener("click", function(e) {
        e.preventDefault();
        btn.classList.add("disabled");
        btn.innerHTML = \'<i class="bi bi-hourglass-split"></i> Conferma in corso...\';
        var idapp = btn.getAttribute("data-idappuntamento");
        fetch("/wp-admin/admin-ajax.php", {
          method: "POST",
          headers: { \'Content-Type\': \'application/x-www-form-urlencoded\' },
          body: new URLSearchParams({
            action: "totemsport_archive_anamnesi",
            appointment_id: idapp
          })
        })
        .then(resp => resp.json())
        .then(data => {
          if(data && data.success) {
            window.location.href = "/totem/?step=3&idappuntamento=" + idapp;
          } else {
            alert("Errore durante l\'archiviazione: " + (data && data.data && data.data.msg ? data.data.msg : \'Errore generico\'));
            btn.classList.remove("disabled");
            btn.innerHTML = \'<i class="bi bi-check-circle"></i> Conferma Anamnesi\';
          }
        })
        .catch(err => {
          alert("Errore di rete: " + err);
          btn.classList.remove("disabled");
          btn.innerHTML = \'<i class="bi bi-check-circle"></i> Conferma Anamnesi\';
        });
      });
    }
  });
</script>';
      
      echo $html;

    }

    //creo una variabile di sessione per salvare lid dellentry

  } else {

    echo do_shortcode('[gravityform id="' . $form_id . '" title="true"]');

    ?>

    <script>
      var cf = '<?php echo $codice_fiscale; ?>';
      var nome = '<?php echo $dati['nome']; ?>';
      var cognome = '<?php echo $dati['cognome']; ?>';
      var formid = 2;
      var dataNascita = '<?php echo $dati['dataNascita']; ?>';
      var luogoNascita = '<?php echo $dati['luogoNascita']; ?>';


      const cfField = document.getElementById('input_' + formid + '_80');

      const nomeField = document.getElementById('input_' + formid + '_65_3');
      const cognomeField = document.getElementById('input_' + formid + '_65_6');


      const dataNascitaField = document.getElementById('input_' + formid + '_79');
      const luogoNascitaField = document.getElementById('input_' + formid + '_85');


      cfField.value = cf;
      nomeField.value = nome;
      cognomeField.value = cognome;
      dataNascitaField.value = dataNascita;
      luogoNascitaField.value = luogoNascita;

    </script>

    <?php
  }
?>

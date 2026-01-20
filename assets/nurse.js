// TotemSport Nurse JS
(function ($) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {

    const nurse_tbody = document.getElementById("nurse-tbody");
    const nurse_tbody_completed = document.getElementById("nurse-tbody-completed");
    const completedSearchInput = document.getElementById("nurse-completed-search");
    const staff_filters_container = document.getElementById("staff-filters-container");

    if (!nurse_tbody || !nurse_tbody_completed) return;

    let selectedStaffFilter = "all";

    // Carica le due tabelle
    let currentCompletedPage = 1;
    function loadNurseTables(pageCompleted = 1) {
      currentCompletedPage = pageCompleted;
      let url = totemsportAjax.ajaxurl + "?action=totemsport_get_nurse_apps&page_completed=" + pageCompleted;
      if (selectedStaffFilter && selectedStaffFilter !== "all") {
        url += "&filter_staff=" + encodeURIComponent(selectedStaffFilter);
      }
      
      fetch(url, {
        credentials: "same-origin",
      })
        .then((response) => response.json())
        .then((data) => {
          if (!data.success) {
            alert(data.message || "Errore durante il caricamento delle tabelle.");
            return;
          }
          nurse_tbody.innerHTML = data.html;
          nurse_tbody_completed.innerHTML = data.html_completed;

          if (staff_filters_container && data.filters) {
            staff_filters_container.innerHTML = data.filters;
            // Sincronizza lo stato visuale dei filtri dopo il refresh
            $(staff_filters_container).find(".filter-staff").removeClass("active");
            $(staff_filters_container).find(`.filter-staff[data-staff="${selectedStaffFilter}"]`).addClass("active");
          }

          // Inserisci la paginazione sotto la tabella completati
          const paginationDiv = document.getElementById("nurse-pagination-completed");
          if (paginationDiv) {
            paginationDiv.innerHTML = data.pagination_completed || "";
          }
          
          attachNurseEvents();
          attachCompletedEvents();
          attachPaginationCompletedEvents();
          // applyFilters() non serve più se filtriamo lato server, 
          // ma lo teniamo per la ricerca live se vogliamo che sia istantanea sui 10 risultati mostrati
          applyFilters(); 
        })
        .catch((error) => {
          console.error("Errore:", error);
        });
    }

    function applyFilters() {
      // Ora facciamo solo filtro testo locale sui risultati già filtrati per sede dal server
      const val = completedSearchInput ? completedSearchInput.value.trim().toLowerCase() : "";
      
      const filterRows = (tbody) => {
        if (!tbody) return;
        Array.from(tbody.querySelectorAll("tr")).forEach(tr => {
          if (tr.cells.length === 1 && tr.classList.contains("text-center")) return;
          const text = tr.textContent.toLowerCase();
          tr.style.display = (val === "" || text.includes(val)) ? "" : "none";
        });
      };

      filterRows(nurse_tbody);
      filterRows(nurse_tbody_completed);
    }

    // Prima chiamata e auto refresh
    loadNurseTables();
    setInterval(() => loadNurseTables(currentCompletedPage), 30000);

    // Gestione filtri sede
    $(document).on("click", ".filter-staff", function () {
      selectedStaffFilter = String($(this).data("staff") || "all");
      $(".filter-staff").removeClass("active");
      $(this).addClass("active");
      // Quando cambiamo sede, torniamo a pagina 1
      loadNurseTables(1); 
    });

    if (completedSearchInput) {
      completedSearchInput.addEventListener("input", applyFilters);
    }

    // Gestione click paginazione tabella completati
    function attachPaginationCompletedEvents() {
      $(".page-link-completed")
        .off("click")
        .on("click", function (e) {
          e.preventDefault();
          const page = parseInt($(this).data("page"), 10);
          if (!isNaN(page) && page !== currentCompletedPage) {
            loadNurseTables(page);
          }
        });
    }

    function attachNurseEvents() {
      // Bottone "Visualizza" per aprire la modale
      $(".nurse-view-btn")
        .off("click")
        .on("click", function (e) {
          e.preventDefault();
          const appointmentId = $(this).data("id");

          // Reset del form per evitare dati residui o stati inconsistenti
          const $f = $("#nurse-form-content form");
          if ($f.length) {
            $f[0].reset();
          }

          // Non ricaricare il form via AJAX: il form rimane sempre presente
          loadPatientDocs(appointmentId);
          $("#nurseModal").modal("show");

          fetch(totemsportAjax.ajaxurl, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=totemsport_save_open_time&appointment_id=" + encodeURIComponent(appointmentId) + "&tipo=nurse"
          });

          $("#nurse-complete-btn").data("appointment-id", appointmentId);
        });
    }

    function attachCompletedEvents() {
      // Bottone "Modifica" su completate
      $(".nurse-edit-btn")
        .off("click")
        .on("click", function (e) {
          e.preventDefault();
          const appointmentId = $(this).data("id");
          loadPatientDocs(appointmentId);
          // Modalità modifica: cambia header e bottone
          $("#nurseModalLabel").text("Modifica visita completata");
          $("#nurseModal .modal-header").css({ background: "linear-gradient(135deg, #ffc107 0%, #ff9800 100%)", color: "#222" });
          $("#nurse-complete-btn").text("Salva Modifiche");
          $("#nurseModal").modal("show");
          $("#nurse-complete-btn").data("appointment-id", appointmentId);
        });
      // Reset header/bottone quando si apre la modale normale
      $(".nurse-view-btn").off("click.nurseReset").on("click.nurseReset", function () {
        $("#nurseModalLabel").text("Documenti Paziente");
        $("#nurseModal .modal-header").css({ background: "linear-gradient(135deg, #28a745 0%, #20873a 100%)", color: "white" });
        $("#nurse-complete-btn").text("Completa e Invia al Medico");
      });
    }

    // RIMOSSO: Filtro live duplicato che sovrascriveva quello dello staff

    function loadPatientDocs(appointmentId) {
      // Carica anamnesi
      $("#nurse-anamnesi-loading").show();
      $("#nurse-anamnesi-content").empty();
      const formDataAnamnesi = new FormData();
      formDataAnamnesi.append("action", "totemsport_get_doc_bo");
      formDataAnamnesi.append("id", appointmentId);
      formDataAnamnesi.append("type", "anamnesi");

      fetch(totemsportAjax.ajaxurl, {
        credentials: "same-origin",
        method: "POST",
        body: formDataAnamnesi,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            $("#nurse-anamnesi-content").html(decodeBase64Utf8(data.html));
          }
          $("#nurse-anamnesi-loading").hide();
        })
        .catch((error) => {
          console.error(error);
          $("#nurse-anamnesi-loading").hide();
        });

      // Carica consenso
      $("#nurse-consenso-loading").show();
      $("#nurse-consenso-content").empty();
      const formDataConsenso = new FormData();
      formDataConsenso.append("action", "totemsport_get_doc_bo");
      formDataConsenso.append("id", appointmentId);
      formDataConsenso.append("type", "consenso");

      fetch(totemsportAjax.ajaxurl, {
        credentials: "same-origin",
        method: "POST",
        body: formDataConsenso,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            $("#nurse-consenso-content").html(decodeBase64Utf8(data.html));
          }
          $("#nurse-consenso-loading").hide();
        })
        .catch((error) => {
          console.error(error);
          $("#nurse-consenso-loading").hide();
        });
    }

    let nurseSubmitting = false;

    // Bottone completa: ora invia anche tutti i campi del form Gravity Forms
    $("#nurse-complete-btn").off("click").on("click", function () {
      if (nurseSubmitting) return;

      const $btn = $(this);
      const appointmentId = $btn.data("appointment-id");

      // Validazione campi obbligatori Gravity Forms
      const $formContainer = $("#nurse-form-content");
      let missingFields = [];
      
      $formContainer.find(".gfield").each(function () {
        const $field = $(this);
        // Verifica se il campo è obbligatorio (tramite classe o presenza dell'asterisco gfield_required)
        const isRequired = $field.hasClass("gfield_contains_required") || $field.find(".gfield_required").length > 0 || $field.find("[aria-required='true']").length > 0;
        
        if (!isRequired || !$field.is(":visible")) return;

        let filled = false;
        const $inputs = $field.find("input, textarea, select").not("[type='hidden'], [type='submit'], [type='button']");

        if ($inputs.length === 0) return;

        $inputs.each(function () {
          const type = $(this).attr("type");
          if (type === "checkbox" || type === "radio") {
            const name = $(this).attr("name");
            if ($formContainer.find("input[name='" + name + "']:checked").length > 0) {
              filled = true;
              return false;
            }
          } else {
            const val = $(this).val();
            if (val && val.trim() !== "") {
              filled = true;
              return false;
            }
          }
        });

        if (!filled) {
          const label = $field.find(".gfield_label").text().replace("*", "").trim() || "Campo obbligatorio";
          missingFields.push(label);
          $field.css({"border": "2px solid red", "padding": "8px", "border-radius": "4px", "margin-bottom": "10px"});
        } else {
          $field.css({"border": "none", "padding": "0", "margin-bottom": "0"});
        }
      });

      if (missingFields.length > 0) {
        alert("Attenzione: i seguenti campi sono obbligatori e non sono stati compilati:\n- " + missingFields.join("\n- "));
        // Scroll al primo errore
        const $firstError = $formContainer.find(".gfield").filter(function() {
            return $(this).css("border-top-color") === "rgb(255, 0, 0)";
        }).first();
        if ($firstError.length) {
            $firstError[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
      }

      if (
        !confirm(
          "Sei sicuro di voler completare la scheda del paziente e inviarla al medico?"
        )
      ) {
        return;
      }

      nurseSubmitting = true;
      const originalText = $btn.text();
      $btn.prop("disabled", true).text("Invio...");

      // Serializza tutti i campi input_* del form Gravity Forms
      const $formFields = $("#nurse-form-content form");
      const formDataArr = $formFields.serializeArray();
      const data = {
        action: "totemsport_nurse_complete",
        appointment_id: appointmentId
      };

      if (formDataArr.length === 0) {
        console.warn("Nessun campo trovato nel form per l'appuntamento " + appointmentId);
      }
      
      formDataArr.forEach(function (field) {
        // Invia solo i campi input_*
        if (field.name.indexOf("input_") === 0) {
          data[field.name] = field.value;
        }
      });

      fetch(totemsportAjax.ajaxurl, {
        credentials: "same-origin",
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams(data),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data && data.success) {
            alert("Visita completata con successo!");
            $("#nurseModal").modal("hide");
            loadNurseTables();
          } else {
            alert((data && data.message) || "Errore durante il completamento.");
          }
        })
        .catch((error) => {
          console.error("Errore:", error);
          alert(error.message || "Si è verificato un errore.");
        })
        .finally(() => {
          nurseSubmitting = false;
          $btn.prop("disabled", false).text(originalText);
        });
    });
    // Crea il div per la paginazione se non esiste
    if (!document.getElementById("nurse-pagination-completed")) {
      const div = document.createElement("div");
      div.id = "nurse-pagination-completed";
      nurse_tbody_completed.parentNode.insertBefore(div, nurse_tbody_completed.nextSibling);
    }

    const quickPhrasesConfig = {
      "input_9_8": [ // Anamnesi Familiare
        "Nulla da segnalare", 
      ],
      "input_9_9": [ // Anamnesi Fisiologica
        "Nulla da segnalare", 
      ],
      "input_9_15": [ // Anamnesi PatologicA
        "Nulla da segnalare", 
      ],
      "input_9_16": [ // Interventi
        "Nulla da segnalare", 
      ],
      "input_9_17": [ // Ricoveri
        "Nulla da segnalare", 
      ],
      "input_9_18": [ // Infortuni
        "Nulla da segnalare", 
      ],
      "input_9_19": [ // Allergie
        "Nulla da segnalare", 
      ],
      "input_9_20": [ // Assunzione Farmaci
        "Nulla da segnalare", 
      ],
      "input_9_27": [ // Trofismo
        "Nulla da segnalare", 
      ],
      "input_9_28": [ // Apparato Locomotore
        "Nulla da segnalare", 
      ],
      "input_9_29": [ // Torace
        "Nulla da segnalare", 
      ],
      "input_9_30": [ // CardioCir.
        "Nulla da segnalare", 
      ],
      "input_9_32": [ // Addome
        "Nulla da segnalare", 
      ],
      "input_9_33": [ // Arti
        "Nulla da segnalare", 
      ],
      "input_9_39": [ // Elettroencefalogramma
        "Nulla da segnalare", 
      ],
      "input_9_40": [ // Esame Neurologico
        "Nulla da segnalare", 
      ],
      "input_9_41": [ // Esame Oto.
        "Nulla da segnalare", 
      ],
      "input_9_42": [ // Esame Audio
        "Nulla da segnalare", 
      ]
    };

    // Funzione che inietta i bottoni nella UI
    function injectQuickPhrases() {
      // Rimuoviamo eventuali bottoni già presenti per non duplicarli
      $(".ts-quick-phrase-wrapper").remove();

      Object.keys(quickPhrasesConfig).forEach(fieldId => {
        const $field = $("#" + fieldId + ", [name='" + fieldId + "']");
        if ($field.length) {
          // Prova a trovare la label standard, altrimenti usa il contenitore del campo
          let $label = $("label[for='" + fieldId + "'], label[for^='" + fieldId + "']");
          
          let html = '<div class="ts-quick-phrase-wrapper mt-1 mb-2" style="display: flex; flex-wrap: wrap; gap: 4px;">';
          quickPhrasesConfig[fieldId].forEach(text => {
            html += `<button type="button" class="btn btn-quick-fill-pill" data-target="${fieldId}" data-text="${text}">${text}</button>`;
          });
          html += '</div>';

          if ($label.length) {
            $label.after(html);
          } else {
            // Se non c'è label (es. in alcuni layout GF), inserisci prima del campo
            $field.before(html);
          }
        }
      });
    }

    // Innesca l'iniezione quando la modale è pronta
    $(document).on("shown.bs.modal", "#nurseModal", function () {
      injectQuickPhrases();
    });

    // Gestione del click sulle Pill (con stopPropagation e off per evitare doppie attivazioni)
    $(document).off("click", ".btn-quick-fill-pill").on("click", ".btn-quick-fill-pill", function (e) {
      e.preventDefault();
      e.stopImmediatePropagation(); // Impedisce che l'evento venga propagato e attivato più volte

      const targetId = $(this).data("target");
      const text = $(this).data("text");
      const $target = $("#" + targetId + ", [name='" + targetId + "']").first(); // Prende solo il primo in caso di duplicati ID/Name

      if ($target.length) {
        const val = $target.val();
        $target.val(val ? val + "\n" + text : text).trigger('change').trigger('input');
        
        // Feedback visivo rapido sul bottone
        $(this).css("background-color", "#28a745").css("color", "white");
        setTimeout(() => $(this).css("background-color", "").css("color", ""), 400);
      }
    });
    
  });
})(jQuery);

// Funzione per decodificare base64 UTF-8
function decodeBase64Utf8(base64) {
  const binary = atob(base64);
  const bytes = Uint8Array.from(binary, (c) => c.charCodeAt(0));
  return new TextDecoder("utf-8").decode(bytes);
}

// Fix: tasto Chiudi modale infermiere
jQuery(document).on("click", "#nurseModal .btn-secondary[data-dismiss=modal]", function () {
  jQuery("#nurseModal").modal("hide");
});

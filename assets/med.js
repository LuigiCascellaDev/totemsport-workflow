// TotemSport Med JS
(function ($) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    const med_tbody = document.getElementById("med-tbody");
    const med_tbody_completed = document.getElementById("med-tbody-completed");
    const completedSearchInput = document.getElementById("med-completed-search");
    const staff_filters_container = document.getElementById("staff-filters-container");

    if (!med_tbody) return;

    let selectedStaffFilter = "all";

    // Carica appuntamenti per il medico
    loadMedAppointments();

    // Auto refresh ogni 60 secondi
    setInterval(loadMedAppointments, 60000);

    function applyFilters() {
      const val = completedSearchInput ? completedSearchInput.value.trim().toLowerCase() : "";
      
      const filterRows = (tbody) => {
        if (!tbody) return;
        Array.from(tbody.querySelectorAll("tr")).forEach(tr => {
          if (tr.cells.length === 1 && tr.classList.contains("text-center")) return;
          const text = tr.textContent.toLowerCase();
          tr.style.display = (val === "" || text.includes(val)) ? "" : "none";
        });
      };

      filterRows(med_tbody);
      filterRows(med_tbody_completed);
    }

    // Gestione filtri sede
    $(document).on("click", "#staff-filters-container .filter-staff", function () {
      selectedStaffFilter = $(this).data("staff") || "all";
      $("#staff-filters-container .filter-staff").removeClass("active");
      $(this).addClass("active");
      loadMedAppointments(); // Carica dal server con il nuovo filtro
    });

    if (completedSearchInput) {
      completedSearchInput.addEventListener("input", applyFilters);
    }

    function loadMedAppointments() {
      const url = new URL(totemsportAjax.ajaxurl);
      url.searchParams.append("action", "totemsport_get_med_apps");
      if (selectedStaffFilter !== "all") {
        url.searchParams.append("filter_staff", selectedStaffFilter);
      }

      fetch(url, {
        credentials: "same-origin",
      })
        .then((response) => response.json())
        .then((data) => {
          if (!data.success) {
            alert(
              data.message ||
              "Errore durante il caricamento degli appuntamenti."
            );
            return;
          }

          med_tbody.innerHTML = data.html;
          if (med_tbody_completed && data.html_completed) {
            med_tbody_completed.innerHTML = data.html_completed;
          }

          if (staff_filters_container && data.filters) {
            staff_filters_container.innerHTML = data.filters;
            // Ripristina classe active sul bottone corretto
            $(staff_filters_container).find(".filter-staff").removeClass("active");
            $(staff_filters_container).find(`.filter-staff[data-staff="${selectedStaffFilter}"]`).addClass("active");
          }

          applyFilters();
          attachMedEvents();
        })
        .catch((error) => {
          console.error("Errore:", error);
        });
    }

    function attachMedEvents() {
      // Bottone "Visualizza" per aprire la modale
      $(".med-view-btn")
        .off("click")
        .on("click", function (e) {
          e.preventDefault();
          const appointmentId = $(this).data("id");

          // Carica i documenti del paziente e il form infermiere
          loadPatientDocsAndNurseForm(appointmentId);

          // Mostra la modale
          $("#medModal").modal("show");

          fetch(totemsportAjax.ajaxurl, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=totemsport_save_open_time&appointment_id=" + encodeURIComponent(appointmentId) + "&tipo=med"
          });

          // Imposta l'ID dell'appuntamento per il completamento
          $("#med-complete-btn").data("appointment-id", appointmentId);
        });

      // Fix: tasto Chiudi modale medico
      $(document).on("click", "#medModal .btn-secondary[data-dismiss=modal]", function () {
        $("#medModal").modal("hide");
      });

      // Fix: tasto Chiudi modale medico
      $(document).on("click", "#medModalCert .btn-secondary[data-dismiss=modal]", function () {
        $("#medModalCert").modal("hide");
      });

      // Bottone azioni extra
      $(".btn-print-certificato-med")
        .off("click")
        .on("click", function (e) {
          e.preventDefault();
          const id = $(this).data("id");
          // Carica i campi certificato via AJAX
          $.post(totemsportAjax.ajaxurl, {
            action: "totemsport_get_certificato_med",
            appointment_id: id
          }, function (resp) {
            if (resp && resp.success && resp.data && resp.data.html) {
              $("#medModalCertBody").html(resp.data.html);
              $("#medModalCert").modal("show");
              $("#medModalCert").data("current-appointment-id", id);
            } else {
              alert((resp && resp.data && resp.data.message) || "Errore nel caricamento delle azioni extra.");
            }
          });
        });

      // Fix manuale per chiusura modali
      $(document).on("click", "[data-dismiss='modal']", function () {
        $(this).closest(".modal").modal("hide");
      });

    }

    // Funzione generica per stampa certificato/sospensione/consigli (Delegata)
    function stampaDocumentoMed(tipo) {
      let spinner, btnText, btn, sportId;
      const id = $("#medModalCert").data("current-appointment-id");

      if (tipo === "certificato") {
        spinner = "#certificato-spinner";
        btnText = "#stampa-certificato-btn-med-text";
        btn = "#stampa-certificato-btn-med";
        sportId = "#input-sport-cert";
      } else if (tipo === "sospensione") {
        spinner = "#sospensione-spinner";
        btnText = "#stampa-sospensione-btn-med-text";
        btn = "#stampa-sospensione-btn-med";
        sportId = "#input-sport-sospensione";
      } else if (tipo === "consiglio") {
        spinner = "#consigli-spinner";
        btnText = "#stampa-consigli-btn-med-text";
        btn = "#stampa-consigli-btn-med";
        sportId = "#input-sport-consigli";
      }

      $(btnText).hide();
      $(spinner).show();

      const oggi = new Date();
      const pad = n => n < 10 ? '0' + n : n;
      const rilascio = pad(oggi.getDate()) + '/' + pad(oggi.getMonth() + 1) + '/' + oggi.getFullYear();
      const scadenzaDate = new Date(oggi);
      scadenzaDate.setFullYear(scadenzaDate.getFullYear() + 1);
      scadenzaDate.setDate(scadenzaDate.getDate() - 1);
      const scadenza = pad(scadenzaDate.getDate()) + '/' + pad(scadenzaDate.getMonth() + 1) + '/' + scadenzaDate.getFullYear();

      var dati = {
        rilascio: rilascio,
        scadenza: scadenza,
        appointment_id: id,
        societa: $("#input-societa").val() || "",
        sport: $(sportId).val() || "",
        lenti: $("#input-lenti").is(":checked") ? "SI" : "NO",
        sangue: $("#input-sangue").val() || "",
        rh: $("#input-rh").val() || "",
        nome: $("#input-nome").val() || "",
        cognome: $("#input-cognome").val() || "",
        luogo_nascita: $("#input-luogo-nascita").val() || "",
        data_nascita: $("#input-data-nascita").val() || "",
        residenza: $("#input-residenza").val() || "",
        provincia: $("#input-provincia").val() || "",
        cf: $("#input-cf").val() || "",
        tipo_certificato: tipo,
        documento: $("#input-documento").val() || "",
        numero_documento: $("#input-numero-documento").val() || "",
        quesito_diagnostico: $("#input-quesito-diagnostico").val() || "",
        consigli: $("#input-consigli").val() || ""
      };

      if (!dati.appointment_id) {
        $(spinner).hide();
        $(btnText).show();
        alert("Dati anagrafici non trovati o appuntamento non selezionato.");
        return;
      }

      $.post(totemsportAjax.ajaxurl, {
        action: "totemsport_generate_certificato",
        rilascio: dati.rilascio,
        scadenza: dati.scadenza,
        appointment_id: dati.appointment_id,
        societa: dati.societa,
        sport: dati.sport,
        lenti: dati.lenti,
        sangue: dati.sangue,
        rh: dati.rh,
        nome: dati.nome,
        cognome: dati.cognome,
        luogo_nascita: dati.luogo_nascita,
        data_nascita: dati.data_nascita,
        residenza: dati.residenza,
        provincia: dati.provincia,
        cf: dati.cf,
        tipo_certificato: dati.tipo_certificato,
        documento: dati.documento,
        numero_documento: dati.numero_documento,
        quesito_diagnostico: dati.quesito_diagnostico,
        consigli: dati.consigli
      }, function (resp) {
        $(spinner).hide();
        $(btnText).show();
        if (resp.success && resp.data.url) {
          window.open(resp.data.url, "_blank");
        } else if (resp.data && resp.data.missing_fields) {
          alert("Attenzione: dati anagrafici mancanti: " + resp.data.missing_fields.join(", "));
        } else {
          alert("Errore nella generazione del documento.");
        }
      });
    }

    // Delegazione eventi stampa
    $(document).off("click", "#stampa-certificato-btn-med").on("click", "#stampa-certificato-btn-med", function () { stampaDocumentoMed("certificato"); });
    $(document).off("click", "#stampa-sospensione-btn-med").on("click", "#stampa-sospensione-btn-med", function () { stampaDocumentoMed("sospensione"); });
    $(document).off("click", "#stampa-consigli-btn-med").on("click", "#stampa-consigli-btn-med", function () { stampaDocumentoMed("consiglio"); });

    function loadPatientDocsAndNurseForm(appointmentId) {
      // Carica anamnesi
      $("#med-anamnesi-loading").show();
      $("#med-anamnesi-content").empty();
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
            $("#med-anamnesi-content").html(decodeBase64Utf8(data.html));
          }
          $("#med-anamnesi-loading").hide();
        })
        .catch((error) => {
          console.error(error);
          $("#med-anamnesi-loading").hide();
        });

      // Carica consenso
      $("#med-consenso-loading").show();
      $("#med-consenso-content").empty();
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
            $("#med-consenso-content").html(decodeBase64Utf8(data.html));
          }
          $("#med-consenso-loading").hide();
        })
        .catch((error) => {
          console.error(error);
          $("#med-consenso-loading").hide();
        });

      // Carica il form compilato dall'infermiere
      $("#med-nurse-loading").show();
      $("#med-nurse-content").empty();
      const formDataNurse = new FormData();
      formDataNurse.append("action", "totemsport_get_nurse_form");
      formDataNurse.append("id", appointmentId);

      fetch(totemsportAjax.ajaxurl, {
        credentials: "same-origin",
        method: "POST",
        body: formDataNurse,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            $("#med-nurse-content").html(decodeBase64Utf8(data.html));
          }
          $("#med-nurse-loading").hide();
        })
        .catch((error) => {
          console.error(error);
          $("#med-nurse-loading").hide();
        });
    }

    let medSubmitting = false;

    // Bottone completa
    $("#med-complete-btn").off("click").on("click", function () {
      if (medSubmitting) return;

      const $btn = $(this);
      const appointmentId = $btn.data("appointment-id");

      // Validazione campi obbligatori Gravity Forms ID 10
      const $formContainer = $("#med-form-content");
      let missingFields = [];
      
      $formContainer.find(".gfield").each(function () {
        const $field = $(this);
        // Verifica se il campo è obbligatorio
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
          "Sei sicuro di voler completare questa visita?"
        )
      ) {
        return;
      }

      // Recupera i dati del form Gravity Forms ID 10
      const gravityForm = $("#med-form-content form");
      const formData = new FormData();

      // Aggiungi i dati del form Gravity (solo input_*)
      if (gravityForm.length > 0) {
        const formValues = gravityForm.serializeArray();
        formValues.forEach(function (item) {
          if (/^input_/.test(item.name)) {
            formData.append(item.name, item.value);
          }
        });
      }

      formData.append("action", "totemsport_med_complete");
      formData.append("appointment_id", appointmentId);

      medSubmitting = true;
      const originalText = $btn.text();
      $btn.prop("disabled", true).text("Invio...");

      fetch(totemsportAjax.ajaxurl, {
        credentials: "same-origin",
        method: "POST",
        body: formData,
      })
        .then(async (response) => {
          const text = await response.text();
          try {
            return JSON.parse(text);
          } catch (e) {
            console.error("Risposta non JSON:", text);
            throw new Error("Risposta non valida dal server");
          }
        })
        .then((data) => {
          if (data && data.success) {
            alert(data.message || "Visita completata con successo!");
            $("#medModal").modal("hide");
            loadMedAppointments(); // Ricarica la lista
          } else {
            alert((data && data.message) || "Errore durante il completamento.");
          }
        })
        .catch((error) => {
          console.error("Errore:", error);
          alert(error.message || "Si è verificato un errore.");
        })
        .finally(() => {
          medSubmitting = false;
          $btn.prop("disabled", false).text(originalText);
        });
    });
  });
})(jQuery);

// Funzione per decodificare base64 UTF-8
function decodeBase64Utf8(base64) {
  const binary = atob(base64);
  const bytes = Uint8Array.from(binary, (c) => c.charCodeAt(0));
  return new TextDecoder("utf-8").decode(bytes);
}
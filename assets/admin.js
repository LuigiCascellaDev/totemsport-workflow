// TotemSport Admin JS
(function () {
  console.log('[TotemSport] JS caricato - controlla questa riga nella console.');
})();
(function ($) {
  "use strict";

  let selectedStaffFilter = "all"; // filtro staff di default

  const app_bo_table = document.getElementById("app-bo-tbody");
  const app_bo_table_completed = document.getElementById("app-bo-tbody-completed");
  const staff_filters_container = document.getElementById("staff-filters-container");
  const searchInput = document.getElementById("bo-search-input");
  const paginationContainer = document.getElementById("app-bo-pagination");
  const paginationCompletedContainer = document.getElementById("app-bo-pagination-completed");

  let currentPage = 1;
  const rowsPerPage = 10;
  let sortColumn = "ora"; // colonna di default
  let sortDirection = "asc"; // direzione di default

  document.addEventListener("DOMContentLoaded", function () {
    if (!app_bo_table) return;

    let table; // riferimento alla DataTable attiva
    let tableCompleted; // riferimento alla DataTable completata

    // Gestione aria-hidden, focus e reset stato modale pre-chiusura
    const $closeModal = $("#modal-close-actions");
    function resetCloseModalState() {
      const $btn = $("#confirm-close-btn");
      $btn.prop("disabled", false);
      $btn.find('.spinner-border').addClass('d-none');
      $btn.find('.btn-text').removeClass('d-none');
    }
    $closeModal
      .off("show.bs.modal")
      .on("show.bs.modal", function () {
        if (document.activeElement) {
          document.activeElement.blur();
        }
        $(this).attr("aria-hidden", "false");
        resetCloseModalState();
      })
      .off("hidden.bs.modal")
      .on("hidden.bs.modal", function () {
        $(this).attr("aria-hidden", "true");
        resetCloseModalState();
      });

    // Attach sort handler
    attachSortHandler();

    // auto refresh ogni 30 secondi
    setInterval(carica_app, 30000);
    carica_app();

    // Attach delegated event listeners once
    attach_eventi();
    attach_staff_filter();

    function carica_app() {
      // Passa la data odierna come filtro
      const oggi = new Date();
      const pad = n => n < 10 ? '0' + n : n;
      const dataOggi = pad(oggi.getDate()) + '/' + pad(oggi.getMonth() + 1) + '/' + oggi.getFullYear();
      const url = totemsportAjax.ajaxurl + "?action=totemsport_get_app_bo&data=" + encodeURIComponent(dataOggi);
      fetch(url, {
        credentials: "same-origin",
        cache: "no-cache",
      })
        .then((response) => {
          if (!response.ok) {
            return response.text().then((txt) => {
              throw new Error("HTTP " + response.status + " " + txt.substring(0, 300));
            });
          }
          return response.json();
        })
        .then((data) => {
          if (!data.success) {
            console.error("Server response:", data);
            alert(data.message || "Errore durante la ricerca dell'accettazione.");
            return;
          }

          // aggiorno tbody
          app_bo_table.innerHTML = data.html;
          if (data.html_completed) {
            app_bo_table_completed.innerHTML = data.html_completed;
          }

          if (staff_filters_container && data.filters) {
            staff_filters_container.innerHTML = data.filters;
          }

          applyFiltersAndPagination();
          applyCompletedPaginationAndDedup();
        })
        .catch((error) => {
          console.error("Errore completo:", error);
          console.error("Stack:", error.stack);
          console.error("URL chiamato:", totemsportAjax.ajaxurl + "?action=totemsport_get_app_bo");
          alert("Si è verificato un errore durante l'elaborazione della richiesta. Controlla la console per dettagli.");
        });
    }

    function applyCompletedPaginationAndDedup() {
      const rows = Array.from(document.querySelectorAll("#app-bo-tbody-completed tr"));
      const term = (searchInput?.value || "").trim().toLowerCase();

      // Rimuovi duplicati per ID (prima occorrenza vince) e applica filtri
      const seen = new Set();
      const filtered = rows.filter(row => {
        const id = row.querySelector("td")?.textContent?.trim();
        if (!id || seen.has(id)) return false;
        seen.add(id);

        // Filtro Staff (Sede)
        const staffAttr = row.getAttribute("data-staff") || "";
        if (selectedStaffFilter !== "all") {
          const staffIds = staffAttr.split(",").map((id) => id.trim());
          if (!staffIds.includes(String(selectedStaffFilter))) return false;
        }

        // Filtro Ricerca
        if (!term) return true;
        const cells = row.querySelectorAll("td");
        const nome = (cells[1]?.textContent || "").toLowerCase();
        const cognome = (cells[2]?.textContent || "").toLowerCase();
        return nome.includes(term) || cognome.includes(term);
      });

      // Nascondi tutti
      rows.forEach(row => row.style.display = "none");

      // Paginazione
      const rowsPerPage = 10;
      let currentPageCompleted = Number(paginationCompletedContainer?.getAttribute('data-page')) || 1;
      const totalPages = Math.max(1, Math.ceil(filtered.length / rowsPerPage));
      if (currentPageCompleted > totalPages) currentPageCompleted = totalPages;
      const start = (currentPageCompleted - 1) * rowsPerPage;
      const end = start + rowsPerPage;
      filtered.slice(start, end).forEach(row => row.style.display = "");
      renderCompletedPagination(totalPages, currentPageCompleted);
    }

    function renderCompletedPagination(totalPages, currentPageCompleted) {
      if (!paginationCompletedContainer) return;
      if (totalPages <= 1) {
        paginationCompletedContainer.innerHTML = "";
        return;
      }
      let html = "";
      for (let p = 1; p <= totalPages; p++) {
        const activeClass = p === currentPageCompleted ? "active" : "";
        html += `<button type="button" class="btn btn-outline-success btn-sm page-btn-completed ${activeClass}" data-page="${p}">${p}</button>`;
      }
      paginationCompletedContainer.innerHTML = html;
      paginationCompletedContainer.setAttribute('data-page', currentPageCompleted);
      $(".page-btn-completed")
        .off("click")
        .on("click", function () {
          const page = parseInt($(this).data("page"), 10) || 1;
          paginationCompletedContainer.setAttribute('data-page', page);
          applyCompletedPaginationAndDedup();
        });
    }

    function attach_eventi() {
      // Modale dettagli - Delegato
      $(document).off("click", ".show_modal").on("click", ".show_modal", function (e) {
          e.preventDefault();
          const id = $(this).data("id");
          const type = $(this).data("type");

          fetch(totemsportAjax.ajaxurl, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=totemsport_save_open_time&appointment_id=" + encodeURIComponent(id) + "&tipo=admin"
          });

          const formData = new FormData();
          formData.append("action", "totemsport_get_doc_bo");
          formData.append("id", id);
          formData.append("type", type);

          fetch(totemsportAjax.ajaxurl, {
            credentials: "same-origin",
            method: "POST",
            body: formData,
          })
            .then((resp) => resp.json())
            .then((data) => {
              if (data.success) {
                $("#modal-preview").modal("show");
                $("#modal-preview-body").html(decodeBase64Utf8(data.html));
              } else {
                alert(data.message || "Errore durante la ricerca dell'accettazione.");
              }
            })
            .catch(console.error);
        });

      // Stampa etichetta - Delegato
      $(document).off("click", ".btn-print-label, .print_label").on("click", ".btn-print-label, .print_label", function (e) {
          e.preventDefault();
          let id = $(this).data("id");

          if (!id) {
            const row = $(this).closest("tr");
            const postId = row.find("td").eq(1).text().trim();
            id = postId;
          }

          if (confirm("Vuoi stampare l'etichetta per questa accettazione?")) {
            window.open(
              totemsportAjax.siteUrl + "?action=totemsport_print_label&id=" + id,
              "_blank"
            );
          }
        });

      // Bottone chiudi appuntamento - Delegato
      $(document).off("click", ".btn-close-appointment").on("click", ".btn-close-appointment", function (e) {
          e.preventDefault();
          const id = $(this).data("id");

          const formData = new FormData();
          formData.append("action", "totemsport_get_close_actions");
          formData.append("appointment_id", id);

          fetch(totemsportAjax.ajaxurl, {
            credentials: "same-origin",
            method: "POST",
            body: formData,
          })
            .then((resp) => resp.json())
            .then((data) => {
              console.log("close-actions data", data);
              if (!data.success) {
                alert(data.message || "Errore durante il caricamento delle azioni.");
                return;
              }

              const html = data.html || (data.data && data.data.html) || "";
              if (!html) {
                alert("Nessun contenuto disponibile per le azioni di chiusura.");
                return;
              }

              $("#modal-close-actions-body").html(html);
              $("#modal-close-actions").data("appointment-id", id);
              attachCloseModalActions();
              if (document.activeElement) {
                document.activeElement.blur();
              }
              $("#modal-close-actions").attr("aria-hidden", "false");
              $("#modal-close-actions").modal("show");
            })
            .catch((error) => {
              console.error(error);
              alert("Errore durante il caricamento delle azioni.");
            });
        });

      // Bottone archivia documenti - Delegato
      $(document).off("click", ".btn-archive").on("click", ".btn-archive", function (e) {
          e.preventDefault();
          const id = $(this).data("id");
          const $btn = $(this);

          if (!confirm("Archiviare i documenti di questa accettazione?")) {
            return;
          }

          $btn.prop("disabled", true).html('<i class="bi bi-hourglass-split"></i> <span>Archiviazione...</span>');

          const formData = new FormData();
          formData.append("action", "totemsport_archive_appointment");
          formData.append("appointment_id", id);

          fetch(totemsportAjax.ajaxurl, {
            credentials: "same-origin",
            method: "POST",
            body: formData,
          })
            .then((resp) => resp.json())
            .then((data) => {
              if (data.success) {
                alert(data.message || "Documenti archiviati con successo nella cartella: wp-content/uploads/totemsport-archive/");
                $btn.html('<i class="bi bi-check-circle"></i> <span>Archiviato</span>');
              } else {
                alert(data.message || "Errore durante l'archiviazione.");
                $btn.prop("disabled", false).html('<i class="bi bi-folder-plus"></i> <span>Archivia</span>');
              }
            })
            .catch((error) => {
              console.error(error);
              $btn.prop("disabled", false).html('<i class="bi bi-folder-plus"></i> <span>Archivia</span>');
            });
        });

      // Bottone elimina appuntamento - Delegato
      $(document).off("click", ".btn-delete-appointment").on("click", ".btn-delete-appointment", function (e) {
          e.preventDefault();
          const id = $(this).data("id");
          const $btn = $(this);

          if (!confirm("Eliminare definitivamente questo appuntamento e il suo archivio? Questa azione non può essere annullata.")) {
            return;
          }

          $btn.prop("disabled", true).html('<i class="bi bi-hourglass-split"></i> <span>Eliminazione...</span>');

          const formData = new FormData();
          formData.append("action", "totemsport_delete_appointment");
          formData.append("appointment_id", id);

          fetch(totemsportAjax.ajaxurl, {
            credentials: "same-origin",
            method: "POST",
            body: formData,
          })
            .then((resp) => resp.json())
            .then((data) => {
              if (data.success) {
                alert(data.message || "Appuntamento eliminato con successo");
                carica_app();
              } else {
                alert(data.message || "Errore durante l'eliminazione.");
                $btn.prop("disabled", false).html('<i class="bi bi-trash"></i> <span>Elimina</span>');
              }
            })
            .catch((error) => {
              console.error(error);
              alert("Errore durante l'eliminazione");
              $btn.prop("disabled", false).html('<i class="bi bi-trash"></i> <span>Elimina</span>');
            });
        });
    }

    // Azioni nella modale di pre-chiusura
    function attachCloseModalActions() {
      // Stampa etichetta urine
      $("#btn-print-etichetta")
        .off("click")
        .on("click", function (e) {
          e.preventDefault();
          const printId = $(this).data("print-id");
          if (!printId) return;
          window.open(totemsportAjax.siteUrl + "?action=totemsport_print_label&id=" + printId, "_blank");
      });

      // Stampa certificato
      $("#stampa-certificato-btn").on("click", function() {

        $("#stampa-certificato-btn-admin-text").hide();
        $("#certificato-spinner").show();

        // Calcola date rilascio e scadenza
        const oggi = new Date();
        const pad = n => n < 10 ? '0' + n : n;
        const rilascio = pad(oggi.getDate()) + '/' + pad(oggi.getMonth() + 1) + '/' + oggi.getFullYear();

        // Scadenza: oggi + 1 anno - 1 giorno
        const scadenzaDate = new Date(oggi);
        scadenzaDate.setFullYear(scadenzaDate.getFullYear() + 1);
        scadenzaDate.setDate(scadenzaDate.getDate() - 1);
        const scadenza = pad(scadenzaDate.getDate()) + '/' + pad(scadenzaDate.getMonth() + 1) + '/' + scadenzaDate.getFullYear();

        var dati = {
          rilascio: rilascio,
          scadenza: scadenza,
          appointment_id: $("#modal-close-actions").data("appointment-id"),
          societa: $("#input-societa").val() || "",
          sport: $("#input-sport").val() || "",
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
          tipo_certificato: $("#input-tipo-certificato").val() || "",
          documento: $("#input-documento").val() || "",
          numero_documento: $("#input-numero-documento").val() || ""
        };

        // Controllo: alert se dati anagrafici non trovati (puoi aggiungere altri campi se vuoi)
        if (!dati.appointment_id) {
            $("#certificato-spinner").hide();
            $("#stampa-certificato-btn-admin-text").show();
            alert("Dati anagrafici non trovati o appuntamento non selezionato. Impossibile generare il certificato.");
            return;
        }

        $.post(totemsportAjax.ajaxurl, {
            action: "totemsport_generate_certificato",
            ...dati
        }, function(resp) {
            $("#certificato-spinner").hide();
            $("#stampa-certificato-btn-admin-text").show();
            if (resp.success && resp.data.url) {
                window.open(resp.data.url, "_blank");
            } else if (resp.data && resp.data.missing_fields) {
              alert("Attenzione: dati anagrafici mancanti: " + resp.data.missing_fields.join(", "));
            } else {
                alert("Errore nella generazione del certificato.");
            }
        });
      });

      // Conferma chiusura
      let adminCloseSubmitting = false;
      $("#confirm-close-btn")
        .off("click")
        .on("click", function () {
          if (adminCloseSubmitting) return;
          adminCloseSubmitting = true;
          var $btn = $(this);
          var $spinner = $btn.find('.spinner-border');
          var $btnText = $btn.find('.btn-text');
          $btn.prop('disabled', true);
          $spinner.removeClass('d-none');
          $btnText.addClass('d-none');

          const appointmentId = $("#modal-close-actions").data("appointment-id");
          const paymentMethod = $("input[name='payment_method']:checked").val() || "contanti";

          const formData = new FormData();
          formData.append("action", "totemsport_admin_close");
          formData.append("appointment_id", appointmentId);
          formData.append("payment_method", paymentMethod);

          fetch(totemsportAjax.ajaxurl, {
            credentials: "same-origin",
            method: "POST",
            body: formData,
          })
            .then((resp) => resp.json())
            .then((data) => {
              adminCloseSubmitting = false;
              if (data.success) {
                // Rimuovi la riga dalla tabella da completare
                $(`#app-bo-tbody tr[data-id='${appointmentId}']`).remove();
                // Chiudi la modale
                $("#modal-close-actions").modal("hide");
                setTimeout(function() {
                  alert('Chiusura completata con successo!');
                  carica_app(); // Forza refresh tabella subito dopo la chiusura
                }, 400);
              } else {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                $btnText.removeClass('d-none');
                // Alert specifico per lock anti-doppio invio
                if (data.message && data.message.indexOf('già in corso') !== -1) {
                  alert('Attenzione: la chiusura è già stata inviata o è in corso. Attendi qualche secondo e aggiorna la pagina.');
                } else {
                  alert(data.message || "Errore durante l'operazione.");
                }
              }
            })
            .catch((error) => {
              adminCloseSubmitting = false;
              $btn.prop('disabled', false);
              $spinner.addClass('d-none');
              $btnText.removeClass('d-none');
              console.error(error);
              alert("Errore durante l'operazione.");
            });
        });

      // Annulla → chiude subito la modale
      $("#modal-close-actions .btn[data-dismiss='modal']")
        .off("click")
        .on("click", function (e) {
          e.preventDefault();
          $("#modal-close-actions").modal("hide");
        });

      // Toggle blocco POS
      const $paymentRadios = $("input[name='payment_method']");
      const $posBlock = $("#pos-payment-block");
      const $posStatus = $("#pos-status");
      const $posBtn = $("#pos-pay-btn");
      const $f24Btn = $("#f24-receipt-btn");
      const $f24BtnCash = $("#f24-receipt-btn-cash");
      const $cashBlock = $("#cashmatic-payment-block");
      const $cashStatus = $("#cashmatic-status");
      const $cashBtn = $("#cashmatic-pay-btn");
      const $cashCancel = $("#cashmatic-cancel-btn");
      const $amountInput = $("#close-actions-amount");
      const $amountDisplay = $("#close-actions-amount-display");
      const $amountReset = $("#close-actions-amount-reset");
      let cashPollInterval = null;

      // Se non c'è la sezione pagamento (già pagato), esci senza bind aggiuntivi
      if ($paymentRadios.length === 0) {
        return;
      }

      const hasAmountControls = $amountInput.length > 0;
      const originalAmount = hasAmountControls
        ? parseFloat(String($amountInput.data("original") ?? $amountInput.val() ?? "0").replace(",", ".")) || 0
        : 0;

      function syncAmount(rawAmount) {
        const safe = Number.isFinite(rawAmount) && rawAmount >= 0 ? rawAmount : 0;
        const normalized = Math.round(safe * 100) / 100;

        if (hasAmountControls) {
          // Aggiorna solo se il valore è effettivamente diverso per evitare loop di eventi
          const currentVal = parseFloat(($amountInput.val() || "0").replace(",", "."));
          if (currentVal !== normalized) {
            $amountInput.val(normalized.toFixed(2));
          }
          $amountDisplay.text(
            `€${normalized.toLocaleString("it-IT", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
          );
        }

        if ($cashBlock.length) {
          $cashBlock.data("amount", normalized);
        }
        if ($posBlock.length) {
          $posBlock.data("amount", normalized);
        }
      }

      function getCurrentAmount() {
        if (hasAmountControls) {
          const raw = ($amountInput.val() || "").toString().replace(",", ".");
          const parsed = parseFloat(raw);
          if (Number.isFinite(parsed) && parsed > 0) return parsed;
        }
        const cashAmount = parseFloat($cashBlock.data("amount")) || 0;
        const posAmount = parseFloat($posBlock.data("amount")) || 0;
        return cashAmount || posAmount || 0;
      }

      if (hasAmountControls) {

        // Utility per abilitare/disabilitare i bottoni ricevuta
        function setReceiptButtonsEnabled(enabled) {
          $("#f24-receipt-btn, #f24-receipt-btn-cash").prop("disabled", !enabled);
        }

        const handleAmountChange = () => {
          const raw = ($amountInput.val() || "").toString().replace(",", ".");
          const parsed = parseFloat(raw);
          syncAmount(parsed);

          // Salva il nuovo importo via AJAX solo se è valido
          const appointmentId = $("#modal-close-actions").data("appointment-id");
          if (appointmentId && Number.isFinite(parsed) && parsed > 0) {
            setReceiptButtonsEnabled(false); // Disabilita i bottoni ricevuta
            $.post(totemsportAjax.ajaxurl, {
              action: "totemsport_save_custom_total",
              appointment_id: appointmentId,
              custom_total: parsed
            }, function(resp) {
              setReceiptButtonsEnabled(true); // Riabilita i bottoni ricevuta
            });
          } else {
            console.log('[TotemSport][DEBUG] handleAmountChange - Valori non validi, non salvo custom_total', 'appointment_id:', appointmentId, 'custom_total:', parsed);
          }
        };

        $amountInput.off("change.amount blur.amount").on("change.amount blur.amount", handleAmountChange);
        $amountInput.off("keyup.amount").on("keyup.amount", function (e) {
          if (e.key === "Enter") {
            handleAmountChange();
          }
        });

        $amountReset.off("click.amount").on("click.amount", function (e) {
          e.preventDefault();
          
          syncAmount(originalAmount);
        });

        syncAmount(originalAmount);
      } else {
        const fallbackAmount = parseFloat($cashBlock.data("amount")) || parseFloat($posBlock.data("amount")) || 0;
        syncAmount(fallbackAmount);
      }

      function togglePaymentBlocks() {
        const method = $("input[name='payment_method']:checked").val();
        if (method === "pos") {
          $posBlock.show();
          $cashBlock.hide();
          $posStatus.text("").removeClass("text-success text-danger text-muted");
          $posBtn.prop("disabled", false).html('<i class="bi bi-credit-card"></i> Avvia pagamento POS');
          $f24Btn.prop("disabled", false).html('<i class="bi bi-receipt"></i> Genera ricevuta');
          $cashBtn.prop("disabled", true).html('<i class="bi bi-cash-coin"></i> Avvia incasso contanti');
          $f24BtnCash.prop("disabled", true).html('<i class="bi bi-receipt"></i> Genera ricevuta');
        } else {
          $cashBlock.show();
          $posBlock.hide();
          $cashStatus.text("").removeClass("text-success text-danger text-muted");
          $cashBtn.prop("disabled", false).html('<i class="bi bi-cash-coin"></i> Avvia incasso contanti');
          $f24BtnCash.prop("disabled", false).html('<i class="bi bi-receipt"></i> Genera ricevuta');
          $posBtn.prop("disabled", true).html('<i class="bi bi-credit-card"></i> Avvia pagamento POS');
          $f24Btn.prop("disabled", true).html('<i class="bi bi-receipt"></i> Genera ricevuta');
        }
      }

      $paymentRadios.off("change.paymentToggle").on("change.paymentToggle", togglePaymentBlocks);
      togglePaymentBlocks();

      // Invio incasso contanti su Cashmatic
      $cashBtn
        .off("click")
        .on("click", function () {
          const amount = parseFloat($cashBlock.data("amount")) || 0;
          const appointmentId = $("#modal-close-actions").data("appointment-id");

          if (!amount || amount <= 0) {
            $cashStatus
              .text("Importo non valido per il contante")
              .removeClass("text-success text-muted")
              .addClass("text-danger");
            return;
          }

          $cashBtn.prop("disabled", true).html('<i class="bi bi-cash-coin"></i> Invio a Cashmatic...');
          $cashStatus
            .removeClass("text-success text-danger")
            .addClass("text-muted")
            .text("Invio richiesta a Cashmatic...");
          $cashCancel.hide();

          const formData = new FormData();
          formData.append("action", "totemsport_pos_payment");
          formData.append("appointment_id", appointmentId);
          formData.append("amount_cents", Math.round(amount * 100));
          formData.append("channel", "contanti");
          formData.append("payment_method", "contanti");

          fetch(totemsportAjax.ajaxurl, {
            credentials: "same-origin",
            method: "POST",
            body: formData,
          })
            .then((resp) => resp.json())
            .then((data) => {
              if (data.success) {
                const res = data.result || data.data?.result || {};
                const statusText = res.status === "OK" ? "Pagamento contanti riuscito" : "Richiesta inviata (Cashmatic)";
                $cashStatus
                  .text(statusText)
                  .removeClass("text-muted text-danger")
                  .addClass("text-success");
                $cashBtn.html('<i class="bi bi-cash-coin"></i> Cashmatic in corso');
                $cashCancel.show().prop("disabled", false).html('<i class="bi bi-x-circle"></i> Annulla pagamento');
                startCashmaticPolling();
              } else {
                const msg = data.message || data.data?.message || "Incasso contante non riuscito";
                $cashStatus
                  .text(msg)
                  .removeClass("text-muted text-success")
                  .addClass("text-danger");
                $cashBtn.prop("disabled", false).html('<i class="bi bi-cash-coin"></i> Riprova Cashmatic');
              }
            })
            .catch((error) => {
              console.error(error);
              $cashStatus
                .text("Errore di connessione con Cashmatic")
                .removeClass("text-muted text-success")
                .addClass("text-danger");
              $cashBtn.prop("disabled", false).html('<i class="bi bi-cash-coin"></i> Riprova Cashmatic');
              $cashCancel.hide();
            });
        });

      // Annulla pagamento Cashmatic (se non raggiunto l'importo richiesto)
      $cashCancel
        .off("click")
        .on("click", function () {
          $cashCancel.prop("disabled", true).html('<i class="bi bi-x-circle"></i> Annullamento...');

          const formData = new FormData();
          formData.append("action", "totemsport_cashmatic_cancel");

          fetch(totemsportAjax.ajaxurl, {
            credentials: "same-origin",
            method: "POST",
            body: formData,
          })
            .then((resp) => resp.json())
            .then((data) => {
              if (data.success) {
                $cashStatus
                  .text("Pagamento annullato su Cashmatic")
                  .removeClass("text-muted text-danger")
                  .addClass("text-success");
                $cashBtn.prop("disabled", false).html('<i class="bi bi-cash-coin"></i> Avvia incasso contanti');
                $cashCancel.hide();
                clearInterval(cashPollInterval);
                cashPollInterval = null;
              } else {
                const msg = data.message || data.data?.message || "Annullamento non riuscito";
                $cashStatus
                  .text(msg)
                  .removeClass("text-muted text-success")
                  .addClass("text-danger");
                $cashCancel.prop("disabled", false).html('<i class="bi bi-x-circle"></i> Annulla pagamento');
                if (msg) {
                  alert(msg);
                }
              }
            })
            .catch((error) => {
              console.error(error);
              $cashStatus
                .text("Errore annullamento Cashmatic")
                .removeClass("text-muted text-success")
                .addClass("text-danger");
              $cashCancel.prop("disabled", false).html('<i class="bi bi-x-circle"></i> Annulla pagamento');
            });
        });

      function startCashmaticPolling() {
        if (cashPollInterval) {
          clearInterval(cashPollInterval);
        }

        const startTime = Date.now();
        const maxDurationMs = 120000; // 2 minuti di safety

        const poll = () => {
          const now = Date.now();
          if (now - startTime > maxDurationMs) {
            clearInterval(cashPollInterval);
            cashPollInterval = null;
            $cashStatus
              .text("Timeout monitoraggio Cashmatic")
              .removeClass("text-muted text-success")
              .addClass("text-danger");
            $cashBtn.prop("disabled", false).text("Riprova Cashmatic");
            return;
          }

          const formData = new FormData();
          formData.append("action", "totemsport_cashmatic_active");

          fetch(totemsportAjax.ajaxurl, {
            credentials: "same-origin",
            method: "POST",
            body: formData,
          })
            .then((resp) => resp.json())
            .then((data) => {
              if (!data.success) {
                clearInterval(cashPollInterval);
                cashPollInterval = null;
                const msg = data.message || data.data?.message || "Errore stato Cashmatic";
                $cashStatus
                  .text(msg)
                  .removeClass("text-muted text-success")
                  .addClass("text-danger");
                $cashBtn.prop("disabled", false).text("Riprova Cashmatic");
                if (msg) {
                  alert(msg);
                }
                return;
              }

              const res = data.result || data.data?.result || {};
              const d = res.data || {};
              const op = d.operation || "";
              const requested = d.requested ?? "";
              const inserted = d.inserted ?? "";
              const dispensed = d.dispensed ?? "";
              const notDispensed = d.notDispensed ?? "";

              $cashStatus
                .text(
                  `Operazione: ${op || "?"} — Richiesto: ${requested} — Inserito: ${inserted} — Restituito: ${dispensed} — Non erogato: ${notDispensed}`
                )
                .removeClass("text-danger text-success")
                .addClass("text-muted");

              // Se è stato già raggiunto l'importo richiesto, disabilita annullamento
              if (Number(inserted) >= Number(requested)) {
                $cashCancel.hide();
              } else {
                $cashCancel.show().prop("disabled", false).html('<i class="bi bi-x-circle"></i> Annulla pagamento');
              }

              // Se l'operazione è conclusa (idle), chiedo l'esito finale e stoppo il polling
              if (op === "idle") {
                clearInterval(cashPollInterval);
                cashPollInterval = null;
                fetchCashmaticLast();
              }
            })
            .catch((error) => {
              console.error(error);
              clearInterval(cashPollInterval);
              cashPollInterval = null;
              $cashStatus
                .text("Errore di monitoraggio Cashmatic")
                .removeClass("text-muted text-success")
                .addClass("text-danger");
              $cashBtn.prop("disabled", false).text("Riprova Cashmatic");
            });
        };

        cashPollInterval = setInterval(poll, 400); // 200-400ms suggeriti
        poll(); // prima chiamata immediata
      }

      function fetchCashmaticLast() {
        const formData = new FormData();
        formData.append("action", "totemsport_cashmatic_last");

        fetch(totemsportAjax.ajaxurl, {
          credentials: "same-origin",
          method: "POST",
          body: formData,
        })
          .then((resp) => resp.json())
          .then((data) => {
            if (!data.success) {
              const msg = data.message || data.data?.message || "Errore esito finale Cashmatic";
              $cashStatus
                .text(msg)
                .removeClass("text-muted text-success")
                .addClass("text-danger");
              $cashBtn.prop("disabled", false).html('<i class="bi bi-cash-coin"></i> Riprova Cashmatic');
              return;
            }

            const res = data.result || data.data?.result || {};
            const d = res.data || {};
            const requested = d.requested ?? "";
            const inserted = d.inserted ?? "";
            const dispensed = d.dispensed ?? "";
            const notDispensed = d.notDispensed ?? "";

            let finalText = `Completato. Richiesto: ${requested} — Inserito: ${inserted} — Restituito: ${dispensed} — Non erogato: ${notDispensed}`;
            if (Number(notDispensed) > 0) {
              finalText += " (attenzione: resto non erogato)";
              $cashStatus.removeClass("text-muted text-success").addClass("text-danger");
              alert("Attenzione: resto non erogato.");
            } else {
              $cashStatus.removeClass("text-muted text-danger").addClass("text-success");
            }

            $cashStatus.text(finalText);
            $cashBtn.html('<i class="bi bi-cash-coin"></i> Cashmatic completato');
            $cashCancel.hide();
          })
          .catch((error) => {
            console.error(error);
            $cashStatus
              .text("Errore nel recupero esito finale")
              .removeClass("text-muted text-success")
              .addClass("text-danger");
            $cashBtn.prop("disabled", false).html('<i class="bi bi-cash-coin"></i> Riprova Cashmatic');
          });
      }


      function sendPosPayment() {
        const amount = parseFloat($posBlock.data("amount")) || 0;
        const appointmentId = $("#modal-close-actions").data("appointment-id");

        if (!amount || amount <= 0) {
          $posStatus.text("Importo non valido per il POS").removeClass("text-success text-muted").addClass("text-danger");
          return;
        }

        $posBtn.prop("disabled", true).html('<i class="bi bi-credit-card"></i> Invio al POS...');
        $posStatus.removeClass("text-success text-danger").addClass("text-muted").text("Invio richiesta al POS...");

        const formData = new FormData();
        formData.append("action", "totemsport_pos_payment");
        formData.append("appointment_id", appointmentId);
        formData.append("amount_cents", Math.round(amount * 100));

        fetch(totemsportAjax.ajaxurl, {
          credentials: "same-origin",
          method: "POST",
          body: formData,
        })
          .then((resp) => resp.json())
          .then((data) => {
            let res = data.result || data.data?.result || {};
            let status = res.status || null;
            let msg = res.message || data.message || data.data?.message || "Richiesta inviata al POS";
            if (data.success && status === "OK") {
              $posStatus.text(msg).removeClass("text-muted text-danger").addClass("text-success");
              $posBtn.html('<i class="bi bi-credit-card"></i> Richiesta inviata (POS)');
              $f24Btn.prop("disabled", false).html('<i class="bi bi-receipt"></i> Genera ricevuta');
            } else {
              $posStatus.text(msg).removeClass("text-muted text-success").addClass("text-danger");
              $posBtn.prop("disabled", false).html('<i class="bi bi-credit-card"></i> Riprova POS');
              $f24Btn.prop("disabled", false).html('<i class="bi bi-receipt"></i> Genera ricevuta');
            }
          })
          .catch((error) => {
            console.error(error);
            $posStatus.text("Errore di connessione col POS").removeClass("text-muted text-success").addClass("text-danger");
            $posBtn.prop("disabled", false).html('<i class="bi bi-credit-card"></i> Riprova POS');
            $f24Btn.prop("disabled", false).html('<i class="bi bi-receipt"></i> Genera ricevuta');
          });
      }

      function bindReceiptButton($btn) {
        $btn
          .off("click")
          .on("click", function () {
            const appointmentId = $("#modal-close-actions").data("appointment-id");
            const amount = getCurrentAmount();

            if (!amount || amount <= 0) {
              alert("Importo non valido per la ricevuta");
              return;
            }

            $btn.prop("disabled", true).html('<i class="bi bi-receipt"></i> Invio...');

            const formData = new FormData();
            formData.append("action", "totemsport_f24_receipt");
            formData.append("appointment_id", appointmentId);
            formData.append("amount", amount.toFixed(2));

            fetch(totemsportAjax.ajaxurl, {
              credentials: "same-origin",
              method: "POST",
              body: formData,
            })
              .then((resp) => resp.json())
              .then((data) => {
                if (data.success) {
                  const pdfUrl = data.pdf_url || data.data?.pdf_url || null;
                  if (pdfUrl) {
                    // Mostra link di stampa/visualizzazione nella modale
                    let $pdfLink = $("#f24-receipt-link");
                    if ($pdfLink.length === 0) {
                      $pdfLink = $('<a id="f24-receipt-link" class="btn btn-outline-primary mt-2" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Visualizza/Stampa Ricevuta PDF</a>');
                      $btn.closest(".pay-block, .payment-section").append($pdfLink);
                    }
                    $pdfLink.attr("href", pdfUrl).show();
                  } else {
                    alert("Ricevuta creata su Fattura24, ma PDF non disponibile dalla risposta.");
                  }
                  alert(data.message || "Ricevuta creata su Fattura24");
                  $btn.html('<i class="bi bi-receipt"></i> Ricevuta inviata');
                } else {
                  const msg = data.message || data.data?.message || "Errore generazione ricevuta";
                  alert(msg);
                  $btn.prop("disabled", false).html('<i class="bi bi-receipt"></i> Genera ricevuta');
                }
              })
              .catch((error) => {
                console.error(error);
                alert("Errore di rete nella generazione ricevuta");
                $btn.prop("disabled", false).html('<i class="bi bi-receipt"></i> Genera ricevuta');
              });
          });
      }

      // Invio pagamento POS
      $posBtn.off("click").on("click", sendPosPayment);

      // Genera ricevuta Fattura24 (POS o contanti)
      bindReceiptButton($f24Btn);
      bindReceiptButton($f24BtnCash);
    }

    // Filtro staff - Delegato
    function attach_staff_filter() {
      $(document).off("click", ".filter-staff").on("click", ".filter-staff", function () {
        selectedStaffFilter = $(this).data("staff");
        currentPage = 1;
        applyFiltersAndPagination();
        applyCompletedPaginationAndDedup();
        $(".filter-staff").removeClass("active");
        $(this).addClass("active");
      });
    }

    function attachSortHandler() {
      $(document).off("click.sortOra").on("click.sortOra", "#sort-ora", function () {
        sortDirection = sortDirection === "asc" ? "desc" : "asc";
        const $el = $(this);
        $el.attr("data-direction", sortDirection).addClass("active");

        const icon = $el.find(".sort-icon");
        if (icon.length) {
          icon.attr("class", sortDirection === "asc" ? "bi bi-arrow-down sort-icon" : "bi bi-arrow-up sort-icon");
        }

        currentPage = 1;
        applyFiltersAndPagination();
      });
    }

    function applyFiltersAndPagination() {
      const rows = Array.from(document.querySelectorAll("#app-bo-tbody tr"));
      const term = (searchInput?.value || "").trim().toLowerCase();

      const filtered = rows.filter((row) => {
        const staffAttr = row.getAttribute("data-staff") || "";
        if (selectedStaffFilter !== "all") {
          const staffIds = staffAttr.split(",").map((id) => id.trim());
          if (!staffIds.includes(String(selectedStaffFilter))) return false;
        }

        if (!term) return true;
        const cells = row.querySelectorAll("td");
        const nome = (cells[2]?.textContent || "").toLowerCase();
        const cognome = (cells[3]?.textContent || "").toLowerCase();
        const cf = (row.getAttribute("data-cf") || "").toLowerCase();
        return nome.includes(term) || cognome.includes(term) || cf.includes(term);
      });

      filtered.sort((a, b) => {
        const oraA = (a.querySelectorAll("td")[4]?.textContent || "").trim();
        const oraB = (b.querySelectorAll("td")[4]?.textContent || "").trim();

        // Confronto orario robusto: converti HH:MM in minuti per evitare problemi di locale
        const toMinutes = (val) => {
          const parts = val.split(":");
          if (parts.length < 2) return Number.MAX_SAFE_INTEGER; // push empty/invalid to bottom
          const h = parseInt(parts[0], 10);
          const m = parseInt(parts[1], 10);
          if (isNaN(h) || isNaN(m)) return Number.MAX_SAFE_INTEGER;
          return h * 60 + m;
        };

        const minutesA = toMinutes(oraA);
        const minutesB = toMinutes(oraB);

        if (minutesA === minutesB) return 0;
        const cmp = minutesA < minutesB ? -1 : 1;
        return sortDirection === "asc" ? cmp : -cmp;
      });

      const tbody = document.getElementById("app-bo-tbody");
      if (tbody) {
        // Riordina fisicamente i nodi secondo l'ordinamento corrente
        filtered.forEach((row) => tbody.appendChild(row));
      }

      rows.forEach((row) => (row.style.display = "none"));

      const totalPages = Math.max(1, Math.ceil(filtered.length / rowsPerPage));
      if (currentPage > totalPages) currentPage = totalPages;

      const start = (currentPage - 1) * rowsPerPage;
      const end = start + rowsPerPage;
      filtered.slice(start, end).forEach((row) => (row.style.display = ""));

      renderPagination(totalPages);
      $(".filter-staff").removeClass("active");
      $(`.filter-staff[data-staff="${selectedStaffFilter}"]`).addClass("active");
    }

    function renderPagination(totalPages) {
      if (!paginationContainer) return;
      if (totalPages <= 1) {
        paginationContainer.innerHTML = "";
        return;
      }

      let html = "";
      for (let p = 1; p <= totalPages; p++) {
        const activeClass = p === currentPage ? "active" : "";
        html += `<button type="button" class="btn btn-outline-primary btn-sm page-btn ${activeClass}" data-page="${p}">${p}</button>`;
      }
      paginationContainer.innerHTML = html;

      $(".page-btn")
        .off("click")
        .on("click", function () {
          const page = parseInt($(this).data("page"), 10) || 1;
          currentPage = page;
          applyFiltersAndPagination();
        });
    }

    if (searchInput) {
      searchInput.addEventListener("input", () => {
        currentPage = 1;
        applyFiltersAndPagination();
        applyCompletedPaginationAndDedup();
      });
    }
  });
})(jQuery);

// Funzione per decodificare base64 UTF-8
function decodeBase64Utf8(base64) {
  const binary = atob(base64);
  const bytes = Uint8Array.from(binary, (c) => c.charCodeAt(0));
  return new TextDecoder("utf-8").decode(bytes);
}

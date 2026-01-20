// TotemSport Public JS
(function ($) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("totemsport-cf-search");
    const suggestions = document.getElementById("totemsport-cf-suggestions");
    let timeout = null;

    var selected_value = null;
    if (input) {
      input.addEventListener("input", function () {
        clearTimeout(timeout);
        selected_value = null; // Reset selection on typing

        const query = input.value.trim();

        // Se la query è troppo corta, pulisco suggerimenti e nascondo pulsante
        if (query.length < 3) {
          suggestions.innerHTML = "";
          const prenota_div = document.getElementById("prenota_ora");
          if (prenota_div) prenota_div.style.display = "none";
          const nextButton = document.getElementById("totemsport-next");
          if (nextButton) nextButton.style.display = "none";
          return;
        }

        // Debounce 300ms
        timeout = setTimeout(function () {
          fetch(
            `${
              totemsportAjax.ajaxurl
            }?action=totemsport_cf_search&term=${encodeURIComponent(query)}`,
            {
              credentials: "same-origin",
            }
          )
            .then((response) => response.json())
            .then((data) => {
              suggestions.innerHTML = "";

              const prenota_div = document.getElementById("prenota_ora");
              const nextButton = document.getElementById("totemsport-next");
              if (prenota_div) prenota_div.style.display = "none";

              if (Array.isArray(data) && data.length == 1) {
                const ul = document.createElement("ul");
                ul.style.background = "#fff";
                ul.style.border = "1px solid #ccc";
                ul.style.margin = 0;
                ul.style.padding = "4px";
                ul.style.listStyle = "none";
                ul.style.position = "absolute";
                ul.style.width = input.offsetWidth + "px";

                data.forEach((item) => {
                  console.log("Aggiungo suggerimento:", item);
                  const li = document.createElement("li");
                  li.textContent = item.label; // testo
                  li.style.color = "#000";
                  li.style.padding = "6px 10px";
                  li.style.cursor = "pointer";
                  li.style.display = "flex";
                  li.style.justifyContent = "space-between";
                  li.style.alignItems = "center";

                  // freccia Unicode a destra
                  const arrow = document.createElement("span");
                  arrow.textContent = "➔";
                  li.appendChild(arrow);

                  li.addEventListener("mousedown", function () {
                    input.value = item.label;
                    selected_value = item.value;
                    suggestions.innerHTML = "";
                    if (nextButton) {
                      nextButton.style.display = "block";
                      nextButton.setAttribute("data-idappuntamento", item.value);
                    }
                  });
                  ul.appendChild(li);
                  suggestions.appendChild(ul);
                });
              } else {
                // Nessun risultato → mostra "Prenota Ora"
                if (prenota_div) prenota_div.style.display = "block";
                if (nextButton) nextButton.style.display = "none";
                const corpo_tabella = document.getElementById("corpo_tabella");
                corpo_tabella.innerHTML = ""; // Pulisci la tabella prima di aggiungere nuovi risultati
                data.forEach((item) => {
                  if (corpo_tabella) {
                    const tr = document.createElement("tr");
                    const td_label = document.createElement("td");

                    td_label.setAttribute("data-label", "Paziente");

                    let nome = item.label;
                    let cf = "";

                    // Separa CF e nome usando " - "
                    if (item.label && item.label.includes(" - ")) {
                      const parts = item.label.split(" - ", 2);
                      cf = parts[0].trim();
                      nome = parts[1].trim();
                    }

                    // Nome
                    const nomeDiv = document.createElement("div");
                    nomeDiv.className = "totemsport-paziente-nome";
                    nomeDiv.textContent = nome;
                    td_label.appendChild(nomeDiv);

                    // Codice Fiscale (se presente)
                    if (cf) {
                      const cfDiv = document.createElement("div");
                      cfDiv.className = "totemsport-paziente-cf";
                      cfDiv.textContent = cf.toUpperCase();
                      td_label.appendChild(cfDiv);
                    }

                    const td_value = document.createElement("td");
                    //aggiungo un button nella cella
                    const button = document.createElement("button");
                    button.textContent = "Seleziona";
                    button.classList.add(
                      "btn",
                      "btn-primary",
                      "totemsport-next"
                    );
                    button.setAttribute("data-idappuntamento", item.value);

                    td_value.appendChild(button);
                    tr.appendChild(td_label);
                    tr.appendChild(td_value);
                    corpo_tabella.appendChild(tr);
                  }
                });
              }
            })
            .catch((err) => {
              console.error("Errore nella ricerca CF:", err);
            });
        }, 300);
      });
    }

    // Gestisco la variabile urine nello step 4

    const urine_options = document.querySelectorAll(
      'input[name="urine_radio"], select[name="urine"]'
    );
    var urine = "no";

    if (urine_options && urine_options.length > 0) {
      urine_options.forEach(function (element) {
        element.addEventListener("change", function () {
          urine = element.value;
          //alert(urine)
          const conferma_last = document.getElementById("conferma_last");
          if (conferma_last) {
            // Aggiorna l'URL con il parametro urine
            const url = new URL(conferma_last.href);
            url.searchParams.set("urine", urine);
            conferma_last.href = url.toString();
          }
        });
      });
    }

    // Previeni doppi click su conferma_last
    const conferma_last = document.getElementById("conferma_last");
    if (conferma_last) {
        conferma_last.addEventListener("click", function (e) {
            if (conferma_last.classList.contains("disabled")) {
                e.preventDefault();
                return;
            }
            conferma_last.classList.add("disabled");
            conferma_last.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Attendere...';
        });
    }

    // Gestione centralizzata dei pulsanti "Prossimo" e "Seleziona"
    $(document).on("click", ".totemsport-next", function (e) {
      e.preventDefault();
      const idappuntamento = $(this).attr("data-idappuntamento");

      if (!idappuntamento) {
        alert("Necessario un codice fiscale prima di procedere.");
        return;
      }

      fetch(
        totemsportAjax.ajaxurl +
          "?action=totemsport_get_app_data&idappuntamento=" +
          idappuntamento,
        {
          credentials: "same-origin",
        }
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Apro il modale con i dati dell'appuntamento
            $("#appointmentModalBody").html(data.html);
            $("#appointmentModal").modal("show");

            // Gestione del pulsante di conferma nel modale (usiamo .off() per evitare duplicati)
            $("#conferma_appuntamento").off("click").on("click", function () {
              const cf = data.dati.cf || "";
              const minor = $("input[name=\"is_minor\"]:checked").val();

              if (minor) {
                let url = "/totem/?step=2" +
                          "&cf=" + encodeURIComponent(cf) +
                          "&idappuntamento=" + encodeURIComponent(idappuntamento) +
                          "&minor=" + minor;

                if (data.dati.is_guest) {
                  url += "&first_name=" + encodeURIComponent(data.dati.first_name || "");
                  url += "&last_name=" + encodeURIComponent(data.dati.last_name || "");
                }

                window.location.href = url;
              } else {
                $(".alert-minor-required").remove();
                const alertHtml =
                  '<div class="alert alert-danger alert-dismissible fade show alert-minor-required" role="alert">' +
                  "Per favore, indica se l'appuntamento è per un figlio minorenne." +
                  '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                  "</div>";
                $("#appointmentModalBody").prepend(alertHtml);
              }
            });
          } else {
            alert(data.message || "Errore durante la ricerca dell'appuntamento.");
          }
        })
        .catch((error) => {
          console.error("Errore:", error);
          alert("Si è verificato un errore durante l'elaborazione.");
        });
    });
  });
})(jQuery);

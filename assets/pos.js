// Esempio: chiusura sessione POS Nexi
jQuery(document).on('click', '#btn-close-session', function() {
    jQuery.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'totemsport_pos_close_session'
        },
        success: function(resp) {
            if (resp.success) {
                alert('Sessione chiusa!\nRisposta: ' + JSON.stringify(resp.data));
            } else {
                alert('Errore: ' + (resp.data && resp.data.message ? resp.data.message : '')); 
            }
        },
        error: function() {
            alert('Errore di comunicazione con il server');
        }
    });
});

// Esempio: abilita/disabilita stampa ricevuta su ECR
jQuery(document).on('click', '#btn-ecr-print-enable', function() {
    jQuery.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'totemsport_pos_set_ecr_print',
            enable: 1 // 1=abilita, 0=disabilita
        },
        success: function(resp) {
            if (resp.success) {
                alert(resp.data.message);
            } else {
                alert('Errore: ' + (resp.data && resp.data.message ? resp.data.message : ''));
            }
        },
        error: function() {
            alert('Errore di comunicazione con il server');
        }
    });
});

// Utility: mostra messaggio colorato
function showPosMessage(msg, isSuccess) {
    var el = document.getElementById('pos-payment-message');
    if (!el) {
        el = document.createElement('div');
        el.id = 'pos-payment-message';
        el.style.margin = '10px 0';
        el.style.fontWeight = 'bold';
        document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.color = isSuccess ? 'green' : 'red';
}

// Esempio: pagamento POS Nexi (adatta l'ID del bottone se serve)
jQuery(document).on('click', '#btn-pos-payment', function() {
    // ... raccogli dati necessari ...
    var amount_cents = 100; // esempio
    jQuery.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'totemsport_pos_payment',
            amount_cents: amount_cents,
            payment_method: 'pos'
        },
        success: function(resp) {
            if (resp.success) {
                showPosMessage(resp.result && resp.result.message ? resp.result.message : 'Pagamento eseguito', true);
            } else {
                showPosMessage(resp.data && resp.data.message ? resp.data.message : 'Pagamento negato', false);
            }
        },
        error: function() {
            showPosMessage('Errore di comunicazione con il server', false);
        }
    });
});

{**
 * Reenvío de la confirmación desde la ficha del pedido. Sale por el mismo camino que el envío
 * original —los hooks del correo— así que el cliente recibe exactamente lo que vería hoy.
 *}
<div class="card mt-2 bkmd-resend" id="bkmd-resend">
  <div class="card-header"><i class="material-icons">mail</i> {l s='Order confirmation' d='Modules.Bkmaildesigner.Admin'}</div>
  <div class="card-body">
    <p class="mb-2">{l s='Send it again to' d='Modules.Bkmaildesigner.Admin'} <strong>{$bkmd_to|escape:'htmlall':'UTF-8'}</strong>{if $bkmd_lang} · {$bkmd_lang|escape:'htmlall':'UTF-8'}{/if}</p>
    <button type="button" class="btn btn-outline-primary" data-bkmd-resend>
      <i class="material-icons">send</i> {l s='Resend the confirmation' d='Modules.Bkmaildesigner.Admin'}
    </button>
    <span class="bkmd-resend__status" data-bkmd-resend-status></span>
  </div>
</div>
<script>
(function () {
  'use strict';
  var box = document.getElementById('bkmd-resend');
  var button = box.querySelector('[data-bkmd-resend]');
  var status = box.querySelector('[data-bkmd-resend-status]');
  var words = {
    sending: "{l s='Sending…' d='Modules.Bkmaildesigner.Admin' js=1}",
    sent: "{l s='Sent' d='Modules.Bkmaildesigner.Admin' js=1}",
    failed: "{l s='It could not be sent. Check the shop email settings.' d='Modules.Bkmaildesigner.Admin' js=1}",
    confirm: "{l s='Send the order confirmation to the customer again?' d='Modules.Bkmaildesigner.Admin' js=1}"
  };

  button.addEventListener('click', function () {
    if (!window.confirm(words.confirm)) { return; }
    var body = new FormData();
    body.append('action', 'BkResendOrder');
    body.append('id_order', '{$bkmd_order|intval}');
    button.disabled = true;
    status.className = 'bkmd-resend__status';
    status.textContent = words.sending;
    fetch("{$bkmd_url|escape:'javascript':'UTF-8' nofilter}", { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (out) {
        button.disabled = false;
        var ok = out && out.ok;
        status.className = 'bkmd-resend__status ' + (ok ? 'is-ok' : 'is-error');
        status.textContent = ok ? words.sent : words.failed;
      })
      .catch(function () {
        button.disabled = false;
        status.className = 'bkmd-resend__status is-error';
        status.textContent = words.failed;
      });
  });
}());
</script>
<style>
.bkmd-resend__status { margin-left: 10px; font-size: 13px; }
.bkmd-resend__status.is-ok { color: #37714a; }
.bkmd-resend__status.is-error { color: #a3352f; }
</style>

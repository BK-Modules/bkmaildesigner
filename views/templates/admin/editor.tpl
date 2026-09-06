{**
 * Editor de una plantilla: lista de bloques a la izquierda, vista previa en vivo en el centro y
 * las propiedades del bloque activo a la derecha. Cabecera y pie no se editan aquí: son del layout.
 * Todo el comportamiento vive en admin.js; esta plantilla solo pinta el armazón y los datos.
 *}
<script>var bkmdBase = {$bkmd_base nofilter}; var bkmdEditor = {$bkmd_editor nofilter};</script>
<div class="panel bkmd bkmd-editor" data-bkmd-editor>
  <div class="bkmd-editor__top">
    <a class="btn btn-default" href="{$bkmd_back|escape:'htmlall':'UTF-8'}"><i class="icon-arrow-left"></i> {l s='Emails' d='Modules.Bkmaildesigner.Admin'}</a>
    <h3 class="bkmd-editor__title">{if $bkmd_tpl.module !== ''}<span class="bkmd-editor__module">{$bkmd_tpl.module|escape:'htmlall':'UTF-8'} /</span> {/if}<code>{$bkmd_tpl.name|escape:'htmlall':'UTF-8'}</code></h3>
    <div class="btn-group bkmd-seg" data-bkmd-mode>
      <button type="button" class="btn btn-default" data-mode="original">{l s='Unchanged' d='Modules.Bkmaildesigner.Admin'}</button>
      <button type="button" class="btn btn-default" data-mode="wrapped">{l s='Header and footer' d='Modules.Bkmaildesigner.Admin'}</button>
      <button type="button" class="btn btn-default" data-mode="designed">{l s='Full design' d='Modules.Bkmaildesigner.Admin'}</button>
    </div>
    <div class="bkmd-langtabs bkmd-langtabs--editor" data-bkmd-editor-langs></div>
    <span class="bkmd-editor__spacer"></span>
    <span class="bkmd-editor__status" data-bkmd-status></span>
    <button type="button" class="btn btn-default" data-bkmd-test><i class="icon-paper-plane"></i> {l s='Send test' d='Modules.Bkmaildesigner.Admin'}</button>
    <button type="button" class="btn btn-primary" data-bkmd-save><i class="icon-save"></i> {l s='Save' d='Modules.Bkmaildesigner.Admin'}</button>
  </div>

  <div class="bkmd-editor__subject">
    <label for="bkmd-subject">{l s='Subject' d='Modules.Bkmaildesigner.Admin'}</label>
    <input type="text" id="bkmd-subject" class="form-control" data-bkmd-subject placeholder="">
    <span class="help-block" data-bkmd-subject-hint>{l s='Empty keeps the subject PrestaShop uses.' d='Modules.Bkmaildesigner.Admin'}</span>
  </div>

  <div class="bkmd-editor__body">
    <aside class="bkmd-editor__left">
      <div class="bkmd-panel" data-bkmd-designed-only>
        <div class="bkmd-panel__hd">{l s='Blocks' d='Modules.Bkmaildesigner.Admin'}</div>
        <div class="bkmd-palette" data-bkmd-palette></div>
      </div>
      <div class="bkmd-panel" data-bkmd-designed-only>
        <div class="bkmd-panel__hd">{l s='Content' d='Modules.Bkmaildesigner.Admin'}</div>
        <div class="bkmd-inherit" data-bkmd-inherit hidden></div>
        <ol class="bkmd-blocklist" data-bkmd-blocklist></ol>
        <div class="bkmd-panel__ft">
          {if $bkmd_tpl.preset}<button type="button" class="btn btn-default btn-xs" data-bkmd-preset><i class="icon-download"></i> {l s='Load built-in content' d='Modules.Bkmaildesigner.Admin'}</button>{/if}
          <button type="button" class="btn btn-default btn-xs" data-bkmd-copy-ref hidden><i class="icon-copy"></i> {l s='Copy from reference' d='Modules.Bkmaildesigner.Admin'}</button>
          <button type="button" class="btn btn-link btn-xs" data-bkmd-reset>{l s='Start over' d='Modules.Bkmaildesigner.Admin'}</button>
        </div>
      </div>
      <div class="bkmd-panel">
        <div class="bkmd-panel__hd">{l s='Variables' d='Modules.Bkmaildesigner.Admin'}</div>
        <div class="bkmd-vars" data-bkmd-vars>
          {foreach from=$bkmd_var_groups item=group}
            <div class="bkmd-vargroup">
              <div class="bkmd-vargroup__hd">{$bkmd_group_names[$group.key]|escape:'htmlall':'UTF-8'}</div>
              <div class="bkmd-vargroup__chips">
                {foreach from=$group.rows item=row}
                  <span class="bkmd-varwrap">
                    <button type="button" class="bkmd-var{if $group.key === 'shop'} bkmd-var--global{/if}{if $row.unknown} bkmd-var--unknown{/if}" data-var="{$row.name|escape:'htmlall':'UTF-8'}" title="{if $row.html}{l s='built by PrestaShop' d='Modules.Bkmaildesigner.Admin'}{else}{$row.value|escape:'htmlall':'UTF-8'|truncate:80}{/if}">{literal}{{/literal}{$row.name|escape:'htmlall':'UTF-8'}{literal}}{/literal}</button>
                    {if $group.key === 'custom'}<button type="button" class="bkmd-var__del" data-bkmd-delvar="{$row.name|escape:'htmlall':'UTF-8'}" title="{l s='Remove' d='Modules.Bkmaildesigner.Admin'}">&times;</button>{/if}
                  </span>
                {/foreach}
              </div>
            </div>
          {/foreach}
        </div>
        <div class="bkmd-addvar">
          <button type="button" class="bkmd-addvar__toggle" data-bkmd-addvar-toggle>+ {l s='Add a variable' d='Modules.Bkmaildesigner.Admin'}</button>
          <div class="bkmd-addvar__form" data-bkmd-addvar-form hidden>
            <input type="text" autocomplete="off" class="form-control" data-bkmd-addvar-name placeholder="{l s='name, without braces' d='Modules.Bkmaildesigner.Admin'}">
            <input type="text" autocomplete="off" class="form-control" data-bkmd-addvar-value placeholder="{l s='what to fill it with while testing' d='Modules.Bkmaildesigner.Admin'}">
            <button type="button" class="btn btn-default btn-sm" data-bkmd-addvar-save>{l s='Add' d='Modules.Bkmaildesigner.Admin'}</button>
          </div>
        </div>
        <p class="help-block">{l s='Only the ones this email really receives. Click one to insert it at the cursor; hover to see what it is filled with. If a module sends one that is not listed, add it by hand.' d='Modules.Bkmaildesigner.Admin'}</p>
      </div>

      {* Con qué se rellena cada variable mientras se prueba. Los correos de un módulo llevan
         variables que este módulo no puede adivinar; aquí se les pone un valor a mano. *}
      <div class="bkmd-panel">
        <div class="bkmd-panel__hd">{l s='Test values' d='Modules.Bkmaildesigner.Admin'}</div>
        <div class="bkmd-samples">
          {foreach from=$bkmd_var_groups item=group}
            {if true}
              <div class="bkmd-vargroup__hd">{$bkmd_group_names[$group.key]|escape:'htmlall':'UTF-8'}</div>
              {foreach from=$group.rows item=row}
                <div class="bkmd-sample{if $row.unknown} is-unknown{/if}">
                  <label for="bkmd-s-{$row.name|escape:'htmlall':'UTF-8'}"><code>{literal}{{/literal}{$row.name|escape:'htmlall':'UTF-8'}{literal}}{/literal}</code></label>
                  {if $row.html}
                    <span class="bkmd-sample__html">{l s='built by PrestaShop' d='Modules.Bkmaildesigner.Admin'}</span>
                  {else}
                    <input type="text" autocomplete="off" id="bkmd-s-{$row.name|escape:'htmlall':'UTF-8'}" class="form-control" data-bkmd-sample="{$row.name|escape:'htmlall':'UTF-8'}" value="{$row.value|escape:'htmlall':'UTF-8'}">
                  {/if}
                </div>
              {/foreach}
            {/if}
          {/foreach}
        </div>
        <p class="help-block">{l s='Only for the preview and the test email; a real send always uses real data. Leave one empty to go back to the automatic value.' d='Modules.Bkmaildesigner.Admin'}</p>
      </div>
    </aside>

    <section class="bkmd-editor__center">
      <div class="bkmd-preview-bar">
        <div class="btn-group bkmd-device" data-bkmd-device>
          <button type="button" class="btn btn-default active" data-device="desktop"><i class="icon-desktop"></i></button>
          <button type="button" class="btn btn-default" data-device="mobile"><i class="icon-mobile"></i></button>
        </div>
        <div class="btn-group" data-bkmd-type>
          <button type="button" class="btn btn-default active" data-type="html">HTML</button>
          <button type="button" class="btn btn-default" data-type="txt">{l s='Plain text' d='Modules.Bkmaildesigner.Admin'}</button>
        </div>
        <span class="bkmd-preview-note" data-bkmd-preview-note></span>
      </div>
      <div class="bkmd-frame bkmd-frame--editor" data-bkmd-frame>
        <iframe title="{l s='Preview' d='Modules.Bkmaildesigner.Admin'}" sandbox="allow-same-origin"></iframe>
      </div>
    </section>

    <aside class="bkmd-editor__right" data-bkmd-designed-only>
      <div class="bkmd-panel">
        <div class="bkmd-panel__hd" data-bkmd-props-title>{l s='Block' d='Modules.Bkmaildesigner.Admin'}</div>
        <div class="bkmd-props" data-bkmd-props>
          <p class="help-block">{l s='Select a block to edit it.' d='Modules.Bkmaildesigner.Admin'}</p>
        </div>
      </div>
    </aside>
  </div>
</div>

<div class="bkmd-modal" data-bkmd-test-modal hidden>
  <div class="bkmd-modal__box bkmd-modal__box--small">
    <div class="bkmd-modal__bar"><strong>{l s='Send a test email' d='Modules.Bkmaildesigner.Admin'}</strong><span class="bkmd-editor__spacer"></span><button type="button" class="btn btn-default" data-bkmd-test-close><i class="icon-remove"></i></button></div>
    <div class="bkmd-modal__body">
      <p>{l s='The email goes through the same path as a real one, with the last real order and sample data. Unsaved changes are saved first.' d='Modules.Bkmaildesigner.Admin'}</p>
      <label for="bkmd-test-email">{l s='Recipient' d='Modules.Bkmaildesigner.Admin'}</label>
      <input type="email" id="bkmd-test-email" class="form-control" value="{$bkmd_test_email|escape:'htmlall':'UTF-8'}">
      <div class="bkmd-actions"><button type="button" class="btn btn-primary" data-bkmd-test-send><i class="icon-paper-plane"></i> {l s='Send now' d='Modules.Bkmaildesigner.Admin'}</button></div>
    </div>
  </div>
</div>

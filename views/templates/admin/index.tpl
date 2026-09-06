{**
 * Pantalla principal: correos, diseño del layout, guía de uso, ajustes y el panel de BK Modules.
 *
 * Las pestañas son propias y no dependen de ningún componente del back office: el menú es el mismo
 * en 1.7.6 y en 9.x, que son back offices distintos. La pestaña abierta viaja en el ancla, así que
 * al guardar se vuelve a la que se estaba tocando.
 *}
<script>var bkmdBase = {$bkmd_base nofilter};</script>
<div class="panel bkmd">
  <div class="panel-heading">
    <i class="icon-envelope"></i> {l s='BK Mail Designer' d='Modules.Bkmaildesigner.Admin'}
    <span class="panel-heading-action bkmd-heading-state">
      {if $bkmd_v.BK_MD_ON}<span class="badge badge-success">{l s='Active' d='Modules.Bkmaildesigner.Admin'}</span>
      {else}<span class="badge badge-danger">{l s='Switched off' d='Modules.Bkmaildesigner.Admin'}</span>{/if}
    </span>
  </div>

  <nav class="bkmd-nav" role="tablist">
    <a class="bkmd-nav__item" href="#bkmd-tab-templates" role="tab"><i class="icon-list"></i> {l s='Emails' d='Modules.Bkmaildesigner.Admin'} <span class="bkmd-nav__count">{$bkmd_counts.total|intval}</span></a>
    <a class="bkmd-nav__item" href="#bkmd-tab-layout" role="tab"><i class="icon-paint-brush"></i> {l s='Design' d='Modules.Bkmaildesigner.Admin'}</a>
    <a class="bkmd-nav__item" href="#bkmd-tab-guide" role="tab"><i class="icon-book"></i> {l s='How it works' d='Modules.Bkmaildesigner.Admin'}</a>
    <a class="bkmd-nav__item" href="#bkmd-tab-settings" role="tab"><i class="icon-cogs"></i> {l s='Settings' d='Modules.Bkmaildesigner.Admin'}</a>
    <a class="bkmd-nav__item" href="#bkmd-tab-more" role="tab"><i class="icon-puzzle-piece"></i> {l s='More from BK Modules' d='Modules.Bkmaildesigner.Admin'}</a>
  </nav>

  <div class="tab-content">

    {* ---------------- correos ---------------- *}
    <div class="tab-pane active" id="bkmd-tab-templates">
      {$bkmd_update_notice nofilter}

      <div class="bkmd-kpis">
        <div class="bkmd-kpi">
          <div class="bkmd-kpi__k">{l s='Emails' d='Modules.Bkmaildesigner.Admin'}</div>
          <div class="bkmd-kpi__v">{$bkmd_counts.total|intval}</div>
          <div class="bkmd-kpi__s">{l s='%core% from PrestaShop · %modules% from modules' d='Modules.Bkmaildesigner.Admin' sprintf=['%core%' => $bkmd_counts.core, '%modules%' => $bkmd_counts.modules]}</div>
        </div>
        <div class="bkmd-kpi bkmd-kpi--designed">
          <div class="bkmd-kpi__k">{l s='Full design' d='Modules.Bkmaildesigner.Admin'}</div>
          <div class="bkmd-kpi__v">{$bkmd_counts.designed|intval}</div>
          <div class="bkmd-kpi__s">{l s='Header, footer and body written by you' d='Modules.Bkmaildesigner.Admin'}</div>
        </div>
        <div class="bkmd-kpi bkmd-kpi--wrapped">
          <div class="bkmd-kpi__k">{l s='Header and footer' d='Modules.Bkmaildesigner.Admin'}</div>
          <div class="bkmd-kpi__v">{$bkmd_counts.wrapped|intval}</div>
          <div class="bkmd-kpi__s">{l s='Original text, your header and your footer' d='Modules.Bkmaildesigner.Admin'}</div>
        </div>
        <div class="bkmd-kpi {if $bkmd_counts.untranslated}bkmd-kpi--warn{/if}">
          <div class="bkmd-kpi__k">{l s='Missing a translation' d='Modules.Bkmaildesigner.Admin'}</div>
          <div class="bkmd-kpi__v">{$bkmd_counts.untranslated|intval}</div>
          <div class="bkmd-kpi__s">{l s='Sent in %lang% until you write them' d='Modules.Bkmaildesigner.Admin' sprintf=['%lang%' => $bkmd_ref_lang_name]}</div>
        </div>
      </div>

      {* Puesta en marcha: al instalar, los correos ya salen con la marca; el primer paso es mirarlo,
         no encender nada. Cada paso lleva al sitio donde se hace. *}
      <ol class="bkmd-steps" data-bkmd-steps>
        <li class="bkmd-step">
          <span class="bkmd-step__n">1</span>
          <strong class="bkmd-step__t">{l s='Your emails already carry your brand' d='Modules.Bkmaildesigner.Admin'}</strong>
          <span class="bkmd-step__d">{l s='Every email in this list leaves with your header and footer from the moment you install the module. Open the preview of any of them to see it.' d='Modules.Bkmaildesigner.Admin'}</span>
        </li>
        <li class="bkmd-step">
          <span class="bkmd-step__n">2</span>
          <strong class="bkmd-step__t">{l s='Set your brand once' d='Modules.Bkmaildesigner.Admin'}</strong>
          <span class="bkmd-step__d">{l s='Colours, header and footer are shared by every email. You only fill this in once.' d='Modules.Bkmaildesigner.Admin'}</span>
          <a class="bkmd-step__go" href="#bkmd-tab-layout">{l s='Design' d='Modules.Bkmaildesigner.Admin'} &rsaquo;</a>
        </li>
        <li class="bkmd-step">
          <span class="bkmd-step__n">3</span>
          <strong class="bkmd-step__t">{l s='Rewrite the ones your customer actually reads' d='Modules.Bkmaildesigner.Admin'}</strong>
          <span class="bkmd-step__d">{l s='Order, account and password. The module ships them already written and translated: load the built-in content and change what you want.' d='Modules.Bkmaildesigner.Admin'}</span>
          <button type="button" class="bkmd-step__go bkmd-step__btn" data-bkmd-bulk="preset">{l s='Use the built-in content everywhere' d='Modules.Bkmaildesigner.Admin'} &rsaquo;</button>
        </li>
        <li class="bkmd-step">
          <span class="bkmd-step__n">4</span>
          <strong class="bkmd-step__t">{l s='Send yourself a test' d='Modules.Bkmaildesigner.Admin'}</strong>
          <span class="bkmd-step__d">{l s='Before you leave it running, send one to your inbox and open it on your phone. It travels exactly like a real one.' d='Modules.Bkmaildesigner.Admin'}</span>
        </li>
      </ol>

      <div class="bkmd-toolbar">
        <input type="search" class="form-control bkmd-search" placeholder="{l s='Search by name or subject' d='Modules.Bkmaildesigner.Admin'}" data-bkmd-search>
        <select class="form-control bkmd-origin" data-bkmd-origin>
          <option value="">{l s='Every source' d='Modules.Bkmaildesigner.Admin'}</option>
          {foreach from=$bkmd_sources item=source}
            <option value="{$source.module|escape:'htmlall':'UTF-8'}">{$source.name|escape:'htmlall':'UTF-8'} ({$source.count|intval})</option>
          {/foreach}
        </select>
        <div class="btn-group bkmd-filter" data-bkmd-filter>
          <button type="button" class="btn btn-default active" data-filter="">{l s='Any state' d='Modules.Bkmaildesigner.Admin'}</button>
          <button type="button" class="btn btn-default" data-filter="state:original">{l s='Unchanged' d='Modules.Bkmaildesigner.Admin'}</button>
          <button type="button" class="btn btn-default" data-filter="state:wrapped">{l s='Header and footer' d='Modules.Bkmaildesigner.Admin'}</button>
          <button type="button" class="btn btn-default" data-filter="state:designed">{l s='Full design' d='Modules.Bkmaildesigner.Admin'}</button>
        </div>
        <span class="bkmd-toolbar__spacer"></span>
        <button type="button" class="btn btn-default" data-bkmd-bulk="restore"><i class="icon-undo"></i> {l s='Restore all as they came' d='Modules.Bkmaildesigner.Admin'}</button>
        <button type="button" class="btn btn-default" data-bkmd-bulk="preset"><i class="icon-download"></i> {l s='Use the built-in content everywhere' d='Modules.Bkmaildesigner.Admin'}</button>
      </div>

      <div class="bkmd-selbar" data-bkmd-selbar hidden>
        <span class="bkmd-selbar__count" data-bkmd-selcount></span>
        <span class="bkmd-selbar__label">{l s='Send them with:' d='Modules.Bkmaildesigner.Admin'}</span>
        <div class="btn-group">
          <button type="button" class="btn btn-default" data-bkmd-setmode="original">{l s='Unchanged' d='Modules.Bkmaildesigner.Admin'}</button>
          <button type="button" class="btn btn-default" data-bkmd-setmode="wrapped">{l s='Header and footer' d='Modules.Bkmaildesigner.Admin'}</button>
          <button type="button" class="btn btn-default" data-bkmd-setmode="designed">{l s='Full design' d='Modules.Bkmaildesigner.Admin'}</button>
        </div>
        <button type="button" class="btn btn-default" data-bkmd-setmode="restore"><i class="icon-undo"></i> {l s='How it came' d='Modules.Bkmaildesigner.Admin'}</button>
        <span class="bkmd-toolbar__spacer"></span>
        <button type="button" class="btn btn-link" data-bkmd-selnone>{l s='Clear selection' d='Modules.Bkmaildesigner.Admin'}</button>
      </div>

      <div class="table-responsive">
        <table class="table bkmd-table">
          <thead>
            <tr>
              <th class="bkmd-col-pick"><input type="checkbox" data-bkmd-selall title="{l s='Select all' d='Modules.Bkmaildesigner.Admin'}"></th>
              <th>{l s='Email' d='Modules.Bkmaildesigner.Admin'}</th>
              <th>{l s='Subject' d='Modules.Bkmaildesigner.Admin'}</th>
              <th class="bkmd-col-audience">{l s='Goes to' d='Modules.Bkmaildesigner.Admin'}</th>
              <th>{l s='Mode' d='Modules.Bkmaildesigner.Admin'}</th>
              <th>{l s='Languages' d='Modules.Bkmaildesigner.Admin'}</th>
              <th class="bkmd-col-date">{l s='Updated' d='Modules.Bkmaildesigner.Admin'}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {assign var=group value='__none__'}
            {foreach from=$bkmd_list item=row}
              {if $row.module !== $group}
                {assign var=group value=$row.module}
                <tr class="bkmd-group" data-group="{$row.module|escape:'htmlall':'UTF-8'}">
                  <td colspan="8">{if $row.module === ''}{l s='PrestaShop core' d='Modules.Bkmaildesigner.Admin'}{else}<i class="icon-puzzle-piece"></i> {$row.module|escape:'htmlall':'UTF-8'}{/if}</td>
                </tr>
              {/if}
              <tr class="bkmd-row" data-key="{$row.key|escape:'htmlall':'UTF-8'}" data-module="{$row.module|escape:'htmlall':'UTF-8'}" data-name="{$row.name|escape:'htmlall':'UTF-8'}" data-state="{$row.state|escape:'htmlall':'UTF-8'}" data-audience="{$row.audience|escape:'htmlall':'UTF-8'}" data-search="{$row.name|escape:'htmlall':'UTF-8'} {$row.subject|escape:'htmlall':'UTF-8'}">
                <td class="bkmd-cell-pick"><input type="checkbox" data-bkmd-pick></td>
                <td class="bkmd-cell-name" data-label="{l s='Email' d='Modules.Bkmaildesigner.Admin'}"><code>{$row.name|escape:'htmlall':'UTF-8'}</code></td>
                <td class="bkmd-cell-subject" data-label="{l s='Subject' d='Modules.Bkmaildesigner.Admin'}">{$row.subject|escape:'htmlall':'UTF-8'}</td>
                <td class="bkmd-cell-audience" data-label="{l s='Goes to' d='Modules.Bkmaildesigner.Admin'}">
                  {if $row.audience === 'merchant'}<span class="bkmd-tag bkmd-tag--merchant">{l s='You' d='Modules.Bkmaildesigner.Admin'}</span>
                  {else}<span class="bkmd-tag bkmd-tag--customer">{l s='Whoever receives it' d='Modules.Bkmaildesigner.Admin'}</span>{/if}
                </td>
                <td class="bkmd-cell-mode" data-label="{l s='Mode' d='Modules.Bkmaildesigner.Admin'}">
                  <select class="bkmd-mode" data-bkmd-mode>
                    <option value="original"{if $row.state === 'original'} selected{/if}>{l s='Unchanged' d='Modules.Bkmaildesigner.Admin'}</option>
                    <option value="wrapped"{if $row.state === 'wrapped'} selected{/if}>{l s='Header and footer' d='Modules.Bkmaildesigner.Admin'}</option>
                    <option value="designed"{if $row.state === 'designed'} selected{/if}>{l s='Full design' d='Modules.Bkmaildesigner.Admin'}</option>
                  </select>
                  {if $row.state !== $row.default_mode}
                    <button type="button" class="bkmd-advice" data-bkmd-advice="{$row.default_mode|escape:'htmlall':'UTF-8'}" title="{if $row.default_reason === 'has_preset'}{l s='This email came with the full design because the module ships its text written and translated.' d='Modules.Bkmaildesigner.Admin'}{else}{l s='This email came with your header and footer because the module ships no text for it.' d='Modules.Bkmaildesigner.Admin'}{/if}">
                      <i class="icon-undo"></i> {l s='It came as' d='Modules.Bkmaildesigner.Admin'} {if $row.default_mode === 'designed'}{l s='Full design' d='Modules.Bkmaildesigner.Admin'}{elseif $row.default_mode === 'wrapped'}{l s='Header and footer' d='Modules.Bkmaildesigner.Admin'}{else}{l s='Unchanged' d='Modules.Bkmaildesigner.Admin'}{/if}
                    </button>
                  {/if}
                </td>
                <td class="bkmd-cell-langs" data-label="{l s='Languages' d='Modules.Bkmaildesigner.Admin'}">
                  {foreach from=$row.langs item=lang}
                    <span class="bkmd-dot bkmd-dot--{$lang.state|escape:'htmlall':'UTF-8'}" title="{$lang.name|escape:'htmlall':'UTF-8'}: {if $lang.state === 'own'}{l s='own content' d='Modules.Bkmaildesigner.Admin'}{elseif $lang.state === 'inherited'}{l s='inherits from the reference language' d='Modules.Bkmaildesigner.Admin'}{elseif $lang.state === 'file'}{l s='original file' d='Modules.Bkmaildesigner.Admin'}{else}{l s='no file for this language' d='Modules.Bkmaildesigner.Admin'}{/if}">{$lang.iso|escape:'htmlall':'UTF-8'}</span>
                  {/foreach}
                </td>
                <td class="bkmd-cell-date" data-label="{l s='Updated' d='Modules.Bkmaildesigner.Admin'}">{if $row.date_upd && $row.date_upd !== '0000-00-00 00:00:00'}{$row.date_upd|date_format:'%d/%m/%Y'}{else}&mdash;{/if}</td>
                <td class="bkmd-cell-actions">
                  <a class="btn btn-default btn-xs" href="{$row.edit_url|escape:'htmlall':'UTF-8'}"><i class="icon-pencil"></i> <span>{l s='Edit' d='Modules.Bkmaildesigner.Admin'}</span></a>
                  <button type="button" class="btn btn-default btn-xs" data-bkmd-preview><i class="icon-eye"></i> <span>{l s='Preview' d='Modules.Bkmaildesigner.Admin'}</span></button>
                  <button type="button" class="btn btn-default btn-xs" data-bkmd-row-test><i class="icon-paper-plane"></i> <span>{l s='Test' d='Modules.Bkmaildesigner.Admin'}</span></button>
                </td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>

      <p class="bkmd-legend">
        <span class="bkmd-dot bkmd-dot--own">ES</span> {l s='own content' d='Modules.Bkmaildesigner.Admin'}
        <span class="bkmd-dot bkmd-dot--inherited">ES</span> {l s='inherits from the reference language' d='Modules.Bkmaildesigner.Admin'}
        <span class="bkmd-dot bkmd-dot--file">ES</span> {l s='original file' d='Modules.Bkmaildesigner.Admin'}
        <span class="bkmd-dot bkmd-dot--missing">ES</span> {l s='no file for this language' d='Modules.Bkmaildesigner.Admin'}
      </p>
    </div>

    {* ---------------- diseño ---------------- *}
    <div class="tab-pane" id="bkmd-tab-layout">
      <fieldset class="bkmd-fieldset bkmd-fieldset--themes">
        <legend>{l s='Start from a theme' d='Modules.Bkmaildesigner.Admin'}</legend>
        <p class="help-block">{l s='Each preview below is a real email of your shop, rendered with that theme. Click one to apply it: your logo and your footer texts are kept, and everything below stays editable.' d='Modules.Bkmaildesigner.Admin'}</p>
        <div class="bkmd-themes" data-bkmd-themes data-tpl="{$bkmd_theme_tpl.module|escape:'htmlall':'UTF-8'}/{$bkmd_theme_tpl.name|escape:'htmlall':'UTF-8'}">
          {foreach from=$bkmd_themes item=theme}
            <figure class="bkmd-theme{if $theme.key === $bkmd_theme_current} is-current{/if}" data-theme="{$theme.key|escape:'htmlall':'UTF-8'}">
              <div class="bkmd-theme__shot">
                <iframe title="{$theme.name|escape:'htmlall':'UTF-8'}" sandbox="allow-same-origin" loading="lazy"></iframe>
                <span class="bkmd-theme__veil"></span>
                <span class="bkmd-theme__apply">{l s='Use this theme' d='Modules.Bkmaildesigner.Admin'}</span>
              </div>
              <figcaption>
                <span class="bkmd-theme__name">{$theme.name|escape:'htmlall':'UTF-8'}{if $theme.key === $bkmd_theme_current} <span class="bkmd-theme__badge">{l s='in use' d='Modules.Bkmaildesigner.Admin'}</span>{/if}</span>
                <span class="bkmd-theme__desc">{$theme.description|escape:'htmlall':'UTF-8'}</span>
              </figcaption>
            </figure>
          {/foreach}
        </div>
        <p class="help-block bkmd-theme__note">{l s='A theme is the look. The words are separate:' d='Modules.Bkmaildesigner.Admin'}
          <button type="button" class="btn btn-default btn-xs" data-bkmd-bulk="preset"><i class="icon-download"></i> {l s='Use the built-in content everywhere' d='Modules.Bkmaildesigner.Admin'}</button>
        </p>
      </fieldset>

      <form id="bkmd-layout-form" method="post" action="{$bkmd_action|escape:'htmlall':'UTF-8'}#bkmd-tab-layout" class="bkmd-layout">
        <input type="hidden" name="submitBkLayout" value="1">
        <div class="bkmd-layout__fields">
          <p class="bkmd-intro">{l s='What you set here is shared by every email in the list. The preview on the right updates as you type.' d='Modules.Bkmaildesigner.Admin'}</p>

          <nav class="bkmd-subnav" data-bkmd-subnav>
            <button type="button" data-pane="brand" class="is-active">{l s='Brand' d='Modules.Bkmaildesigner.Admin'}</button>
            <button type="button" data-pane="type">{l s='Typography' d='Modules.Bkmaildesigner.Admin'}</button>
            <button type="button" data-pane="frame">{l s='Frame' d='Modules.Bkmaildesigner.Admin'}</button>
            <button type="button" data-pane="header">{l s='Header' d='Modules.Bkmaildesigner.Admin'}</button>
            <button type="button" data-pane="body">{l s='Body' d='Modules.Bkmaildesigner.Admin'}</button>
            <button type="button" data-pane="order">{l s='Order lines' d='Modules.Bkmaildesigner.Admin'}</button>
            <button type="button" data-pane="footer">{l s='Footer' d='Modules.Bkmaildesigner.Admin'}</button>
            <button type="button" data-pane="advanced">{l s='Advanced' d='Modules.Bkmaildesigner.Admin'}</button>
          </nav>

          {* ---------- marca ---------- *}
          <div class="bkmd-subpane" data-pane="brand">
          <fieldset class="bkmd-fieldset">
            <legend>{l s='Brand' d='Modules.Bkmaildesigner.Admin'}</legend>
            <p class="bkmd-panehint">{l s='What is yours and shows up in all of them: the logo and the three colours the whole email is built from.' d='Modules.Bkmaildesigner.Admin'}</p>
            <div class="bkmd-field">
              <label>{l s='Logo' d='Modules.Bkmaildesigner.Admin'}</label>
              <div class="bkmd-logo-preview"><img src="{$bkmd_logo|escape:'htmlall':'UTF-8'}" alt=""></div>
              <p class="help-block">{l s='The email logo of your shop, embedded by PrestaShop so it shows in Outlook without downloading images.' d='Modules.Bkmaildesigner.Admin'} <a href="{$bkmd_logo_link|escape:'htmlall':'UTF-8'}" target="_blank">{l s='Change it in Design › Theme & Logo' d='Modules.Bkmaildesigner.Admin'}</a></p>
            </div>
            <div class="bkmd-grid2">
              <div class="bkmd-field">
                <label for="bkmd-logo-width">{l s='Logo width' d='Modules.Bkmaildesigner.Admin'}</label>
                <div class="bkmd-unit">
                  <input type="number" id="bkmd-logo-width" name="layout[logo_width]" class="form-control" min="60" max="400" value="{$bkmd_layout.logo_width|intval}">
                  <span class="bkmd-unit__suffix">px</span>
                </div>
              </div>
              <div class="bkmd-field">
                <label for="bkmd-logo-url">{l s='Alternative logo URL' d='Modules.Bkmaildesigner.Admin'}</label>
                <input type="url" id="bkmd-logo-url" name="layout[logo_url]" class="form-control" value="{$bkmd_layout.logo_url|escape:'htmlall':'UTF-8'}" placeholder="https://">
              </div>
            </div>
            <div class="bkmd-colors">
              {foreach from=['color_primary' => 'Brand colour', 'color_text' => 'Text', 'color_muted' => 'Secondary text'] key=k item=lbl}
                <label class="bkmd-color">
                  <input type="color" name="layout[{$k}]" value="{$bkmd_layout.$k|escape:'htmlall':'UTF-8'}">
                  <span>{l s=$lbl d='Modules.Bkmaildesigner.Admin'}</span>
                </label>
              {/foreach}
            </div>
            <p class="help-block">{l s='The brand colour paints the buttons and the links. The band and the header have their own colour in Frame and in Header.' d='Modules.Bkmaildesigner.Admin'}</p>
          </fieldset>
          </div>

          {* ---------- tipografía ---------- *}
          <div class="bkmd-subpane" data-pane="type" hidden>
          <fieldset class="bkmd-fieldset">
            <legend>{l s='Typography' d='Modules.Bkmaildesigner.Admin'}</legend>
            <p class="bkmd-panehint">{l s='The two typefaces and how headings behave. Everything that goes in capitals is decided here.' d='Modules.Bkmaildesigner.Admin'}</p>
            <p class="help-block">{l s='Fonts every email client has installed. Web fonts are not offered on purpose: Outlook and Gmail ignore them.' d='Modules.Bkmaildesigner.Admin'}</p>
            <div class="bkmd-grid2">
              <div class="bkmd-field">
                <label for="bkmd-font-heading">{l s='Headings' d='Modules.Bkmaildesigner.Admin'}</label>
                <select id="bkmd-font-heading" name="layout[font_heading]" class="form-control">
                  <option value=""{if $bkmd_layout.font_heading === ''} selected{/if}>{l s='Same as the body' d='Modules.Bkmaildesigner.Admin'}</option>
                  {foreach from=$bkmd_fonts key=k item=stack}<option value="{$k}"{if $bkmd_layout.font_heading === $k} selected{/if}>{$stack|escape:'htmlall':'UTF-8'}</option>{/foreach}
                </select>
              </div>
              <div class="bkmd-field">
                <label for="bkmd-font">{l s='Body' d='Modules.Bkmaildesigner.Admin'}</label>
                <select id="bkmd-font" name="layout[font]" class="form-control">
                  {foreach from=$bkmd_fonts key=k item=stack}<option value="{$k}"{if $bkmd_layout.font === $k} selected{/if}>{$stack|escape:'htmlall':'UTF-8'}</option>{/foreach}
                </select>
              </div>
              <div class="bkmd-field">
                <label for="bkmd-hsize">{l s='Heading size' d='Modules.Bkmaildesigner.Admin'}</label>
                <input type="number" id="bkmd-hsize" name="layout[heading_size]" class="form-control" min="15" max="40" value="{$bkmd_layout.heading_size|intval}">
              </div>
              <div class="bkmd-field">
                <label for="bkmd-bsize">{l s='Body size' d='Modules.Bkmaildesigner.Admin'}</label>
                <input type="number" id="bkmd-bsize" name="layout[body_size]" class="form-control" min="12" max="18" value="{$bkmd_layout.body_size|intval}">
              </div>
              <div class="bkmd-field">
                <label for="bkmd-lead">{l s='Line height' d='Modules.Bkmaildesigner.Admin'}</label>
                <input type="number" id="bkmd-lead" name="layout[body_leading]" class="form-control" min="130" max="200" step="5" value="{$bkmd_layout.body_leading|intval}">
              </div>
              <div class="bkmd-field">
                <label for="bkmd-hspacing">{l s='Heading letter spacing' d='Modules.Bkmaildesigner.Admin'}</label>
                <input type="number" id="bkmd-hspacing" name="layout[heading_spacing]" class="form-control" min="0" max="12" value="{$bkmd_layout.heading_spacing|intval}">
              </div>
            </div>
            <div class="bkmd-field">
              <label>{l s='Heading style' d='Modules.Bkmaildesigner.Admin'}</label>
              <div class="btn-group bkmd-seg">
                <label class="btn btn-default{if $bkmd_layout.heading_weight === 'bold'} active{/if}"><input type="radio" name="layout[heading_weight]" value="bold"{if $bkmd_layout.heading_weight === 'bold'} checked{/if}> {l s='Bold' d='Modules.Bkmaildesigner.Admin'}</label>
                <label class="btn btn-default{if $bkmd_layout.heading_weight === 'normal'} active{/if}"><input type="radio" name="layout[heading_weight]" value="normal"{if $bkmd_layout.heading_weight === 'normal'} checked{/if}> {l s='Regular' d='Modules.Bkmaildesigner.Admin'}</label>
              </div>
              <div class="btn-group bkmd-seg">
                <label class="btn btn-default{if $bkmd_layout.heading_case === 'none'} active{/if}"><input type="radio" name="layout[heading_case]" value="none"{if $bkmd_layout.heading_case === 'none'} checked{/if}> {l s='As written' d='Modules.Bkmaildesigner.Admin'}</label>
                <label class="btn btn-default{if $bkmd_layout.heading_case === 'upper'} active{/if}"><input type="radio" name="layout[heading_case]" value="upper"{if $bkmd_layout.heading_case === 'upper'} checked{/if}> {l s='Capitals' d='Modules.Bkmaildesigner.Admin'}</label>
              </div>
            </div>
            <div class="bkmd-field">
              <label>{l s='Buttons' d='Modules.Bkmaildesigner.Admin'}</label>
              <div class="btn-group bkmd-seg">
                <label class="btn btn-default{if $bkmd_layout.button_case === 'none'} active{/if}"><input type="radio" name="layout[button_case]" value="none"{if $bkmd_layout.button_case === 'none'} checked{/if}> {l s='As written' d='Modules.Bkmaildesigner.Admin'}</label>
                <label class="btn btn-default{if $bkmd_layout.button_case === 'upper'} active{/if}"><input type="radio" name="layout[button_case]" value="upper"{if $bkmd_layout.button_case === 'upper'} checked{/if}> {l s='Capitals' d='Modules.Bkmaildesigner.Admin'}</label>
              </div>
              <div class="btn-group bkmd-seg">
                <label class="btn btn-default{if $bkmd_layout.button_weight === 'bold'} active{/if}"><input type="radio" name="layout[button_weight]" value="bold"{if $bkmd_layout.button_weight === 'bold'} checked{/if}> {l s='Bold' d='Modules.Bkmaildesigner.Admin'}</label>
                <label class="btn btn-default{if $bkmd_layout.button_weight === 'normal'} active{/if}"><input type="radio" name="layout[button_weight]" value="normal"{if $bkmd_layout.button_weight === 'normal'} checked{/if}> {l s='Regular' d='Modules.Bkmaildesigner.Admin'}</label>
              </div>
            </div>
          </fieldset>
          </div>

          {* ---------- marco ---------- *}
          <div class="bkmd-subpane" data-pane="frame" hidden>
          <fieldset class="bkmd-fieldset">
            <legend>{l s='Frame' d='Modules.Bkmaildesigner.Admin'}</legend>
            <p class="bkmd-panehint">{l s='The paper the email is printed on: how wide it is, how much air it has and what surrounds it.' d='Modules.Bkmaildesigner.Admin'}</p>
            <div class="bkmd-grid2">
              <div class="bkmd-field">
                <label for="bkmd-width">{l s='Email width' d='Modules.Bkmaildesigner.Admin'}</label>
                <div class="bkmd-unit">
                  <input type="number" id="bkmd-width" name="layout[width]" class="form-control" min="480" max="680" step="10" value="{$bkmd_layout.width|intval}">
                  <span class="bkmd-unit__suffix">px</span>
                </div>
              </div>
              <div class="bkmd-field">
                <label for="bkmd-space">{l s='Air' d='Modules.Bkmaildesigner.Admin'}</label>
                <select id="bkmd-space" name="layout[space]" class="form-control">
                  <option value="tight"{if $bkmd_layout.space === 'tight'} selected{/if}>{l s='Tight' d='Modules.Bkmaildesigner.Admin'}</option>
                  <option value="normal"{if $bkmd_layout.space === 'normal'} selected{/if}>{l s='Normal' d='Modules.Bkmaildesigner.Admin'}</option>
                  <option value="roomy"{if $bkmd_layout.space === 'roomy'} selected{/if}>{l s='Roomy' d='Modules.Bkmaildesigner.Admin'}</option>
                </select>
              </div>
              <div class="bkmd-field">
                <label for="bkmd-card-radius">{l s='Card corners' d='Modules.Bkmaildesigner.Admin'}</label>
                <input type="number" id="bkmd-card-radius" name="layout[card_radius]" class="form-control" min="0" max="30" value="{$bkmd_layout.card_radius|intval}">
              </div>
              <div class="bkmd-field">
                <label for="bkmd-band-height">{l s='Band thickness' d='Modules.Bkmaildesigner.Admin'}</label>
                <div class="bkmd-unit">
                  <input type="number" id="bkmd-band-height" name="layout[band_height]" class="form-control" min="2" max="16" value="{$bkmd_layout.band_height|intval}">
                  <span class="bkmd-unit__suffix">px</span>
                </div>
              </div>
            </div>
            <div class="bkmd-colors">
              {foreach from=['color_outer' => 'Outer background', 'color_card' => 'Card', 'color_band' => 'Top band'] key=k item=lbl}
                <label class="bkmd-color">
                  <input type="color" name="layout[{$k}]" value="{$bkmd_layout.$k|escape:'htmlall':'UTF-8'}">
                  <span>{l s=$lbl d='Modules.Bkmaildesigner.Admin'}</span>
                </label>
              {/foreach}
            </div>
            <div class="bkmd-field bkmd-field--check">
              <label><input type="hidden" name="layout[top_band]" value="0"><input type="checkbox" name="layout[top_band]" value="1"{if $bkmd_layout.top_band} checked{/if}> {l s='Colour band on top' d='Modules.Bkmaildesigner.Admin'}</label>
            </div>
          </fieldset>
          </div>

          {* ---------- cabecera ---------- *}
          <div class="bkmd-subpane" data-pane="header" hidden>
          <fieldset class="bkmd-fieldset">
            <legend>{l s='Header' d='Modules.Bkmaildesigner.Admin'}</legend>
            <p class="bkmd-panehint">{l s='The first thing anyone sees: the logo, the links next to it and the line that closes it.' d='Modules.Bkmaildesigner.Admin'}</p>
            <div class="bkmd-field">
              <label>{l s='Layout' d='Modules.Bkmaildesigner.Admin'}</label>
              <div class="btn-group bkmd-seg">
                <label class="btn btn-default{if $bkmd_layout.header_style === 'center'} active{/if}"><input type="radio" name="layout[header_style]" value="center"{if $bkmd_layout.header_style === 'center'} checked{/if}> {l s='Centred logo' d='Modules.Bkmaildesigner.Admin'}</label>
                <label class="btn btn-default{if $bkmd_layout.header_style === 'left'} active{/if}"><input type="radio" name="layout[header_style]" value="left"{if $bkmd_layout.header_style === 'left'} checked{/if}> {l s='Logo and links in a row' d='Modules.Bkmaildesigner.Admin'}</label>
                <label class="btn btn-default{if $bkmd_layout.header_style === 'band'} active{/if}"><input type="radio" name="layout[header_style]" value="band"{if $bkmd_layout.header_style === 'band'} checked{/if}> {l s='On a colour' d='Modules.Bkmaildesigner.Admin'}</label>
              </div>
            </div>
            <div class="bkmd-colors">
              {foreach from=['color_header_bg' => 'Header background', 'color_header_text' => 'Header text'] key=k item=lbl}
                <label class="bkmd-color">
                  <input type="color" name="layout[{$k}]" value="{$bkmd_layout.$k|escape:'htmlall':'UTF-8'}">
                  <span>{l s=$lbl d='Modules.Bkmaildesigner.Admin'}</span>
                </label>
              {/foreach}
            </div>
            <p class="help-block">{l s='Only used by «On a colour». With a dark background give the logo a light version in Brand.' d='Modules.Bkmaildesigner.Admin'}</p>
            {foreach from=$bkmd_layout_langs item=lang}
              <div class="bkmd-langpane" data-lang="{$lang.id|intval}"{if $lang.id != $bkmd_context_lang} hidden{/if}>
                <div class="bkmd-field">
                  <label>{l s='Header links' d='Modules.Bkmaildesigner.Admin'} <span class="bkmd-langtag">{$lang.iso|escape:'htmlall':'UTF-8'|upper}</span></label>
                  <div class="bkmd-links" data-bkmd-links="header_links[{$lang.id|intval}]" data-links='{$lang.header_links|json_encode|escape:'htmlall':'UTF-8'}'></div>
                </div>
              </div>
            {/foreach}
            <div class="bkmd-field bkmd-field--check">
              <label><input type="hidden" name="layout[header_rule]" value="0"><input type="checkbox" name="layout[header_rule]" value="1"{if $bkmd_layout.header_rule} checked{/if}> {l s='Line under the header' d='Modules.Bkmaildesigner.Admin'}</label>
            </div>
          </fieldset>
          </div>

          {* ---------- cuerpo ---------- *}
          <div class="bkmd-subpane" data-pane="body" hidden>
          <fieldset class="bkmd-fieldset">
            <legend>{l s='Body' d='Modules.Bkmaildesigner.Admin'}</legend>
            <p class="bkmd-panehint">{l s='What the blocks look like by default. Every email can change it block by block in its own editor.' d='Modules.Bkmaildesigner.Admin'}</p>
            <div class="bkmd-field">
              <label>{l s='Button' d='Modules.Bkmaildesigner.Admin'}</label>
              <div class="btn-group bkmd-seg">
                <label class="btn btn-default{if $bkmd_layout.button_style === 'solid'} active{/if}"><input type="radio" name="layout[button_style]" value="solid"{if $bkmd_layout.button_style === 'solid'} checked{/if}> {l s='Filled' d='Modules.Bkmaildesigner.Admin'}</label>
                <label class="btn btn-default{if $bkmd_layout.button_style === 'outline'} active{/if}"><input type="radio" name="layout[button_style]" value="outline"{if $bkmd_layout.button_style === 'outline'} checked{/if}> {l s='Outlined' d='Modules.Bkmaildesigner.Admin'}</label>
              </div>
              <div class="btn-group bkmd-seg">
                <label class="btn btn-default{if $bkmd_layout.button_size === 's'} active{/if}"><input type="radio" name="layout[button_size]" value="s"{if $bkmd_layout.button_size === 's'} checked{/if}> S</label>
                <label class="btn btn-default{if $bkmd_layout.button_size === 'm'} active{/if}"><input type="radio" name="layout[button_size]" value="m"{if $bkmd_layout.button_size === 'm'} checked{/if}> M</label>
                <label class="btn btn-default{if $bkmd_layout.button_size === 'l'} active{/if}"><input type="radio" name="layout[button_size]" value="l"{if $bkmd_layout.button_size === 'l'} checked{/if}> L</label>
              </div>
            </div>
            <div class="bkmd-grid2">
              <div class="bkmd-field">
                <label for="bkmd-btn-radius">{l s='Button corners' d='Modules.Bkmaildesigner.Admin'}</label>
                <input type="number" id="bkmd-btn-radius" name="layout[button_radius]" class="form-control" min="0" max="30" value="{$bkmd_layout.button_radius|intval}">
              </div>
              <div class="bkmd-field">
                <label for="bkmd-box">{l s='Highlight box' d='Modules.Bkmaildesigner.Admin'}</label>
                <select id="bkmd-box" name="layout[box_style]" class="form-control">
                  <option value="fill"{if $bkmd_layout.box_style === 'fill'} selected{/if}>{l s='Filled' d='Modules.Bkmaildesigner.Admin'}</option>
                  <option value="bar"{if $bkmd_layout.box_style === 'bar'} selected{/if}>{l s='With a coloured bar' d='Modules.Bkmaildesigner.Admin'}</option>
                  <option value="outline"{if $bkmd_layout.box_style === 'outline'} selected{/if}>{l s='Outlined' d='Modules.Bkmaildesigner.Admin'}</option>
                </select>
              </div>
              <div class="bkmd-field">
                <label for="bkmd-rule">{l s='Divider' d='Modules.Bkmaildesigner.Admin'}</label>
                <select id="bkmd-rule" name="layout[rule_style]" class="form-control">
                  <option value="solid"{if $bkmd_layout.rule_style === 'solid'} selected{/if}>{l s='Hairline' d='Modules.Bkmaildesigner.Admin'}</option>
                  <option value="dotted"{if $bkmd_layout.rule_style === 'dotted'} selected{/if}>{l s='Dotted' d='Modules.Bkmaildesigner.Admin'}</option>
                  <option value="thick"{if $bkmd_layout.rule_style === 'thick'} selected{/if}>{l s='Thick, in the brand colour' d='Modules.Bkmaildesigner.Admin'}</option>
                </select>
              </div>
            </div>
            <div class="bkmd-field bkmd-field--check">
              <label><input type="hidden" name="layout[button_full]" value="0"><input type="checkbox" name="layout[button_full]" value="1"{if $bkmd_layout.button_full} checked{/if}> {l s='Button across the full width' d='Modules.Bkmaildesigner.Admin'}</label>
            </div>
          </fieldset>
          </div>

          {* ---------- líneas del pedido ---------- *}
          <div class="bkmd-subpane" data-pane="order" hidden>
          <fieldset class="bkmd-fieldset">
            <legend>{l s='Order lines' d='Modules.Bkmaildesigner.Admin'}</legend>
            <p class="bkmd-panehint">{l s='They only show up in the order confirmation and in the new order alert. This is the default: each of those emails can change it in its own editor.' d='Modules.Bkmaildesigner.Admin'}</p>
            <div class="bkmd-grid">
              <div class="bkmd-field">
                <label for="bkmd-order-layout">{l s='Presentation' d='Modules.Bkmaildesigner.Admin'}</label>
                <select id="bkmd-order-layout" name="layout[order_layout]" class="form-control">
                  <option value="stacked"{if $bkmd_layout.order_layout === 'stacked'} selected{/if}>{l s='One line per product, reads on a phone' d='Modules.Bkmaildesigner.Admin'}</option>
                  <option value="table"{if $bkmd_layout.order_layout === 'table'} selected{/if}>{l s='Five columns, like PrestaShop' d='Modules.Bkmaildesigner.Admin'}</option>
                </select>
              </div>
              <div class="bkmd-field">
                <label for="bkmd-line-style">{l s='Line style' d='Modules.Bkmaildesigner.Admin'}</label>
                <select id="bkmd-line-style" name="layout[line_style]" class="form-control">
                  <option value="plain"{if $bkmd_layout.line_style === 'plain'} selected{/if}>{l s='Separated by a hairline' d='Modules.Bkmaildesigner.Admin'}</option>
                  <option value="zebra"{if $bkmd_layout.line_style === 'zebra'} selected{/if}>{l s='Alternating background' d='Modules.Bkmaildesigner.Admin'}</option>
                  <option value="boxed"{if $bkmd_layout.line_style === 'boxed'} selected{/if}>{l s='Each product in its own box' d='Modules.Bkmaildesigner.Admin'}</option>
                </select>
              </div>
              <div class="bkmd-field">
                <label for="bkmd-image-size">{l s='Photo size' d='Modules.Bkmaildesigner.Admin'}</label>
                <input type="number" id="bkmd-image-size" name="layout[order_image_size]" class="form-control" min="32" max="120" step="4" value="{$bkmd_layout.order_image_size|intval}">
              </div>
            </div>
            <div class="bkmd-field bkmd-field--check">
              <label><input type="hidden" name="layout[order_image]" value="0"><input type="checkbox" name="layout[order_image]" value="1"{if $bkmd_layout.order_image} checked{/if}> {l s='Product photo' d='Modules.Bkmaildesigner.Admin'}</label>
              <label><input type="hidden" name="layout[order_reference]" value="0"><input type="checkbox" name="layout[order_reference]" value="1"{if $bkmd_layout.order_reference} checked{/if}> {l s='Reference' d='Modules.Bkmaildesigner.Admin'}</label>
              <label><input type="hidden" name="layout[order_options]" value="0"><input type="checkbox" name="layout[order_options]" value="1"{if $bkmd_layout.order_options} checked{/if}> {l s='Combination and customisations' d='Modules.Bkmaildesigner.Admin'}</label>
              <label><input type="hidden" name="layout[order_unit]" value="0"><input type="checkbox" name="layout[order_unit]" value="1"{if $bkmd_layout.order_unit} checked{/if}> {l s='Quantity and unit price' d='Modules.Bkmaildesigner.Admin'}</label>
            </div>
            {if $bkmd_order_emails}
              <p class="help-block">{l s='Change it for one email only:' d='Modules.Bkmaildesigner.Admin'}
                {foreach from=$bkmd_order_emails item=row name=oe}<a href="{$row.url|escape:'htmlall':'UTF-8'}">{$row.name|escape:'htmlall':'UTF-8'}</a>{if !$smarty.foreach.oe.last} · {/if}{/foreach}
              </p>
            {/if}
          </fieldset>
          </div>

          {* ---------- pie ---------- *}
          <div class="bkmd-subpane" data-pane="footer" hidden>
          <fieldset class="bkmd-fieldset">
            <legend>{l s='Footer' d='Modules.Bkmaildesigner.Admin'}</legend>
            <p class="bkmd-panehint">{l s='It closes the email: its colours, its links, your social networks and the two lines of small print.' d='Modules.Bkmaildesigner.Admin'}</p>
            <div class="bkmd-colors">
              {foreach from=['color_footer_bg' => 'Footer background', 'color_footer_text' => 'Footer text'] key=k item=lbl}
                <label class="bkmd-color">
                  <input type="color" name="layout[{$k}]" value="{$bkmd_layout.$k|escape:'htmlall':'UTF-8'}">
                  <span>{l s=$lbl d='Modules.Bkmaildesigner.Admin'}</span>
                </label>
              {/foreach}
            </div>
            <div class="bkmd-field bkmd-field--check">
              <label><input type="hidden" name="layout[shop_name_in_footer]" value="0"><input type="checkbox" name="layout[shop_name_in_footer]" value="1"{if $bkmd_layout.shop_name_in_footer} checked{/if}> {l s='Shop name linked to the home page' d='Modules.Bkmaildesigner.Admin'}</label>
              <label><input type="hidden" name="layout[footer_rule]" value="0"><input type="checkbox" name="layout[footer_rule]" value="1"{if $bkmd_layout.footer_rule} checked{/if}> {l s='Line above the footer' d='Modules.Bkmaildesigner.Admin'}</label>
            </div>

            <div class="bkmd-field">
              <label>{l s='Social networks' d='Modules.Bkmaildesigner.Admin'}</label>
              <p class="help-block">{l s='Written once, not per language: an Instagram page does not change with the language of the buyer.' d='Modules.Bkmaildesigner.Admin'}</p>
              <div class="bkmd-social" data-bkmd-social="layout[social]" data-rows='{$bkmd_layout.social|json_encode|escape:'htmlall':'UTF-8'}' data-networks='{$bkmd_networks|json_encode|escape:'htmlall':'UTF-8'}'></div>
            </div>
            <div class="bkmd-grid">
              <div class="bkmd-field">
                <label for="bkmd-social-style">{l s='Icon style' d='Modules.Bkmaildesigner.Admin'}</label>
                <select id="bkmd-social-style" name="layout[social_style]" class="form-control">
                  <option value="color"{if $bkmd_layout.social_style === 'color'} selected{/if}>{l s='Each network in its own colour' d='Modules.Bkmaildesigner.Admin'}</option>
                  <option value="brand"{if $bkmd_layout.social_style === 'brand'} selected{/if}>{l s='All in your brand colour' d='Modules.Bkmaildesigner.Admin'}</option>
                  <option value="plain"{if $bkmd_layout.social_style === 'plain'} selected{/if}>{l s='No background' d='Modules.Bkmaildesigner.Admin'}</option>
                </select>
              </div>
              <div class="bkmd-field">
                <label for="bkmd-social-shape">{l s='Icon shape' d='Modules.Bkmaildesigner.Admin'}</label>
                <select id="bkmd-social-shape" name="layout[social_shape]" class="form-control">
                  <option value="circle"{if $bkmd_layout.social_shape === 'circle'} selected{/if}>{l s='Round' d='Modules.Bkmaildesigner.Admin'}</option>
                  <option value="square"{if $bkmd_layout.social_shape === 'square'} selected{/if}>{l s='Rounded square' d='Modules.Bkmaildesigner.Admin'}</option>
                </select>
              </div>
              <div class="bkmd-field">
                <label for="bkmd-social-size">{l s='Icon size' d='Modules.Bkmaildesigner.Admin'}</label>
                <div class="bkmd-unit">
                  <input type="number" id="bkmd-social-size" name="layout[social_size]" class="form-control" min="20" max="48" step="2" value="{$bkmd_layout.social_size|intval}">
                  <span class="bkmd-unit__suffix">px</span>
                </div>
              </div>
            </div>

            {foreach from=$bkmd_layout_langs item=lang}
              <div class="bkmd-langpane" data-lang="{$lang.id|intval}"{if $lang.id != $bkmd_context_lang} hidden{/if}>
                <div class="bkmd-field">
                  <label>{l s='Footer links' d='Modules.Bkmaildesigner.Admin'} <span class="bkmd-langtag">{$lang.iso|escape:'htmlall':'UTF-8'|upper}</span></label>
                  <div class="bkmd-links" data-bkmd-links="footer_links[{$lang.id|intval}]" data-links='{$lang.footer_links|json_encode|escape:'htmlall':'UTF-8'}'></div>
                </div>
                <div class="bkmd-field">
                  <label for="bkmd-legal-{$lang.id|intval}">{l s='Legal line' d='Modules.Bkmaildesigner.Admin'} <span class="bkmd-langtag">{$lang.iso|escape:'htmlall':'UTF-8'|upper}</span></label>
                  <textarea id="bkmd-legal-{$lang.id|intval}" name="footer_legal[{$lang.id|intval}]" class="form-control" rows="2" placeholder="{l s='Company name · Address · VAT number' d='Modules.Bkmaildesigner.Admin'}">{$lang.footer_legal|escape:'htmlall':'UTF-8'}</textarea>
                </div>
                <div class="bkmd-field">
                  <label>{l s='Why they receive this email' d='Modules.Bkmaildesigner.Admin'} <span class="bkmd-langtag">{$lang.iso|escape:'htmlall':'UTF-8'|upper}</span></label>
                  <p class="help-block">{l s='There is no way to know whether the person has an account —an order can be from a guest—, so the line does not claim it. Leave a box empty and that line does not appear.' d='Modules.Bkmaildesigner.Admin'}</p>
                  <label for="bkmd-reason-{$lang.id|intval}" class="bkmd-sublabel">{l s='Emails to whoever receives them' d='Modules.Bkmaildesigner.Admin'}</label>
                  <textarea id="bkmd-reason-{$lang.id|intval}" name="footer_reason[{$lang.id|intval}][recipient]" class="form-control" rows="2" placeholder="{l s='You receive this email because this address was used at {shop_name}.' d='Modules.Bkmaildesigner.Admin'}">{$lang.footer_reason.recipient|escape:'htmlall':'UTF-8'}</textarea>
                  <label for="bkmd-reason-merchant-{$lang.id|intval}" class="bkmd-sublabel">{l s='Emails to the shop itself' d='Modules.Bkmaildesigner.Admin'}</label>
                  <textarea id="bkmd-reason-merchant-{$lang.id|intval}" name="footer_reason[{$lang.id|intval}][merchant]" class="form-control" rows="2" placeholder="{l s='Usually empty: nobody needs to explain to you why your shop writes to you.' d='Modules.Bkmaildesigner.Admin'}">{$lang.footer_reason.merchant|escape:'htmlall':'UTF-8'}</textarea>
                </div>
              </div>
            {/foreach}
          </fieldset>
          </div>

          {* ---------- avanzado ---------- *}
          <div class="bkmd-subpane" data-pane="advanced" hidden>
          <fieldset class="bkmd-fieldset">
            <legend>{l s='Your own CSS' d='Modules.Bkmaildesigner.Admin'}</legend>
            <p class="bkmd-panehint">{l s='For whoever knows what they are doing. Added at the end of the stylesheet, so it wins over the theme, and turned into inline styles before sending like the rest.' d='Modules.Bkmaildesigner.Admin'}</p>
            <textarea name="layout[custom_css]" class="form-control bkmd-css" rows="7" spellcheck="false" autocomplete="off" placeholder=".bk-line__name &#123; color: #b12704; &#125;">{$bkmd_layout.custom_css|escape:'htmlall':'UTF-8'}</textarea>
            <p class="help-block">{l s='The classes you will need:' d='Modules.Bkmaildesigner.Admin'}
              <code>.bk-card</code> <code>.bk-header</code> <code>.bk-body</code> <code>.bk-footer</code>
              <code>.bk-h1</code> <code>.bk-h2</code> <code>.bk-rich</code> <code>.bk-btn</code> <code>.bk-box</code>
              <code>.bk-line</code> <code>.bk-line__name</code> <code>.bk-line__meta</code> <code>.bk-line__amount</code>
              <code>.bk-total</code> <code>.bk-social</code>
            </p>
            <p class="help-block"><a href="#bkmd-tab-settings" data-bkmd-goto-settings>{l s='Export or import the whole design as a JSON file' d='Modules.Bkmaildesigner.Admin'}</a></p>
          </fieldset>
          </div>

          <div class="bkmd-actions">
            <button type="submit" class="btn btn-primary"><i class="icon-save"></i> {l s='Save design' d='Modules.Bkmaildesigner.Admin'}</button>
            <span class="bkmd-editor__status" data-bkmd-layout-status></span>
          </div>
        </div>

        <div class="bkmd-layout__preview">
          <div class="bkmd-preview-bar">
            <select class="form-control bkmd-preview-tpl" data-bkmd-preview-tpl>
              {foreach from=$bkmd_list item=row}
                <option value="{$row.key|escape:'htmlall':'UTF-8'}"{if $row.module === $bkmd_first_tpl.module && $row.name === $bkmd_first_tpl.name} selected{/if}>{if $row.module !== ''}{$row.module|escape:'htmlall':'UTF-8'} / {/if}{$row.name|escape:'htmlall':'UTF-8'}</option>
              {/foreach}
            </select>
            <div class="btn-group bkmd-device" data-bkmd-device>
              <button type="button" class="btn btn-default active" data-device="desktop"><i class="icon-desktop"></i></button>
              <button type="button" class="btn btn-default" data-device="mobile"><i class="icon-mobile"></i></button>
            </div>
          </div>
          <div class="bkmd-frame" data-bkmd-frame>
            <iframe title="{l s='Preview' d='Modules.Bkmaildesigner.Admin'}" sandbox="allow-same-origin"></iframe>
          </div>
          <p class="help-block">{l s='Rendered with the same engine that sends the emails, using the last real order and sample data.' d='Modules.Bkmaildesigner.Admin'}</p>
        </div>
      </form>
    </div>

    {* ---------------- guía ---------------- *}
    <div class="tab-pane" id="bkmd-tab-guide">
      {include file="./guide.tpl"}
    </div>

    {* ---------------- ajustes ---------------- *}
    <div class="tab-pane" id="bkmd-tab-settings">
      <form method="post" action="{$bkmd_action|escape:'htmlall':'UTF-8'}#bkmd-tab-settings" class="form-horizontal bkmd-settings">
        <input type="hidden" name="submitBkSettings" value="1">
        {include file="./_switch.tpl" name='BK_MD_ON' label={l s='Apply the design when sending' d='Modules.Bkmaildesigner.Admin'} on=$bkmd_v.BK_MD_ON desc={l s='Off, every email leaves exactly as PrestaShop generates it. Your designs are kept.' d='Modules.Bkmaildesigner.Admin'}}
        <div class="form-group">
          <label class="control-label col-lg-3">{l s='Reference language' d='Modules.Bkmaildesigner.Admin'}</label>
          <div class="col-lg-9">
            <select name="BK_MD_REF_LANG" class="form-control bkmd-chosen">
              {foreach from=$bkmd_languages item=lang}<option value="{$lang.id_lang|intval}"{if $lang.id_lang == $bkmd_v.BK_MD_REF_LANG} selected{/if}>{$lang.name|escape:'htmlall':'UTF-8'}</option>{/foreach}
            </select>
            <p class="help-block">{l s='A designed email without content in a language is sent with the content of this one.' d='Modules.Bkmaildesigner.Admin'}</p>
          </div>
        </div>
        <div class="form-group">
          <label class="control-label col-lg-3">{l s='Newly found emails' d='Modules.Bkmaildesigner.Admin'}</label>
          <div class="col-lg-9">
            <select name="BK_MD_NEW_MODE" class="form-control bkmd-chosen">
              <option value="wrapped"{if $bkmd_v.BK_MD_NEW_MODE === 'wrapped'} selected{/if}>{l s='Header and footer, automatically' d='Modules.Bkmaildesigner.Admin'}</option>
              <option value="original"{if $bkmd_v.BK_MD_NEW_MODE === 'original'} selected{/if}>{l s='Left unchanged until I decide' d='Modules.Bkmaildesigner.Admin'}</option>
            </select>
            <p class="help-block">{l s='What happens to an email that appears after installing a new module.' d='Modules.Bkmaildesigner.Admin'}</p>
          </div>
        </div>
        <div class="form-group">
          <label class="control-label col-lg-3">{l s='Test emails go to' d='Modules.Bkmaildesigner.Admin'}</label>
          <div class="col-lg-9"><input type="email" name="BK_MD_TEST_EMAIL" class="form-control" value="{$bkmd_v.BK_MD_TEST_EMAIL|escape:'htmlall':'UTF-8'}"></div>
        </div>
        {include file="./_switch.tpl" name='BK_MD_DEBUG' label={l s='Debug log' d='Modules.Bkmaildesigner.Admin'} on=$bkmd_v.BK_MD_DEBUG desc={l s='Writes one line per email sent, with the template and the mode applied.' d='Modules.Bkmaildesigner.Admin'}}
        <div class="form-group">
          <label class="control-label col-lg-3">{l s='Log' d='Modules.Bkmaildesigner.Admin'}</label>
          <div class="col-lg-9"><pre class="bkmd-log">{if $bkmd_log}{$bkmd_log|escape:'htmlall':'UTF-8'}{else}{l s='Empty.' d='Modules.Bkmaildesigner.Admin'}{/if}</pre></div>
        </div>
        <div class="panel-footer">
          <button type="submit" class="btn btn-default pull-right"><i class="process-icon-save"></i> {l s='Save' d='Modules.Bkmaildesigner.Admin'}</button>
        </div>
      </form>

      <fieldset class="bkmd-fieldset bkmd-audit">
        <legend>{l s='What else touches your emails' d='Modules.Bkmaildesigner.Admin'}</legend>
        <p class="help-block">{l s='Read every time you open this page. If an email does not look the way you designed it, the reason is usually here.' d='Modules.Bkmaildesigner.Admin'}</p>
        <div class="bkmd-actions">
          <button type="button" class="btn btn-default" data-bkmd-check><i class="icon-stethoscope"></i> {l s='Check how the original text is read' d='Modules.Bkmaildesigner.Admin'}</button>
        </div>
        <div class="bkmd-checkbox" data-bkmd-checkbox hidden></div>
        <ul class="bkmd-audit__list">
          {foreach from=$bkmd_audit item=row}
            <li class="bkmd-audit__item">
              <span class="bkmd-audit__mark bkmd-audit__mark--{$row.level|escape:'htmlall':'UTF-8'}">{if $row.level === 'ok'}&#10003;{elseif $row.level === 'warn'}!{else}i{/if}</span>
              <span>
                <strong>{$row.title|escape:'htmlall':'UTF-8'}</strong>
                {if $row.help} — {$row.help|escape:'htmlall':'UTF-8'}{/if}
                {if $row.detail}<span class="bkmd-audit__detail">{$row.detail|escape:'htmlall':'UTF-8'}</span>{/if}
              </span>
            </li>
          {/foreach}
        </ul>
      </fieldset>

      <fieldset class="bkmd-fieldset bkmd-transfer">
        <legend>{l s='Backup and move' d='Modules.Bkmaildesigner.Admin'}</legend>
        <p class="help-block">{l s='The export file holds your design and the content of every email in every language. Use it to keep a copy before a big change, or to carry your work from a test shop to the real one.' d='Modules.Bkmaildesigner.Admin'}</p>
        <div class="bkmd-transfer__row">
          <form method="post" action="{$bkmd_action|escape:'htmlall':'UTF-8'}">
            <input type="hidden" name="submitBkExport" value="1">
            <button type="submit" class="btn btn-default"><i class="icon-download"></i> {l s='Export to a JSON file' d='Modules.Bkmaildesigner.Admin'}</button>
          </form>
          <form method="post" enctype="multipart/form-data" action="{$bkmd_action|escape:'htmlall':'UTF-8'}#bkmd-tab-settings" class="bkmd-transfer__import">
            <input type="hidden" name="submitBkImport" value="1">
            <input type="file" name="bk_import" accept=".json,application/json" class="form-control">
            <button type="submit" class="btn btn-default"><i class="icon-upload"></i> {l s='Import' d='Modules.Bkmaildesigner.Admin'}</button>
          </form>
        </div>
        <p class="help-block">{l s='Importing overwrites the design and the emails the file carries. Anything the file does not mention is left alone.' d='Modules.Bkmaildesigner.Admin'}</p>
      </fieldset>
    </div>

    {* ---------------- más de BK Modules ---------------- *}
    <div class="tab-pane" id="bkmd-tab-more">{$bkmd_info_panel nofilter}</div>
  </div>
</div>

<div class="bkmd-modal" data-bkmd-modal hidden>
  <div class="bkmd-modal__box">
    <div class="bkmd-modal__bar">
      <strong data-bkmd-modal-title></strong>
      <div class="btn-group bkmd-device" data-bkmd-device>
        <button type="button" class="btn btn-default active" data-device="desktop"><i class="icon-desktop"></i></button>
        <button type="button" class="btn btn-default" data-device="mobile"><i class="icon-mobile"></i></button>
      </div>
      <div class="btn-group" data-bkmd-langs></div>
      <button type="button" class="btn btn-default" data-bkmd-modal-close><i class="icon-remove"></i></button>
    </div>
    <div class="bkmd-frame bkmd-frame--modal" data-bkmd-frame>
      <iframe title="{l s='Preview' d='Modules.Bkmaildesigner.Admin'}" sandbox="allow-same-origin"></iframe>
    </div>
  </div>
</div>

<div class="bkmd-modal" data-bkmd-test-modal hidden>
  <div class="bkmd-modal__box bkmd-modal__box--small">
    <div class="bkmd-modal__bar"><strong>{l s='Send a test email' d='Modules.Bkmaildesigner.Admin'}</strong><span class="bkmd-toolbar__spacer"></span><button type="button" class="btn btn-default" data-bkmd-test-close><i class="icon-remove"></i></button></div>
    <div class="bkmd-modal__body">
      <p data-bkmd-test-what></p>
      <label for="bkmd-test-email">{l s='Recipient' d='Modules.Bkmaildesigner.Admin'}</label>
      <input type="email" id="bkmd-test-email" class="form-control" value="{$bkmd_v.BK_MD_TEST_EMAIL|escape:'htmlall':'UTF-8'}">
      <div class="bkmd-field" style="margin-top:10px">
        <label for="bkmd-test-lang">{l s='Language' d='Modules.Bkmaildesigner.Admin'}</label>
        <select id="bkmd-test-lang" class="form-control">
          {foreach from=$bkmd_languages item=lang}<option value="{$lang.id_lang|intval}"{if $lang.id_lang == $bkmd_context_lang} selected{/if}>{$lang.name|escape:'htmlall':'UTF-8'}</option>{/foreach}
        </select>
      </div>
      <div class="bkmd-advanced">
        <button type="button" class="bkmd-advanced__toggle" data-bkmd-adv-toggle>{l s='Advanced options' d='Modules.Bkmaildesigner.Admin'}</button>
        <div class="bkmd-advanced__body" data-bkmd-adv-body hidden>
          <p class="help-block">{l s='With what each variable of this email is filled in while you test. What you change here is kept for the next preview; a real send always uses real data.' d='Modules.Bkmaildesigner.Admin'}</p>
          <div class="bkmd-samples" data-bkmd-adv-samples></div>
        </div>
      </div>
      <div class="bkmd-actions"><button type="button" class="btn btn-primary" data-bkmd-test-send><i class="icon-paper-plane"></i> {l s='Send now' d='Modules.Bkmaildesigner.Admin'}</button> <span class="bkmd-test-status" data-bkmd-test-status></span></div>
    </div>
  </div>
</div>
<script>
var bkmdLangs = {$bkmd_languages|json_encode nofilter};
var bkmdLinkWords = {
  col_label: "{l s='Text' d='Modules.Bkmaildesigner.Admin' js=1}",
  col_dest: "{l s='Where it goes' d='Modules.Bkmaildesigner.Admin' js=1}",
  label_hint: "{l s='Support' d='Modules.Bkmaildesigner.Admin' js=1}",
  home: "{l s='Shop home page' d='Modules.Bkmaildesigner.Admin' js=1}",
  account: "{l s='Customer account' d='Modules.Bkmaildesigner.Admin' js=1}",
  orders: "{l s='Order history' d='Modules.Bkmaildesigner.Admin' js=1}",
  tracking: "{l s='Track an order as a guest' d='Modules.Bkmaildesigner.Admin' js=1}",
  contact: "{l s='Write to the shop' d='Modules.Bkmaildesigner.Admin' js=1}",
  custom: "{l s='Another address…' d='Modules.Bkmaildesigner.Admin' js=1}",
  add: "{l s='Add a link' d='Modules.Bkmaildesigner.Admin' js=1}",
  remove: "{l s='Remove' d='Modules.Bkmaildesigner.Admin' js=1}",
  empty: "{l s='No links yet. The header shows only your logo.' d='Modules.Bkmaildesigner.Admin' js=1}"
};
var bkmdUploadWords = {
  pick: "{l s='Upload an image' d='Modules.Bkmaildesigner.Admin' js=1}",
  sending: "{l s='Uploading…' d='Modules.Bkmaildesigner.Admin' js=1}",
  failed: "{l s='The image could not be uploaded.' d='Modules.Bkmaildesigner.Admin' js=1}"
};
var bkmdSocialWords = {
  network: "{l s='Network' d='Modules.Bkmaildesigner.Admin' js=1}",
  url: "{l s='Address of your page' d='Modules.Bkmaildesigner.Admin' js=1}",
  custom: "{l s='Another one' d='Modules.Bkmaildesigner.Admin' js=1}",
  icon: "{l s='Image of the icon' d='Modules.Bkmaildesigner.Admin' js=1}",
  color: "{l s='Background' d='Modules.Bkmaildesigner.Admin' js=1}",
  no_color: "{l s='No background: the image is the icon' d='Modules.Bkmaildesigner.Admin' js=1}",
  icon_hint: "{l s='A square PNG with transparency looks like the rest. A wide logo also fits: it is placed at the same height, without cropping.' d='Modules.Bkmaildesigner.Admin' js=1}",
  label: "{l s='Name' d='Modules.Bkmaildesigner.Admin' js=1}",
  add: "{l s='Add a network' d='Modules.Bkmaildesigner.Admin' js=1}",
  remove: "{l s='Remove' d='Modules.Bkmaildesigner.Admin' js=1}",
  empty: "{l s='No networks yet. The footer shows no icons.' d='Modules.Bkmaildesigner.Admin' js=1}",
  base: "{$bkmd_social_base|escape:'javascript':'UTF-8'}"
};
var bkmdLayoutWords = {
  saving: "{l s='Saving…' d='Modules.Bkmaildesigner.Admin' js=1}",
  saved: "{l s='Design saved.' d='Modules.Bkmaildesigner.Admin' js=1}",
  error: "{l s='The design could not be saved.' d='Modules.Bkmaildesigner.Admin' js=1}"
};
var bkmdSampleWords = { html: "{l s='built by PrestaShop' d='Modules.Bkmaildesigner.Admin' js=1}" };
var bkmdSelWords = {
  one: "{l s='1 email selected' d='Modules.Bkmaildesigner.Admin' js=1}",
  many: "{l s='%n% emails selected' d='Modules.Bkmaildesigner.Admin' js=1}"
};
var bkmdBulkWords = {
  restore: "{l s='Put every email back the way it came when you installed the module? Emails with built-in text go back to the full design; the rest go back to your header and footer. What you have written is kept.' d='Modules.Bkmaildesigner.Admin' js=1}",
  preset: "{l s='Load the built-in content into every email that has one? Anything you have written in those emails is replaced.' d='Modules.Bkmaildesigner.Admin' js=1}"
};
var bkmdCheckWords = {
  known: "{l s='recognised' d='Modules.Bkmaildesigner.Admin' js=1}",
  known_help: "{l s='A PrestaShop template: its markup is known, the body is taken out exactly.' d='Modules.Bkmaildesigner.Admin' js=1}",
  generic: "{l s='generic read' d='Modules.Bkmaildesigner.Admin' js=1}",
  generic_help: "{l s='A template with its own markup: the logo and the signature are removed by shape. Worth a look at the preview.' d='Modules.Bkmaildesigner.Admin' js=1}",
  none: "{l s='not readable' d='Modules.Bkmaildesigner.Admin' js=1}",
  none_help: "{l s='The body could not be taken out, so this email is sent exactly as it was.' d='Modules.Bkmaildesigner.Admin' js=1}",
  summary: "{l s='%known% recognised by their markup, %generic% read generically, %none% left as they are.' d='Modules.Bkmaildesigner.Admin' js=1}"
};
</script>

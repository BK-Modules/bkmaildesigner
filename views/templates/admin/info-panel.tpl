{**
 * Módulos y artículos de bkmodules.com en el panel de configuración.
 *
 * El contenido llega del endpoint del hub con caché de un día; los enlaces del área de cliente
 * vienen ya construidos desde allí y aquí solo se pintan.
 *
 * Las imágenes viven en bkmodules.com: se piden con prioridad baja y decodificación asíncrona
 * para que no compitan con la carga de la pantalla, y sin referer para que ningún CDN las trate
 * como enlazadas desde otro sitio. Sin `loading=lazy` a propósito: en una pestaña que no está en
 * primer plano una imagen diferida no se pide nunca y el panel parecería estar cargando siempre.
 *}
<div class="panel bkmd-info">
  <div class="panel-heading">
    <i class="icon-info-circle"></i> {l s='More from BK Modules' d='Modules.Bkmaildesigner.Admin'}
  </div>

  <div class="bkmd-status {if !$bkmd_version.known}bkmd-status-unknown{elseif $bkmd_version.outdated}bkmd-status-outdated{else}bkmd-status-current{/if}">
    <span class="bkmd-status-icon">{if !$bkmd_version.known}?{elseif $bkmd_version.outdated}!{else}&#10003;{/if}</span>
    <span class="bkmd-status-text">
      <strong>{$bkmd_display_name|escape:'html':'UTF-8'} {$bkmd_version.installed|escape:'html':'UTF-8'}</strong>
      {if !$bkmd_version.known}
        {l s='The latest published version could not be checked right now.' d='Modules.Bkmaildesigner.Admin'}
      {elseif $bkmd_version.outdated}
        {l s='You have version %installed%; the latest published version is %latest%.' sprintf=['%installed%' => $bkmd_version.installed, '%latest%' => $bkmd_version.latest] d='Modules.Bkmaildesigner.Admin'}
      {else}
        {l s='Your module is up to date.' d='Modules.Bkmaildesigner.Admin'}
      {/if}
    </span>
    {if $bkmd_version.outdated}
      <a class="btn btn-primary" href="{$bkmd_version.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
        <i class="icon-download"></i> {l s='Get the update' d='Modules.Bkmaildesigner.Admin'}
      </a>
    {/if}
  </div>

  <div class="bkmd-info-links">
    <a class="btn btn-primary" href="{$bk_remote.links.contact|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
      <i class="icon-envelope"></i> {l s='Contact support' d='Modules.Bkmaildesigner.Admin'}
    </a>
    <a class="btn btn-default" href="{$bk_remote.links.licenses|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
      <i class="icon-download"></i> {l s='My licenses and downloads' d='Modules.Bkmaildesigner.Admin'}
    </a>
    <a class="btn btn-default" href="{$bk_remote.links.blog|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
      <i class="icon-book"></i> {l s='Guides and news' d='Modules.Bkmaildesigner.Admin'}
    </a>
  </div>

  {if $bk_remote.catalog}
    <h4 class="bkmd-info-title">
      <i class="icon-puzzle-piece"></i> {l s='Other BK Modules modules' d='Modules.Bkmaildesigner.Admin'}
    </h4>
    <div class="bk-catalog">
      {foreach from=$bk_remote.catalog item=item}
        <a class="bk-catalog-card" href="{$item.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
          {if $item.image}
            {* Si la imagen no llega, se retira el hueco en vez de dejar el icono de rota *}
            <span class="bk-catalog-media"><img src="{$item.image|escape:'html':'UTF-8'}" alt=""
                     decoding="async" fetchpriority="low" referrerpolicy="no-referrer"
                     onerror="this.parentNode.style.display='none';"></span>
          {/if}
          <span class="bk-catalog-body">
            <span class="bk-catalog-name">{$item.name|escape:'html':'UTF-8'}</span>
            <span class="bk-catalog-desc">{$item.description|escape:'html':'UTF-8'|truncate:120}</span>
            <span class="bk-catalog-more">{l s='View the module' d='Modules.Bkmaildesigner.Admin'} →</span>
          </span>
        </a>
      {/foreach}
    </div>
  {/if}

  {if $bk_remote.posts}
    <h4 class="bkmd-info-title">
      <i class="icon-rss"></i> {l s='Latest blog posts' d='Modules.Bkmaildesigner.Admin'}
    </h4>
    <div class="bk-posts">
      {foreach from=$bk_remote.posts item=post}
        <a class="bk-post-card" href="{$post.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
          {if $post.image}
            <span class="bk-post-thumb"><img src="{$post.image|escape:'html':'UTF-8'}" alt=""
                     decoding="async" fetchpriority="low" referrerpolicy="no-referrer"
                     onerror="this.parentNode.style.display='none';"></span>
          {/if}
          <span class="bk-post-body">
            <span class="bk-post-title">{$post.title|escape:'html':'UTF-8'}</span>
            <span class="bk-post-summary">{$post.summary|escape:'html':'UTF-8'}</span>
            <span class="bk-post-date">{$post.date|date_format:'%d/%m/%Y'}</span>
          </span>
        </a>
      {/foreach}
    </div>
  {/if}
</div>

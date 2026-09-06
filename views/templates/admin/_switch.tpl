{**
 * Interruptor del back office. Vive aparte porque se repite once veces y la marcación de PrestaShop
 * para un switch es larga; con {include} el formulario se lee.
 *}
<div class="form-group">
  <label class="control-label col-lg-3">{$label|escape:'htmlall':'UTF-8'}</label>
  <div class="col-lg-9">
    <span class="switch prestashop-switch fixed-width-lg">
      <input type="radio" name="{$name|escape:'htmlall':'UTF-8'}" id="{$name|escape:'htmlall':'UTF-8'}_on" value="1"{if $on} checked{/if}>
      <label for="{$name|escape:'htmlall':'UTF-8'}_on">{l s='Yes' d='Modules.Bkmaildesigner.Admin'}</label>
      <input type="radio" name="{$name|escape:'htmlall':'UTF-8'}" id="{$name|escape:'htmlall':'UTF-8'}_off" value="0"{if !$on} checked{/if}>
      <label for="{$name|escape:'htmlall':'UTF-8'}_off">{l s='No' d='Modules.Bkmaildesigner.Admin'}</label>
      <a class="slide-button btn"></a>
    </span>
    {if $desc}<p class="help-block">{$desc|escape:'htmlall':'UTF-8'}</p>{/if}
  </div>
</div>

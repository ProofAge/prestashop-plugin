{**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}
<div class="proofage-picker" data-proofage-picker data-search-url="{$proofage_search_url|escape:'htmlall':'UTF-8'}">
  <input type="hidden" name="{$proofage_picker_name|escape:'htmlall':'UTF-8'}" value="{$proofage_picker_value|escape:'htmlall':'UTF-8'}" data-proofage-picker-value>
  <input type="text" class="form-control" autocomplete="off" data-proofage-picker-search
         placeholder="{l s='Search a product by name or reference' d='Modules.Proofage.Admin'}">
  <ul class="list-group proofage-picker__results" data-proofage-picker-results></ul>
  <ul class="proofage-picker__selected" data-proofage-picker-selected>
    {foreach $proofage_picker_selected as $product}
      <li data-id="{$product.id|intval}">
        {$product.name|escape:'htmlall':'UTF-8'} <span class="text-muted">#{$product.id|intval}</span>
        <button type="button" class="btn btn-link btn-xs" data-proofage-picker-remove aria-label="{l s='Remove' d='Modules.Proofage.Admin'}">&times;</button>
      </li>
    {/foreach}
  </ul>
</div>

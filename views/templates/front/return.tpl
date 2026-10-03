{**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}
{extends file='page.tpl'}

{block name='page_content_container'}
  <section id="content" class="page-content proofage-gate" data-proofage-return>
    {if !$proofage.found}
      <h1 class="proofage-gate__title">{l s='Verification not found' d='Modules.Proofage.Shop'}</h1>
      <p><a class="btn btn-primary" href="{$proofage.back|escape:'htmlall':'UTF-8'}">{l s='Continue shopping' d='Modules.Proofage.Shop'}</a></p>
    {elseif $proofage.cookieMissing}
      <h1 class="proofage-gate__title">{l s='We could not confirm your verification in this browser' d='Modules.Proofage.Shop'}</h1>
      <p>{l s='Please make sure cookies are enabled, then start the verification again from the same browser.' d='Modules.Proofage.Shop'}</p>
      <p><a class="btn btn-primary" href="{$proofage.back|escape:'htmlall':'UTF-8'}">{l s='Back to the shop' d='Modules.Proofage.Shop'}</a></p>
    {else}
      <h1 class="proofage-gate__title">{l s='Checking your verification…' d='Modules.Proofage.Shop'}</h1>
      <p class="proofage-gate__status" data-proofage-status role="status" aria-live="polite"></p>
      <button type="button" class="btn btn-primary proofage-gate__button" data-proofage-start hidden>
        {l s='Continue verification' d='Modules.Proofage.Shop'}
      </button>
    {/if}
  </section>
{/block}

{**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}
<div class="proofage-overlay" data-proofage-gate role="dialog" aria-modal="true" aria-labelledby="proofage-overlay-title">
  <div class="proofage-overlay__card">
    <h2 id="proofage-overlay-title" class="proofage-gate__title">{$proofage.texts.title|escape:'htmlall':'UTF-8'}</h2>
    <p class="proofage-gate__description">{$proofage.texts.description|escape:'htmlall':'UTF-8'}</p>
    <button type="button" class="btn btn-primary proofage-gate__button" data-proofage-start>
      {$proofage.texts.button|escape:'htmlall':'UTF-8'}
    </button>
    <p class="proofage-gate__status" data-proofage-status role="status" aria-live="polite"></p>
    <noscript>
      <p class="proofage-gate__status">{l s='Please enable JavaScript to verify your age.' d='Modules.Proofage.Shop'}</p>
    </noscript>
  </div>
</div>

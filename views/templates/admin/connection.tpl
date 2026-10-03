{**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}
<div class="proofage-connection" data-proofage-connection data-test-url="{$proofage_test_url|escape:'htmlall':'UTF-8'}">
  <p><strong>{l s='Webhook URL' d='Modules.Proofage.Admin'}</strong></p>
  <div class="input-group">
    <input type="text" class="form-control" readonly value="{$proofage_webhook_url|escape:'htmlall':'UTF-8'}" data-proofage-webhook-url>
    <span class="input-group-btn">
      <button type="button" class="btn btn-default" data-proofage-copy>{l s='Copy' d='Modules.Proofage.Admin'}</button>
    </span>
  </div>
  <p class="help-block">{l s='Paste this URL into the webhook settings of your ProofAge workspace.' d='Modules.Proofage.Admin'}</p>
  <p>
    <button type="button" class="btn btn-default" data-proofage-test>{l s='Test connection' d='Modules.Proofage.Admin'}</button>
  </p>
  <div data-proofage-test-result></div>
  <p class="help-block">{l s='The check type, minimum age and wallet option are set in your ProofAge workspace, not here.' d='Modules.Proofage.Admin'}</p>
</div>

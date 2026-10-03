{**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}
<div class="card mt-2" id="proofage-order-card">
  <div class="card-header">
    <h3 class="card-header-title">{l s='Age verification (ProofAge)' d='Modules.Proofage.Admin'}</h3>
  </div>
  <div class="card-body">
    {if !$proofage_snapshot}
      <p class="mb-0">{l s='No verification data for this order.' d='Modules.Proofage.Admin'}</p>
    {elseif !$proofage_snapshot->required && $proofage_snapshot->status == 'not_required'}
      <p class="mb-0">{l s='Not required for the products in this order.' d='Modules.Proofage.Admin'}</p>
    {else}
      <dl class="row mb-0">
        <dt class="col-sm-4">{l s='Status' d='Modules.Proofage.Admin'}</dt>
        <dd class="col-sm-8">
          {if $proofage_snapshot->status == 'approved'}<span class="badge badge-success">{l s='Approved' d='Modules.Proofage.Admin'}</span>
          {elseif $proofage_snapshot->status == 'revoked'}<span class="badge badge-danger">{l s='Approval revoked after the order' d='Modules.Proofage.Admin'}</span>
          {else}<span class="badge badge-warning">{l s='Not verified' d='Modules.Proofage.Admin'}</span>{/if}
        </dd>
        {if $proofage_snapshot->verificationId}
          <dt class="col-sm-4">{l s='Verification ID' d='Modules.Proofage.Admin'}</dt>
          <dd class="col-sm-8"><code>{$proofage_snapshot->verificationId|escape:'htmlall':'UTF-8'}</code></dd>
          <dt class="col-sm-4">{l s='Method' d='Modules.Proofage.Admin'}</dt>
          <dd class="col-sm-8">{if $proofage_snapshot->method == 'wallet'}{l s='Digital identity wallet' d='Modules.Proofage.Admin'}{else}{l s='Face or document' d='Modules.Proofage.Admin'}{/if}</dd>
          <dt class="col-sm-4">{l s='Verified at' d='Modules.Proofage.Admin'}</dt>
          <dd class="col-sm-8">{$proofage_snapshot->verifiedAt|date_format:'%Y-%m-%d %H:%M'}</dd>
        {/if}
      </dl>
    {/if}
  </div>
</div>

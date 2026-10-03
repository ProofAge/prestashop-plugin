{**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}
<div class="card mt-2" id="proofage-customer-card">
  <div class="card-header">
    <h3 class="card-header-title">{l s='Age verification (ProofAge)' d='Modules.Proofage.Admin'}</h3>
  </div>
  <div class="card-body">
    {if $proofage_customer_verification}
      <p>
        {if $proofage_customer_valid}<span class="badge badge-success">{l s='Verified' d='Modules.Proofage.Admin'}</span>{else}<span class="badge badge-secondary">{l s='Expired' d='Modules.Proofage.Admin'}</span>{/if}
        <code>{$proofage_customer_verification->verificationId|escape:'htmlall':'UTF-8'}</code>
      </p>
      <p>
        {l s='Verified at' d='Modules.Proofage.Admin'}: {$proofage_verified_at|escape:'htmlall':'UTF-8'}<br>
        {l s='Expires' d='Modules.Proofage.Admin'}: {if $proofage_expires_at}{$proofage_expires_at|escape:'htmlall':'UTF-8'}{else}{l s='Never' d='Modules.Proofage.Admin'}{/if}
      </p>
      <a class="btn btn-outline-danger btn-sm" href="{$proofage_reset_url|escape:'htmlall':'UTF-8'}">{l s='Reset verification' d='Modules.Proofage.Admin'}</a>
    {else}
      <p class="mb-0">{l s='This customer has not been verified.' d='Modules.Proofage.Admin'}</p>
    {/if}
  </div>
</div>

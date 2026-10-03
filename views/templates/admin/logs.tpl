{**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}
<div class="panel" data-proofage-logs>
  <div class="panel-heading"><i class="icon-list"></i> {l s='Recent verifications' d='Modules.Proofage.Admin'}</div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr>
        <th>{l s='Verification ID' d='Modules.Proofage.Admin'}</th>
        <th>{l s='Status' d='Modules.Proofage.Admin'}</th>
        <th>{l s='Method' d='Modules.Proofage.Admin'}</th>
        <th>{l s='Customer' d='Modules.Proofage.Admin'}</th>
        <th>{l s='Created' d='Modules.Proofage.Admin'}</th>
        <th>{l s='Decided' d='Modules.Proofage.Admin'}</th>
      </tr></thead>
      <tbody>
        {foreach $proofage_verifications as $v}
          <tr>
            <td><code>{$v.verification_id|escape:'htmlall':'UTF-8'}</code></td>
            <td>{$v.status|escape:'htmlall':'UTF-8'}</td>
            <td>{if $v.method}{$v.method|escape:'htmlall':'UTF-8'}{else}—{/if}</td>
            <td>{if $v.id_customer}#{$v.id_customer|intval}{else}{l s='Guest' d='Modules.Proofage.Admin'}{/if}</td>
            <td>{$v.created_at|escape:'htmlall':'UTF-8'}</td>
            <td>{if $v.decided_at}{$v.decided_at|escape:'htmlall':'UTF-8'}{else}—{/if}</td>
          </tr>
        {foreachelse}
          <tr><td colspan="6">{l s='No verifications yet.' d='Modules.Proofage.Admin'}</td></tr>
        {/foreach}
      </tbody>
    </table>
  </div>
</div>
<div class="panel">
  <div class="panel-heading"><i class="icon-exchange"></i> {l s='Recent webhook deliveries' d='Modules.Proofage.Admin'}</div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr>
        <th>{l s='Delivery ID' d='Modules.Proofage.Admin'}</th>
        <th>{l s='Verification ID' d='Modules.Proofage.Admin'}</th>
        <th>{l s='Status' d='Modules.Proofage.Admin'}</th>
        <th>{l s='Received (UTC)' d='Modules.Proofage.Admin'}</th>
      </tr></thead>
      <tbody>
        {foreach $proofage_deliveries as $d}
          <tr>
            <td><code>{$d.delivery_id|escape:'htmlall':'UTF-8'}</code></td>
            <td><code>{$d.verification_id|escape:'htmlall':'UTF-8'}</code></td>
            <td>{$d.status|escape:'htmlall':'UTF-8'}</td>
            <td>{$d.received_at|escape:'htmlall':'UTF-8'}</td>
          </tr>
        {foreachelse}
          <tr><td colspan="4">{l s='No webhook received yet.' d='Modules.Proofage.Admin'}</td></tr>
        {/foreach}
      </tbody>
    </table>
  </div>
</div>

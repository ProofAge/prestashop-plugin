# ProofAge Age Verification for PrestaShop

Restrict products, categories, pages or the whole shop to visitors who have passed a
[ProofAge](https://proofage.xyz) verification. Verification runs on ProofAge's side; the module only
gates content, tracks the outcome and blocks order creation for protected carts.

## Requirements

- PrestaShop 8.1 or later, including 9.x
- PHP 7.2.5 or later with the cURL extension
- A ProofAge account
- An HTTPS storefront (needed by the verification window)

## Setup

1. Create a workspace in your ProofAge dashboard. Choose there whether it performs an age check or a
   full identity (KYC) check, and which wallet options are offered. These choices are made in ProofAge,
   not in the module.
2. Install the module, open its configuration page and copy the public key (`pk`) and secret key (`sk`)
   from the workspace into the Connection panel.
3. Copy the webhook URL displayed in the module's Connection panel and paste it into the webhook setting
   of your ProofAge workspace. Outcomes are delivered to this URL.

## Rules and precedence

Choose what is protected: products, categories (optionally with their subcategories), CMS pages,
front controllers, URL patterns, or the whole site. You can also exclude items from a broader
protection.

The most specific rule wins: product, then category, then page, then the whole site. When a protection
and an exclusion apply at the same level, the protection wins. The cart and checkout always use the
verification page when the cart contains a protected product.

## Display modes

- **Verification page** (default): protected pages are replaced by a verification page that search
  engines do not index.
- **Overlay**: the page is blurred behind a verification window and its content stays indexable.
- The verification itself opens either **in a window on top of the shop** or **on the ProofAge page**,
  then returns to the shop.

Title, description and button text are editable per language.

## Validity

A passed verification is remembered for a configurable time: guests 1 to 720 hours (default 24),
customers a number of days (default 365, 0 means forever). Customer results follow the account across
devices.

## Orders

Order creation is guarded server side. If a cart holds a protected product and the customer is not
verified, the order is refused. Orders created through the webservice are guarded as well.

Note that a blocked order surfaces as an error page: the guard throws to stop order creation. With
redirect-style payment providers a payment could in theory be captured with no order created. Keep the
checkout gated (the default) so a non-verified customer never reaches payment; then this never happens
in normal flow.

After upgrading the module, clear the PrestaShop cache.

## Privacy

The module stores only verification IDs, statuses, the verification method and dates, linked to a
shop-generated pseudonymous ID. No document, face image or birth date is stored. Visitor IPs are kept
hashed for rate limiting only. Data is included in PrestaShop's GDPR personal data export and is erased
by the GDPR delete.

## License

AFL-3.0

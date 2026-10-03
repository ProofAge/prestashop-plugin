# Development

This file is for module developers; it is not shipped in the module zip.

## Tooling

```bash
composer install
vendor/bin/phpunit                  # unit tests
node --test tests/js/*.test.js      # storefront script tests
bin/lint-legacy.sh                  # PHP 7.2 syntax floor for shipped code
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix         # PrestaShop coding standard
php bin/check-translations.php      # every source string has fr/es/it translations
php bin/build-translations.php      # regenerate translations/*.xlf from translations/source/catalog.php
php tests/Integration/run.php       # integration suite against a local PrestaShop, using a fake ProofAge API
composer build                      # = bin/build-zip.sh → build/proofage-<version>.zip
```

## Integration suite

The integration suite boots the PrestaShop installation that contains the module (`modules/proofage`
inside a shop) and starts a local fake ProofAge API on `127.0.0.1:8765`, so no real account is needed.
The settings form accepts only `https` API URLs; the runner writes the fake API URL straight into the
configuration.
It talks to the storefront at `https://proofage-prestashop.test` by default; set `PS_BASE_URL` for
another shop, e.g.:

```bash
PS_BASE_URL=http://proofage-prestashop8.test php tests/Integration/run.php
```

Pass a file-name fragment to run a subset: `php tests/Integration/run.php Gate`.

The checks overwrite the module settings with fake keys and empty the module tables. Before the first
check the runner copies the `PROOFAGE_*` configuration (with its language rows), the `PS_*` settings it
touches and the module tables into `*__bak` tables, and restores them after the last check, also when a
check fails or the script dies. A run that was killed outright is repaired at the start of the next run.

## Webhooks

Replay a webhook to a local shop:

```bash
php bin/send-test-webhook.php <webhook-url> <secret> <verification-id> <status> [method]
```

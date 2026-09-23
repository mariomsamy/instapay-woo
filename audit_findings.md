# Production Readiness Assessment

Assessment date: 2026-09-23

Scope: Static review and local syntax checks for the Instapay WooCommerce Gateway repository. No live WordPress site, production system, customer account, or third-party service was accessed.

## Discovery and architecture

- Application: Instapay Gateway for Egypt, a WordPress plugin providing a manual Instapay payment method for Egyptian-pound WooCommerce orders.
- Runtime: PHP 7.4+ on WordPress 6.3+ and WooCommerce 8.5+; jQuery is supplied by WordPress.
- Components: one WooCommerce gateway class, AJAX upload and manager-action endpoints, one optional REST endpoint under `instapay-gateway-for-egypt/v1`, a custom WooCommerce order status, WP-Cron cleanup, dashboard/order-list UI, and CSS/JavaScript assets.
- Storage: receipt images are stored below the WordPress uploads base directory in `instapay_receipts`; the absolute path and filename are stored as private order metadata.
- Sensitive data: receipt images may contain financial transaction details, payer identity, phone numbers, and transaction references. WooCommerce orders contain customer PII but the plugin does not create a separate customer database.
- External services: WooCommerce mailer only. No direct Instapay API, analytics, AI/LLM, queue, cache, cloud SDK, Composer package, or npm package is present.
- Roles: anonymous customer with an order key, authenticated order owner, and WooCommerce managers with `edit_shop_orders`.

## Data flow and trust boundaries

1. A customer selects Instapay for an EGP order and receives payment instructions.
2. The browser sends an image, order ID, order key, and nonce to WordPress AJAX.
3. The plugin validates order state/ownership and image content, re-encodes the image, and stores it in the protected receipt directory.
4. The order moves to Payment Review. A manager views the protected image through a nonce-protected controller and accepts or rejects it.
5. Optional REST uploads follow the same validation/storage path and are disabled by default.
6. WP-Cron removes validated receipt files for old failed, cancelled, or refunded orders when cleanup is enabled.

Trust boundaries exist between the public browser and WordPress, WordPress and the filesystem, customer and manager privileges, optional REST clients and WordPress, and WordPress/WooCommerce data APIs.

## Attack surface

- Public AJAX receipt upload.
- Authenticated AJAX manager quick actions.
- Receipt-view query controller.
- Optional public REST route authenticated by order key.
- Uploaded image parser/editor and filesystem storage.
- Gateway settings, order meta box, dashboard widget, order list, email attachment hook, and WP-Cron callback.

## STRIDE summary

| Threat | Primary control | Residual risk |
| --- | --- | --- |
| Spoofing an order owner | Order key plus action nonce; logged-in ownership for views | Order keys in URLs must remain confidential |
| Tampering with status or files | Capability, nonce, state-transition allowlist, MIME/path checks | Requires staging integration tests |
| Repudiation by managers | Optional WooCommerce order audit notes | Logging can be disabled by an administrator |
| Receipt disclosure | Protected directory, randomized names, validated controller authorization | Nginx needs equivalent deny rules outside this plugin |
| Upload denial of service | 5MB limit, allowed states, UI duplicate prevention, per-order lock | No IP-wide rate limiter; hosting/WAF limits remain important |
| Elevation of privilege | `edit_shop_orders`, strict Instapay-order checks | Depends on correct WordPress role administration |

## Findings remediated in 1.2.0

- High: receipt access lacked complete nonce and ownership checks.
- High: stored file paths were trusted for reads, deletion, and email attachment.
- High: upload validation and order-state checks were incomplete.
- Medium: REST upload behavior was always exposed and used a WooCommerce-owned namespace.
- Medium: direct post-meta access was incompatible with HPOS.
- Medium: manager status transitions were broader than the intended workflow.
- Medium: upload requests could race and duplicate work.
- Medium: cleanup scheduling ran during ordinary requests and raw deletion bypassed path validation.
- Low: AJAX handlers were registered twice.
- Low: PHP 8.2 dynamic properties could emit deprecation warnings.
- Low: inline CSS/JavaScript and inline styles violated WordPress review guidance and reduced maintainability.
- Low: upload/admin feedback lacked consistent busy, focus, recovery, and assistive-technology states.
- Functional: the New Order email ran before receipt upload, so its attachment hook could not normally attach a receipt; upload now triggers a manager review email with the validated attachment.

## Verification baseline

| Check | Result | Evidence |
| --- | --- | --- |
| Main PHP syntax | Passed | `/opt/homebrew/bin/php -l instapay-woo.php` exited 0 on PHP 8.5.7 |
| Gateway PHP syntax | Passed | `/opt/homebrew/bin/php -l includes/class-wc-gateway-instapay.php` exited 0 |
| JavaScript syntax | Passed | `node --check assets/js/gateway.js` exited 0 |
| Patch whitespace | Passed | `git diff --check` produced no errors |
| Inline asset scan | Passed | No embedded style/script tags or inline style attributes remain in PHP |
| Legacy metadata/file APIs | Passed | No direct post-meta calls, raw `unlink`, or `move_uploaded_file` remain |
| Secret/risky-function scan | Passed | No embedded credentials, eval, shell execution, unsafe deserialization, or remote-fetch calls found |

## Release gates not tested

- WordPress Plugin Check and PHPCS/WPCS are unavailable in this repository.
- No disposable WordPress/WooCommerce runtime or database was provided, so checkout, HPOS, Classic order storage, email, REST multipart upload, image-editor variations, cron, activation/deactivation, and upgrade flows are not end-to-end tested.
- No browser runtime was provided, so visual regression, keyboard-only operation, screen-reader output, RTL layout, and mobile viewport behavior are not empirically tested.
- Infrastructure, TLS, HTTP headers, WAF/rate limits, filesystem permissions, Nginx rules, backups, monitoring, alerting, and rollback are outside repository scope.
- Translation catalogs were regenerated and compiled, but new or changed 1.2.0 strings still need human Arabic translation review and completion.

## Production decision

The reviewed source is materially safer and passes available static gates, but full production readiness is **not proven** without the staging and WordPress-specific tests listed above. Run those tests on a disposable WordPress/WooCommerce staging site before deployment.

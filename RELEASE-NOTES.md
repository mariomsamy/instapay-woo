# Instapay Gateway for Egypt 1.2.0

Release date: 2026-09-23

## Ownership and contribution

Developed and copyrighted by Recipe Codes / Mario M. Samy. This release incorporates and extends security-hardening work contributed by Abdelrahman Elawadi through his public `abdelrahman-elawadi/instapay-woo` fork. The original author and copyright metadata were not replaced.

The plugin name, release package, text domain, REST namespace, and translation filenames use the requested Instapay Gateway for Egypt name. Existing `instapay_woo` PHP prefixes, option keys, hooks, metadata keys, and the main filename are retained to avoid breaking upgrades and saved settings.

## Security

- Receipt views require a valid WordPress nonce plus manager capability, customer ownership, or the matching WooCommerce order key.
- Stored paths are canonicalized and must resolve inside the dedicated receipt directory before a file can be viewed, emailed, or removed.
- Uploads accept only verified JPEG, PNG, or WebP images up to 5MB; client MIME values are not trusted.
- Upload filenames contain the order ID plus a random value and are handled through the WordPress upload API.
- Uploaded images are re-encoded to remove unnecessary metadata; optional resizing remains available.
- Uploads require a matching order key, Instapay as the payment method, and an allowed order status.
- A per-order lock reduces duplicate and concurrent upload races.
- Manager actions require `edit_shop_orders`; accepting payment requires a valid stored receipt.
- REST uploads are disabled by default and use the plugin-owned `/wp-json/instapay-gateway-for-egypt/v1/receipt` route.
- Rejection email content and dashboard/order output are escaped for their output contexts.

## Compatibility and reliability

- WooCommerce order CRUD replaces direct post-meta calls for HPOS compatibility.
- HPOS compatibility is declared through `FeaturesUtil`.
- PHP 8.2 dynamic-property warnings are avoided with declared gateway properties.
- Cleanup is scheduled on activation, cleared on deactivation, and no longer scheduled during normal requests.
- Cleanup and email attachments use the same validated receipt-path helper.
- Duplicate AJAX handlers were removed.
- REST and browser uploads share the same validation and storage path.
- The previous New Order attachment hook was replaced with a manager review email sent after upload, when the receipt actually exists.
- Receipt storage writes deny rules for Apache 2.4/2.2 and IIS; Nginx still requires an equivalent server rule.
- The POT template was regenerated and existing Arabic/English catalogs were merged and compiled with Recipe Codes contact metadata.

## UI and accessibility

- Embedded `<style>` and `<script>` blocks were replaced with dedicated, conditionally enqueued CSS and JavaScript assets.
- The gateway script declares jQuery as a WordPress dependency.
- Upload controls provide drag, focus, selected-file, busy, success, and recoverable error states.
- Upload and action buttons remain dimensionally stable and are disabled during requests to prevent duplicate submissions.
- Status output uses an ARIA live region and text is inserted without HTML interpretation.
- Receipt previews are responsive and external receipt views use `noopener`.
- Admin actions use consistent full-width controls with clear destructive styling.
- Admin actions that cannot apply to the current order status are hidden instead of failing after a click.

## Verification evidence

- `php -l instapay-woo.php`: passed on PHP 8.5.7.
- `php -l includes/class-wc-gateway-instapay.php`: passed on PHP 8.5.7.
- `node --check assets/js/gateway.js`: passed.
- Static scan for embedded `<style>`, `<script>`, inline `style=`, direct post-meta calls, raw `unlink`, and `move_uploaded_file`: no matches in plugin PHP files after refactoring.
- Release archive inspection: performed during packaging; hidden files and nested ZIP files are excluded.

## Not tested in this repository-only assessment

- End-to-end checkout, guest upload, manager approval, email delivery, and cron execution in a running WordPress/WooCommerce site.
- Web-server behavior for Apache `.htaccess` versus Nginx or other server configurations.
- WordPress.org Plugin Check and PHPCS, because those tools are not installed in the provided repository.
- Production TLS, security headers, backups, monitoring, infrastructure permissions, and disaster recovery.
- Upgrade behavior against a copy of a production database.

These items require a disposable staging WordPress installation and relevant infrastructure access before production deployment.

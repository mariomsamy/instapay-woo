# Instapay Gateway for Egypt 1.3.0

Release date: 2026-10-02

## Upgrade notes

- **New orders now wait in On hold instead of Pending.** WooCommerce no longer auto-cancels them after the hold-stock time, the customer receives the order email with Instapay instructions and an upload link, and the store receives the New order email. Orders placed before 1.3.0 in Pending keep working.
- **Unpaid orders are cancelled after 48 hours** by default (Instapay settings > Cancel Unpaid Orders After). Set it to 0 to keep them until you cancel them.
- **The review email no longer attaches the receipt** by default; enable "Attach Receipt to Email" if you need it.
- Updates now come from GitHub releases. Only releases with the package ZIP and its `.sha256` file are offered; the included workflow attaches both. See the README.

## Fixes

- Receipt uploads crashed with "There has been a critical error" on every site: the upload-directory filter called `wp_upload_dir()`, which re-ran the same filter forever.
- The gateway did not appear in the block checkout, the WooCommerce default since 8.3.
- Uploads failed on Windows/IIS because `$_FILES` was unslashed, which removed the backslashes from the temporary path.
- Dragging an image onto the upload area did nothing.
- Customers received no "order confirmed" email when a payment was accepted.
- Messages on the thank-you page did not match the order status (for example "rejected" on cancelled orders).
- The rejection email linked to the order-pay page instead of the upload form.

## Security and data integrity

- Uploads, accept/reject/cancel and expiry share one atomic per-order lock (`INSERT IGNORE`) and re-read the order inside it. Double clicks, two managers or two tabs apply a decision once; an upload that finishes after a cancellation no longer revives the order.
- Accepting, rejecting or cancelling an order in review requires the receipt the manager was shown.
- Locks carry an owner token, so a request that outlives the lock timeout cannot release another request's lock.
- An order that was already paid and later put on hold by a manager is never expired and does not accept new receipts.
- The SHA-256 of every receipt and the optional transaction reference are compared across orders; reuse is flagged in order notes and the review box.
- Upload limit of 5 per order per hour; images over 10,000 px per side or 40 MP are rejected before decoding; metadata is stripped (GD re-encode, or Imagick `stripImage()`), and the upload is refused rather than stored with metadata if stripping fails.
- Accepting uses `payment_complete()`, recording the paid date and transaction reference.
- Deny rules for the receipts folder are written once with plain file writes and restored after updates.

## Privacy (Egypt Law 151/2020)

- Privacy policy text, personal data exporter and eraser (following WooCommerce's order erasure setting).
- Optional retention period for receipts on paid orders; receipts are deleted when an order is permanently deleted; orphaned files are swept daily.
- `uninstall.php` removes receipts, metadata, settings and scheduled jobs.
- Upload form tells customers how the receipt is used.

## UI and UX

- Customer panel: progress steps, status badge, copy buttons for amount, address and phone, QR caption, image preview, upload progress, clear review and confirmed states, rejection reason.
- Admin review box: amount to check, upload age, reference, duplicate warnings, larger receipt preview, reject reason inside a Reject section, de-emphasized Cancel.
- Dashboard widget lists the oldest receipts first with their waiting time.
- Logical CSS properties for right-to-left layouts; complete Arabic translation (195 strings).

## Verification

Tested on a disposable WordPress 7.1.2 site with WooCommerce 11.1.2 (SQLite database), on both HPOS and posts order storage. 68 end-to-end checks cover upload validation, metadata stripping, the receipt viewer's access checks, rate limiting, concurrent uploads and accepts, duplicate detection, reject/cancel flows, expiry, cleanup, orphan sweep, privacy tools, the block checkout through the Store API, the GitHub update (including checksum rejection) and uninstall. Checks that could not run there: Windows/IIS, Nginx, real email delivery, Imagick-only hosts.

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

## Documentation

- Condensed the GitHub README, removed duplicated release details, and kept the bilingual setup, security, API, screenshots, ownership, and contributor information easier to scan.

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

=== Instapay Gateway for Egypt ===
Contributors: mariomsamy
Donate link: https://recipe.codes/
Tags: woocommerce, instapay, payment gateway, egypt, payment
Requires at least: 6.3
Tested up to: 7.1
WC requires at least: 8.5
WC tested up to: 11.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manual Instapay payments for WooCommerce: customers upload a receipt, managers approve it.

== Description ==

Instapay Gateway for Egypt allows your customers to check out via Instapay with receipt screenshot uploads, secure storage, and administrative review tools.

Developed by **Recipe Codes**
Author: Mario M. Samy

Security hardening in version 1.2.0 was contributed by Abdelrahman Elawadi (GitHub: @abdelrahman-elawadi). Recipe Codes and Mario M. Samy remain the plugin author and copyright owner.

### ✨ Features
* **Direct Instapay Deep Linking:** Seamless mobile experience allowing users to tap and pay directly through the Instapay app.
* **Drag-and-Drop Receipt Upload:** Modern, beautiful, and secure image uploader for users to attach their payment proofs after placing an order.
* **Smart Currency Restriction:** The gateway automatically hides itself for non-EGP currencies to prevent invalid transactions.
* **Block and Classic Checkout:** Works with the WooCommerce block checkout and the classic shortcode checkout.
* **Guided Payment Page:** Step-by-step instructions, one-tap copy for the amount, Instapay address and phone, image preview and upload progress, in Arabic and English.
* **Image Compression:** Receipts are optionally resized to 1200 pixels and re-encoded to remove camera metadata such as GPS location.
* **Reused Receipt Warnings:** Managers are warned when the same receipt image or transaction reference appears on more than one order.
* **"Quick Action" Approval:** Approve, Reject, or Cancel pending payments instantly using AJAX buttons inside the order meta box.
* **Custom Rejection Reasons:** Enter specific rejection reasons that are immediately injected into the rejection email sent to the customer.
* **Thickbox Lightbox Previews:** View receipt screenshots securely via a built-in Thickbox pop-up.
* **Dashboard Widget:** A beautiful WordPress Dashboard widget to immediately surface orders requiring Instapay receipt approval.
* **Secure File Storage:** Receipts are stored in a dedicated protected folder.
* **Automatic Storage Cleanup (Cron):** A daily task deletes receipts on cancelled, failed and refunded orders after 30 days, with an optional retention period for paid orders.
* **Automatic Expiry:** Unpaid orders are cancelled and their stock released after a configurable number of hours (48 by default).
* **Updates from GitHub:** New releases appear on the WordPress Updates screen and are verified against their published SHA-256 checksum.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/instapay-gateway-for-egypt` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **WooCommerce > Settings > Payments**.
4. Find **Instapay (Egypt)** and click **Manage** to configure your gateway.

== Frequently Asked Questions ==

= Does this plugin support currencies other than EGP? =
No, the plugin intelligently hides itself if the customer's cart is not in Egyptian Pounds (EGP).

= How are receipt images secured? =
Images are stored in an `instapay_receipts` folder inside the uploads directory with randomized file names, and are only served through a protected viewer that checks the order key or account. The folder includes Apache and IIS deny rules; on Nginx, add a rule that denies direct access to `/wp-content/uploads/instapay_receipts/`.

= What happens to old receipt images? =
A daily task deletes receipts on cancelled, failed and refunded orders after 30 days. Receipts on paid orders are kept unless you set a retention period. Deleting the plugin from the Plugins screen removes all receipts and plugin data.

= How do updates work? =
The plugin checks the GitHub releases of mariomsamy/instapay-woo at most twice a day and offers new versions on the normal Updates screen. Only releases that publish the package ZIP together with its `.sha256` checksum are offered, and the download is verified against it before it is installed.

== Screenshots ==

1. Admin Order Verification & Quick Actions.
2. Orders List Custom Column.
3. Reject Confirmation Dialog.
4. Frontend Rejected Message.
5. Audit Order Notes.

== Changelog ==

= 1.3.0 =
* Fix: Receipt uploads crashed with a critical error on every site (infinite recursion in the upload directory filter).
* Fix: The gateway now appears in the WooCommerce block checkout (the default since WooCommerce 8.3).
* Fix: Uploads failed on Windows/IIS servers because the temporary file path was unslashed.
* Fix: Drag and drop now places the dropped image into the upload field.
* Change: New orders wait in On hold instead of Pending, so WooCommerce no longer cancels them after the hold-stock time, the customer receives the order email with Instapay instructions and an upload link, and the store receives the New order email.
* Change: Rejected receipts return the order to On hold, and the rejection reason is shown to the customer.
* Change: Accepting a payment uses WooCommerce payment completion: the paid date and transaction reference are recorded and the customer receives the order confirmation email.
* New: Optional transaction reference field, plus warnings when a receipt image or reference is reused on another order.
* New: Unpaid orders are cancelled after a configurable number of hours (default 48; 0 disables).
* New: Updates from GitHub releases with SHA-256 verification, and a release workflow that builds the package and checksum.
* New: Privacy policy text, personal data exporter and eraser, retention setting for paid receipts, and full data removal on uninstall.
* Security: Accept, reject, cancel and upload share an atomic per-order lock and re-check the order inside it, so double clicks, two managers or an upload during cancellation cannot apply twice or revive a cancelled order.
* Security: Accepting requires the receipt the manager was shown; a receipt replaced in the meantime must be reviewed first.
* Security: Upload rate limit per order, rejection of oversized image dimensions before decoding, guaranteed metadata stripping, and the review email no longer attaches the receipt unless enabled.
* Performance: Cleanup only loads Instapay orders that still have a receipt, in batches, and removes orphaned files.
* UI/UX: Redesigned customer payment panel with progress steps, copy buttons, image preview, upload progress and clear states; redesigned admin review box with the amount to check, upload age and a safer reject flow; RTL-ready styles; complete Arabic translation.

= 1.2.0 =
* Documentation: Condensed and reorganized the GitHub README for faster scanning and reduced duplication.
* Branding: Updated the plugin name, package, text domain, REST namespace, and translation filenames to Instapay Gateway for Egypt while retaining compatibility-sensitive PHP prefixes and saved-data keys.
* Security: Added nonce-protected receipt viewing with customer ownership, order-key, and manager capability checks.
* Security: Validated receipt paths against the protected receipt directory before viewing, attaching, or deleting files.
* Security: Hardened uploads with real image MIME checks, randomized filenames, a 5MB limit, allowed order-state checks, and per-order concurrency locks.
* Security: Re-encoded uploaded images to remove unnecessary camera metadata and optionally resize them.
* Security: Restricted quick actions to Instapay orders and required a valid receipt before accepting payment.
* Security: Moved the REST route to `instapay-gateway-for-egypt/v1/receipt`, disabled REST uploads by default, and added strict order validation.
* Compatibility: Replaced direct post-meta access with WooCommerce order CRUD for HPOS compatibility.
* Compatibility: Declared WooCommerce HPOS compatibility and added current WordPress/WooCommerce plugin headers.
* Reliability: Scheduled cleanup only during activation, cleared it during deactivation, and validated files before cleanup.
* Reliability: Removed duplicate AJAX registrations and prevented duplicate upload submissions.
* Reliability: Replaced the ineffective New Order attachment hook with a manager notification sent after a receipt is uploaded.
* Compatibility: Added Apache 2.4/2.2 and IIS receipt-directory deny rules; Nginx still requires server configuration.
* Translations: Regenerated the POT template, merged the Arabic and English catalogs, and updated Recipe Codes contact metadata.
* UI/UX: Moved all inline CSS and JavaScript into enqueued asset files.
* UI/UX: Improved responsive layout, focus feedback, accessible live upload status, file selection feedback, and stable admin actions.
* UI/UX: Improved receipt previews, status colors, mobile sizing, and error recovery.
* UI/UX: Hid manager actions that are invalid for the current order status.
* Privacy: REST receipt uploads now require an explicit administrator opt-in.
* Credits: Security hardening based on work contributed by Abdelrahman Elawadi (@abdelrahman-elawadi).

= 1.0.0 =
* Initial release on WordPress.org.

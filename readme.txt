=== Instapay Gateway for Egypt ===
Contributors: mariomsamy
Donate link: https://recipe.codes/
Tags: woocommerce, instapay, payment gateway, egypt, payment
Requires at least: 6.3
Tested up to: 7.0
WC requires at least: 8.5
WC tested up to: 10.7
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A professional, enterprise-grade Instapay payment gateway plugin for WooCommerce.

== Description ==

Instapay Gateway for Egypt allows your customers to check out via Instapay with receipt screenshot uploads, secure storage, and administrative review tools.

Developed by **Recipe Codes**
Author: Mario M. Samy

Security hardening in version 1.2.0 was contributed by Abdelrahman Elawadi (GitHub: @abdelrahman-elawadi). Recipe Codes and Mario M. Samy remain the plugin author and copyright owner.

### ✨ Features
* **Direct Instapay Deep Linking:** Seamless mobile experience allowing users to tap and pay directly through the Instapay app.
* **Drag-and-Drop Receipt Upload:** Modern, beautiful, and secure image uploader for users to attach their payment proofs after placing an order.
* **Smart Currency Restriction:** The gateway automatically hides itself for non-EGP currencies to prevent invalid transactions.
* **Auto Image Compression:** Receipt images are automatically compressed, resized, and converted to modern formats (saving bandwidth and disk space).
* **"Quick Action" Approval:** Approve, Reject, or Cancel pending payments instantly using AJAX buttons inside the order meta box.
* **Custom Rejection Reasons:** Enter specific rejection reasons that are immediately injected into the rejection email sent to the customer.
* **Thickbox Lightbox Previews:** View receipt screenshots securely via a built-in Thickbox pop-up.
* **Dashboard Widget:** A beautiful WordPress Dashboard widget to immediately surface orders requiring Instapay receipt approval.
* **Secure File Storage:** Receipts are stored in a dedicated protected folder.
* **Automatic Storage Cleanup (Cron):** A scheduled daily background task automatically deletes rejected/cancelled receipt images older than 30 days.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/instapay-gateway-for-egypt` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **WooCommerce > Settings > Payments**.
4. Find **Instapay (Egypt)** and click **Manage** to configure your gateway.

== Frequently Asked Questions ==

= Does this plugin support currencies other than EGP? =
No, the plugin intelligently hides itself if the customer's cart is not in Egyptian Pounds (EGP).

= How are receipt images secured? =
Images are stored in a custom `instapay_receipts` folder within the uploads directory, protected by strict `.htaccess` rules to prevent direct URL access.

= What happens to old receipt images? =
The plugin runs a daily background task (cron) that automatically deletes any rejected or cancelled receipt images that are older than 30 days to save server disk space.

== Screenshots ==

1. Admin Order Verification & Quick Actions.
2. Orders List Custom Column.
3. Reject Confirmation Dialog.
4. Frontend Rejected Message.
5. Audit Order Notes.

== Changelog ==

= 1.2.0 =
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

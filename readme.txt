=== Instapay WooCommerce Gateway ===
Contributors: mariomsamy
Donate link: https://recipe.codes/
Tags: woocommerce, instapay, payment gateway, egypt, payment
Requires at least: 5.8
Tested up to: 6.5
WC requires at least: 6.0
WC tested up to: 8.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A professional, enterprise-grade Instapay payment gateway plugin for WooCommerce.

== Description ==

Instapay WooCommerce Gateway allows your customers to seamlessly check out via Instapay (Egypt) with automated receipt screenshot uploads, secure storage, and advanced administrative dashboards.

Developed by **Recipe Codes**
Author: Mario M. Samy

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

1. Upload the plugin files to the `/wp-content/plugins/instapay-woo` directory, or install the plugin through the WordPress plugins screen directly.
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

= 1.0.0 =
* Initial release on WordPress.org.

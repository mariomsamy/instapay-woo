# Instapay WooCommerce Gateway 🚀

A professional, enterprise-grade Instapay payment gateway plugin for WooCommerce. Allow your customers to seamlessly check out via Instapay (Egypt) with automated receipt screenshot uploads, secure storage, and advanced administrative dashboards.

## ✨ Features

### 🛒 Customer Experience
- **Direct Instapay Deep Linking:** Seamless mobile experience allowing users to tap and pay directly through the Instapay app (`https://ipn.eg/S/...`).
- **Drag-and-Drop Receipt Upload:** Modern, beautiful, and secure image uploader for users to attach their payment proofs after placing an order.
- **Smart Currency Restriction:** The gateway automatically hides itself for non-EGP currencies to prevent invalid transactions.
- **Auto Image Compression:** Receipt images are automatically compressed, resized, and converted to modern formats (saving bandwidth and disk space).
- **100% Arabic & English Translation:** Full localization (`.po`/`.mo`) baked in for both standard texts and error messages.

### 💼 Administrator Control
- **"Quick Action" Approval:** Approve, Reject, or Cancel pending payments instantly using AJAX buttons inside the order meta box.
- **Custom Rejection Reasons:** Enter specific rejection reasons (e.g., "Image blurry") that are immediately injected into the rejection email sent to the customer.
- **Thickbox Lightbox Previews:** View receipt screenshots securely via a built-in Thickbox pop-up without leaving the order screen.
- **Dashboard Widget:** A beautiful WordPress Dashboard widget to immediately surface orders requiring Instapay receipt approval.
- **Custom Orders Column:** See at-a-glance if an order has a receipt attached directly from the main `WooCommerce > Orders` list.

### 🛡️ Security & Automation
- **Secure File Storage:** Receipts are stored in a dedicated protected folder (`/wp-content/uploads/instapay_receipts`) with strict `.htaccess` rules and direct access blocking.
- **Automatic Storage Cleanup (Cron):** A scheduled daily background task automatically deletes rejected/cancelled receipt images older than 30 days, saving hosting disk space.
- **Admin Email Attachments:** When a new Instapay order is placed, the receipt image is automatically attached to the "New Order" email sent to the store admin.
- **Headless Mobile Ready (REST API):** Includes a secure, built-in REST API endpoint (`POST /wp-json/wc/v3/instapay/upload`) to accept mobile application uploads.
- **Audit Logging:** Logs all admin actions (Accept, Reject + Reason) directly into the WooCommerce Order Notes.

## 📥 Installation

1. Download the latest `instapay-woo.zip` release.
2. Go to your WordPress Admin panel > **Plugins** > **Add New**.
3. Click **Upload Plugin** and select the `.zip` file.
4. Click **Install Now** and then **Activate**.
5. Navigate to **WooCommerce > Settings > Payments**.
6. Find **Instapay (Egypt)** and click **Manage** to configure your gateway.

## ⚙️ Configuration
In the gateway settings, you can configure:
- **Enable/Disable** the gateway.
- **Title & Description** seen by the customer at checkout.
- **Instapay Payment Address (IPA / Phone Number).**
- **Deep Linking Settings:** Enable direct links to open the Instapay app natively.
- **Advanced Options:** Enable automated rejection emails, audit logging, auto-compression, and automated storage cleanup.

## 🚀 Pushing to GitHub

To push this exact repository to your GitHub account from your local machine, run the following commands in your terminal:

```bash
# 1. Initialize Git (if not already done)
git init

# 2. Add all files
git add .

# 3. Make your first commit
git commit -m "Initial commit: Instapay WooCommerce Gateway (Production Ready)"

# 4. Link to your GitHub repository
git remote add origin https://github.com/mariomsamy/instapay-woo.git

# 5. Push to the main branch
git branch -M main
git push -u origin main
```

*(Note: Ensure you have created the empty repository on GitHub first!)*

## 📄 License
This plugin is licensed under the GPL-2.0 or later.

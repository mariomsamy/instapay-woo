# Instapay Gateway for Egypt

A manual Instapay payment gateway for WooCommerce stores that accept Egyptian pounds. Customers receive payment instructions, upload a receipt, and store managers approve or reject it from the order screen.

[Download the latest release](https://github.com/mariomsamy/instapay-woo/releases/latest) | [Release notes](RELEASE-NOTES.md) | [WordPress readme](readme.txt)

## Features

- Works with the WooCommerce block checkout and the classic checkout.
- Guided payment page: progress steps, one-tap copy for the amount, Instapay address and phone, QR code, optional mobile payment link, image preview and upload progress.
- Secure JPG, PNG, and WebP receipt uploads up to 5MB, with an optional transaction reference.
- Payment Review order status with accept, reject (with a reason shown to the customer), and cancel actions.
- Warnings when the same receipt image or transaction reference is used on more than one order.
- Orders wait in On hold, so customers get the order email with payment instructions; unpaid orders expire after a configurable time.
- Protected receipt viewing, randomized filenames, image metadata removal, and optional resizing.
- HPOS-compatible WooCommerce order storage.
- Dashboard review queue, order-list receipt indicator, audit notes, and manager email notifications.
- Automatic cleanup for receipts on old failed, cancelled, or refunded orders, and an optional retention period for paid orders.
- WordPress privacy tools support (export and erase) and full data removal on uninstall.
- Updates delivered from GitHub releases and verified against a SHA-256 checksum.
- Optional REST uploads, disabled by default.
- Complete Arabic and English translations, RTL-ready layout.

## Requirements

| Component | Minimum |
| --- | --- |
| WordPress | 6.3 |
| WooCommerce | 8.5 |
| PHP | 7.4 |
| Currency | EGP |

## Installation

1. Download the plugin ZIP from the [latest release](https://github.com/mariomsamy/instapay-woo/releases/latest).
2. In WordPress, open **Plugins > Add New > Upload Plugin**.
3. Upload the ZIP, install it, and activate it.
4. Open **WooCommerce > Settings > Payments > Instapay (Egypt)**.
5. Configure the payment address, phone number, QR code, and optional settings.

The gateway is shown only when the store currency is EGP.

## Receipt Security

Receipt access requires an authorized manager, the owning customer account, or a matching order key, together with a valid nonce. Uploaded files are validated by image content and must remain inside the dedicated receipt directory.

The plugin writes deny rules for Apache and IIS. Nginx administrators should also block direct public access to `/wp-content/uploads/instapay_receipts/`; receipts should be served only through the plugin's protected viewer.

## Updates

The plugin checks this repository's latest GitHub release at most every 12 hours and offers newer versions on the normal **Dashboard > Updates** screen, including automatic updates if you enable them. The `Update URI` header keeps WordPress.org from ever replacing it with a different plugin.

To publish a release:

1. Set the same version in the plugin header, `INSTAPAY_WOO_VERSION`, and `Stable tag` in `readme.txt`.
2. Create a GitHub release with the tag `vX.Y.Z`.
3. The **Release package** workflow builds `instapay-gateway-for-egypt-X.Y.Z.zip` and its `.sha256` file and attaches both to the release. Sites verify the ZIP against the checksum before installing it. A release without both files is not offered, so a release you attach by hand must include the `.sha256` too.

To turn off GitHub updates on a site, add `add_filter( 'instapay_woo_github_updates', '__return_false' );` to a must-use plugin.

## REST Uploads

Enable REST uploads only when needed under the gateway settings. Send a multipart `POST` request to:

```text
/wp-json/instapay-gateway-for-egypt/v1/receipt
```

Required fields: `order_id`, `order_key`, and the `instapay_receipt` file. Optional: `transaction_reference`. The same file, order-state, ownership and rate-limit checks used by browser uploads apply.

## Screenshots

<details>
<summary>View plugin screenshots</summary>

![Instapay Gateway screenshot 1](img/screenshots/1.png)
![Instapay Gateway screenshot 2](img/screenshots/2.png)
![Instapay Gateway screenshot 3](img/screenshots/3.png)
![Instapay Gateway screenshot 4](img/screenshots/4.png)
![Instapay Gateway screenshot 5](img/screenshots/5.png)
![Instapay Gateway screenshot 6](img/screenshots/6.png)
![Instapay Gateway screenshot 7](img/screenshots/7.png)
![Instapay Gateway screenshot 8](img/screenshots/8.png)
![Instapay Gateway screenshot 9](img/screenshots/9.png)

</details>

## العربية

إضافة دفع يدوي عبر إنستاباي لمتاجر WooCommerce التي تستخدم الجنيه المصري. تعرض الإضافة بيانات الدفع للعميل، وتسمح له برفع صورة الإيصال، ثم تتيح لمدير المتجر مراجعة الإيصال وقبوله أو رفضه من صفحة الطلب.

**التثبيت:** حمّل ملف الإضافة من [أحدث إصدار](https://github.com/mariomsamy/instapay-woo/releases/latest)، ثم ارفعه من **إضافات > أضف جديد > رفع إضافة**. بعد التفعيل، انتقل إلى **WooCommerce > الإعدادات > المدفوعات > Instapay (Egypt)** لإدخال عنوان إنستاباي ورقم الهاتف ورابط رمز QR والإعدادات الاختيارية.

تعمل الإضافة مع صفحة الدفع بالمكوّنات (Blocks) وصفحة الدفع الكلاسيكية، وتصل التحديثات الجديدة من إصدارات GitHub مباشرةً إلى صفحة التحديثات في ووردبريس بعد التحقق من صحتها. يتم تعطيل رفع الإيصالات عبر REST API افتراضياً. يجب على مستخدمي Nginx منع الوصول المباشر إلى مجلد `/wp-content/uploads/instapay_receipts/` من إعدادات الخادم.

## Credits

Developed and copyrighted by [Recipe Codes](https://recipe.codes) / Mario M. Samy.

Security hardening in version 1.2.0 includes work contributed by [Abdelrahman Elawadi](https://github.com/abdelrahman-elawadi). Recipe Codes and Mario M. Samy remain the plugin author and copyright owner.

## License

GPL-2.0-or-later. See [GNU GPL 2.0](https://www.gnu.org/licenses/gpl-2.0.html).

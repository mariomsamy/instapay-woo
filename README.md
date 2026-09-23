# Instapay Gateway for Egypt 🚀

A professional, enterprise-grade Instapay payment gateway plugin for WooCommerce. Allow your customers to seamlessly check out via Instapay (Egypt) with automated receipt screenshot uploads, secure storage, and advanced administrative dashboards.

**👨‍💻 Developed by:** [Recipe Codes](https://recipe.codes)  
**🌐 Author:** Mario M. Samy

**Contributor credit:** Security hardening in version 1.2.0 includes work contributed by [Abdelrahman Elawadi](https://github.com/abdelrahman-elawadi) through his public fork. Recipe Codes and Mario M. Samy remain the plugin author and copyright owner.

*(Scroll down for Arabic | انزل للأسفل للغة العربية)*

---

## 🇬🇧 English Documentation

### ✨ Features

**🛒 Customer Experience**
- **Direct Instapay Deep Linking:** Seamless mobile experience allowing users to tap and pay directly through the Instapay app (`https://ipn.eg/S/...`).
- **Drag-and-Drop Receipt Upload:** Modern, beautiful, and secure image uploader for users to attach their payment proofs after placing an order.
- **Smart Currency Restriction:** The gateway automatically hides itself for non-EGP currencies to prevent invalid transactions.
- **Auto Image Compression:** Receipt images are automatically compressed, resized, and converted to modern formats (saving bandwidth and disk space).
- **Arabic & English Localization:** Translation catalogs are included; new strings fall back to English until their Arabic translations are completed.

**💼 Administrator Control**
- **"Quick Action" Approval:** Approve, Reject, or Cancel pending payments instantly using AJAX buttons inside the order meta box.
- **Custom Rejection Reasons:** Enter specific rejection reasons (e.g., "Image blurry") that are immediately injected into the rejection email sent to the customer.
- **Thickbox Lightbox Previews:** View receipt screenshots securely via a built-in Thickbox pop-up without leaving the order screen.
- **Dashboard Widget:** A beautiful WordPress Dashboard widget to immediately surface orders requiring Instapay receipt approval.
- **Custom Orders Column:** See at-a-glance if an order has a receipt attached directly from the main `WooCommerce > Orders` list.

**🛡️ Security & Automation**
- **Secure File Storage:** Receipts are stored in a dedicated protected folder (`/wp-content/uploads/instapay_receipts`) with strict `.htaccess` rules and direct access blocking.
- **Automatic Storage Cleanup (Cron):** A scheduled daily background task automatically deletes rejected/cancelled receipt images older than 30 days, saving hosting disk space.
- **Admin Email Attachments:** After a receipt is uploaded, the store administrator receives a review notification with the validated receipt attached.
- **Headless Mobile Ready (REST API):** Optional receipt uploads are available at `POST /wp-json/instapay-gateway-for-egypt/v1/receipt` and are disabled by default until enabled by an administrator.
- **Audit Logging:** Logs all admin actions (Accept, Reject + Reason) directly into the WooCommerce Order Notes.

### 📸 Screenshots

<p align="center">
  <img src="img/screenshots/1.png" width="100%" />
  <img src="img/screenshots/2.png" width="100%" />
  <img src="img/screenshots/3.png" width="100%" />
  <img src="img/screenshots/4.png" width="100%" />
  <img src="img/screenshots/5.png" width="100%" />
  <img src="img/screenshots/6.png" width="100%" />
  <img src="img/screenshots/7.png" width="100%" />
  <img src="img/screenshots/8.png" width="100%" />
  <img src="img/screenshots/9.png" width="100%" />
</p>

### 📥 Installation
1. Download the latest `instapay-gateway-for-egypt.zip` release.
2. Go to your WordPress Admin panel > **Plugins** > **Add New**.
3. Click **Upload Plugin** and select the `.zip` file.
4. Click **Install Now** and then **Activate**.
5. Navigate to **WooCommerce > Settings > Payments**.
6. Find **Instapay (Egypt)** and click **Manage** to configure your gateway.

---

## 🇪🇬 التوثيق باللغة العربية (Arabic Documentation)

إضافة احترافية ومتقدمة لبوابة دفع إنستاباي الخاصة بووكومرس. تتيح لعملائك الدفع بسلاسة عبر إنستاباي (مصر) مع ميزات الرفع التلقائي لصور الإيصالات، التخزين الآمن، ولوحات تحكم إدارية متطورة.

**👨‍💻 تم التطوير بواسطة:** [Recipe Codes](https://recipe.codes)  
**🌐 المبرمج:** Mario M. Samy

### ✨ المميزات

**🛒 تجربة العميل**
- **الربط المباشر بتطبيق إنستاباي:** تجربة سلسة للهاتف المحمول تتيح للمستخدمين الدفع مباشرة عبر تطبيق إنستاباي (`https://ipn.eg/S/...`).
- **رفع الإيصالات بالسحب والإفلات:** واجهة حديثة وآمنة لرفع الصور ليتمكن المستخدمون من إرفاق إثبات الدفع الخاص بهم.
- **تقييد العملة الذكي:** تقوم البوابة بإخفاء نفسها تلقائياً للعملات غير الجنيه المصري (EGP) لمنع المعاملات غير الصالحة.
- **ضغط الصور التلقائي:** يتم ضغط صور الإيصالات وتغيير حجمها وتحويلها إلى تنسيقات حديثة تلقائياً (مما يوفر مساحة التخزين).
- **ترجمة كاملة للغتين العربية والإنجليزية:** دعم كامل للغات (`.po`/`.mo`) لجميع النصوص ورسائل الخطأ.

**💼 تحكم الإدارة**
- **إجراءات الموافقة السريعة:** قبول، رفض، أو إلغاء المدفوعات المعلقة فوراً باستخدام أزرار AJAX من داخل صفحة الطلب.
- **أسباب الرفض المخصصة:** يمكنك كتابة سبب محدد للرفض (مثل "الصورة غير واضحة") ليتم إرساله مباشرة في رسالة البريد الإلكتروني الخاصة بالرفض للعميل.
- **عرض الصور المنبثق (Thickbox):** عرض لقطات الإيصالات بأمان عبر نافذة منبثقة مدمجة دون مغادرة شاشة الطلب.
- **أداة لوحة التحكم:** أداة رائعة في لوحة تحكم ووردبريس لعرض الطلبات التي تحتاج إلى مراجعة إيصال إنستاباي فوراً.
- **عمود مخصص للطلبات:** رؤية سريعة إذا ما كان الطلب يحتوي على إيصال مرفق مباشرة من قائمة `ووكومرس > الطلبات`.

**🛡️ الأمان والأتمتة**
- **تخزين آمن للملفات:** يتم تخزين الإيصالات في مجلد محمي مخصص (`/wp-content/uploads/instapay_receipts`) بفضل قواعد `.htaccess` صارمة.
- **تنظيف مساحة التخزين تلقائياً:** مهمة يومية مبرمجة (Cron) تحذف إيصالات الطلبات المرفوضة/الملغاة التي مر عليها أكثر من 30 يوماً.
- **مرفقات بريد الإدارة:** بعد رفع الإيصال، يتلقى مدير المتجر إشعاراً للمراجعة مرفقاً به الإيصال الذي تم التحقق منه.
- **دعم تطبيقات الهاتف (REST API):** تتوفر نقطة نهاية اختيارية (`POST /wp-json/instapay-gateway-for-egypt/v1/receipt`) ويتم تعطيلها افتراضياً حتى يقوم مدير المتجر بتفعيلها.
- **سجلات التدقيق:** تسجيل جميع إجراءات المشرفين (قبول، رفض + السبب) مباشرة في ملاحظات طلب ووكومرس.

### 📸 لقطات الشاشة

<p align="center">
  <img src="img/screenshots/1.png" width="100%" />
  <img src="img/screenshots/2.png" width="100%" />
  <img src="img/screenshots/3.png" width="100%" />
  <img src="img/screenshots/4.png" width="100%" />
  <img src="img/screenshots/5.png" width="100%" />
  <img src="img/screenshots/6.png" width="100%" />
  <img src="img/screenshots/7.png" width="100%" />
  <img src="img/screenshots/8.png" width="100%" />
  <img src="img/screenshots/9.png" width="100%" />
</p>

### 📥 طريقة التثبيت
1. قم بتنزيل أحدث إصدار من ملف `instapay-gateway-for-egypt.zip`.
2. اذهب إلى لوحة تحكم ووردبريس > **إضافات** > **أضف جديد**.
3. انقر على **رفع إضافة** واختر ملف `.zip`.
4. انقر على **التنصيب الآن** ثم **تفعيل**.
5. انتقل إلى **ووكومرس > الإعدادات > المدفوعات**.
6. ابحث عن **إنستاباي (مصر)** وانقر على **إدارة** لضبط إعدادات البوابة الخاصة بك.

---
## Release Notes

### 1.2.0

- Integrated and extended Abdelrahman Elawadi's receipt-security contribution without changing the original Recipe Codes/Mario M. Samy ownership or copyright.
- Updated the plugin name, package, text domain, REST namespace, and translation filenames to Instapay Gateway for Egypt; compatibility-sensitive PHP prefixes and saved-data keys remain unchanged.
- Added authorization and nonce checks to receipt viewing, with strict protected-directory path validation.
- Hardened image uploads with magic-byte MIME verification, randomized names, size limits, metadata removal, order-state validation, and per-order upload locking.
- Added HPOS-compatible order metadata access and declared WooCommerce HPOS compatibility.
- Restricted manager quick actions and blocked payment acceptance when no valid receipt exists.
- Made REST uploads opt-in, moved them to a plugin-owned namespace, and applied the same order and upload protections as the browser flow.
- Corrected cron lifecycle handling and secure receipt cleanup.
- Replaced the ineffective New Order email attachment hook with a manager review email sent after successful receipt upload.
- Added compatible receipt-directory deny files for Apache and IIS while documenting the Nginx configuration requirement.
- Regenerated the translation template and merged the Arabic and English catalogs with Recipe Codes metadata.
- Removed duplicate AJAX handlers and all embedded/inline CSS and JavaScript.
- Added dedicated, conditionally enqueued `assets/css/gateway.css` and `assets/js/gateway.js` files with jQuery declared as a dependency.
- Improved responsive payment instructions, keyboard focus, upload feedback, accessible status announcements, error recovery, receipt previews, and admin action controls.
- Updated compatibility metadata and prepared a clean WordPress.org release package without hidden macOS files.

See `RELEASE-NOTES.md` for verification evidence and known test limitations.

---
## 📄 License | الترخيص
This plugin is licensed under the GPL-2.0 or later.
تم ترخيص هذه الإضافة بموجب GPL-2.0 أو أحدث.

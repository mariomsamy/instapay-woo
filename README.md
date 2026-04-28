# Instapay WooCommerce Gateway 🚀

A professional, enterprise-grade Instapay payment gateway plugin for WooCommerce. Allow your customers to seamlessly check out via Instapay (Egypt) with automated receipt screenshot uploads, secure storage, and advanced administrative dashboards.

*(Scroll down for Arabic | انزل للأسفل للغة العربية)*

## 🇬🇧 English Documentation

### ✨ Features

**🛒 Customer Experience**
- **Direct Instapay Deep Linking:** Seamless mobile experience allowing users to tap and pay directly through the Instapay app (`https://ipn.eg/S/...`).
- **Drag-and-Drop Receipt Upload:** Modern, beautiful, and secure image uploader for users to attach their payment proofs after placing an order.
- **Smart Currency Restriction:** The gateway automatically hides itself for non-EGP currencies to prevent invalid transactions.
- **Auto Image Compression:** Receipt images are automatically compressed, resized, and converted to modern formats (saving bandwidth and disk space).
- **100% Arabic & English Translation:** Full localization (`.po`/`.mo`) baked in for both standard texts and error messages.

**💼 Administrator Control**
- **"Quick Action" Approval:** Approve, Reject, or Cancel pending payments instantly using AJAX buttons inside the order meta box.
- **Custom Rejection Reasons:** Enter specific rejection reasons (e.g., "Image blurry") that are immediately injected into the rejection email sent to the customer.
- **Thickbox Lightbox Previews:** View receipt screenshots securely via a built-in Thickbox pop-up without leaving the order screen.
- **Dashboard Widget:** A beautiful WordPress Dashboard widget to immediately surface orders requiring Instapay receipt approval.
- **Custom Orders Column:** See at-a-glance if an order has a receipt attached directly from the main `WooCommerce > Orders` list.

**🛡️ Security & Automation**
- **Secure File Storage:** Receipts are stored in a dedicated protected folder (`/wp-content/uploads/instapay_receipts`) with strict `.htaccess` rules and direct access blocking.
- **Automatic Storage Cleanup (Cron):** A scheduled daily background task automatically deletes rejected/cancelled receipt images older than 30 days, saving hosting disk space.
- **Admin Email Attachments:** When a new Instapay order is placed, the receipt image is automatically attached to the "New Order" email sent to the store admin.
- **Headless Mobile Ready (REST API):** Includes a secure, built-in REST API endpoint (`POST /wp-json/wc/v3/instapay/upload`) to accept mobile application uploads.
- **Audit Logging:** Logs all admin actions (Accept, Reject + Reason) directly into the WooCommerce Order Notes.

### 📥 Installation
1. Download the latest `instapay-woo.zip` release.
2. Go to your WordPress Admin panel > **Plugins** > **Add New**.
3. Click **Upload Plugin** and select the `.zip` file.
4. Click **Install Now** and then **Activate**.
5. Navigate to **WooCommerce > Settings > Payments**.
6. Find **Instapay (Egypt)** and click **Manage** to configure your gateway.

---

## 🇪🇬 التوثيق باللغة العربية (Arabic Documentation)

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
- **مرفقات بريد الإدارة:** عند إنشاء طلب إنستاباي جديد، تُرفق صورة الإيصال تلقائياً ببريد "الطلب الجديد" المُرسل إلى مدير المتجر.
- **دعم تطبيقات الهاتف (REST API):** يتضمن نقطة نهاية API مدمجة (`POST /wp-json/wc/v3/instapay/upload`) لاستقبال الإيصالات المرفوعة عبر تطبيقات الهواتف المحمولة.
- **سجلات التدقيق:** تسجيل جميع إجراءات المشرفين (قبول، رفض + السبب) مباشرة في ملاحظات طلب ووكومرس.

### 📥 طريقة التثبيت
1. قم بتنزيل أحدث إصدار من ملف `instapay-woo.zip`.
2. اذهب إلى لوحة تحكم ووردبريس > **إضافات** > **أضف جديد**.
3. انقر على **رفع إضافة** واختر ملف `.zip`.
4. انقر على **التنصيب الآن** ثم **تفعيل**.
5. انتقل إلى **ووكومرس > الإعدادات > المدفوعات**.
6. ابحث عن **إنستاباي (مصر)** وانقر على **إدارة** لضبط إعدادات البوابة الخاصة بك.

---
## 📄 License | الترخيص
This plugin is licensed under the GPL-2.0 or later.
تم ترخيص هذه الإضافة بموجب GPL-2.0 أو أحدث.

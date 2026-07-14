# palgoal/media-library

مكتبة وسائط (Media Library) مستقلة لـ Laravel: رفع، تصفح، بحث/فلترة، وتعديل ميتاداتا للملفات، بالإضافة إلى منتقي وسائط (Media Picker) قابل لإعادة الاستخدام في أي فورم — Single أو Multiple.

هذا المستودع **مستقل بالكامل** ولا يعتمد على أي مشروع Laravel بعينه. لا يفترض وجود `App\Models\User`، ولا أي View أو Route أو ملف CSS/JS خارج هذه الحزمة — كل ما هو خاص بالتطبيق المضيف يمرّ عبر `config/media-library.php`.

## المحتوى

- **Model**: `Palgoal\MediaLibrary\Models\Media` (جدول `media`)
- **Controller**: `Palgoal\MediaLibrary\Http\Controllers\MediaController` (index/store/show/edit/update/destroy/bulkDestroy)
- **Resource**: `Palgoal\MediaLibrary\Http\Resources\MediaResource`
- **Policy**: `Palgoal\MediaLibrary\Policies\MediaPolicy` (قابلة للاستبدال بالكامل)
- **Support**: `Palgoal\MediaLibrary\Support\MediaPathNormalizer` (أداة مساعدة لتحويل مسارات نصية قديمة إلى `media_id` عند الترحيل من نظام آخر)
- **Migration**: إنشاء جدول `media`
- **Routes**: صفحة المكتبة الكاملة + JSON API، تحت بادئة قابلة للتهيئة (افتراضياً `/media-library`)
- **Views**: صفحة المكتبة، مكوّن الاختيار `<x-media-library::picker>`، مودال الاختيار العام، Layout بسيطة مستقلة (Tailwind عبر CDN) تعمل دون أي ثيم خارجي
- **JS**: `media-library.js` (صفحة المكتبة) و `media-picker.js` (المودال القابل لإعادة الاستخدام) — كلاهما يعتمد فقط على `window.MEDIA_CONFIG`، بلا أي مسار مربوط بمشروع بعينه

## المتطلبات

| المتطلب | الإصدار |
|---|---|
| PHP | ‎8.1 فأعلى |
| Laravel | 10.x أو 11.x أو 12.x |
| قاعدة بيانات | MySQL / MariaDB / PostgreSQL / SQLite (انظر ملاحظات التوافق أدناه) |

## التثبيت

### من Packagist (بعد النشر)

```bash
composer require palgoal/media-library
```

> ملاحظة: هذه الحزمة لم تُنشر على Packagist بعد وقت كتابة هذا الملف. إلى أن يتم النشر، استخدم أحد الخيارين التاليين.

### كمستودع VCS مباشرة من GitHub

أضف الحزمة إلى `composer.json` الخاص بمشروعك:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/palgooal/Palgoal-Laravel-Media-Library"
        }
    ],
    "require": {
        "palgoal/media-library": "dev-main"
    }
}
```

ثم:

```bash
composer update palgoal/media-library
```

### كـ path repository (تطوير محلي / مونوريبو)

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../path/to/Palgoal-Laravel-Media-Library"
        }
    ],
    "require": {
        "palgoal/media-library": "*"
    }
}
```

## الإعداد بعد التثبيت

الحزمة تُسجَّل تلقائياً عبر Laravel Package Discovery (`extra.laravel.providers` في `composer.json`). لا حاجة لإضافة الـ Service Provider يدوياً.

### ١. انشر الإعدادات والأصول والـ Views حسب الحاجة

```bash
php artisan vendor:publish --tag=media-library-config      # config/media-library.php
php artisan vendor:publish --tag=media-library-assets      # public/vendor/media-library/js/*
php artisan vendor:publish --tag=media-library-views        # اختياري، لتخصيص الشكل
php artisan vendor:publish --tag=media-library-migrations   # اختياري، إذا أردت تعديل الـ migration نفسها
```

إذا لم تنشر `media-library-migrations`، ستُحمَّل الـ migration تلقائياً من داخل الحزمة عند تشغيل `migrate` — لا حاجة لنسخها يدوياً في الحالة العادية.

### ٢. شغّل الـ migration

```bash
php artisan migrate
```

### ٣. اربط التخزين إذا كنت تستخدم disk "public" (الافتراضي)

```bash
php artisan storage:link
```

### ٤. اضبط `config/media-library.php` حسب مشروعك

أهم المفاتيح:

- `route_prefix` — بادئة الروابط (افتراضياً `media-library`).
- `middleware` — أضف الـ guard/الصلاحيات الخاصة بلوحتك (مثال: `['web', 'auth', 'can:access-admin']`).
- `disk` / `directory` — أين تُخزَّن الملفات فعلياً.
- `max_upload_size_kb`, `allowed_mimes`, `allowed_mimetypes` — قيود الرفع (**SVG غير مسموح افتراضياً**، انظر [مخاطر SVG](#مخاطر-svg)).
- `allowed_types` — القيم المسموحة لفلتر `type` في الـ API (`image|video|audio|document|other`).
- `user_model` — كلاس المستخدم المستخدم في علاقة `uploader()` (انظر [تخصيص User model](#تخصيص-user-model)).
- `policy` / `register_policy` — انظر [تخصيص Policy](#تخصيص-policy).

## استخدام صفحة المكتبة

الرابط الافتراضي: `/media-library` (اسم الـ route: `media-library.page`).
الـ API: `/media-library/media/*` (كل أسماء الـ routes تبدأ بـ `media-library.media.`).

الصفحة الكاملة (Blade + JS) جاهزة للاستخدام مباشرة بتصميم Tailwind مستقل، ويمكنك تخصيصها بنشر الـ views وتعديلها لتستخدم Layout مشروعك الخاص.

## استخدام منتقي الوسائط (Media Picker)

أضف المودال العام **مرة واحدة فقط** في نهاية الـ layout الرئيسي لمشروعك:

```blade
@include('media-library::partials.picker-modal')
```

### Single Picker

```blade
<x-media-library::picker name="logo" label="الشعار" />
```

يُنشئ حقل `<input type="hidden" name="logo">` يحمل `id` العنصر المختار من جدول `media`، بالإضافة إلى زر يفتح المودال ومعاينة مصغّرة.

### Multiple Picker

```blade
<x-media-library::picker name="gallery" :multiple="true" label="معرض الصور" />
```

القيمة المخزَّنة في الحقل المخفي تكون IDs مفصولة بفواصل (`1,5,9`).

### تخصيص القيمة المخزَّنة

`storeValue` يتحكم فيما يُكتب داخل الحقل المخفي: `id` (افتراضي)، `path`، أو `url`.

```blade
<x-media-library::picker name="cover_url" :multiple="false" store-value="url" />
```

### الأحداث القابلة للربط في JS

بعد التأكيد يُطلق المودال حدثين على `window`:

- `media-picker-confirmed` — الحمولة الكاملة `{ items, ids, values, storeValue, targetInputId }`.
- `media-selected` (للتوافق مع إصدارات سابقة) — أول عنصر مختار فقط.

```js
window.addEventListener('media-picker-confirmed', (e) => {
    console.log(e.detail.items);
});
```

## تخصيص Policy

الـ Policy الافتراضية (`Palgoal\MediaLibrary\Policies\MediaPolicy`) **تسمح لأي مستخدم مسجّل دخول بعرض/تعديل/حذف أي عنصر وسائط، بغض النظر عمّن رفعه**. هذا قرار افتراضي متعمَّد يناسب لوحة تحكم بمستخدم واحد أو فريق موثوق — وليس افتراضاً آمناً لتطبيق متعدد المستخدمين غير الموثوقين. راجع `SECURITY.md` لتفاصيل أوسع.

لتخصيصها:

```php
// config/media-library.php بعد النشر
'policy' => \App\Policies\MyMediaPolicy::class,
```

أو عطّل التسجيل التلقائي للـ Policy وسجّلها بنفسك:

```php
// config/media-library.php
'register_policy' => false,
```

```php
// أحد Service Providers الخاصة بتطبيقك
Gate::policy(\Palgoal\MediaLibrary\Models\Media::class, \App\Policies\MyMediaPolicy::class);
```

مثال على Policy تقيّد الحذف/التعديل بصاحب الملف فقط:

```php
class MyMediaPolicy
{
    public function viewAny($user): bool { return (bool) $user; }
    public function view($user, Media $media): bool { return (bool) $user; }
    public function create($user): bool { return (bool) $user; }
    public function update($user, Media $media): bool { return $media->uploader_id === $user->id; }
    public function delete($user, Media $media): bool { return $media->uploader_id === $user->id; }
}
```

## تخصيص User model

علاقة `Media::uploader()` تستخدم — بالترتيب — `config('media-library.user_model')` ثم `config('auth.providers.users.model')`. إن لم يُحدَّد أيّهما، أو كان الكلاس المحدَّد غير موجود فعلياً، تُرمى `RuntimeException` واضحة بدل افتراض `App\Models\User` (الحزمة لا تفترض بنية تطبيق بعينه).

```php
// config/media-library.php
'user_model' => \App\Models\Admin::class,
```

## تخصيص disk وdirectory

```php
// config/media-library.php
'disk'      => 's3',       // أي disk معرَّف في config/filesystems.php
'directory' => 'uploads',  // الملفات تُخزَّن تحت {disk}/{directory}/{Year}/{Month}/{file}
```

إذا كان الـ disk المستخدم لا يدعم توليد روابط (`url()`)، فإن `Media::url` (accessor) تعيد `null` بدل رمي استثناء — تحقق من القيمة قبل استخدامها إن كنت تستخدم disk غير قياسي.

## مخاطر SVG

SVG **غير مسموح افتراضياً** في `allowed_mimes` / `allowed_mimetypes`. ملف SVG يمكن أن يحتوي `<script>` أو خصائص أحداث (`onload`, `onerror`) تُنفَّذ عند فتح الملف مباشرة أو تضمينه — وهي ثغرة Stored XSS معروفة وشائعة في أنظمة رفع الملفات. هذه الحزمة **لا تحتوي أداة Sanitization لملفات SVG**.

لا تُفعّل SVG إلا بعد إضافة تعقيم فعلي (مثل [`enshrined/svg-sanitize`](https://github.com/darylldoyle/svg-sanitizer)) يُطبَّق على كل ملف قبل حفظه. التفاصيل الكاملة في `SECURITY.md`.

## توافق جدول `media` الموجود مسبقاً

الـ migration تتحقق عبر `Schema::hasTable('media')` وتتجاهل الإنشاء إن كان الجدول موجوداً مسبقاً — **لكنها لا تتحقق من تطابق الأعمدة**. إن كان لديك جدول `media` سابق (من حزمة أخرى أو كودك الخاص)، لن تفشل الـ migration، لكنك قد تواجه أخطاء SQL لاحقاً (عمود غير موجود) عند استخدام الـ Model/Controller إن كان المخطط غير متوافق. تحقّق من أعمدة الجدول الموجود فعلياً قبل التثبيت فوقه؛ لا تفترض عدم وجود أي تعارض لمجرد نجاح الـ migration بصمت. راجع تعليقات ملف الـ migration نفسه لمزيد من التفاصيل.

## Upgrade Guide

هذا أول تدقيق (audit) رسمي للحزمة بعد استقلالها في مستودعها الخاص؛ لا يوجد إصدار موسوم سابق. عند الترقية من نسخة محلية أقدم غير موسومة (منسوخة يدوياً من PalgooalWeb مثلاً)، انتبه للتغييرات التالية:

- **SVG لم يعد مسموحاً افتراضياً.** إن كنت تعتمد عليه، أضفه صراحة إلى `allowed_mimes`/`allowed_mimetypes` بعد التأكد من وجود تعقيم فعلي.
- **فلتر `type` أصبح مُتحقَّقاً منه.** قيمة خارج `image|video|audio|document|other` تُعيد الآن `422` بدل نتيجة فارغة بصمت.
- **`Media::uploader()` يرمي استثناءً** بدل الرجوع الصامت إلى `App\Models\User` إن لم تضبط `user_model`/`auth.providers.users.model`.
- راجع `CHANGELOG.md` للقائمة الكاملة.

## Troubleshooting

**الصور لا تظهر بعد الرفع (404 على الرابط)**
تأكد من تشغيل `php artisan storage:link` إن كنت تستخدم disk `public`، ومن أن `APP_URL` مضبوط بشكل صحيح.

**`419 Page Expired` عند الرفع من صفحة المكتبة**
تأكد من أن `window.MEDIA_CONFIG.csrfToken` يصل فعلياً (راجع `<meta name="csrf-token">` في الـ layout المنشور)، ومن أن الجلسة (session) تعمل بشكل صحيح خلف أي بروكسي/CDN.

**`403` عند محاولة الحذف رغم تسجيل الدخول**
هذا متوقَّع إن استبدلت الـ Policy الافتراضية بأخرى تقيّد الحذف على صاحب الملف أو دور معيّن — راجع [تخصيص Policy](#تخصيص-policy).

**خطأ SQL يخص عمود غير موجود في جدول `media`**
غالباً يعني وجود جدول `media` سابق بمخطط مختلف — راجع [توافق جدول media الموجود مسبقاً](#توافق-جدول-media-الموجود-مسبقاً).

**رفع الملفات يفشل بصمت أو يظهر خطأ عام**
شغّل الطلب مع `Accept: application/json` وراجع نص الخطأ في الاستجابة (رسائل التحقق `mimes`/`mimetypes`/`max` تُعاد كما هي من Laravel Validation).

## Security considerations

راجع `SECURITY.md` للتفاصيل الكاملة، وأبرزها: SVG معطّل افتراضياً، الـ Policy الافتراضية غير مخصَّصة للمستخدمين غير الموثوقين، التحقق من الملفات يعتمد على `mimes` + `mimetypes` معاً (فحص المحتوى الفعلي وليس اسم الملف/الـ Content-Type القادم من المتصفح فقط)، ولا يوجد Foreign Key على `uploader_id` لأسباب تتعلق بقابلية النقل بين قواعد البيانات.

## الاختبارات

الحزمة تستخدم [Orchestra Testbench](https://github.com/orchestral/testbench):

```bash
composer install
composer test              # vendor/bin/phpunit
composer format-test       # vendor/bin/pint --test
```

## نقل الباكدج أو المساهمة

راجع `CONTRIBUTING.md`. هذا مستودع مستقل تماماً — لا تفترض وجود أي ملف من مشروع PalgooalWeb أو أي مشروع آخر عند العمل عليه أو نشره.

## الترخيص

[MIT](LICENSE)

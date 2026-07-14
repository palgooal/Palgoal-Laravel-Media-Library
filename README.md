# palgoal/media-library

مكتبة وسائط (Media Library) مستقلة لـ Laravel، مُستخرجة من مشروع PalgooalWeb لتكون قابلة لإعادة الاستخدام في أي مشروع Laravel آخر (10 / 11 / 12).

**هذا المجلد لا يؤثر إطلاقاً على مشروع PalgooalWeb الحالي** — كل ملفاته جديدة تماماً تحت `packages/palgoal/media-library/`، ولم يتم تعديل أو حذف أي ملف من `app/`, `routes/`, `resources/views/dashboard/`, أو `composer.json` الأصلي للمشروع. المكتبة الأصلية (`App\Models\Media` وما حولها) ما زالت تعمل كما هي بدون أي تغيير.

## المحتوى

- **Model**: `Palgoal\MediaLibrary\Models\Media` (جدول `media`)
- **Controller**: `Palgoal\MediaLibrary\Http\Controllers\MediaController` (index/store/show/update/destroy/bulkDestroy)
- **Resource**: `Palgoal\MediaLibrary\Http\Resources\MediaResource`
- **Policy**: `Palgoal\MediaLibrary\Policies\MediaPolicy` (قابلة للاستبدال)
- **Support**: `Palgoal\MediaLibrary\Support\MediaPathNormalizer` (لتحويل مسارات نصية قديمة إلى `media_id`)
- **Migration**: إنشاء جدول `media` (بحماية `Schema::hasTable()` — لن تفشل لو الجدول موجود مسبقاً)
- **Routes**: صفحة المكتبة الكاملة + JSON API، تحت بادئة قابلة للتهيئة (افتراضياً `/media-library`)
- **Views**: صفحة المكتبة (`media.blade.php`)، مكوّن الاختيار `<x-media-library::picker>`، مودال الاختيار العام (`partials/picker-modal.blade.php`)، Layout بسيطة مستقلة (Tailwind CDN) تعمل من دون أي ثيم خارجي
- **JS**: `media-library.js` (صفحة المكتبة الكاملة) و `media-picker.js` (المودال القابل لإعادة الاستخدام) — كلاهما نسخة طبق الأصل من ملفات PalgooalWeb، مبنيان على `window.MEDIA_CONFIG` فقط بدون أي مسارات مربوطة بالمشروع

## الاستخدام في مشروع Laravel جديد

### ١. أضف المكتبة كـ path repository في `composer.json` للمشروع الجديد

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../path/to/palgoal/media-library"
        }
    ],
    "require": {
        "palgoal/media-library": "*"
    }
}
```

ثم:

```bash
composer update palgoal/media-library
```

(بديل: انسخ مجلد `packages/palgoal/media-library` كاملاً إلى المشروع الجديد، أو ارفعه إلى مستودع Git خاص واستخدمه كـ `"type": "vcs"` بدلاً من `"path"`.)

### ٢. انشر الإعدادات والأصول

```bash
php artisan vendor:publish --tag=media-library-config
php artisan vendor:publish --tag=media-library-assets
php artisan vendor:publish --tag=media-library-views   # اختياري، لتخصيص الشكل
php artisan migrate
php artisan storage:link   # إذا كنت تستخدم disk "public" (الافتراضي)
```

### ٣. اضبط `config/media-library.php` حسب مشروعك

- `middleware` — أضف الـ guard الخاص بلوحتك (auth، صلاحيات، إلخ)
- `policy` — اربطه بنظام الصلاحيات الحالي لديك إذا لم يكن نظام "مستخدم مسجّل دخول = يقدر يدير الوسائط" كافياً
- `disk` / `directory` / حدود الرفع حسب الحاجة

### ٤. افتح صفحة المكتبة

الافتراضي: `/media-library` (باسم route: `media-library.page`)
API الوسائط: `/media-library/media/*` (أسماء routes تبدأ بـ `media-library.media.`)

### ٥. استخدم منتقي الوسائط في أي فورم

```blade
{{-- مرة واحدة فقط في نهاية الـ layout الرئيسي --}}
@include('media-library::partials.picker-modal')
```

```blade
{{-- في أي فورم --}}
<x-media-library::picker name="logo" label="الشعار" />
<x-media-library::picker name="gallery" :multiple="true" label="معرض الصور" />
```

## دمجها (اختيارياً) داخل PalgooalWeb نفسه للاختبار

المشروع الحالي عنده بالفعل مكتبة وسائط داخلية (`App\Models\Media` + `dashboard.media` + `x-dashboard.media-picker`) تعمل تحت `/admin/media-library`. الباكدج الجديد يستخدم namespace مختلف (`Palgoal\MediaLibrary\*`) وبادئة routes مختلفة (`media-library.*` بدل `media.*`)، لذلك **لا تعارض بينهما لو تم تفعيل الاثنين معاً**. لتجربته داخل نفس المشروع دون المساس بأي شيء قائم:

1. أضف `repositories` + `require` كما في الخطوة ١ أعلاه، مع الإشارة لمسار `./packages/palgoal/media-library`
2. شغّل `composer update palgoal/media-library`
3. لن يحدث شيء تلقائياً بعدها — الجدول موجود مسبقاً فتتجاهله الـ migration، والـ routes الجديدة على بادئة منفصلة، والـ Model/Controller بأسماء كلاسات مختلفة تماماً

**لم يتم تنفيذ هذه الخطوة في هذه الجلسة** — الباكدج بُني بمعزل تام عن `composer.json` الأصلي، لتبقى بيئة PalgooalWeb الحالية كما هي بدون أي احتمال عطل، وتترك القرار لك متى تريد ربطها.

## نقل الباكدج لمشروع آخر بالكامل

انسخ مجلد `packages/palgoal/media-library` وحده — لا يعتمد على أي ملف آخر من PalgooalWeb. أو ارفعه لمستودع Git منفصل (`git subtree split --prefix=packages/palgoal/media-library -b media-library-standalone`) ليصبح باكدج مستقل بتاريخ Git خاص به، جاهز للنشر على Packagist أو استخدامه كـ VCS repository في أي مشروع.

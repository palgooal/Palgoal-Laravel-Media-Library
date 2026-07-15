# ربط الوسائط بأي Model — `Concerns\HasMedia`

هذا المستند يشرح ميزة إضافية **اختيارية بالكامل** فوق الحزمة الأساسية: ربط عناصر من مكتبة الوسائط (`media`) بأي Model في تطبيقك (منتج، مستخدم، شركة، مقال...) عبر Trait واحد، بدون أي حزمة خارجية وبدون كسر أي سلوك حالي للحزمة.

> إن كنت تستخدم الحزمة فقط للرفع/التصفح/الـ Picker، **لا تحتاج قراءة هذا المستند إطلاقاً** — لا شيء هنا يُفعَّل تلقائياً.

## لماذا Trait + جدول Pivot عام (وليس عمود URL/مسار)؟

أفضل ممارسة هنا هي حفظ **`media.id`** (عبر جدول `mediables`)، وليس حفظ `url` أو `file_path` كنص داخل جدول Model الخاص بك. السبب:

- `media.id` مرجع مستقر: يبقى صحيحاً حتى لو تغيّر الـ disk، الدومين، أو مسار التخزين لاحقاً.
- الحذف يبقى متسقاً: حذف عنصر Media من المكتبة يحذف تلقائياً كل الروابط إليه (`cascadeOnDelete` على `media_id`) بدل ترك نصوص مسارات معطوبة متناثرة في جداول متعددة.
- تُمكّنك من إعادة استخدام **نفس** الملف مع أكثر من Model/Collection بدون تكرار الرفع (انظر [إعادة استخدام نفس Media](#٨-إعادة-استخدام-نفس-media-مع-أكثر-من-model)).

## التثبيت (خطوة لمرة واحدة)

الجدول `mediables` **لا يُحمَّل تلقائياً**. فعّله فقط إن كنت ستستخدم `HasMedia`:

```bash
php artisan vendor:publish --tag=media-library-relations-migration
php artisan migrate
```

هذا وسم (tag) مستقل تماماً عن `media-library-migrations` (migration جدول `media` الأساسي) — تشغيل أحدهما لا يؤثر على الآخر، وإعادة تشغيل هذا الأمر أكثر من مرة يستبدل نفس الملف بدل تكراره.

## الاستخدام الأساسي

```php
use Illuminate\Database\Eloquent\Model;
use Palgoal\MediaLibrary\Concerns\HasMedia;

class Product extends Model
{
    use HasMedia;
}
```

هذا كل ما يلزم — لا migration خاصة بـ `Product`، لا علاقة تكتبها يدوياً.

### API الكاملة

```php
$product->media();                                  // MorphToMany — كل الوسائط، كل الـ Collections
$product->mediaCollection('gallery');                // Collection مرتبة حسب sort_order
$product->firstMedia('cover');                        // ?Media
$product->firstMediaUrl('cover');                     // ?string
$product->firstMediaUrl('cover', '/img/default.png'); // string — قيمة افتراضية عند الغياب
$product->attachMedia(5, 'gallery');                  // إضافة، لا يحذف الموجود — يعيد $this
$product->attachMedia([5, 8, 10], 'gallery');
$product->syncMedia([5, 8, 10], 'gallery');           // استبدال المجموعة المحددة فقط — يعيد $this
$product->detachMedia(5);                             // إزالة من كل الـ Collections
$product->detachMedia(5, 'gallery');                  // إزالة من gallery فقط
$product->clearMediaCollection('gallery');            // إفراغ المجموعة (لا يحذف ملف الوسيط نفسه)
$product->hasMedia('cover');                          // bool
```

---

## ١. صورة مفردة لمنتج

**Model:**

```php
class Product extends Model
{
    use HasMedia;
}
```

**Blade (نموذج التعديل):**

```blade
@include('media-library::partials.picker-modal')

<form method="POST" action="{{ route('products.update', $product) }}">
    @csrf
    @method('PUT')

    <x-media-library::picker
        name="cover_media_id"
        label="صورة المنتج"
        :value="$product->firstMedia('cover')?->id"
    />

    <button type="submit">حفظ</button>
</form>
```

**Controller:**

```php
use Illuminate\Validation\Rule;

public function update(Request $request, Product $product)
{
    $validated = $request->validate([
        'cover_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
    ]);

    if (! empty($validated['cover_media_id'])) {
        $product->syncMedia([$validated['cover_media_id']], 'cover');
    } else {
        $product->clearMediaCollection('cover');
    }

    return back()->with('success', 'تم الحفظ.');
}
```

**العرض:**

```blade
<img src="{{ $product->firstMediaUrl('cover', asset('images/placeholder.png')) }}" alt="{{ $product->name }}">
```

---

## ٢. Product Gallery (معرض صور منتج)

**Blade:**

```blade
<x-media-library::picker
    name="gallery_media_ids"
    label="معرض الصور"
    :multiple="true"
    :value="$product->mediaCollection('gallery')->pluck('id')->all()"
/>
```

**Controller:**

```php
use Palgoal\MediaLibrary\Support\MediaSelection;

public function update(Request $request, Product $product)
{
    $ids = MediaSelection::parse($request->input('gallery_media_ids'));

    validator(['gallery_media_ids' => $ids], [
        'gallery_media_ids' => ['array'],
        'gallery_media_ids.*' => ['integer', Rule::exists('media', 'id')],
    ])->validate();

    $product->syncMedia($ids, 'gallery');

    return back()->with('success', 'تم تحديث المعرض.');
}
```

**العرض:**

```blade
@foreach ($product->mediaCollection('gallery') as $image)
    <img src="{{ $image->url }}" alt="{{ $image->alt }}">
@endforeach
```

---

## ٣. Company Logo

مطابق تماماً لمثال "صورة مفردة"، بمجموعة باسم مختلف فقط — لا حاجة لأي كود إضافي:

```php
$company->attachMedia($mediaId, 'logo');
// ...
$company->firstMediaUrl('logo', asset('images/default-logo.png'));
```

---

## ٤. User Avatar

```php
class User extends Model
{
    use HasMedia;
}
```

```php
$user->syncMedia([$request->integer('avatar_media_id')], 'avatar');
```

```blade
<img class="rounded-full" src="{{ $user->firstMediaUrl('avatar', asset('images/default-avatar.png')) }}">
```

---

## ٥. Documents (مستندات متعددة، بلا صور مصغّرة بالضرورة)

نفس آلية الـ Gallery — الاسم `documents` مجرد Collection أخرى، لا فرق برمجي:

```php
$deal->syncMedia(MediaSelection::parse($request->input('document_media_ids')), 'documents');
```

```blade
@foreach ($deal->mediaCollection('documents') as $doc)
    <a href="{{ $doc->url }}" target="_blank">{{ $doc->file_original_name }} ({{ $doc->readable_size }})</a>
@endforeach
```

---

## ٦. Attachments (مرفقات عامة على أي Model)

نفس الفكرة تماماً — استخدم أي اسم Collection يناسب سياقك (`attachments`, `banners`, ...). لا توجد قائمة مغلقة بأسماء الـ Collections داخل الحزمة عمداً، لأن كل مشروع يحتاج تسميات مختلفة.

---

## ٧. ترتيب Gallery (إعادة الترتيب)

لا توجد دالة `reorder()` منفصلة — استدعِ `syncMedia()` مجدداً بنفس المعرّفات وبترتيب جديد؛ ترتيب المصفوفة نفسه يصبح `sort_order`:

```php
// كان الترتيب: [5, 8, 10] → أصبح: [10, 5, 8]
$product->syncMedia([10, 5, 8], 'gallery');
```

عادة ما يأتي هذا الترتيب الجديد من واجهة سحب-وإفلات في الواجهة الأمامية ترسل مصفوفة IDs بالترتيب النهائي.

---

## ٨. إعادة استخدام نفس Media مع أكثر من Model

بما أن الربط يتم عبر جدول `mediables` منفصل (وليس عموداً على جدول `media` نفسه)، يمكن لنفس عنصر الوسائط أن يكون مرتبطاً بعدة Models وعدة Collections في آنٍ واحد، دون أي رفع مكرر:

```php
$logo = Media::find(42);

$product->attachMedia($logo->id, 'logo');
$company->attachMedia($logo->id, 'logo');
$anotherProduct->attachMedia($logo->id, 'gallery');
```

حذف `$logo` نفسه (من المكتبة) يحذف تلقائياً **كل** هذه الروابط الثلاثة (`cascadeOnDelete` على `media_id`) — لكنه لا يحذف أي شيء آخر من `Product`/`Company`.

---

## ٩. استخدام Morph Map

الحزمة تستخدم `$this->getMorphClass()` داخلياً (وليس اسم الكلاس الخام) في كل قراءة وكتابة على `mediables`، لذا فهي متوافقة تلقائياً مع `Relation::morphMap()` إن عرّفته في مشروعك (عادة في `AppServiceProvider::boot()`):

```php
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::morphMap([
    'product' => \App\Models\Product::class,
    'post'    => \App\Models\Post::class,
]);
```

بعد هذا التعريف، عمود `mediable_type` سيخزّن `product`/`post` بدل الاسم الكامل للكلاس — ويستمر كل شيء بالعمل دون أي تغيير في كود `HasMedia` نفسه. **مهم:** فعّل الـ morph map قبل أي بيانات حقيقية في `mediables`، أو نفّذ ترحيلاً يدوياً للقيم القديمة إن فعّلته على جدول يحتوي بيانات بالفعل (نفس القيد المعروف لأي جدول polymorphic في Laravel، وليس خاصاً بهذه الحزمة).

---

## ١٠. Soft Delete

قاعدة التنظيف التلقائي لصفوف `mediables` عند حذف الموديل المضيف:

| الحالة | ماذا يحدث لصفوف `mediables`؟ |
|---|---|
| Model عادي (بدون `SoftDeletes`) → `delete()` | تُحذف فوراً |
| Model يستخدم `SoftDeletes` → `delete()` (Soft Delete) | **لا تُحذف** — الصف الأصلي ما زال موجوداً (`deleted_at` فقط) |
| نفس الـ Model → `restore()` | تبقى كما كانت (لم تُحذف أصلاً) |
| نفس الـ Model → `forceDelete()` | تُحذف فعلياً |

```php
class Post extends Model
{
    use HasMedia, \Illuminate\Database\Eloquent\SoftDeletes;
}

$post->attachMedia($mediaId, 'gallery');

$post->delete();       // Soft delete — الربط ما يزال موجوداً
$post->restore();      // الربط ما يزال موجوداً (لم يُلمس إطلاقاً)
$post->forceDelete();  // الآن فقط يُحذف الربط
```

---

## ١١. تثبيت Migration الاختيارية

```bash
php artisan vendor:publish --tag=media-library-relations-migration
php artisan migrate
```

انظر [التثبيت](#التثبيت-خطوة-لمرة-واحدة) أعلاه للتفاصيل الكاملة، بما فيها لماذا هذه الخطوة مقصودة كاختيارية وليست جزءاً من `media-library-migrations`.

---

## ١٢. Validation

**لصورة مفردة:**

```php
'cover_media_id' => [
    'nullable',
    'integer',
    Rule::exists('media', 'id'),
],
```

**لاختيار متعدد (بعد التطبيع عبر `MediaSelection`):**

```php
use Illuminate\Validation\Rule;
use Palgoal\MediaLibrary\Support\MediaSelection;

$mediaIds = MediaSelection::parse($request->input('gallery_media_ids'));

validator(
    ['gallery_media_ids' => $mediaIds],
    [
        'gallery_media_ids' => ['array'],
        'gallery_media_ids.*' => ['integer', Rule::exists('media', 'id')],
    ]
)->validate();
```

**ملاحظة مهمة:** لا تُجري `HasMedia` أي Validation نيابة عنك. `attachMedia()`/`syncMedia()` يتحققان فقط من وجود الـ ID فعلياً في جدول `media` (كشبكة أمان تمنع خطأ Foreign Key)، وهذا **ليس بديلاً** عن التحقق الرسمي في الكنترولر (مثال أعلاه) — تجاوز صامت لهذا التحقق قد يسمح مثلاً بقبول قيم من مستخدم دون التأكد أنها من النوع الصحيح قبل استخدامها في منطق العمل.

---

## ١٣. استخدام `MediaSelection`

القيمة القادمة من حقل الـ Picker متعدد الاختيار هي سلسلة نصية مفصولة بفواصل (`"5,8,10"`). `MediaSelection::parse()` يحوّلها إلى مصفوفة أعداد صحيحة نظيفة، بلا أي استعلام لقاعدة البيانات:

```php
use Palgoal\MediaLibrary\Support\MediaSelection;

MediaSelection::parse('5,8,10');           // [5, 8, 10]
MediaSelection::parse([5, '8', 10]);       // [5, 8, 10]
MediaSelection::parse(' 5, 8, 8, abc, 10 '); // [5, 8, 10] — تكرار وقيم غير رقمية تُزال
MediaSelection::parse(null);               // []

$product->syncMedia(
    MediaSelection::parse($request->input('gallery_media_ids')),
    'gallery'
);
```

---

## ١٤. Picker مفرد (Single)

**لم يتغيّر أي سلوك للـ Picker نفسه.** هذا فقط يوضح كيف يتصل ناتجه بـ `HasMedia`:

```blade
<x-media-library::picker name="cover_media_id" label="الغلاف" />
```

الحقل المخفي يحمل `id` واحداً (أو فارغاً). في الكنترولر:

```php
$product->syncMedia(
    MediaSelection::parse($request->input('cover_media_id')),
    'cover'
);
```

---

## ١٥. Picker متعدد (Multiple)

```blade
<x-media-library::picker name="gallery_media_ids" :multiple="true" label="المعرض" />
```

الحقل المخفي يحمل IDs مفصولة بفواصل (`"1,5,9"`) — تماماً كما كان دائماً. في الكنترولر:

```php
$product->syncMedia(
    MediaSelection::parse($request->input('gallery_media_ids')),
    'gallery'
);
```

`storeValue="id"` يبقى الافتراضي كما هو؛ `HasMedia` مصمم للعمل مع IDs تحديداً (وليس مسارات أو روابط) للأسباب الموضحة في [أعلى هذا المستند](#لماذا-trait--جدول-pivot-عام-وليس-عمود-url-مسار).

---

## أسئلة شائعة

**هل يؤثر هذا على مشروع يستخدم الحزمة حالياً بدون `HasMedia`؟**
لا. لا شيء هنا يُحمَّل أو يُسجَّل تلقائياً — لا migration، لا تغيير في `Media`، لا تغيير في الـ Picker أو أسماء الـ routes أو الـ namespace.

**هل يمكن أن يتكرر نفس (media, model, collection) في `mediables`؟**
لا — يمنعه فهرس فريد (`mediables_unique_attachment`) على مستوى قاعدة البيانات، وتتعامل `attachMedia()` معه بصمت (لا خطأ) بدل رمي استثناء.

**ماذا لو احتجت التحقق العكسي: "أي الموديلات تستخدم هذا الوسيط؟"**
هذا خارج نطاق `HasMedia` حالياً (يبقى Model `Media` نفسه Generic 100% بلا علاقات عكسية مفترضة تجاه موديلات لا تعرفها الحزمة) — استعلم مباشرة عن جدول `mediables`:

```php
DB::table('mediables')->where('media_id', $mediaId)->get();
```

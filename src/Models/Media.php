<?php

namespace Palgoal\MediaLibrary\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * @property int $id
 * @property string $file_name
 * @property string|null $file_original_name
 * @property string $file_path
 * @property string|null $file_extension
 * @property string|null $mime_type
 * @property int $size
 * @property string|null $file_type
 * @property string $disk
 * @property int|null $width
 * @property int|null $height
 * @property int|null $uploader_id
 * @property string|null $alt
 * @property string|null $title
 * @property string|null $caption
 * @property string|null $description
 * @property-read string|null $url
 * @property-read string $readable_size
 */
class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    /**
     * الحقول المسموح تعبئتها جماعياً
     */
    protected $fillable = [
        'file_name',
        'file_original_name',
        'file_path',
        'file_extension',
        'mime_type',
        'size',
        'file_type',
        'disk',
        'width',
        'height',
        'uploader_id',
        'alt',
        'title',
        'caption',
        'description',
    ];

    /**
     * حقول تضاف تلقائياً عند التحويل إلى JSON/Array
     */
    protected $appends = [
        'url',
        'readable_size',
    ];

    /**
     * التحويلات (casts)
     */
    protected $casts = [
        'size'        => 'integer',
        'width'       => 'integer',
        'height'      => 'integer',
        'uploader_id' => 'integer',
    ];

    /**
     * علاقة الرافع — تستخدم user_model من config('media-library.user_model')
     * أو موديل المستخدم الافتراضي للتطبيق المضيف (auth.providers.users.model).
     *
     * لا يوجد افتراض ضمني لأي كلاس تابع لتطبيق معين (مثل App\Models\User):
     * إذا لم يكن أي من المصدرين معرّفاً، أو كان الكلاس المعرّف غير موجود
     * فعلياً، تُرمى استثناء واضح بدل تخمين كلاس قد لا يكون موجوداً في
     * التطبيق المضيف (الحزمة مستقلة ولا يجوز أن تفترض بنية تطبيق بعينه).
     *
     * @throws RuntimeException
     */
    public function uploader(): BelongsTo
    {
        $userModel = config('media-library.user_model')
            ?? config('auth.providers.users.model');

        if (! $userModel || ! class_exists($userModel)) {
            throw new RuntimeException(
                'Palgoal\\MediaLibrary: no valid user model could be resolved for the '
                . '"uploader" relation. Set config("media-library.user_model") to your '
                . 'user model class, or ensure config("auth.providers.users.model") '
                . 'points to an existing class.'
            );
        }

        return $this->belongsTo($userModel, 'uploader_id');
    }

    /**
     * Accessor: URL مباشر للملف.
     *
     * يعيد null بدلاً من رمي استثناء إذا كان الـ disk المُستخدم لا يدعم
     * توليد روابط (مثال: بعض أدوات تخزين مخصصة لا تُطبّق واجهة URL).
     * لا يتحقق هذا الـ accessor من وجود الملف فعلياً على التخزين (تجنباً
     * لاستدعاء Storage::exists() لكل عنصر عند عرض قوائم كبيرة) — استخدم
     * fileExistsOnDisk() صراحةً إذا احتجت لذلك التحقق.
     */
    public function getUrlAttribute(): ?string
    {
        $disk = $this->disk ?: 'public';

        if (! $this->file_path) {
            return null;
        }

        try {
            return Storage::disk($disk)->url($this->file_path);
        } catch (\Throwable $e) {
            // Driver doesn't support url() (e.g. some custom/remote disks),
            // or the disk isn't configured at all. Fail soft instead of a
            // fatal error when serializing the model.
            return null;
        }
    }

    /**
     * Explicit, opt-in check for whether the underlying file still exists
     * on its disk. Not called automatically by any accessor because it
     * performs a Storage I/O call per invocation.
     */
    public function fileExistsOnDisk(): bool
    {
        if (! $this->file_path) {
            return false;
        }

        try {
            return Storage::disk($this->disk ?: 'public')->exists($this->file_path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Accessor: حجم مقروء (KB / MB / GB)
     */
    public function getReadableSizeAttribute(): string
    {
        $bytes = $this->size ?? 0;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Scope: جلب الصور فقط
     */
    public function scopeImages(Builder $query): Builder
    {
        return $query->where('file_type', 'image');
    }

    /**
     * Scope: فلترة حسب نوع الميديا
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('file_type', $type);
    }

    /**
     * Scope: بحث بسيط بالاسم الأصلي أو العنوان أو الكابشن.
     *
     * ملاحظة: LIKE قد يكون حساساً لحالة الأحرف على بعض محركات قواعد
     * البيانات (مثل PostgreSQL) وغير حساس على أخرى (مثل MySQL/SQLite
     * الافتراضي)، وهذا سلوك المحرك نفسه وليس خطأ في الحزمة.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('file_original_name', 'LIKE', "%{$term}%")
                ->orWhere('file_name', 'LIKE', "%{$term}%")
                ->orWhere('title', 'LIKE', "%{$term}%")
                ->orWhere('caption', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Best-effort classification of the file into one of the package's
     * logical types (image/video/audio/document/other) based on the
     * detected MIME type and extension.
     */
    public function detectType(): string
    {
        if (str_starts_with((string) $this->mime_type, 'image/')) {
            return 'image';
        }

        if (str_starts_with((string) $this->mime_type, 'video/')) {
            return 'video';
        }

        if (str_starts_with((string) $this->mime_type, 'audio/')) {
            return 'audio';
        }

        $documentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
        if (in_array($this->file_extension, $documentExtensions, true)) {
            return 'document';
        }

        return 'other';
    }
}

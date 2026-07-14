<?php

namespace Palgoal\MediaLibrary\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
     * أو موديل المستخدم الافتراضي للتطبيق المضيف.
     */
    public function uploader()
    {
        $userModel = config('media-library.user_model')
            ?? config('auth.providers.users.model')
            ?? 'App\\Models\\User';

        return $this->belongsTo($userModel, 'uploader_id');
    }

    /**
     * Accessor: URL مباشر للملف
     */
    public function getUrlAttribute(): string
    {
        $disk = $this->disk ?: 'public';

        return Storage::disk($disk)->url($this->file_path);
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
    public function scopeImages($query)
    {
        return $query->where('file_type', 'image');
    }

    /**
     * Scope: فلترة حسب نوع الميديا
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('file_type', $type);
    }

    /**
     * Scope: بحث بسيط بالاسم الأصلي أو العنوان أو الكابشن
     */
    public function scopeSearch($query, ?string $term)
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('file_original_name', 'LIKE', "%{$term}%")
                ->orWhere('file_name', 'LIKE', "%{$term}%")
                ->orWhere('title', 'LIKE', "%{$term}%")
                ->orWhere('caption', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%");
        });
    }

    public function detectType()
    {
        if (str_starts_with((string) $this->mime_type, 'image/')) return 'image';
        if (str_starts_with((string) $this->mime_type, 'video/')) return 'video';
        if (str_starts_with((string) $this->mime_type, 'audio/')) return 'audio';
        if ($this->file_extension === 'pdf') return 'document';

        return 'other';
    }
}

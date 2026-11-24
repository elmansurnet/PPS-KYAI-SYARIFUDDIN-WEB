<?php

namespace Modules\Berita\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Berita\Database\Factories\PostFactory;
use Modules\Berita\Support\HtmlCleaner;

/**
 * @property string $title
 * @property string $slug
 * @property string|null $cover_path
 * @property string|null $body
 * @property int|null $category_id
 * @property string $status
 * @property Carbon|null $published_at
 * @property int|null $author_id
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Konsep',
        self::STATUS_PUBLISHED => 'Terbit',
    ];

    protected $fillable = ['title', 'slug', 'cover_path', 'body', 'category_id', 'status', 'published_at', 'author_id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    protected static function newFactory(): Factory
    {
        return PostFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $post) {
            // Slug dibuat dari judul; stabil saat judul berubah, kecuali slug diubah eksplisit.
            if (blank($post->slug) || ! $post->exists || ($post->exists && $post->isDirty('slug'))) {
                $post->slug = self::uniqueSlug(
                    self::query(), filled($post->slug) ? $post->slug : $post->title, $post->getKey(), 'berita',
                );
            }

            // Penulis diisi dari akun yang masuk (bukan dari input form).
            if (! $post->exists && blank($post->author_id) && Auth::id()) {
                $post->author_id = Auth::id();
            }

            // Terbit tanpa tanggal berarti terbit sekarang.
            if ($post->status === self::STATUS_PUBLISHED && blank($post->published_at)) {
                $post->published_at = now();
            }

            // Penulis tidak tepercaya: bersihkan HTML saat disimpan (dan lagi saat ditampilkan).
            if (! $post->exists || $post->isDirty('body')) {
                $post->body = HtmlCleaner::clean($post->body);
            }
        });

        // Hapus sampul yang diganti atau dibuang supaya tidak menumpuk di disk publik.
        static::updated(function (self $post) {
            if ($post->wasChanged('cover_path')) {
                self::deleteCover($post->getOriginal('cover_path'));
            }
        });

        static::deleted(fn (self $post) => self::deleteCover($post->cover_path));
    }

    /**
     * Slug ASCII unik: slug-dasar, lalu slug-dasar-2, -3, dst.
     *
     * @param  Builder<*>  $query  kueri tabel yang dicek
     */
    public static function uniqueSlug(Builder $query, string $source, mixed $ignoreId, string $fallback): string
    {
        $base = rtrim(Str::limit(Str::slug($source), 100, ''), '-') ?: $fallback;
        $slug = $base;
        $n = 2;

        while ((clone $query)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    /** Terbit: status terbit dan tanggal terbit sudah lewat (bukan konsep atau terjadwal). */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** HTML isi yang aman ditampilkan (dibersihkan lagi walau sudah bersih saat disimpan). */
    public function safeBody(): string
    {
        return HtmlCleaner::clean($this->body);
    }

    /** Ringkasan teks polos (tanpa HTML), dipotong di batas kata. */
    public function excerpt(int $limit = 160): string
    {
        $html = preg_replace('~</?(p|br|li|h[1-6]|blockquote|pre|tr|td|th|ul|ol|table|hr)\b[^>]*>~i', ' ', $this->safeBody());
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return Str::limit($text, $limit, '…', preserveWords: true);
    }

    public function coverUrl(): ?string
    {
        if (blank($this->cover_path)) {
            return null;
        }

        $url = Storage::disk('public')->url($this->cover_path);

        return str_starts_with($url, 'http') ? $url : url($url);
    }

    /** Tanggal terbit dalam bahasa Indonesia, mis. "17 Agustus 2026". */
    public function publishedLabel(): string
    {
        return $this->published_at?->copy()->locale('id')->translatedFormat('j F Y') ?? '';
    }

    public function url(): string
    {
        return route('berita.show', $this->slug);
    }

    private static function deleteCover(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}

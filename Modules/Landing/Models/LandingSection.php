<?php

namespace Modules\Landing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Modules\Landing\Support\VideoEmbed;

/**
 * Satu bagian halaman beranda. Kunci tetap (admin menyunting, tidak menambah/menghapus).
 *
 * @property string $key
 * @property string|null $title
 * @property string|null $body
 * @property string|null $media_path
 * @property string|null $video_url
 * @property array<int, array{title?: ?string, body?: ?string, image_path?: ?string}>|null $items
 * @property int $sort_order
 * @property bool $is_visible
 */
class LandingSection extends Model
{
    public const KEY_HERO = 'hero';

    public const KEY_VIDEO = 'video_profil';

    public const KEY_HISTORY = 'sejarah';

    public const KEY_ACTIVITIES = 'kegiatan_unggulan';

    public const KEY_LEARNING = 'proses_belajar';

    public const KEY_CTA = 'ajakan_daftar';

    public const KEYS = [
        self::KEY_HERO, self::KEY_VIDEO, self::KEY_HISTORY,
        self::KEY_ACTIVITIES, self::KEY_LEARNING, self::KEY_CTA,
    ];

    /** Bagian yang berisi daftar butir (JSON items). */
    public const ITEM_KEYS = [self::KEY_ACTIVITIES, self::KEY_LEARNING];

    /** Bagian yang boleh tampil dengan teks netral walau admin belum mengisi. */
    public const SELF_SUFFICIENT_KEYS = [self::KEY_HERO, self::KEY_CTA];

    protected $fillable = ['key', 'title', 'body', 'media_path', 'video_url', 'items', 'sort_order', 'is_visible'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Hapus berkas yang diganti atau dibuang supaya tidak menumpuk di disk publik.
        static::updated(function (self $section) {
            if ($section->wasChanged('media_path')) {
                self::deleteFiles([$section->getOriginal('media_path')]);
            }

            if ($section->wasChanged('items')) {
                $old = self::itemImages(json_decode((string) $section->getOriginal('items'), true));
                $new = self::itemImages($section->items);
                self::deleteFiles(array_diff($old, $new));
            }
        });

        static::deleted(function (self $section) {
            self::deleteFiles([$section->media_path, ...self::itemImages($section->items)]);
        });
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true)->orderBy('sort_order')->orderBy('id');
    }

    public function usesItems(): bool
    {
        return in_array($this->key, self::ITEM_KEYS, true);
    }

    public function embedUrl(): ?string
    {
        return $this->key === self::KEY_VIDEO ? VideoEmbed::embedUrl($this->video_url) : null;
    }

    public function mediaUrl(): ?string
    {
        return self::publicUrl($this->media_path);
    }

    /**
     * Butir yang punya isi (judul atau teks), siap dirender.
     *
     * @return list<array{title: ?string, body: ?string, image_url: ?string}>
     */
    public function renderableItems(): array
    {
        if (! $this->usesItems()) {
            return [];
        }

        $rows = [];

        foreach ((array) $this->items as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $body = trim((string) ($item['body'] ?? ''));

            if ($title === '' && $body === '') {
                continue;
            }

            $rows[] = [
                'title' => $title !== '' ? $title : null,
                'body' => $body !== '' ? $body : null,
                'image_url' => self::publicUrl($item['image_path'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * Bagian tanpa isi (tanpa teks, gambar, butir, atau video yang valid) tidak tampil di publik.
     * hero dan ajakan_daftar selalu punya teks netral cadangan di tampilan.
     */
    public function hasPublicContent(): bool
    {
        if (in_array($this->key, self::SELF_SUFFICIENT_KEYS, true)) {
            return true;
        }

        if ($this->key === self::KEY_VIDEO) {
            return $this->embedUrl() !== null;
        }

        if ($this->usesItems()) {
            return $this->renderableItems() !== [] || filled(trim((string) $this->body));
        }

        return filled(trim((string) $this->body)) || filled($this->media_path);
    }

    private static function publicUrl(?string $path): ?string
    {
        return filled($path) ? Storage::disk('public')->url($path) : null;
    }

    /** @return list<string> */
    private static function itemImages(mixed $items): array
    {
        return array_values(array_filter(array_map(
            fn ($item) => is_array($item) ? ($item['image_path'] ?? null) : null,
            (array) $items,
        )));
    }

    /** @param  array<int, mixed>  $paths */
    private static function deleteFiles(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            Storage::disk('public')->delete($path);
        }
    }
}

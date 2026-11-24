<?php

namespace Modules\Berita\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Berita\Database\Factories\CategoryFactory;

/**
 * @property string $name
 * @property string $slug
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    protected static function newFactory(): Factory
    {
        return CategoryFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $category) {
            if (blank($category->slug) || ! $category->exists || ($category->exists && $category->isDirty('slug'))) {
                $category->slug = Post::uniqueSlug(
                    self::query(), filled($category->slug) ? $category->slug : $category->name, $category->getKey(), 'kategori',
                );
            }
        });
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}

<?php

namespace Modules\Berita\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Berita\Models\Category;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true), 'slug' => null];
    }
}

<?php

namespace Modules\Berita\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Berita\Models\Post;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(5),
            'slug' => null,
            'body' => '<p>'.fake()->paragraph().'</p>',
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'author_id' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => Post::STATUS_DRAFT, 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(['status' => Post::STATUS_PUBLISHED, 'published_at' => now()->addWeek()]);
    }
}

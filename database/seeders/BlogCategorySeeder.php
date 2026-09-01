<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use RuntimeException;

/**
 * The four landing-page blog categories. Idempotent: keyed on
 * `blog_category_name`, so re-running only refreshes description/image/owner.
 *
 * Must run after {@see RolePermissionSeeder} and {@see UserSeeder} — the owner
 * is resolved from the seeded SUPER_ADMIN rather than a hardcoded `user_id`, so
 * the FK holds on a database whose first user row is not id 1.
 *
 * `uuid` is assigned HERE, explicitly, and only when the row is new:
 *  - It must not come from the model's `creating` hook: {@see DatabaseSeeder}
 *    applies `WithoutModelEvents`, which mutes that hook, and the column is
 *    NOT NULL — the insert would fail on a cold database.
 *  - It must not sit in an `updateOrCreate()` update payload either: it is the
 *    public identifier every route and the landing-page feed bind to, so
 *    re-seeding must never rotate it.
 *
 * Hence `firstOrNew()` + `??=` — the same explicit-uuid convention the Portfolio,
 * Client and Service seeders follow.
 */
final class BlogCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userId = User::query()
            ->role('SUPER_ADMIN')
            ->orderBy('id')
            ->value('id');

        if (! is_int($userId)) {
            throw new RuntimeException('RolePermissionSeeder and UserSeeder must create a SUPER_ADMIN before BlogCategorySeeder runs.');
        }

        $blogCategories = [
            [
                'blog_category_name' => 'AI',
                'blog_category_description' => 'Artificial Intelligence trends, tools and insights',
                'blog_category_image' => 'https://pub-1bd403ae2aa74a938cd014ab41d2beda.r2.dev/blog-categories-cards/ai.webp',
            ],
            [
                'blog_category_name' => 'Software',
                'blog_category_description' => 'Software development, engineering and best practices',
                'blog_category_image' => 'https://pub-1bd403ae2aa74a938cd014ab41d2beda.r2.dev/blog-categories-cards/sotfware.webp',
            ],
            [
                'blog_category_name' => 'Marketing Online',
                'blog_category_description' => 'Digital marketing strategies, SEO and content marketing',
                'blog_category_image' => 'https://pub-1bd403ae2aa74a938cd014ab41d2beda.r2.dev/blog-categories-cards/marketing.webp',
            ],
            [
                'blog_category_name' => 'Social Network',
                'blog_category_description' => 'Social media platforms, networking and community building',
                'blog_category_image' => 'https://pub-1bd403ae2aa74a938cd014ab41d2beda.r2.dev/blog-categories-cards/social_network.webp',
            ],
        ];

        foreach ($blogCategories as $category) {
            $model = BlogCategoryEloquentModel::query()
                ->firstOrNew(['blog_category_name' => $category['blog_category_name']]);

            $model->uuid ??= (string) Str::uuid7();

            $model->fill([
                'blog_category_description' => $category['blog_category_description'],
                'blog_category_image' => $category['blog_category_image'],
                'user_id' => $userId,
            ])->save();
        }
    }
}

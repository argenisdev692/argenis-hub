<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Services\Infrastructure\Cache\ServicePublicFeedCache;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;
use Ramsey\Uuid\Uuid;

/**
 * Foundation service catalog matching the landing page's `<select>` options.
 * Must run after {@see UserSeeder} (`user_id => 1` is the seeded super admin).
 */
final class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Landing Page',
                'slug' => 'landing_page',
                'description' => 'Single-page site optimized for a campaign, ads, or one clear conversion goal.',
                'sort_order' => 0,
            ],
            [
                'name' => 'Business Website',
                'slug' => 'new_website',
                'description' => 'Multi-page marketing site with services, about, blog, and SEO foundations.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Redesign',
                'slug' => 'redesign',
                'description' => 'UX, performance, and conversion improvements for an existing site.',
                'sort_order' => 2,
            ],
            [
                'name' => 'E-Commerce',
                'slug' => 'ecommerce',
                'description' => 'Online store with catalog, cart, checkout, and payment integrations.',
                'sort_order' => 3,
            ],
            [
                'name' => 'Custom Business App',
                'slug' => 'web_app',
                'description' => 'Custom software: CRM workflows, dashboards, internal tools, or SaaS MVP.',
                'sort_order' => 4,
            ],
            [
                'name' => 'Maintenance & Support',
                'slug' => 'maintenance',
                'description' => 'Ongoing updates, security patches, hosting support, and small enhancements.',
                'sort_order' => 5,
            ],
            [
                'name' => 'Other',
                'slug' => 'other',
                'description' => 'Something else — describe your project in the booking notes.',
                'sort_order' => 6,
            ],
        ];

        foreach ($services as $service) {
            $existing = ServiceEloquentModel::query()->where('slug', $service['slug'])->first();

            ServiceEloquentModel::query()->updateOrCreate(
                ['slug' => $service['slug']],
                [
                    'uuid' => $existing?->uuid ?? Uuid::uuid7()->toString(),
                    'name' => $service['name'],
                    'description' => $service['description'],
                    'is_active' => true,
                    'sort_order' => $service['sort_order'],
                    'user_id' => 1,
                ],
            );
        }

        ServiceEloquentModel::query()
            ->where('slug', 'mobile_app')
            ->update(['is_active' => false]);

        ServicePublicFeedCache::flush();
    }
}

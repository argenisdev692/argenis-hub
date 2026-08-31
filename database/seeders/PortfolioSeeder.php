<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Portfolios\Infrastructure\Cache\PortfolioPublicFeedCache;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;
use RuntimeException;

/**
 * Real showcase projects rendered by the public landing-page feed.
 *
 * Keyed by the canonical `uuid` so re-running the seeder updates the existing
 * rows instead of duplicating them. Must run after {@see RolePermissionSeeder}
 * and {@see UserSeeder} — the owner is resolved by the `SUPER_ADMIN` role, not
 * by a hardcoded email or id.
 *
 * `cover_path` / `video_path` are R2 object keys, not absolute URLs; the model's
 * `cover_url` / `video_url` accessors resolve them through the Shared storage
 * port.
 */
final class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::query()
            ->role('SUPER_ADMIN')
            ->orderBy('id')
            ->value('id');

        if (! is_int($userId)) {
            throw new RuntimeException('RolePermissionSeeder and UserSeeder must create a SUPER_ADMIN before PortfolioSeeder runs.');
        }

        /** @var list<array{uuid: string, title: string, client_name: string, project_type: string, tech_stack: list<string>, live_url: string, published_at: string, is_public: bool, cover_path: string, video_path: string, description: string, sort_order: int}> $portfolios */
        $portfolios = [
            [
                'uuid' => '019fd7b6-3cf6-7050-83a4-2b7cdd56d5a2',
                'title' => 'SERVISPIN — Appointment Management and Technical Support System',
                'client_name' => 'SERVISPIN',
                'project_type' => 'Business Website',
                'tech_stack' => ['PHP', 'Laravel', 'Livewire', 'Alpine JS', 'Tailwind CSS'],
                'live_url' => 'https://servispin.net/',
                'published_at' => '2026-08-06 00:00:00',
                'is_public' => true,
                'cover_path' => 'portfolios/cover/003a5dd7-eb71-40b0-b81b-993a61c77a4c/ser-1.png',
                'video_path' => 'portfolios/video/0b5b66ca-3e5f-4f47-8ef9-afcf033bb685/video-servispin.mp4',
                'description' => 'Web platform for Servispin (home appliance repair services in Gran Canaria): public landing page, in-home appointment booking, remote technical support via video call with SumUp payment verification, administration dashboard, and REST API.',
                'sort_order' => 0,
            ],
            [
                'uuid' => '019fd7b9-dfdf-734d-baf9-784b206e1067',
                'title' => 'AQUASHIELD RESTORIATION LLC - Professional Water Damage Restoration',
                'client_name' => 'AQUASHIELD RESTORATION LLC',
                'project_type' => 'Business Website',
                'tech_stack' => ['Astro', 'React', 'Typescript', 'Tailwind CSS'],
                'live_url' => 'https://aquashieldrestorationusa.com/',
                'published_at' => '2026-08-06 00:00:00',
                'is_public' => true,
                'cover_path' => 'portfolios/cover/096c8a83-df73-45f9-92c6-162f14fc6cf5/aq-1.png',
                'video_path' => 'portfolios/video/fa0c9b69-8def-41ae-bb7c-01ab0a2307de/video-aquashield.mp4',
                'description' => 'AquaShield Restoration USA is a next-generation water damage restoration and roofing services platform built with modern web technologies. This enterprise-grade landing page serves as the primary customer acquisition channel, featuring advanced lead generation, multi-channel contact forms, and real-time spam protection.',
                'sort_order' => 1,
            ],
            [
                'uuid' => '019fd7bc-502e-727c-93db-30401a624137',
                'title' => 'VIDULA',
                'client_name' => 'VIDULA',
                'project_type' => 'Custom Business App',
                'tech_stack' => ['PHP', 'Laravel', 'Typescript', 'Vue JS', 'Tailwind CSS'],
                'live_url' => 'https://vidula.up.railway.app/',
                'published_at' => '2026-08-06 00:00:00',
                'is_public' => true,
                'cover_path' => 'portfolios/cover/e3124fed-3576-44e9-880c-5e376559c329/vi-1.png',
                'video_path' => 'portfolios/video/d674d062-f061-43d2-8955-9a2907564d26/video_vidula.mp4',
                'description' => 'AI-powered operations workspace for creators, educators & coaches. CRM, scheduling, content generation, video production and personal-branding tools in a single modular Laravel monolith.',
                'sort_order' => 2,
            ],
        ];

        foreach ($portfolios as $attributes) {
            $portfolio = PortfolioEloquentModel::withTrashed()->firstOrNew(['uuid' => $attributes['uuid']]);

            $portfolio->fill([
                'uuid' => $attributes['uuid'],
                'title' => $attributes['title'],
                'client_name' => $attributes['client_name'],
                'project_type' => $attributes['project_type'],
                'tech_stack' => $attributes['tech_stack'],
                'live_url' => $attributes['live_url'],
                'published_at' => $attributes['published_at'],
                'is_public' => $attributes['is_public'],
                'cover_path' => $attributes['cover_path'],
                'video_path' => $attributes['video_path'],
                'description' => $attributes['description'],
                'sort_order' => $attributes['sort_order'],
                'user_id' => $userId,
            ]);

            $portfolio->save();

            if ($portfolio->trashed()) {
                $portfolio->restore();
            }
        }

        PortfolioPublicFeedCache::flush();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Shared\Infrastructure\Company\CompanyProfile;

/**
 * Public landing page.
 *
 * Exists so the footer's contact block reads the same `company_data` row that
 * already brands emails and PDF exports, instead of hard-coding a second copy
 * of the links in the Vue tree.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $profile = CompanyProfile::data();

        return Inertia::render('Welcome', [
            'company' => [
                'socials' => $profile['socials'],
                'support_email' => $profile['support_email'],
            ],
        ]);
    }
}

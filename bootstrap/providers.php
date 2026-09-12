<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use Modules\ActivityLog\Providers\ActivityLogServiceProvider;
use Modules\Auth\Providers\AuthServiceProvider;
use Modules\Authorization\Providers\AuthorizationServiceProvider;
use Modules\Availability\Providers\AvailabilityServiceProvider;
use Modules\Backups\Providers\BackupsServiceProvider;
use Modules\Blog\Providers\BlogServiceProvider;
use Modules\Campaigns\Providers\CampaignServiceProvider;
use Modules\Clients\Providers\ClientsServiceProvider;
use Modules\Company\Providers\CompanyServiceProvider;
use Modules\ContactSupport\Providers\ContactSupportServiceProvider;
use Modules\CourseScripts\Providers\CourseScriptsServiceProvider;
use Modules\Cvs\Providers\CvsServiceProvider;
use Modules\Invoices\Providers\InvoicesServiceProvider;
use Modules\PaymentAccounts\Providers\PaymentAccountsServiceProvider;
use Modules\Portfolios\Providers\PortfoliosServiceProvider;
use Modules\Post\Providers\PostServiceProvider;
use Modules\Products\Providers\ProductsServiceProvider;
use Modules\Services\Providers\ServicesServiceProvider;
use Modules\SocialMedia\Providers\SocialMediaServiceProvider;
use Modules\VideoEdits\Providers\VideoEditsServiceProvider;
use Shared\Providers\SharedServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    // Binds the TypeScript transformer config consumed by `php artisan typescript:transform`.
    TypeScriptTransformerServiceProvider::class,
    // Binds the cross-cutting Shared ports (AuditPort, StoragePort, …) the
    // modules depend on — must come before the module providers.
    SharedServiceProvider::class,
    AuthServiceProvider::class,
    // Roles/permissions back every other module's `permission:*` middleware.
    AuthorizationServiceProvider::class,
    CompanyServiceProvider::class,
    // Listens for CompanyCountryChanged, so it must boot after the Company module.
    AvailabilityServiceProvider::class,
    ServicesServiceProvider::class,
    ClientsServiceProvider::class,
    ContactSupportServiceProvider::class,
    PortfoliosServiceProvider::class,
    BlogServiceProvider::class,
    PostServiceProvider::class,
    SocialMediaServiceProvider::class,
    CampaignServiceProvider::class,
    CvsServiceProvider::class,
    // Billable catalog (courses / video courses) — must boot before Invoices,
    // whose line items and form options read the product catalog.
    ProductsServiceProvider::class,
    // Issuer payment accounts — Invoices snapshots one onto every invoice.
    PaymentAccountsServiceProvider::class,
    InvoicesServiceProvider::class,
    ActivityLogServiceProvider::class,
    BackupsServiceProvider::class,
    // Video editing pipeline (spec 001-video-edit) — depends on the Shared StoragePort / AuditPort.
    VideoEditsServiceProvider::class,
    // Course script generation (spec 002-course-scripts) — depends on the Shared
    // AIClientInterface / ExportPort / StoragePort / AuditPort.
    CourseScriptsServiceProvider::class,
];

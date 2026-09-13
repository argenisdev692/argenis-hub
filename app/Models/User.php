<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Passkey;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Auth\Infrastructure\Persistence\Eloquent\Models\AuthSessionEloquentModel;
use Modules\Auth\Infrastructure\Persistence\Eloquent\Models\PasswordHistoryEloquentModel;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseGenerationRunEloquentModel;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostAiGenerationEloquentModel;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;
use Spatie\OneTimePasswords\Models\OneTimePassword;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $uuid
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $password_changed_at
 * @property bool $must_change_password
 * @property Carbon|null $locked_until
 * @property Carbon|null $invited_at
 * @property string|null $invited_by
 * @property string|null $phone
 * @property string|null $date_of_birth
 * @property string|null $address
 * @property string|null $address_2
 * @property string|null $zip_code
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $country_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $gender
 * @property string|null $profile_photo_path
 * @property bool $terms_and_conditions
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read CompanyData|null $companyData
 * @property-read Collection<int, AuthSessionEloquentModel> $authSessions
 * @property-read int|null $auth_sessions_count
 * @property-read Collection<int, PasswordHistoryEloquentModel> $passwordHistories
 * @property-read int|null $password_histories_count
 * @property-read Collection<int, ServiceEloquentModel> $services
 * @property-read int|null $services_count
 * @property-read Collection<int, PortfolioEloquentModel> $portfolios
 * @property-read int|null $portfolios_count
 * @property-read Collection<int, ClientEloquentModel> $clients
 * @property-read int|null $clients_count
 * @property-read Collection<int, BlogCategoryEloquentModel> $blogCategories
 * @property-read int|null $blog_categories_count
 * @property-read Collection<int, ContactSupportEloquentModel> $contactSupports
 * @property-read int|null $contact_supports_count
 * @property-read Collection<int, PostEloquentModel> $posts
 * @property-read int|null $posts_count
 * @property-read Collection<int, PostAiGenerationEloquentModel> $postAiGenerations
 * @property-read int|null $post_ai_generations_count
 * @property-read Collection<int, SocialMediaContentEloquentModel> $socialMediaContents
 * @property-read int|null $social_media_contents_count
 * @property-read Collection<int, CampaignEloquentModel> $campaigns
 * @property-read int|null $campaigns_count
 * @property-read Collection<int, CvEloquentModel> $cvs
 * @property-read Collection<int, ProductEloquentModel> $products
 * @property-read Collection<int, InvoiceEloquentModel> $invoices
 * @property-read Collection<int, PaymentAccountEloquentModel> $paymentAccounts
 * @property-read Collection<int, VideoEditEloquentModel> $videoEdits
 * @property-read int|null $video_edits_count
 * @property-read Collection<int, CourseEloquentModel> $courses
 * @property-read int|null $courses_count
 * @property-read Collection<int, CourseGenerationRunEloquentModel> $courseGenerationRuns
 * @property-read int|null $course_generation_runs_count
 * @property-read int|null $cvs_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, OneTimePassword> $oneTimePasswords
 * @property-read int|null $one_time_passwords_count
 * @property-read Collection<int, Passkey> $passkeys
 * @property-read int|null $passkeys_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, Permission> $teams
 * @property-read int|null $teams_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User team($teams, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTeam($teams)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 *
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAddress2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereInvitedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereInvitedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLockedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMustChangePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePasswordChangedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfilePhotoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTermsAndConditions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereZipCode($value)
 *
 * @property-read int|null $invoices_count
 * @property-read int|null $payment_accounts_count
 * @property-read int|null $products_count
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'uuid',
    'first_name',
    'last_name',
    'username',
    'email',
    'password',
    'password_changed_at',
    'must_change_password',
    'invited_at',
    'invited_by',
    'phone',
    'date_of_birth',
    'address',
    'address_2',
    'zip_code',
    'city',
    'state',
    'country',
    'country_code',
    'latitude',
    'longitude',
    'gender',
    'profile_photo_path',
    'terms_and_conditions',
    'email_verified_at',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasOneTimePasswords, HasRoles, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Generate a UUIDv7 for new users.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if ($user->uuid === null) {
                $user->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * Verify by 6-digit code instead of a signed link (spec 001 FR-02).
     *
     * Overriding the notification here — rather than replacing Fortify's
     * verification routes — means every existing entry point that asks for a new
     * code (registration, `POST /email/verification-notification`) sends the
     * right thing without knowing anything about one-time passwords.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->sendOneTimePassword((int) config('auth-security.otp.expires_in_minutes'));
    }

    /**
     * Reset by 6-digit code instead of a signed link (spec 001 FR-11).
     *
     * The broker token is deliberately discarded: the one-time password IS the
     * proof of control, and issuing two independent secrets for one flow would
     * only widen the attack surface.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->sendOneTimePassword((int) config('auth-security.otp.expires_in_minutes'));
    }

    /** @return HasMany<AuthSessionEloquentModel, $this> */
    public function authSessions(): HasMany
    {
        return $this->hasMany(AuthSessionEloquentModel::class);
    }

    /** @return HasMany<PasswordHistoryEloquentModel, $this> */
    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistoryEloquentModel::class);
    }

    /** @return HasMany<ServiceEloquentModel, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(ServiceEloquentModel::class);
    }

    /**
     * The portfolio projects this user owns.
     *
     * Inverse of `PortfolioEloquentModel::user()` — declared here because
     * `portfolios.user_id` is a foreign key and every FK in this project
     * carries both sides of the relation.
     *
     * @return HasMany<PortfolioEloquentModel, $this>
     */
    public function portfolios(): HasMany
    {
        return $this->hasMany(PortfolioEloquentModel::class);
    }

    /**
     * The CRM clients this user owns.
     *
     * Inverse of `ClientEloquentModel::user()` — declared here because
     * `clients.user_id` is a foreign key and every FK in this project carries
     * both sides of the relation.
     *
     * @return HasMany<ClientEloquentModel, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(ClientEloquentModel::class);
    }

    /**
     * The blog categories this user authored.
     *
     * Inverse of `BlogCategoryEloquentModel::user()` — declared here because
     * `blog_categories.user_id` is a foreign key and every FK in this project
     * carries both sides of the relation.
     *
     * @return HasMany<BlogCategoryEloquentModel, $this>
     */
    public function blogCategories(): HasMany
    {
        return $this->hasMany(BlogCategoryEloquentModel::class);
    }

    /**
     * The contact-support requests attributed to this user — set when a
     * signed-in visitor submits the public form or an operator logs a request
     * from the admin UI. Inverse of `ContactSupportEloquentModel::user()`;
     * declared here because `contact_supports.user_id` is a nullable foreign key
     * and every FK in this project carries both sides of the relation.
     *
     * @return HasMany<ContactSupportEloquentModel, $this>
     */
    public function contactSupports(): HasMany
    {
        return $this->hasMany(ContactSupportEloquentModel::class);
    }

    /**
     * The blog posts this user authored.
     *
     * Inverse of `PostEloquentModel::user()`. `posts.user_id` is nullable and
     * `ON DELETE SET NULL` — a deleted author leaves the post orphaned but
     * published, so this collection is authorship, not ownership.
     *
     * @return HasMany<PostEloquentModel, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(PostEloquentModel::class);
    }

    /**
     * The AI post-generation runs this user launched.
     *
     * Inverse of `PostAiGenerationEloquentModel::causer()`; the FK is
     * `post_ai_generations.created_by`, not the conventional `user_id`.
     *
     * @return HasMany<PostAiGenerationEloquentModel, $this>
     */
    public function postAiGenerations(): HasMany
    {
        return $this->hasMany(PostAiGenerationEloquentModel::class, 'created_by');
    }

    /**
     * The social-media contents this user created.
     *
     * Inverse of `SocialMediaContentEloquentModel::user()`; the FK is
     * `social_media_contents.created_by`, not the conventional `user_id`.
     *
     * @return HasMany<SocialMediaContentEloquentModel, $this>
     */
    public function socialMediaContents(): HasMany
    {
        return $this->hasMany(SocialMediaContentEloquentModel::class, 'created_by');
    }

    /**
     * The campaigns this user created.
     *
     * Inverse of `CampaignEloquentModel::creator()`; the FK is
     * `campaigns.created_by`, not the conventional `user_id`.
     *
     * @return HasMany<CampaignEloquentModel, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(CampaignEloquentModel::class, 'created_by');
    }

    /**
     * The CVs this user uploaded.
     *
     * Inverse of `CvEloquentModel::user()` — declared here because `cvs.user_id`
     * is a foreign key and every FK in this project carries both sides of the
     * relation. `ON DELETE CASCADE`: a CV is personal data with no meaning once
     * its owner is gone, so this collection is ownership, not authorship.
     *
     * @return HasMany<CvEloquentModel, $this>
     */
    public function cvs(): HasMany
    {
        return $this->hasMany(CvEloquentModel::class);
    }

    /**
     * The billable catalog products (courses, video courses) this user owns.
     *
     * Inverse of `ProductEloquentModel::user()` — declared here because
     * `products.user_id` is a foreign key and every FK in this project carries
     * both sides of the relation.
     *
     * @return HasMany<ProductEloquentModel, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(ProductEloquentModel::class);
    }

    /**
     * The invoices this user issued.
     *
     * Inverse of `InvoiceEloquentModel::user()` — declared here because
     * `invoices.user_id` is a foreign key and every FK in this project carries
     * both sides of the relation.
     *
     * @return HasMany<InvoiceEloquentModel, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(InvoiceEloquentModel::class);
    }

    /**
     * The settlement rails (Remitly USD, bank transfer EUR, …) this user can
     * put on an invoice.
     *
     * Inverse of `PaymentAccountEloquentModel::user()` — declared here because
     * `payment_accounts.user_id` is a foreign key and every FK in this project
     * carries both sides of the relation.
     *
     * @return HasMany<PaymentAccountEloquentModel, $this>
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccountEloquentModel::class);
    }

    /**
     * The video edits this user requested (spec 001-video-edit).
     *
     * Inverse of `VideoEditEloquentModel::user()` — declared because
     * `video_edits.user_id` is a foreign key and every FK in this project
     * carries both sides of the relation.
     *
     * @return HasMany<VideoEditEloquentModel, $this>
     */
    public function videoEdits(): HasMany
    {
        return $this->hasMany(VideoEditEloquentModel::class);
    }

    /**
     * Inverse of `CourseEloquentModel::user()` (Course Scripts module).
     *
     * @return HasMany<CourseEloquentModel, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(CourseEloquentModel::class);
    }

    /**
     * Inverse of `CourseGenerationRunEloquentModel::user()`.
     *
     * @return HasMany<CourseGenerationRunEloquentModel, $this>
     */
    public function courseGenerationRuns(): HasMany
    {
        return $this->hasMany(CourseGenerationRunEloquentModel::class);
    }

    /**
     * The company record this user owns.
     *
     * `hasOne`, not `hasMany`: `company_data` is a singleton and the Company
     * module exposes no way to create a second row. Declared because
     * `company_data.user_id` is a foreign key, and every FK in this project
     * carries both sides of the relation.
     *
     * @return HasOne<CompanyData, $this>
     */
    public function companyData(): HasOne
    {
        return $this->hasOne(CompanyData::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
            'locked_until' => 'datetime',
            'invited_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'terms_and_conditions' => 'boolean',
        ];
    }
}

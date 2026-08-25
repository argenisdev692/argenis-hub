<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Shared\Infrastructure\Company\CompanyProfile;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $name
 * @property string $company_name
 * @property string|null $description
 * @property string|null $signature_path
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $nif_nipc
 * @property string|null $nie
 * @property string|null $bank_beneficiary
 * @property string|null $bank_iban
 * @property string|null $bank_bic
 * @property string|null $bank_name
 * @property string|null $invoice_notes
 * @property string|null $address
 * @property string|null $address_2
 * @property string|null $website
 * @property string|null $facebook_link
 * @property string|null $instagram_link
 * @property string|null $linkedin_link
 * @property string|null $twitter_link
 * @property string|null $tiktok_link
 * @property string|null $logo_path
 * @property string|null $logo_white_path
 * @property string|null $mark_path
 * @property int $user_id
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $zip_code
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $country_code
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereAddress2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereBankBeneficiary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereBankBic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereBankIban($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereBankName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereCompanyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereFacebookLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereInstagramLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereInvoiceNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereLinkedinLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereLogoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereLogoWhitePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereMarkPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereNie($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereNifNipc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereSignaturePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereTiktokLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereTwitterLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereWebsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereZipCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData withoutTrashed()
 *
 * @mixin \Eloquent
 */
class CompanyData extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'company_data';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'company_name',
        'description',
        'signature_path',
        'logo_path',
        'logo_white_path',
        'mark_path',
        'email',
        'phone',
        'nif_nipc',
        'nie',
        'bank_beneficiary',
        'bank_iban',
        'bank_bic',
        'bank_name',
        'invoice_notes',
        'address',
        'address_2',
        'zip_code',
        'city',
        'state',
        'country',
        'country_code',
        'website',
        'facebook_link',
        'instagram_link',
        'linkedin_link',
        'twitter_link',
        'tiktok_link',
        'user_id',
        'latitude',
        'longitude',
    ];

    protected static function booted(): void
    {
        static::creating(function (CompanyData $company): void {
            if (empty($company->uuid)) {
                $company->uuid = (string) Str::uuid7();
            }
        });

        // Keep the email-branding cache fresh.
        static::saved(static function (): void {
            CompanyProfile::forget();
        });
        static::deleted(static function (): void {
            CompanyProfile::forget();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Audit trail (spatie/laravel-activitylog). Allowlist only — logs contact,
     * fiscal and asset-path changes on the single company record.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'company_name',
                'description',
                'email',
                'phone',
                'nif_nipc',
                'nie',
                'bank_beneficiary',
                'bank_iban',
                'bank_bic',
                'bank_name',
                'invoice_notes',
                'address',
                'address_2',
                'zip_code',
                'city',
                'state',
                'country',
                'country_code',
                'website',
                'facebook_link',
                'instagram_link',
                'linkedin_link',
                'twitter_link',
                'tiktok_link',
                'logo_path',
                'logo_white_path',
                'mark_path',
                'signature_path',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('company.data');
    }
}

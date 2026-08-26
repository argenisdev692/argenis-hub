declare namespace Illuminate {
    export type CursorPaginator<TKey, TValue> = {
        data: TKey extends string ? Record<TKey, TValue> : TValue[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        meta: {
            path: string;
            per_page: number;
            next_cursor: string | null;
            next_page_url: string | null;
            prev_cursor: string | null;
            prev_page_url: string | null;
        };
    };
    export type CursorPaginatorInterface<TKey, TValue> =
        Illuminate.CursorPaginator<TKey, TValue>;
    export type LengthAwarePaginator<TKey, TValue> = {
        data: TKey extends string ? Record<TKey, TValue> : TValue[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        meta: {
            total: number;
            current_page: number;
            first_page_url: string;
            from: number | null;
            last_page: number;
            last_page_url: string;
            next_page_url: string | null;
            path: string;
            per_page: number;
            prev_page_url: string | null;
            to: number | null;
        };
    };
    export type LengthAwarePaginatorInterface<TKey, TValue> =
        Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Modules {
    namespace Auth {
        namespace Application {
            namespace DTOs {
                export type ApiTokenData = {
                    readonly access_token: string;
                    readonly token_type: string;
                    readonly expires_at: string;
                    readonly abilities: string[];
                };
                export type AuthSessionData = {
                    readonly uuid: string;
                    readonly ip_address: string | null;
                    readonly user_agent: string | null;
                    readonly last_seen_at: string | null;
                    readonly created_at: string;
                    readonly is_current: boolean;
                };
                export type AuthenticatedUserData = {
                    readonly uuid: string;
                    readonly name: string;
                    readonly email: string;
                    readonly email_verified_at: string | null;
                    readonly two_factor_enabled: boolean;
                    readonly roles: string[];
                    readonly permissions: string[];
                };
            }
        }
    }
    namespace Company {
        namespace Application {
            namespace DTOs {
                export type CompanyAddressData = {
                    readonly line_1: string | null;
                    readonly line_2: string | null;
                    readonly zip_code: string | null;
                    readonly city: string | null;
                    readonly state: string | null;
                    readonly country: string | null;
                    readonly country_code: string | null;
                    readonly latitude: number | null;
                    readonly longitude: number | null;
                    readonly formatted: string | null;
                };
                export type CompanyLogosData = {
                    readonly logo: string;
                    readonly logo_white: string;
                    readonly mark: string;
                };
                export type CompanyProfileData = {
                    readonly uuid: string;
                    readonly company_name: string;
                    readonly legal_name: string | null;
                    readonly description: string | null;
                    readonly website: string | null;
                    readonly email: string | null;
                    readonly phone: string | null;
                    readonly address: string | null;
                    readonly address_2: string | null;
                    readonly zip_code: string | null;
                    readonly city: string | null;
                    readonly state: string | null;
                    readonly country: string | null;
                    readonly country_code: string | null;
                    readonly latitude: number | null;
                    readonly longitude: number | null;
                    readonly nif_nipc: string | null;
                    readonly nie: string | null;
                    readonly bank_beneficiary: string | null;
                    readonly bank_iban: string | null;
                    readonly bank_bic: string | null;
                    readonly bank_name: string | null;
                    readonly invoice_notes: string | null;
                    readonly facebook_link: string | null;
                    readonly github_link: string | null;
                    readonly instagram_link: string | null;
                    readonly linkedin_link: string | null;
                    readonly tiktok_link: string | null;
                    readonly twitter_link: string | null;
                    readonly logos: Modules.Company.Application.DTOs.CompanyLogosData;
                    readonly updated_at: string | null;
                };
                export type CompanySocialsData = {
                    readonly facebook: string | null;
                    readonly github: string | null;
                    readonly instagram: string | null;
                    readonly linkedin: string | null;
                    readonly tiktok: string | null;
                    readonly twitter: string | null;
                };
                export type PublicCompanyData = {
                    readonly name: string;
                    readonly legal_name: string | null;
                    readonly description: string | null;
                    readonly website: string | null;
                    readonly email: string | null;
                    readonly phone: string | null;
                    readonly logos: Modules.Company.Application.DTOs.CompanyLogosData;
                    readonly socials: Modules.Company.Application.DTOs.CompanySocialsData;
                    readonly address: Modules.Company.Application.DTOs.CompanyAddressData;
                };
                export type UpdateCompanyData = {
                    readonly company_name: string;
                    readonly legal_name: string | null;
                    readonly description: string | null;
                    readonly website: string | null;
                    readonly email: string | null;
                    readonly phone: string | null;
                    readonly address: string | null;
                    readonly address_2: string | null;
                    readonly zip_code: string | null;
                    readonly city: string | null;
                    readonly state: string | null;
                    readonly country: string | null;
                    readonly country_code: string | null;
                    readonly latitude: number | null;
                    readonly longitude: number | null;
                    readonly nif_nipc: string | null;
                    readonly nie: string | null;
                    readonly bank_beneficiary: string | null;
                    readonly bank_iban: string | null;
                    readonly bank_bic: string | null;
                    readonly bank_name: string | null;
                    readonly invoice_notes: string | null;
                    readonly facebook_link: string | null;
                    readonly github_link: string | null;
                    readonly instagram_link: string | null;
                    readonly linkedin_link: string | null;
                    readonly tiktok_link: string | null;
                    readonly twitter_link: string | null;
                };
            }
        }
        namespace Domain {
            namespace Enums {
                export type LogoVariant = 'logo' | 'logo_white' | 'mark';
                export type SocialChannel =
                    | 'facebook'
                    | 'github'
                    | 'instagram'
                    | 'linkedin'
                    | 'tiktok'
                    | 'twitter';
            }
        }
    }
    namespace Services {
        namespace Application {
            namespace DTOs {
                export type PublicServiceData = {
                    readonly uuid: string;
                    readonly name: string;
                    readonly slug: string;
                    readonly description: string | null;
                    readonly sort_order: number;
                };
                export type ServiceData = {
                    readonly uuid: string;
                    readonly name: string;
                    readonly slug: string;
                    readonly description: string | null;
                    readonly is_active: boolean;
                    readonly sort_order: number;
                    readonly created_at: string | null;
                    readonly updated_at: string | null;
                    readonly deleted_at: string | null;
                };
                export type ServiceFilterData = {
                    readonly search: string | null;
                    readonly status: string | null;
                    readonly dateFrom: string | null;
                    readonly dateTo: string | null;
                    readonly sortField: string;
                    readonly sortOrder: number;
                    readonly page: number;
                    readonly perPage: number;
                };
            }
        }
    }
}
declare namespace Shared {
    namespace Application {
        namespace DTOs {
            export type BulkUuidsData = {
                uuids: string[];
            };
            export type SoftDeleteFilterData = {
                search: string | null;
                status: string | null;
                date_from: string | null;
                date_to: string | null;
            };
        }
    }
    namespace Infrastructure {
        namespace Resilience {
            namespace CircuitBreaker {
                export type CircuitBreakerState =
                    'closed' | 'open' | 'half_open';
            }
        }
    }
}
declare namespace Spatie {
    namespace LaravelData {
        export type CursorPaginatedDataCollection<TKey, TValue> =
            Illuminate.CursorPaginator<TKey, TValue>;
        export type PaginatedDataCollection<TKey, TValue> =
            Illuminate.LengthAwarePaginator<TKey, TValue>;
    }
}

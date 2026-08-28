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
    namespace ActivityLog {
        namespace Application {
            namespace DTOs {
                export type ActivityLogData = {
                    readonly id: number;
                    readonly log_name: string | null;
                    readonly description: string;
                    readonly event: string | null;
                    readonly subject_type: string | null;
                    readonly subject_id: string | null;
                    readonly causer_id: string | null;
                    readonly causer_label: string | null;
                    readonly created_at: string | null;
                };
                export type ActivityLogDetailData = {
                    readonly id: number;
                    readonly log_name: string | null;
                    readonly description: string;
                    readonly event: string | null;
                    readonly subject_type: string | null;
                    readonly subject_id: string | null;
                    readonly causer_id: string | null;
                    readonly causer_type: string | null;
                    readonly causer_label: string | null;
                    readonly properties: Record<string, any> | null;
                    readonly attribute_changes: Record<string, any> | null;
                    readonly created_at: string | null;
                    readonly updated_at: string | null;
                };
                export type ActivityLogFilterData = {
                    search: string | null;
                    event: string | null;
                    log_name: string | null;
                    causer_id: string | null;
                    date_from: string | null;
                    date_to: string | null;
                    sort_direction: string;
                    per_page: number;
                };
            }
        }
    }
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
    namespace Backups {
        namespace Application {
            namespace DTOs {
                export type BackupData = {
                    readonly uuid: string;
                    readonly disk: string;
                    readonly path: string | null;
                    readonly filename: string;
                    readonly size_bytes: number | null;
                    readonly human_size: string;
                    readonly status: string;
                    readonly connection: string | null;
                    readonly error: string | null;
                    readonly started_at: string | null;
                    readonly finished_at: string | null;
                    readonly created_at: string | null;
                    readonly updated_at: string | null;
                };
                export type BackupFilterData = {
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
        namespace Domain {
            namespace Enums {
                export type BackupStatus = 'running' | 'completed' | 'failed';
            }
        }
    }
    namespace Clients {
        namespace Application {
            namespace DTOs {
                export type ClientData = {
                    readonly uuid: string;
                    readonly client_name: string;
                    readonly email: string | null;
                    readonly status: Modules.Clients.Domain.Enums.ClientStatus;
                    readonly phone: string;
                    readonly address: string | null;
                    readonly country: string | null;
                    readonly country_code: string | null;
                    readonly tax_id: string | null;
                    readonly nif: string | null;
                    readonly website: string | null;
                    readonly facebook_link: string | null;
                    readonly instagram_link: string | null;
                    readonly linkedin_link: string | null;
                    readonly twitter_link: string | null;
                    readonly notes: string | null;
                    readonly created_at: string | null;
                    readonly updated_at: string | null;
                    readonly deleted_at: string | null;
                };
                export type ClientFilterData = {
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
        namespace Domain {
            namespace Enums {
                export type ClientStatus = 'DRAFT' | 'ACTIVE' | 'INACTIVE';
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
    namespace ContactSupport {
        namespace Application {
            namespace DTOs {
                export type ContactSupportData = {
                    readonly uuid: string;
                    readonly first_name: string;
                    readonly last_name: string;
                    readonly email: string;
                    readonly phone: string;
                    readonly subject: string;
                    readonly message: string;
                    readonly sms_consent: boolean;
                    readonly readed: boolean;
                    readonly is_spam: boolean;
                    readonly spam_score: number;
                    readonly spam_reasons: string[] | null;
                    readonly created_at: string | null;
                    readonly updated_at: string | null;
                    readonly deleted_at: string | null;
                };
                export type ContactSupportFilterData = {
                    readonly search: string | null;
                    readonly status: string | null;
                    readonly readed: boolean | null;
                    readonly isSpam: boolean | null;
                    readonly dateFrom: string | null;
                    readonly dateTo: string | null;
                    readonly sortField: string;
                    readonly sortOrder: number;
                    readonly page: number;
                    readonly perPage: number;
                };
                export type PublicContactSupportData = {
                    readonly uuid: string;
                    readonly subject: string;
                };
            }
        }
    }
    namespace Portfolios {
        namespace Application {
            namespace DTOs {
                export type PortfolioData = {
                    readonly uuid: string;
                    readonly title: string;
                    readonly client_name: string;
                    readonly project_type: string;
                    readonly tech_stack: string[];
                    readonly live_url: string | null;
                    readonly published_at: string | null;
                    readonly is_public: boolean;
                    readonly cover_path: string | null;
                    readonly cover_url: string | null;
                    readonly video_path: string | null;
                    readonly video_url: string | null;
                    readonly description: string | null;
                    readonly sort_order: number;
                    readonly media_paths: string[];
                    readonly gallery: string[];
                    readonly created_at: string | null;
                    readonly updated_at: string | null;
                    readonly deleted_at: string | null;
                };
                export type PortfolioFilterData = {
                    readonly search: string | null;
                    readonly status: string | null;
                    readonly dateFrom: string | null;
                    readonly dateTo: string | null;
                    readonly sortField: string;
                    readonly sortOrder: number;
                    readonly page: number;
                    readonly perPage: number;
                };
                export type PublicPortfolioData = {
                    readonly uuid: string;
                    readonly title: string;
                    readonly client_name: string;
                    readonly project_type: string;
                    readonly tech_stack: string[];
                    readonly live_url: string | null;
                    readonly cover_url: string | null;
                    readonly video_url: string | null;
                    readonly description: string | null;
                    readonly published_at: string | null;
                    readonly sort_order: number;
                    readonly gallery: string[];
                };
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

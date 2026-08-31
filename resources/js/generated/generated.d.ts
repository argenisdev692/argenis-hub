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
    namespace Blog {
        namespace Application {
            namespace DTOs {
                export type BlogCategoryData = {
                    name: string;
                    description: string | null;
                    image: undefined | null;
                };
                export type BlogCategoryFilterData = {
                    search: string | null;
                    status: string | null;
                    date_from: string | null;
                    date_to: string | null;
                };
            }
            namespace ReadModels {
                export type BlogCategoryPublicReadModel = {
                    uuid: string;
                    name: string | null;
                    description: string | null;
                    image_url: string | null;
                    posts_count: number;
                    posts: Modules.Blog.Application.ReadModels.PublicCategoryPostReadModel[];
                };
                export type PublicCategoryPostReadModel = {
                    uuid: string;
                    title: string;
                    slug: string;
                    excerpt: string | null;
                    cover_image_url: string | null;
                    published_at: string | null;
                };
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
    namespace Post {
        namespace Application {
            namespace DTOs {
                export type GenerateContentVariantData = {
                    topic: string;
                    provider: string;
                    angle: string | null;
                    keyTrend: string | null;
                };
                export type GeneratePostContentData = {
                    topic: string;
                    provider: string;
                    angle: string | null;
                    keyTrend: string | null;
                    imageMode: Modules.Post.Domain.Enums.PostImageMode;
                };
                export type GeneratedPostContentData = {
                    title: string;
                    content: string;
                    excerpt: string;
                    meta_title: string;
                    meta_description: string;
                    meta_keywords: string;
                    image_mode: Modules.Post.Domain.Enums.PostImageMode;
                    cover_image_path: string | null;
                    cover_image_url: string | null;
                    image_prompts: {
                        background: string;
                        content: string;
                    };
                    provider: string;
                    seo_score: number;
                    eeat_score: number;
                    virality_score: number;
                    roi_score: number;
                    human_writing_index: number;
                    ai_detection_risk: number;
                    all_scores_pass: boolean;
                    iterations_required: number;
                    quality_warning: boolean;
                    quality_warning_message: string | null;
                    scores: Record<string, any>;
                    optimization_suggestions: string[];
                    seo_analysis: {
                        primary_keyword: string;
                        lsi_keywords: string[];
                    };
                };
                export type PostData = {
                    title: string;
                    content: string;
                    excerpt: string | null;
                    coverImage: undefined | null;
                    coverImagePath: string | null;
                    metaTitle: string | null;
                    metaDescription: string | null;
                    metaKeywords: string | null;
                    categoryUuid: string | null;
                    status: string;
                    scheduledAt: string | null;
                    isAiGenerated: boolean;
                    aiProvider: string | null;
                    seoScore: number | null;
                    eeatScore: number | null;
                    humanWritingIndex: number | null;
                    aiDetectionRisk: number | null;
                    aiScores: Record<string, any> | null;
                };
                export type PostFilterData = {
                    category_uuid: string | null;
                    sort_field: string;
                    sort_order: number;
                    search: string | null;
                    status: string | null;
                    date_from: string | null;
                    date_to: string | null;
                };
                export type PostTopicIdeaData = {
                    title: string;
                    angle: string;
                    hook: string;
                    estimated_virality: number;
                    estimated_roi: number;
                    eeat_potential: number;
                    why_it_works: string;
                    key_trend: string;
                };
                export type ReelPackageData = {
                    scenes: Modules.Post.Application.DTOs.ReelSceneData[];
                    clean_script: string;
                    sound_suggestion: string;
                    tiktok_caption: string;
                    tiktok_hashtags: string[];
                    voiceover_audio_url: string | null;
                    target_duration_seconds: number;
                    creative_style: string;
                };
                export type ReelSceneData = {
                    time_range: string;
                    action: string;
                    on_screen_text: string;
                    voiceover_line: string;
                    visual_prompt: string;
                };
                export type SocialCopyData = {
                    linkedin_post: string;
                    social_caption: string;
                    hashtags: string[];
                };
                export type SuggestPostTopicsData = {
                    provider: string;
                    categoryUuid: string;
                    topic: string | null;
                };
            }
            namespace ReadModels {
                export type PostPublicReadModel = {
                    uuid: string;
                    title: string;
                    slug: string;
                    excerpt: string | null;
                    content: string | null;
                    cover_image_url: string | null;
                    meta_title: string | null;
                    meta_description: string | null;
                    meta_keywords: string | null;
                    category_uuid: string | null;
                    category_name: string | null;
                    published_at: string | null;
                };
            }
        }
        namespace Domain {
            namespace Enums {
                export type PostImageMode = 'full' | 'base' | 'none';
                export type PostStatus = 'draft' | 'published' | 'scheduled';
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
    namespace SocialMedia {
        namespace Application {
            namespace DTOs {
                export type ContentEvaluationData = {
                    scores: Modules.SocialMedia.Application.DTOs.ScoreSetData;
                    eeat_analysis: {
                        experience_signals: string[];
                        expertise_signals: string[];
                        authoritativeness_signals: string[];
                        trustworthiness_signals: string[];
                    };
                    optimization_suggestions: string[];
                    ai_detection_risk: {
                        value: number;
                        label: string;
                        explanation: string;
                    };
                    evaluator_provider: string;
                };
                export type GenerateSocialMediaContentData = {
                    topic: string;
                    provider: string;
                    language: string;
                    businessGoal: string;
                    brandVoice: string;
                    funnelStage: string;
                    angle: string | null;
                    hook: string | null;
                    keyTrend: string | null;
                    niche: string | null;
                    audience: string | null;
                    imageMode: Modules.SocialMedia.Domain.Enums.SocialMediaImageMode;
                    generateVoiceover: boolean;
                };
                export type GeneratedSocialMediaContentData = {
                    headline: string;
                    body: string;
                    call_to_action: string;
                    hashtags: string[];
                    platforms: Record<
                        string,
                        Modules.SocialMedia.Application.DTOs.PlatformContentData
                    >;
                    cover_image_concept: Modules.SocialMedia.Application.DTOs.ImageConceptData;
                    research_sources: {
                        source: string;
                        relevance: string;
                        key_insight: string;
                        used_in: string[];
                    }[];
                    tavily_data_used: string[];
                    provider: string;
                    cover_image_prompt: string;
                    cover_image_path: string | null;
                    cover_image_url: string | null;
                };
                export type ImageConceptData = {
                    title: string;
                    visual: string;
                    route: string;
                    svg_steps: string[];
                };
                export type PlatformContentData = {
                    platform: string;
                    adapted_content: string;
                    character_count: number;
                    hashtags: string[];
                    image_concept: Modules.SocialMedia.Application.DTOs.ImageConceptData;
                    is_thread: boolean;
                    thread_tweets: string[];
                    video_package: Modules.SocialMedia.Application.DTOs.VideoPackageData | null;
                    image_prompt: string;
                    image_path: string | null;
                    image_url: string | null;
                    voiceover_audio_path: string | null;
                    voiceover_audio_url: string | null;
                };
                export type ScoreResultData = {
                    value: number;
                    threshold: number;
                    passes: boolean;
                    factors: Record<string, number>;
                    explanation: string;
                };
                export type ScoreSetData = {
                    human_writing_index: Modules.SocialMedia.Application.DTOs.ScoreResultData;
                    virality_score: Modules.SocialMedia.Application.DTOs.ScoreResultData;
                    engagement_score: Modules.SocialMedia.Application.DTOs.ScoreResultData;
                    roi_score: Modules.SocialMedia.Application.DTOs.ScoreResultData;
                    trend_alignment: Modules.SocialMedia.Application.DTOs.ScoreResultData;
                    all_scores_pass: boolean;
                    overall_average: number;
                };
                export type SocialMediaContentFilterData = {
                    search: string | null;
                    status: string | null;
                    date_from: string | null;
                    date_to: string | null;
                };
                export type SocialMediaTopicIdeaData = {
                    title: string;
                    angle: string;
                    hook: string;
                    platform: string;
                    estimated_virality: number;
                    estimated_engagement: string;
                    estimated_roi: number;
                    difficulty: string;
                    why_it_works: string;
                    key_trend: string;
                    suggested_format: string;
                    content_type: string;
                    funnel_stage: string;
                };
                export type SuggestSocialMediaTopicsData = {
                    provider: string;
                    language: string;
                    niche: string | null;
                    audience: string | null;
                    businessGoal: string | null;
                };
                export type UpdateSocialMediaContentData = {
                    headline: string;
                    body: string;
                    callToAction: string;
                    hashtags: string[];
                    status: string;
                    scheduledAt: string | null;
                };
                export type VideoPackageData = {
                    scenes: Modules.SocialMedia.Application.DTOs.VideoSceneData[];
                    clean_script: string;
                    sound_suggestion: string;
                    target_duration_seconds: number;
                    creative_style: string;
                };
                export type VideoSceneData = {
                    time_range: string;
                    action: string;
                    on_screen_text: string;
                    voiceover_line: string;
                    visual_prompt: string;
                };
            }
            namespace ReadModels {
                export type SocialMediaContentPublicReadModel = {
                    uuid: string;
                    topic: string;
                    headline: string | null;
                    body: string | null;
                    call_to_action: string | null;
                    hashtags: string[];
                    cover_image_url: string | null;
                    funnel_stage: string;
                    language: string;
                    platforms: Record<
                        string,
                        {
                            platform: string;
                            adapted_content: string;
                            hashtags: string[];
                            image_url: string | null;
                        }
                    > | null;
                    published_at: string | null;
                };
            }
        }
        namespace Domain {
            namespace Enums {
                export type BrandVoice =
                    | 'professional'
                    | 'conversational'
                    | 'trendy'
                    | 'inspirational'
                    | 'humorous';
                export type BusinessGoal =
                    | 'awareness'
                    | 'engagement'
                    | 'viral'
                    | 'leads'
                    | 'sales'
                    | 'community';
                export type ContentLanguage = 'es' | 'en' | 'pt-PT';
                export type FunnelStage = 'tofu' | 'mofu' | 'bofu';
                export type SocialMediaContentStatus =
                    | 'draft'
                    | 'generating'
                    | 'ready'
                    | 'needs_review'
                    | 'published'
                    | 'scheduled';
                export type SocialMediaImageMode = 'full' | 'base' | 'none';
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

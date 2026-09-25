<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * One canonical skill key per requirement, or null when the requirement is not
 * a skill at all (FR-26, FR-32).
 *
 * A requirement-frequency report is only as good as its grouping key. Raw
 * `canonical_name` strings are not one: a single real market signal arrives
 * under half a dozen spellings — "English Communication Skills",
 * "English communication skills", "English Proficiency" and "English language
 * assessment" are the same demand, counted four times at a quarter of the
 * frequency each. Worse, a real 21-posting corpus produced a table whose three
 * top rows were "Adaptability and Flexibility", "Reliability" and
 * "Self-Organization": HR filler, not skills, each with as many postings as
 * Laravel had. The report was faithfully counting things that are not skills.
 *
 * So this does three jobs, in order:
 *
 * 1. Drop what is not a skill — connectivity ("Stable internet connection
 *    (min 50 Mbit/s)"), equipment ("Headset, two monitors, webcam"), admin
 *    scraped from unrelated templates ("Gmail and social media usage",
 *    "Bookkeeping experience"), and years-of-experience / degree clauses,
 *    which the funnel counters already carry. These are real requirements of
 *    real postings and are deliberately NOT dropped silently: the store keeps
 *    every row, and only the frequency table omits them.
 * 2. Count availability and eligibility clauses ("EU residency outside
 *    Germany", "Full-time availability", "Preference for contract work") in
 *    their own bucket, never mixed into the skill table. For a contract
 *    seeker this bucket is often the most actionable part of the report.
 * 3. Map known aliases and facets onto one key, most specific first
 *    ("Application Architecture Expertise" → `application_architecture`).
 *
 * Soft skills are deliberately KEPT. "Reliability" and "Adaptability" really
 * are the most frequent requirements in a scraped corpus — a real 21-posting
 * run put them above Laravel — and silently dropping them would make the
 * report disagree with its own data. They stay in the table; the reader's job
 * is to know that a requirement every posting lists differentiates nobody.
 *
 * Deterministic (NFR-1): no model, no clock, no locale-sensitive comparison
 * beyond ASCII case folding. The same requirement always yields the same key.
 *
 * Not `readonly` for one reason: the alias map is rebuilt once per process and
 * cached in a static, and a readonly property cannot carry a default. The class
 * holds no instance state, so nothing is lost by dropping the modifier.
 */
final class RequirementCanonicalizer
{
    /**
     * Facets and aliases, mapped to one canonical key. Keys are lowercase —
     * the same shape `GateCode` uses — so rule-extracted and LLM-extracted
     * requirements land on the same key instead of two disjoint populations.
     * The display form lives in {@see LABELS}.
     *
     * @var array<string, string>
     */
    private const array ALIASES = [
        // English, the single most-fragmented signal in real corpora.
        'english professional' => 'english',
        'english proficiency' => 'english',
        'english communication skills' => 'english',
        'english communication' => 'english',
        'english language' => 'english',
        'english language assessment' => 'english',
        'english writing' => 'english',
        'english writing skills' => 'english',
        'business english' => 'english',

        // Architecture, the classic "Expertise/Knowledge/Experience" facet.
        'application architecture' => 'application_architecture',
        'application architecture expertise' => 'application_architecture',
        'application architecture knowledge' => 'application_architecture',
        'application architecture skill' => 'application_architecture',
        'software architecture' => 'application_architecture',
        'system design' => 'application_architecture',
        'technical architecture skills' => 'application_architecture',

        // Frontend pairings — the axis the report exists to inform.
        'react' => 'react',
        'react experience' => 'react',
        'typescript react and related technologies' => 'react',
        'react node nextjs experience' => 'react',
        'vue' => 'vue',
        'vue js' => 'vue',
        'vue.js' => 'vue',
        'vue inertia' => 'vue',
        'inertia vue' => 'vue',
        'typescript' => 'typescript',
        'typescript expertise' => 'typescript',
        'type script modern css' => 'typescript',
        'laravel' => 'laravel',
        'laravel php' => 'laravel',
        'php laravel' => 'laravel',
        'laravel php nuxt knowledge' => 'laravel',
        'php' => 'laravel',

        // Infrastructure, commonly split by vendor when it is one capability.
        'docker' => 'docker',
        'docker and docker compose' => 'docker',
        'kubernetes' => 'kubernetes',
        'terraform kubernetes sql experience' => 'kubernetes',
        'restful api development' => 'rest_api',
        'rest api' => 'rest_api',
        'api design' => 'rest_api',
        'database management mysql postgresql' => 'sql',
        'postgresql' => 'sql',
        'mysql' => 'sql',
        'redis' => 'redis',
        'terraform' => 'terraform',
        'elasticsearch' => 'elasticsearch',
        'microservices experience' => 'microservices',
        'performance caching optimization' => 'performance',

        // AI, the cluster a 2026 corpus keeps asking for.
        'rag and llm' => 'rag',
        'rag or openai' => 'rag',
        'llm integrations' => 'llm',
        'llm frameworks apis' => 'llm',
        'llms vllms' => 'llm',
        'langchain' => 'langchain',
        'vector databases pinecone or weaviate' => 'vector_db',
        'ai ml model integration' => 'ai_ml',
        'ai backed backend development' => 'ai_ml',
        'ai assisted coding' => 'ai_ml',
        'commercial ai experience' => 'ai_ml',
        'production ai system deployment experience' => 'ai_ml',
        'senior ai ml engineering experience' => 'ai_ml',
        'other ai programming languages' => 'ai_ml',
        'understanding of ai tradeoffs' => 'ai_ml',
        'not simple ai wrapper developers' => 'ai_ml',
        'not without production ai experience' => 'ai_ml',

        // Frontend delivery, adjacent to the pairings above.
        'tailwind css' => 'tailwind',
        'state management api integration' => 'state_management',
        'full stack api design' => 'full_stack',
        'full stack development schema api ui tests' => 'full_stack',
        'client side single page application development' => 'spa',
        'restful api' => 'rest_api',
        'html' => 'html',
        'http' => 'http',
        'css' => 'css',

        // Soft skills worth keeping — unlike the filler below, these are real
        // and are named in genuine technical JDs.
        'adaptability' => 'adaptability',
        'adaptability and flexibility' => 'adaptability',
        'flexibility' => 'adaptability',
        'self organization' => 'organization',
        'organizational skills' => 'organization',
        'attention to detail and organization' => 'organization',
        'self organization organizational skills' => 'organization',
        'attention to detail' => 'attention_to_detail',
        'communication' => 'communication',
        'communication skills' => 'communication',
        'team collaboration' => 'teamwork',
        'teamwork' => 'teamwork',
        'problem solving' => 'problem_solving',
        'problem solving critical thinking' => 'problem_solving',
        'critical thinking' => 'problem_solving',
        'code review experience' => 'code_review',
        'code review' => 'code_review',
        'reliability' => 'reliability',
        'self direction' => 'self_direction',
        'client management leadership' => 'client_management',
        'client oriented approach' => 'client_management',
        'leadership mentoring' => 'leadership',
        'it project leadership experience' => 'leadership',
        'microservices' => 'microservices',
        'open source contributions' => 'open_source',
    ];

    /**
     * Display form for each canonical key. The stored key is the stable slug;
     * this is what a reader sees in the table.
     *
     * @var array<string, string>
     */
    private const array LABELS = [
        'english' => 'English',
        'application_architecture' => 'Application architecture',
        'react' => 'React',
        'vue' => 'Vue',
        'typescript' => 'TypeScript',
        'laravel' => 'Laravel',
        'docker' => 'Docker',
        'kubernetes' => 'Kubernetes',
        'rest_api' => 'REST API',
        'sql' => 'SQL',
        'redis' => 'Redis',
        'terraform' => 'Terraform',
        'elasticsearch' => 'Elasticsearch',
        'microservices' => 'Microservices',
        'performance' => 'Performance & caching',
        'rag' => 'RAG',
        'llm' => 'LLM integration',
        'langchain' => 'LangChain',
        'vector_db' => 'Vector databases',
        'ai_ml' => 'AI / ML',
        'tailwind' => 'Tailwind CSS',
        'state_management' => 'State management',
        'full_stack' => 'Full-stack & API design',
        'spa' => 'SPA development',
        'html' => 'HTML',
        'http' => 'HTTP',
        'css' => 'CSS',
        'adaptability' => 'Adaptability',
        'organization' => 'Organization',
        'attention_to_detail' => 'Attention to detail',
        'communication' => 'Communication',
        'teamwork' => 'Teamwork',
        'problem_solving' => 'Problem solving',
        'code_review' => 'Code review',
        'reliability' => 'Reliability',
        'self_direction' => 'Self direction',
        'client_management' => 'Client management',
        'leadership' => 'Leadership',
        'open_source' => 'Open-source contribution',
    ];

    /**
     * Substrings that mark a requirement as job logistics or HR filler rather
     * than a skill. Matched against the normalized string.
     *
     * @var list<string>
     */
    private const array NOISE_MARKERS = [
        // Connectivity and equipment — a real requirement of a real posting,
        // and completely useless in a skill-frequency table.
        'internet connection', 'internet access', 'reliable internet',
        'broadband', 'wifi', 'headset', 'webcam', 'monitor', 'computer equipment',
        'personal computer', 'powerful pc', 'smartphone', 'mobile phone',
        'home office', 'remote workspace', 'quiet', 'undisturbed', 'desk space',
        'web browser', 'computer access', 'equipment',

        // Eligibility and geography live in ELIGIBILITY_MARKERS above, where
        // they are counted rather than dropped.

        // HR filler.
        'politeness', 'patience', 'professionalism', 'self starter',
        'self-starter', 'self direction', 'self_direction', 'willingness to learn',
        'willing to learn', 'calm, collaborative', 'discretion and ethics',
        'diverse creative', 'creative writing', 'entrepreneurial mindset',
        'financial motivation', 'growth oriented', 'team player',
        'mandatory training', 'training attendance', 'not early-career',
        'not small gig', 'not simple ai wrapper', 'positive attitude',
        'strong work ethic', 'hardworking', 'motivated',

        // Years of experience and degree — eligibility, not a skill. Kept out of
        // the frequency table; the funnel counters already carry experience.
        'years of experience', 'years experience', 'year experience',
        'minimum 4 years', 'at least 4 years', 'it degree', 'degree or certification',
        'bachelor', 'master degree', 'university degree',

        // Generic admin that survives in scraped templates.
        'gmail', 'social media usage', 'word processing', 'spreadsheet',
        'gaming and social', 'data entry', 'bookkeeping', 'administrative and bookkeeping',
        'billing and admin', 'issue compliant invoices', 'customer service',
        'phone customer', 'direct end-user support', 'end-user support',
        'professional writing', 'seo writing', 'marketing web developer',

        // Domain noise from boards that mix roles: a PHP frequency report does
        // not need the requirement lists of data collection, 3D or Shopify jobs.
        'xlsform', 'xform', 'openrosa', 'webgl', '3d graphics', 'shopify',
        'databricks', 'ruby on rails', 'cypress or selenium',
    ];

    /**
     * Requirements that look like skills but are logistics in disguise: the
     * availability/eligibility half of a posting. Reported separately so a
     * reader can see the corpus was not silently truncated.
     *
     * @var list<string>
     */
    private const array ELIGIBILITY_MARKERS = [
        'availability', 'full-time', 'full time', 'freelancer', 'self-employed',
        'independent contractor', 'contract work', 'contractor engagement',
        'tax compliance', 'remote', 'location', 'schedule', 'hours/week',
        'hours per week', 'open source contributions', 'portfolio',
        'reading comprehension', 'track record of shipping', 'proficient use of',
        // Geography and right to work — a real clause of a real posting, and
        // for a contract seeker the single most decisive line in the report.
        'residency', 'residence', 'resid', 'based location', 'visa',
        'work permit', 'right to work', 'relocation', 'time zone', 'timezone',
        'nationality', 'citizenship', 'citizen', 'uk-based', 'us residency',
        'americas, europe', 'eu residency', 'israel',
    ];

    /**
     * Normalized alias → canonical key, built once from the readable const.
     *
     * @var array<string, string>|null
     */
    private static ?array $targets = null;

    /**
     * @return array<string, string>
     */
    private static function targets(): array
    {
        if (self::$targets !== null) {
            return self::$targets;
        }

        $targets = [];

        foreach (self::ALIASES as $alias => $key) {
            $targets[(string) self::normalizeText((string) $alias)] = $key;
        }

        return self::$targets = $targets;
    }

    /**
     * The canonical key, or null when the requirement carries no skill signal.
     */
    public function key(string $requirement): ?string
    {
        $normalized = self::normalizeText($requirement);

        if ($normalized === '' || $this->isNoise($normalized)) {
            return null;
        }

        $targets = self::targets();

        if (isset($targets[$normalized])) {
            return $targets[$normalized];
        }

        // "Vue.js", "React.js" and "Node.js" keep their dot through
        // normalization, so retry without it before giving up on the catalog.
        $withoutDots = str_replace('.', ' ', $normalized);

        if ($withoutDots !== $normalized && isset($targets[$withoutDots])) {
            return $targets[$withoutDots];
        }

        return str_replace(' ', '_', $normalized);
    }

    /**
     * Display label for a key: the human form stored in the table.
     */
    public function label(string $key): string
    {
        return self::LABELS[$key] ?? mb_convert_case(str_replace('_', ' ', $key), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * True when the requirement is job logistics, eligibility or HR filler.
     *
     * @param  string  $normalized  already passed through {@see normalize()}
     */
    public function isNoise(string $normalized): bool
    {
        foreach (self::NOISE_MARKERS as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The human label for the key a requirement maps to, or null when the
     * requirement is noise. One call instead of normalize-then-key-then-label.
     */
    public function labelFor(string $requirement): ?string
    {
        $key = $this->key($requirement);

        return $key === null ? null : $this->label($key);
    }

    /**
     * True when a skill requirement is really an availability or eligibility
     * clause ("Full-time availability", "EU residency outside Germany",
     * "Preference for contract work"). Kept out of the skill table and
     * counted in their own bucket.
     */
    public function isEligibility(string $requirement): bool
    {
        $normalized = self::normalizeText($requirement);

        if ($normalized === '') {
            return false;
        }

        // Checked before noise on purpose: "EU residency outside Germany" also
        // contains a noise marker, but it is a real eligibility clause the
        // reader should see counted, not a line to vanish.
        foreach (self::ELIGIBILITY_MARKERS as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lowercase, collapse whitespace, unify the two separators the two
     * extractors emit (`-`, `_`, `/`) into a single space, and strip
     * surrounding punctuation, so "Attention-to-Detail", "attention_to_detail"
     * and "Attention to Detail" all reduce to the same lookup.
     */
    private static function normalizeText(string $requirement): string
    {
        $normalized = mb_strtolower(trim($requirement));
        $normalized = (string) preg_replace('/[\s_\-\/]+/u', ' ', $normalized);
        $normalized = (string) preg_replace('/[^\p{L}\p{N}\s.+#]/u', '', $normalized);

        return trim((string) preg_replace('/\s+/u', ' ', $normalized));
    }
}

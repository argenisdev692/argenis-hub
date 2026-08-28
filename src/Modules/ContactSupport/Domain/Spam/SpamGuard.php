<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Domain\Spam;

use Spatie\Honeypot\ProtectAgainstSpam;

/**
 * Content-heuristic spam scoring for the public contact form.
 *
 * This is the second line of defence, not the first: the route already runs
 * spatie/laravel-honeypot ({@see ProtectAgainstSpam}) and a
 * per-IP throttle, which stop the crude bots. SpamGuard scores what gets
 * through — link floods, SEO/crypto keyword blasts, shouting, throw-away
 * inboxes, links smuggled into the name field — and produces a 0–100 score
 * plus the list of signals that fired, so the operator inbox filter has
 * something to sort on and a false positive can be restored with context.
 *
 * Pure and I/O-free by design (unit-tested without a database). The container
 * binds {@see fromConfig()}; construct it directly with overrides in tests.
 */
final class SpamGuard
{
    /** @var list<string> */
    private const array DEFAULT_BLOCKLIST = [
        'viagra', 'cialis', 'casino', 'poker', 'bitcoin', 'crypto', 'forex',
        'binary options', 'payday loan', 'seo services', 'buy backlinks',
        'guest post', 'make money fast', 'work from home', 'weight loss',
        'this is not a scam', 'nigerian prince', 'click here now',
        'limited time offer', 'wire transfer',
    ];

    /** @var list<string> */
    private const array DEFAULT_DISPOSABLE_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', 'sharklasers.com',
        '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'yopmail.com',
        'trashmail.com', 'getnada.com', 'maildrop.cc', 'fakeinbox.com',
    ];

    /**
     * @param  list<string>  $blocklist
     * @param  list<string>  $disposableDomains
     */
    public function __construct(
        private readonly int $threshold = 60,
        private readonly int $freeLinks = 1,
        private readonly int $linkPerUrlWeight = 20,
        private readonly int $linkMarkupWeight = 35,
        private readonly int $keywordPerHitWeight = 25,
        private readonly int $shoutingWeight = 15,
        private readonly int $repetitionWeight = 10,
        private readonly int $disposableEmailWeight = 25,
        private readonly int $nameContainsLinkWeight = 30,
        private readonly int $gibberishPhoneWeight = 10,
        private readonly array $blocklist = self::DEFAULT_BLOCKLIST,
        private readonly array $disposableDomains = self::DEFAULT_DISPOSABLE_DOMAINS,
    ) {}

    public static function fromConfig(): self
    {
        /** @var array<string, int> $weights */
        $weights = (array) config('contact-support.spam.weights', []);

        /** @var list<string> $blocklist */
        $blocklist = (array) config('contact-support.spam.blocklist', self::DEFAULT_BLOCKLIST);

        /** @var list<string> $disposable */
        $disposable = (array) config('contact-support.spam.disposable_domains', self::DEFAULT_DISPOSABLE_DOMAINS);

        return new self(
            threshold: (int) config('contact-support.spam.threshold', 60),
            freeLinks: (int) config('contact-support.spam.free_links', 1),
            linkPerUrlWeight: (int) ($weights['link_per_url'] ?? 20),
            linkMarkupWeight: (int) ($weights['link_markup'] ?? 35),
            keywordPerHitWeight: (int) ($weights['keyword_per_hit'] ?? 25),
            shoutingWeight: (int) ($weights['shouting'] ?? 15),
            repetitionWeight: (int) ($weights['repetition'] ?? 10),
            disposableEmailWeight: (int) ($weights['disposable_email'] ?? 25),
            nameContainsLinkWeight: (int) ($weights['name_contains_link'] ?? 30),
            gibberishPhoneWeight: (int) ($weights['gibberish_phone'] ?? 10),
            blocklist: array_values(array_map('strval', $blocklist)),
            disposableDomains: array_values(array_map('strval', $disposable)),
        );
    }

    /**
     * Score one submission. A clean enquiry returns {@see SpamAssessment::clean()}.
     */
    public function assess(
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $subject,
        string $message,
    ): SpamAssessment {
        $score = 0;

        /** @var list<string> $reasons */
        $reasons = [];

        $links = $this->countLinks($message);

        if ($links > $this->freeLinks) {
            $score += ($links - $this->freeLinks) * $this->linkPerUrlWeight;
            $reasons[] = 'too_many_links';
        }

        if ($this->hasLinkMarkup($message)) {
            $score += $this->linkMarkupWeight;
            $reasons[] = 'link_markup';
        }

        $keywordHits = $this->countKeywordHits($subject.' '.$message);

        if ($keywordHits > 0) {
            $score += $keywordHits * $this->keywordPerHitWeight;
            $reasons[] = 'blocked_keyword';
        }

        if ($this->isShouting($message)) {
            $score += $this->shoutingWeight;
            $reasons[] = 'excessive_caps';
        }

        if ($this->hasCharacterFlood($message)) {
            $score += $this->repetitionWeight;
            $reasons[] = 'character_repetition';
        }

        if ($this->hasDisposableDomain($email)) {
            $score += $this->disposableEmailWeight;
            $reasons[] = 'disposable_email';
        }

        if ($this->countLinks($firstName.' '.$lastName) > 0 || $this->hasLinkMarkup($firstName.' '.$lastName)) {
            $score += $this->nameContainsLinkWeight;
            $reasons[] = 'name_contains_link';
        }

        if ($this->hasGibberishPhone($phone)) {
            $score += $this->gibberishPhoneWeight;
            $reasons[] = 'suspicious_phone';
        }

        $score = min(100, $score);

        if ($reasons === []) {
            return SpamAssessment::clean();
        }

        return new SpamAssessment($score, $reasons, $score >= $this->threshold);
    }

    private function countLinks(string $text): int
    {
        $pattern = '#\bhttps?://|\bwww\.[a-z0-9-]+\.[a-z]{2,}|\b[a-z0-9][a-z0-9-]*\.(?:com|net|org|io|ru|info|biz|xyz|top|online|site|shop|link|click|club)\b#i';

        return (int) preg_match_all($pattern, $text);
    }

    private function hasLinkMarkup(string $text): bool
    {
        return preg_match('#\[/?url|\[/?link|<a\s|</a>|\[/?a\]#i', $text) === 1;
    }

    private function countKeywordHits(string $haystack): int
    {
        $haystack = mb_strtolower($haystack);
        $hits = 0;

        foreach ($this->blocklist as $needle) {
            if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                $hits++;
            }

            if ($hits === 2) {
                break;
            }
        }

        return $hits;
    }

    private function isShouting(string $message): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $message) ?? '';

        if (mb_strlen($letters) < 20) {
            return false;
        }

        $upper = preg_replace('/[^\p{Lu}]/u', '', $letters) ?? '';

        return mb_strlen($upper) / mb_strlen($letters) > 0.6;
    }

    private function hasCharacterFlood(string $message): bool
    {
        return preg_match('/(.)\1{6,}/u', $message) === 1
            || preg_match('/[!?]{4,}/', $message) === 1;
    }

    private function hasDisposableDomain(string $email): bool
    {
        $at = mb_strrpos($email, '@');

        if ($at === false) {
            return false;
        }

        $domain = mb_strtolower(mb_substr($email, $at + 1));

        foreach ($this->disposableDomains as $blocked) {
            $blocked = mb_strtolower($blocked);

            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }

    private function hasGibberishPhone(string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (mb_strlen($digits) < 7) {
            return false;
        }

        return preg_match('/(\d)\1{6,}/', $digits) === 1;
    }
}

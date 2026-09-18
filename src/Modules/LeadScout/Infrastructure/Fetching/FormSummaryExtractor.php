<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Fetching;

/**
 * Form inventory from raw HTML (spec US-12, T050): field names, textarea
 * presence, CAPTCHA markers and the submit host. The HTML itself is never
 * persisted — callers keep only this summary.
 */
final readonly class FormSummaryExtractor
{
    /**
     * @return array{forms: list<array{fields: list<string>, has_textarea: bool, has_captcha: bool, action_host: ?string}>}|null
     */
    public static function summarize(?string $html, string $pageUrl): ?array
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $forms = [];

        if (preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/is', $html, $matches, PREG_SET_ORDER) === 0) {
            return null;
        }

        foreach ($matches as $match) {
            $fields = [];

            if (preg_match_all('/<(?:input|select|textarea)\b[^>]*\bname\s*=\s*["\']?([^"\'\s>]+)/i', $match[0], $names) > 0) {
                foreach ($names[1] as $name) {
                    $fields[] = mb_strtolower(trim($name));
                }
            }

            $fields = array_values(array_unique($fields));

            if ($fields === []) {
                continue;
            }

            $forms[] = [
                'fields' => $fields,
                'has_textarea' => preg_match('/<textarea\b/i', $match[0]) === 1,
                'has_captcha' => preg_match('/captcha|recaptcha|turnstile|hcaptcha|cf-challenge/i', $match[0]) === 1,
                'action_host' => self::actionHost($match[1], $pageUrl),
            ];
        }

        return $forms === [] ? null : ['forms' => $forms];
    }

    private static function actionHost(string $attributes, string $pageUrl): ?string
    {
        if (preg_match('/\baction\s*=\s*["\']?([^"\'\s>]+)/i', $attributes, $m) !== 1) {
            return null;
        }

        $action = trim($m[1]);

        if ($action === '' || str_starts_with($action, '#') || str_starts_with($action, 'javascript:') || str_starts_with($action, 'mailto:')) {
            return null;
        }

        if (preg_match('~^[a-z][a-z0-9+.-]*://([^/:?#]+)~i', $action, $host) === 1) {
            return mb_strtolower($host[1]);
        }

        if (preg_match('~^[a-z][a-z0-9+.-]*://([^/:?#]+)~i', $pageUrl, $host) === 1) {
            return mb_strtolower($host[1]);
        }

        return null;
    }
}

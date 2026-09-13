<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI\PromptCache;

/**
 * A stable part of a prompt that repeated calls share.
 *
 * `longLived` layers are expected to be reused across many calls over
 * minutes (a course, a campaign, a knowledge base) and get the long TTL where
 * the provider supports one; the rest get the provider's default window.
 */
final readonly class PromptLayer
{
    public function __construct(
        public string $text,
        public bool $longLived = false,
    ) {}

    public static function long(string $text): self
    {
        return new self($text, true);
    }

    public static function short(string $text): self
    {
        return new self($text, false);
    }

    public function isEmpty(): bool
    {
        return trim($this->text) === '';
    }
}

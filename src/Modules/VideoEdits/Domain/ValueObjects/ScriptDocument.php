<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * A script the user attached (`.md` or `.pdf`), reduced to plain text (EX-9).
 *
 * The extracted text is what reaches the AI, never the original file: a PDF is
 * a container that can carry far more than the words on the page, and the model
 * only needs the words.
 */
final readonly class ScriptDocument
{
    public function __construct(
        public string $fileName,
        public string $text,
    ) {}

    public function isEmpty(): bool
    {
        return trim($this->text) === '';
    }

    /**
     * Scripts are short next to a transcript, but a pasted-in appendix can be
     * enormous; truncating keeps one runaway upload from blowing the context
     * window and the bill.
     */
    #[\NoDiscard]
    public function truncated(int $maxCharacters): self
    {
        return mb_strlen($this->text) <= $maxCharacters
            ? $this
            : new self($this->fileName, mb_substr($this->text, 0, $maxCharacters));
    }
}

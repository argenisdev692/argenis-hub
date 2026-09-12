<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Parsing;

use Modules\CourseScripts\Domain\Exceptions\UnrecognisableIndexException;
use Modules\CourseScripts\Domain\Ports\IndexDocumentParserPort;
use Modules\CourseScripts\Domain\ValueObjects\ParsedIndex;

/**
 * Selects the parser for an uploaded index by its REAL MIME type (spec FR-1).
 *
 * The bound implementation of {@see IndexDocumentParserPort}: handlers depend on
 * the port and never on a concrete format, so adding a future format is a new
 * adapter plus one entry here.
 *
 * The type it dispatches on must come from `ext-fileinfo` reading the actual
 * bytes, never from the client-supplied `Content-Type` — a caller that renames
 * `payload.exe` to `index.pdf` must not choose the branch (FR-58).
 */
final readonly class IndexDocumentParser implements IndexDocumentParserPort
{
    /**
     * @param  list<IndexDocumentParserPort>  $parsers
     */
    public function __construct(private array $parsers) {}

    public function supports(string $mimeType): bool
    {
        return $this->resolve($mimeType) !== null;
    }

    public function parse(string $absolutePath, string $mimeType): ParsedIndex
    {
        $parser = $this->resolve($mimeType);

        if ($parser === null) {
            throw new UnrecognisableIndexException([
                sprintf('Unsupported file type "%s". Upload a Markdown or PDF index.', $mimeType),
            ]);
        }

        return $parser->parse($absolutePath, $mimeType);
    }

    private function resolve(string $mimeType): ?IndexDocumentParserPort
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($mimeType)) {
                return $parser;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

/**
 * FR-36d: a table that declares a total row must add up. A comparison demo
 * over two proposals is ruined by a wrong total — the sample's are exact
 * (16.280 + 9.072 + 1.704 + 1.800 = 28.856 €).
 *
 * Numbers are read in Spanish (`16.280,50 €`) and English (`16,280.50`)
 * formats. A column is checked only when every data row has a number in it.
 */
final readonly class TableArithmeticValidator
{
    public function __construct(
        private float $tolerance = 0.01,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $blocks  artifact content blocks
     * @return list<string>
     */
    #[\NoDiscard]
    public function violations(string $fileName, array $blocks): array
    {
        $violations = [];

        foreach ($blocks as $index => $block) {
            if (($block['type'] ?? null) !== 'table' || ! (bool) ($block['total_row'] ?? false)) {
                continue;
            }

            $rows = array_values((array) ($block['table_rows'] ?? []));

            if (count($rows) < 2) {
                continue;
            }

            $total = array_pop($rows);
            $columns = max(array_map(static fn (array $row): int => count($row), [...$rows, $total]));

            for ($column = 1; $column < $columns; $column++) {
                $expected = $this->number((string) ($total[$column] ?? ''));

                if ($expected === null) {
                    continue;
                }

                $values = array_map(fn (array $row): ?float => $this->number((string) ($row[$column] ?? '')), $rows);

                if (in_array(null, $values, true)) {
                    continue;
                }

                $sum = array_sum($values);

                if (abs($sum - $expected) > max($this->tolerance, abs($expected) * 0.0005)) {
                    $violations[] = sprintf(
                        'In "%s", table %d column %d: rows add up to %s but the total row says %s.',
                        $fileName,
                        $index + 1,
                        $column + 1,
                        rtrim(rtrim(number_format($sum, 2, '.', ''), '0'), '.'),
                        rtrim(rtrim(number_format($expected, 2, '.', ''), '0'), '.'),
                    );
                }
            }
        }

        return $violations;
    }

    /**
     * The first money-like number in a cell, or null. Cells such as
     * "7,40 €/envío" (a unit price) parse too, which is why only columns with
     * a total are compared.
     */
    public function number(string $cell): ?float
    {
        if (preg_match('/-?\d[\d.,\s]*/u', $cell, $match) !== 1) {
            return null;
        }

        $raw = preg_replace('/\s+/u', '', rtrim($match[0], '.,')) ?? '';
        $hasComma = str_contains($raw, ',');
        $hasDot = str_contains($raw, '.');

        $decimal = match (true) {
            // Both present: whichever comes last is the decimal separator.
            $hasComma && $hasDot => strrpos($raw, ',') > strrpos($raw, '.') ? ',' : '.',
            // "16,280" (English thousands) vs "7,40" (Spanish decimal).
            $hasComma => preg_match('/^\d{1,3}(,\d{3})+$/', $raw) === 1 ? null : ',',
            // "16.280" (Spanish thousands) vs "7.5" (English decimal).
            $hasDot => preg_match('/^\d{1,3}(\.\d{3})+$/', $raw) === 1 ? null : '.',
            default => null,
        };

        $normalised = match ($decimal) {
            ',' => str_replace(',', '.', str_replace('.', '', $raw)),
            '.' => str_replace(',', '', $raw),
            default => str_replace([',', '.'], '', $raw),
        };

        return is_numeric($normalised) ? (float) $normalised : null;
    }
}

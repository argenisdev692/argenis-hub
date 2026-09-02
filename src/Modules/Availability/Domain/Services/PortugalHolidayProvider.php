<?php

declare(strict_types=1);

namespace Modules\Availability\Domain\Services;

use Carbon\CarbonImmutable;

final readonly class PortugalHolidayProvider implements HolidayProvider
{
    public function countryCode(): string
    {
        return 'PT';
    }

    /**
     * @return array<string, string>
     */
    public function forYear(int $year): array
    {
        $easter = $this->easterSunday($year);

        return [
            sprintf('%d-01-01', $year) => 'Ano Novo',
            $easter->subDays(2)->format('Y-m-d') => 'Sexta-feira Santa',
            $easter->format('Y-m-d') => 'Páscoa',
            sprintf('%d-04-25', $year) => 'Dia da Liberdade',
            sprintf('%d-05-01', $year) => 'Dia do Trabalhador',
            $easter->addDays(60)->format('Y-m-d') => 'Corpo de Deus',
            sprintf('%d-06-10', $year) => 'Dia de Portugal',
            sprintf('%d-08-15', $year) => 'Assunção de Nossa Senhora',
            sprintf('%d-10-05', $year) => 'Implantação da República',
            sprintf('%d-11-01', $year) => 'Dia de Todos os Santos',
            sprintf('%d-12-01', $year) => 'Restauração da Independência',
            sprintf('%d-12-08', $year) => 'Imaculada Conceição',
            sprintf('%d-12-25', $year) => 'Natal',
        ];
    }

    private function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0);
    }
}

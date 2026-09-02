<?php

declare(strict_types=1);

namespace Modules\Availability\Tests\Unit;

use Modules\Availability\Domain\Services\PortugalHolidayProvider;
use PHPUnit\Framework\TestCase;

final class PortugalHolidayProviderTest extends TestCase
{
    private PortugalHolidayProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new PortugalHolidayProvider;
    }

    public function test_it_returns_the_thirteen_national_holidays_for_2026(): void
    {
        $holidays = $this->provider->forYear(2026);

        $this->assertCount(13, $holidays);

        $expected = [
            '2026-01-01' => 'Ano Novo',
            '2026-04-03' => 'Sexta-feira Santa', // Easter − 2
            '2026-04-05' => 'Páscoa',            // Easter Sunday
            '2026-04-25' => 'Dia da Liberdade',
            '2026-05-01' => 'Dia do Trabalhador',
            '2026-06-04' => 'Corpo de Deus',     // Easter + 60
            '2026-06-10' => 'Dia de Portugal',
            '2026-08-15' => 'Assunção de Nossa Senhora',
            '2026-10-05' => 'Implantação da República',
            '2026-11-01' => 'Dia de Todos os Santos',
            '2026-12-01' => 'Restauração da Independência',
            '2026-12-08' => 'Imaculada Conceição',
            '2026-12-25' => 'Natal',
        ];

        $this->assertSame($expected, $holidays);
    }

    public function test_it_computes_easter_derived_holidays_for_another_year(): void
    {
        // Easter 2025 = 20 April → Good Friday 18 Apr, Corpus Christi 19 Jun.
        $holidays = $this->provider->forYear(2025);

        $this->assertSame('Sexta-feira Santa', $holidays['2025-04-18'] ?? null);
        $this->assertSame('Páscoa', $holidays['2025-04-20'] ?? null);
        $this->assertSame('Corpo de Deus', $holidays['2025-06-19'] ?? null);
    }

    public function test_country_code_is_portugal(): void
    {
        $this->assertSame('PT', $this->provider->countryCode());
    }
}

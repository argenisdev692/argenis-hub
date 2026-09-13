<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Tests\Support;

/**
 * The author's sample practice pack — `GUIDE/MODULE-VIDEOS/Propuestas_Logistica_Heliantia`
 * (video 22) — as the structured plan and artifacts this module stores
 * (research §12). Values are copied from the sample, including its exact totals.
 */
final class CanonicalPracticePackFixture
{
    public const string FILE_A = 'Propuesta_Logistica_ProveedorA_2026';

    public const string FILE_B = 'Propuesta_Logistica_ProveedorB_2026';

    /**
     * @return array<string, mixed>
     */
    public static function plan(): array
    {
        return [
            'warranted' => true,
            'reason' => 'La DEMO 2 compara dos documentos subidos a Google Drive: el alumno necesita propuestas reales para ver una tabla comparativa útil.',
            'topic' => 'Google Workspace',
            'files_summary' => 'Archivos: '.self::FILE_A.'.pdf y '.self::FILE_B.'.pdf',
            'setup_instruction' => 'Subir ambos documentos a Google Drive como archivos separados con los nombres indicados.',
            'instructor_note' => 'Este documento contiene DOS propuestas de servicio logístico con diferencias deliberadas en precio, cobertura y penalizaciones para que la tabla comparativa generada por Claude resulte informativa y no trivial. El Proveedor A es más económico pero tiene más restricciones de cobertura. El Proveedor B tiene mejor cobertura pero mayor precio y penalizaciones más estrictas. Ambas son deliberadamente imperfectas para favorecer una recomendación con matices.',
            'artifacts' => [
                [
                    'file_name' => self::FILE_A,
                    'title' => 'Propuesta de servicio logístico — Proveedor A · Transportes Meridional S.L.',
                    'genre' => 'proposal',
                    'purpose' => 'Proveedor económico con cobertura limitada.',
                    'used_by_demos' => ['DEMO 2'],
                    'organisations' => ['Transportes Meridional S.L.', 'Heliantia Group'],
                ],
                [
                    'file_name' => self::FILE_B,
                    'title' => 'Propuesta de servicio logístico — Proveedor B · Logística Peninsular Norte S.A.',
                    'genre' => 'proposal',
                    'purpose' => 'Proveedor caro con cobertura completa.',
                    'used_by_demos' => ['DEMO 2'],
                    'organisations' => ['Logística Peninsular Norte S.A.', 'Heliantia Group'],
                ],
            ],
            'contrasts' => [
                ['dimension' => 'Precio mensual', 'intended_effect' => 'A es más barato', 'values' => [
                    ['file_name' => self::FILE_A, 'value' => '28.856 €'],
                    ['file_name' => self::FILE_B, 'value' => '32.252 €'],
                ]],
                ['dimension' => 'Cobertura geográfica', 'intended_effect' => 'B cubre islas sin suplemento', 'values' => [
                    ['file_name' => self::FILE_A, 'value' => '34 provincias propias'],
                    ['file_name' => self::FILE_B, 'value' => '50 provincias e islas'],
                ]],
                ['dimension' => 'Penalizaciones por incidencia', 'intended_effect' => 'B compensa más', 'values' => [
                    ['file_name' => self::FILE_A, 'value' => '15 %'],
                    ['file_name' => self::FILE_B, 'value' => '25 % + 2 €/envío'],
                ]],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function artifacts(): array
    {
        return [
            self::FILE_A => [
                'file_name' => self::FILE_A,
                'organisations' => [
                    ['name' => 'Transportes Meridional S.L.', 'role' => 'Proveedor A', 'sector' => 'Logística'],
                    ['name' => 'Heliantia Group', 'role' => 'Cliente', 'sector' => 'Industria'],
                ],
                'characters' => [['name' => 'Ramón Alcántara', 'role' => 'Persona de contacto', 'organisation' => 'Transportes Meridional S.L.']],
                'content_blocks' => [
                    self::heading(1, 'PROPUESTA DE SERVICIO LOGÍSTICO — PROVEEDOR A'),
                    self::heading(2, 'TRANSPORTES MERIDIONAL S.L.'),
                    self::keyValues([['Dirigida a', 'Heliantia Group'], ['Referencia', 'TM-2026-HG-031'], ['Fecha', '10 de abril de 2026']]),
                    self::heading(2, '1. PRESENTACIÓN'),
                    self::paragraph('Transportes Meridional S.L. es una empresa de logística con 22 años de actividad en el transporte nacional de mercancía industrial.'),
                    self::heading(2, '2. CONDICIONES ECONÓMICAS'),
                    self::table(
                        ['Concepto', 'Precio unitario', 'Volumen estimado', 'Importe mensual'],
                        [
                            ['Envío estándar peninsular (hasta 30 kg)', '7,40 €/envío', '2.200 envíos', '16.280 €'],
                            ['Envío pesado peninsular (30–150 kg)', '18,90 €/envío', '480 envíos', '9.072 €'],
                            ['Envío urgente 24h peninsular', '14,20 €/envío', '120 envíos', '1.704 €'],
                            ['Tarifa plataforma mensual (gestión y handling)', '1.800 €/mes', '—', '1.800 €'],
                            ['TOTAL MENSUAL ESTIMADO', '', '', '28.856 €'],
                        ],
                    ),
                    self::heading(2, '3. PENALIZACIONES POR INCIDENCIA'),
                    self::table(
                        ['Tipo de incidencia', 'Compensación', 'Condición'],
                        [['Retraso > 24h sobre plazo comprometido', 'Descuento del 15% sobre el envío afectado', 'Reclamación en los 5 días hábiles siguientes']],
                        totalRow: false,
                    ),
                    self::footer('Persona de contacto: Ramón Alcántara · r.alcantara@t-meridional.es · +34 963 211 445'),
                ],
            ],
            self::FILE_B => [
                'file_name' => self::FILE_B,
                'organisations' => [
                    ['name' => 'Logística Peninsular Norte S.A.', 'role' => 'Proveedor B', 'sector' => 'Logística'],
                ],
                'characters' => [['name' => 'Cristina Moya', 'role' => 'Persona de contacto', 'organisation' => 'Logística Peninsular Norte S.A.']],
                'content_blocks' => [
                    self::heading(1, 'PROPUESTA DE SERVICIO LOGÍSTICO — PROVEEDOR B'),
                    self::heading(2, 'LOGÍSTICA PENINSULAR NORTE S.A.'),
                    self::heading(2, '2. CONDICIONES ECONÓMICAS'),
                    self::table(
                        ['Concepto', 'Precio unitario', 'Volumen estimado', 'Importe mensual'],
                        [
                            ['Envío estándar peninsular (hasta 30 kg)', '8,90 €/envío', '2.200 envíos', '19.580 €'],
                            ['Envío pesado peninsular (30–150 kg)', '21,50 €/envío', '480 envíos', '10.320 €'],
                            ['Envío urgente 24h peninsular', '16,80 €/envío', '120 envíos', '2.016 €'],
                            ['Cobertura Baleares y Canarias (incluida en tarifa)', '+ 4,20 €/envío', 'estimado 80 env.', '336 €'],
                            ['TOTAL MENSUAL ESTIMADO', '', '', '32.252 €'],
                        ],
                    ),
                    self::list(['Revisión de tarifas: anual, aplicando el IPC del ejercicio anterior sin margen adicional.', 'Gestor de cuenta dedicado asignado desde el primer mes de contrato.']),
                    self::footer('Persona de contacto: Cristina Moya · c.moya@lpnorte.es · +34 944 512 380'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function heading(int $level, string $text): array
    {
        return self::block('heading', ['level' => $level, 'text' => $text]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function paragraph(string $text): array
    {
        return self::block('paragraph', ['text' => $text]);
    }

    /**
     * @param  list<string>  $items
     * @return array<string, mixed>
     */
    public static function list(array $items): array
    {
        return self::block('list', ['items' => $items]);
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     * @return array<string, mixed>
     */
    public static function table(array $header, array $rows, bool $totalRow = true): array
    {
        return self::block('table', ['table_header' => $header, 'table_rows' => $rows, 'total_row' => $totalRow]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return array<string, mixed>
     */
    public static function keyValues(array $pairs): array
    {
        return self::block('key_values', ['pairs' => array_map(static fn (array $pair): array => ['key' => $pair[0], 'value' => $pair[1]], $pairs)]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function footer(string $text): array
    {
        return self::block('footer', ['text' => $text]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function block(string $type, array $values): array
    {
        return [
            'type' => $type,
            'level' => 0,
            'text' => '',
            'items' => [],
            'table_header' => [],
            'table_rows' => [],
            'total_row' => false,
            'pairs' => [],
            ...$values,
        ];
    }
}

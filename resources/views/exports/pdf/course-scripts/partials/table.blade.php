{{-- $header: list<string>, $rows: list<list<string>>, $totalRow: bool --}}
@php
    $columns = max(count($header), ...array_map(static fn (array $row): int => count($row), $rows ?: [[]]));
@endphp
@if ($columns > 0)
    <table class="grid">
        @if ($header !== [])
            <thead>
                <tr>
                    @for ($column = 0; $column < $columns; $column++)
                        <th>{{ $header[$column] ?? '' }}</th>
                    @endfor
                </tr>
            </thead>
        @endif
        <tbody>
            @foreach ($rows as $index => $row)
                <tr @class(['total' => ($totalRow ?? false) && $index === count($rows) - 1])>
                    @for ($column = 0; $column < $columns; $column++)
                        <td>{{ $row[$column] ?? '' }}</td>
                    @endfor
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

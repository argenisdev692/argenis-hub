{{-- A practice artifact's structured content (FR-36a). $blocks: list<array> --}}
@foreach ($blocks as $block)
    @switch($block['type'] ?? 'paragraph')
        @case('heading')
            @if ((int) ($block['level'] ?? 1) <= 1)
                <h1>{{ $block['text'] }}</h1>
            @elseif ((int) $block['level'] === 2)
                <h2>{{ $block['text'] }}</h2>
            @else
                <h3>{{ $block['text'] }}</h3>
            @endif
            @break
        @case('list')
            <ul>
                @foreach ((array) ($block['items'] ?? []) as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
            @break
        @case('table')
            @include('exports.pdf.course-scripts.partials.table', [
                'header' => (array) ($block['table_header'] ?? []),
                'rows' => (array) ($block['table_rows'] ?? []),
                'totalRow' => (bool) ($block['total_row'] ?? false),
            ])
            @break
        @case('key_values')
            <p class="kv">
                @foreach ((array) ($block['pairs'] ?? []) as $pair)
                    <span class="key">{{ $pair['key'] }}:</span> {{ $pair['value'] }}@if (! $loop->last) &nbsp;·&nbsp; @endif
                @endforeach
            </p>
            @break
        @case('footer')
            <p class="doc-footer">{{ $block['text'] }}</p>
            @break
        @default
            <p>{{ $block['text'] ?? '' }}</p>
    @endswitch
@endforeach

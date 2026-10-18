{{-- Materials & finishes: a table, because AI assistants and buyers both quote tables. --}}
@php $rows = array_values(array_filter((array) $block->field('rows', []))); @endphp
<section class="mx-auto max-w-4xl px-5 py-14 md:px-8">
    <h2 class="text-[clamp(24px,3vw,36px)]">{{ $block->field('heading', 'Materials & finishes') }}</h2>
    <div class="mt-6 overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-line text-left text-muted"><th class="py-2 pr-4 font-normal">Item</th><th class="py-2 font-normal">Specification</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-line/70">
                        <th scope="row" class="py-2.5 pr-4 text-left font-medium text-ink">{{ $row['label'] ?? '' }}</th>
                        <td class="py-2.5 text-body">{{ $row['value'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

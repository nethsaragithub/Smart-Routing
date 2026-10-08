@php
    use App\Services\Reports\Report;
    $query = $period->toQuery();
    $chart = $report->chart();
    $hasChartData = $chart && collect($chart['datasets'])->flatMap(fn ($d) => $d['data'])->filter()->isNotEmpty();
@endphp
<x-layouts.app :title="$report::title()">
    <x-page-header :title="$report::title()" :subtitle="$report::description()" :back="route('reports.index')">
        <x-slot:actions>
            <a href="{{ route('reports.export', ['report' => $report::key(), 'format' => 'csv'] + $query) }}" class="btn btn-secondary"><x-icon name="download" size="16" /> CSV</a>
            <a href="{{ route('reports.export', ['report' => $report::key(), 'format' => 'pdf'] + $query) }}" class="btn btn-primary"><x-icon name="download" size="16" /> Export PDF</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Period controls --}}
    <form method="GET" action="{{ route('reports.show', $report::key()) }}" class="panel mb-6 flex flex-wrap items-end gap-3 px-4 py-3"
          x-data="{ period: @js($period->type) }">
        <div class="inline-flex rounded-md border border-line-strong p-0.5" role="radiogroup" aria-label="Period">
            @foreach (['weekly' => 'Week', 'monthly' => 'Month', 'custom' => 'Custom'] as $value => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="period" value="{{ $value }}" x-model="period" class="peer sr-only" @change="if (period !== 'custom') $el.form.submit()">
                    <span class="block rounded px-3.5 py-1.5 text-sm font-medium text-ink-soft peer-checked:bg-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-signal">{{ $label }}</span>
                </label>
            @endforeach
        </div>

        <template x-if="period !== 'custom'">
            <div class="flex items-center gap-1">
                <a href="{{ route('reports.show', ['report' => $report::key()] + $period->previous()->toQuery()) }}" class="btn btn-ghost btn-sm" aria-label="Previous period"><x-icon name="chevron-left" size="16" /></a>
                <span class="min-w-40 text-center font-semibold">{{ $period->label() }}</span>
                <a href="{{ route('reports.show', ['report' => $report::key()] + $period->next()->toQuery()) }}" class="btn btn-ghost btn-sm" aria-label="Next period"><x-icon name="chevron-right" size="16" /></a>
                <input type="hidden" name="date" value="{{ $period->from->toDateString() }}">
            </div>
        </template>
        <template x-if="period === 'custom'">
            <div class="flex flex-wrap items-end gap-2">
                <div><label for="from" class="field-label">From</label><input id="from" type="date" name="from" value="{{ $period->from->toDateString() }}" class="control py-1.5 text-sm"></div>
                <div><label for="to" class="field-label">To</label><input id="to" type="date" name="to" value="{{ $period->to->toDateString() }}" class="control py-1.5 text-sm"></div>
                <button class="btn btn-primary btn-sm">Show</button>
            </div>
        </template>

        <label for="switch-report" class="sr-only">Switch report</label>
        <select id="switch-report" class="control ml-auto w-auto py-1.5 text-sm"
                onchange="window.location = this.value">
            @foreach ($reports as $item)
                <option value="{{ route('reports.show', ['report' => $item['key']] + $query) }}" @selected($item['key'] === $report::key())>{{ $item['title'] }}</option>
            @endforeach
        </select>
    </form>

    <section class="panel mb-6 grid grid-cols-2 gap-px overflow-hidden bg-line lg:grid-cols-4" aria-label="Summary">
        @foreach ($report->summary() as $label => $value)
            <x-stat :label="$label" :value="$value" />
        @endforeach
    </section>

    <div class="mb-6 grid gap-6 {{ $report->insights() ? 'xl:grid-cols-[1fr_360px]' : '' }}">
        @if ($chart)
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">{{ count($chart['datasets']) === 1 ? $chart['datasets'][0]['label'] : 'Overview' }}</h2></div>
                <div class="p-4">
                    @if ($hasChartData)
                        <x-chart :definition="$chart" :label="$report::title().' chart'" :height="$chart['horizontal'] ?? false ? max(220, count($chart['labels']) * 34) : 300" />
                    @else
                        <x-empty icon="chart" title="No data for this period" />
                    @endif
                </div>
            </section>
        @endif

        @if ($report->insights())
            <section class="panel self-start">
                <div class="panel-head"><h2 class="panel-title">Findings</h2></div>
                <ul class="space-y-3 p-5 text-sm">
                    @foreach ($report->insights() as $insight)
                        <li class="flex gap-3"><x-icon name="info" size="16" class="mt-0.5 text-muted" /><span>{{ $insight }}</span></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    @foreach ([['title' => 'Details', 'columns' => $report->columns(), 'rows' => $report->rows()], ...$report->extraTables()] as $table)
        <section class="panel mb-6 overflow-hidden">
            <div class="panel-head"><h2 class="panel-title">{{ $table['title'] }}</h2><span class="text-sm text-muted">{{ count($table['rows']) }} rows</span></div>
            @if (count($table['rows']) === 0)
                <x-empty icon="list" title="Nothing recorded in this period" />
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                        <tr>
                            @foreach ($table['columns'] as [$label, $format])
                                <th scope="col" @class(['num' => $format !== 'text'])>{{ $label }}</th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($table['rows'] as $row)
                            <tr>
                                @foreach ($table['columns'] as $key => [$label, $format])
                                    <td @class(['num whitespace-nowrap' => $format !== 'text'])>{{ Report::format($row[$key] ?? null, $format) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach
</x-layouts.app>

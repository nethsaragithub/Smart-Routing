<x-layouts.app title="Reports">
    <x-page-header title="Reports" subtitle="Weekly and monthly analysis of trips, routes, fuel, maintenance and driver hours. Every report can be exported to PDF or CSV." />

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $report)
            <article class="panel flex flex-col p-5">
                <h2 class="font-display text-xl font-semibold tracking-wide">{{ $report['title'] }}</h2>
                <p class="mt-1 flex-1 text-sm text-muted">{{ $report['description'] }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('reports.show', ['report' => $report['key'], 'period' => 'weekly']) }}" class="btn btn-secondary btn-sm">This week</a>
                    <a href="{{ route('reports.show', ['report' => $report['key'], 'period' => 'monthly']) }}" class="btn btn-primary btn-sm">This month</a>
                </div>
            </article>
        @endforeach
    </div>
</x-layouts.app>

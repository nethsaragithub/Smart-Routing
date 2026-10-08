@php use App\Services\Reports\Report; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report::title() }} – {{ $report->period()->label() }}</title>
    <style>
        @page { margin: 26mm 16mm 18mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1c2730; }
        header { position: fixed; top: -18mm; left: 0; right: 0; border-bottom: 2px solid #b3261e; padding-bottom: 6px; }
        header .brand { font-size: 13pt; font-weight: bold; }
        header .brand span { background: #22272b; color: #ffb81c; padding: 1px 5px; border-radius: 3px; margin-right: 6px; }
        header .meta { float: right; text-align: right; color: #5f6f77; font-size: 8.5pt; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; color: #5f6f77; font-size: 8pt; border-top: 1px solid #dde3e1; padding-top: 4px; }
        footer .page:after { content: counter(page); }
        h1 { font-size: 18pt; margin: 0 0 2px; }
        h2 { font-size: 11.5pt; margin: 18px 0 6px; }
        .sub { color: #5f6f77; margin: 0 0 14px; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 6px; }
        .summary td { background: #f3f5f4; border: 1px solid #dde3e1; padding: 8px 10px; width: 25%; }
        .summary .label { color: #5f6f77; font-size: 8pt; }
        .summary .value { font-size: 12pt; font-weight: bold; margin-top: 2px; white-space: nowrap; }
        ul.insights { margin: 6px 0 0; padding-left: 16px; }
        ul.insights li { margin-bottom: 4px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { text-align: left; font-size: 8pt; color: #5f6f77; border-bottom: 1.5px solid #c6cfcc; padding: 5px 6px; }
        table.data td { border-bottom: 1px solid #e6ebe9; padding: 5px 6px; }
        table.data tr:nth-child(even) td { background: #fafbfa; }
        .num { text-align: right; }
        .empty { color: #5f6f77; font-style: italic; }
    </style>
</head>
<body>
<header>
    <div class="meta">{{ $report->depot()?->name ?? 'All depots' }}<br>Generated {{ $generatedAt->format('j M Y, H:i') }}</div>
    <div class="brand"><span>SR</span>SRMSS</div>
</header>
<footer>
    Smart Route Management and Scheduling System · {{ $report::title() }} · {{ $report->period()->label() }}
    <span style="float:right">Page <span class="page"></span></span>
</footer>

<h1>{{ $report::title() }}</h1>
<p class="sub">
    {{ $report->period()->label() }}@if ($report->period()->type !== 'custom') ({{ $report->period()->from->format('j M Y') }} – {{ $report->period()->to->format('j M Y') }})@endif
    · {{ $report::description() }}
</p>

<table class="summary">
    <tr>
        @foreach ($report->summary() as $label => $value)
            <td><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></td>
        @endforeach
    </tr>
</table>

@if ($report->insights())
    <h2>Findings</h2>
    <ul class="insights">
        @foreach ($report->insights() as $insight)
            <li>{{ $insight }}</li>
        @endforeach
    </ul>
@endif

@foreach ([['title' => 'Details', 'columns' => $report->columns(), 'rows' => $report->rows()], ...$report->extraTables()] as $table)
    <h2>{{ $table['title'] }}</h2>
    @if (count($table['rows']) === 0)
        <p class="empty">Nothing recorded in this period.</p>
    @else
        <table class="data">
            <thead>
            <tr>@foreach ($table['columns'] as [$label, $format])<th class="{{ $format !== 'text' ? 'num' : '' }}">{{ $label }}</th>@endforeach</tr>
            </thead>
            <tbody>
            @foreach ($table['rows'] as $row)
                <tr>@foreach ($table['columns'] as $key => [$label, $format])<td class="{{ $format !== 'text' ? 'num' : '' }}">{{ Report::format($row[$key] ?? null, $format) }}</td>@endforeach</tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endforeach
</body>
</html>

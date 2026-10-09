@props(['title' => null])
@php
    $nav = [
        'Overview' => [
            ['Dashboard', 'dashboard', 'dashboard', 'dashboard', null],
        ],
        'Operations' => [
            ['Trips', 'trips.index', 'trips.*', 'clock', null],
            ['Timetable', 'timetable', 'timetable', 'calendar', 'view-depot-records'],
            ['Schedules', 'schedules.index', 'schedules.*', 'list', 'view-depot-records'],
        ],
        'Planning' => [
            ['Routes', 'routes.index', 'routes.*', 'route', 'view-depot-records'],
        ],
        'Fleet' => [
            ['Buses', 'buses.index', 'buses.*', 'bus', 'view-depot-records'],
            ['Drivers', 'drivers.index', 'drivers.*', 'user', 'view-depot-records'],
            ['Fuel log', 'fuel.index', 'fuel.*', 'fuel', null],
            ['Maintenance', 'maintenance.index', 'maintenance.*', 'wrench', null],
        ],
        'Insights' => [
            ['Reports', 'reports.index', 'reports.*', 'chart', 'view-reports'],
        ],
        'Administration' => [
            ['Users', 'users.index', 'users.*', 'users', 'manage-users'],
            ['Depots', 'depots.index', 'depots.*', 'depot', 'manage-depots'],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' – ' : '' }}SRMSS</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" x-data="{ nav: false }" @keydown.escape="nav = false">
<x-backdrop />
<x-curtain />
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 btn btn-secondary">Skip to content</a>

{{-- Sidebar --}}
<div x-show="nav" x-cloak class="fixed inset-0 z-30 bg-ink/50 dark:bg-black/65 lg:hidden" @click="nav = false"></div>
<aside class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-depot text-depot-text transition-transform lg:translate-x-0"
       :class="nav ? 'translate-x-0' : '-translate-x-full'" aria-label="Main navigation">
    <div class="flex items-center gap-3 px-5 h-16 border-b border-depot-line">
        <span class="route-board h-8 px-2 text-lg">SR</span>
        <div class="leading-tight">
            <div class="font-display text-xl font-semibold text-white tracking-wide">SRMSS</div>
            <div class="text-[12px] text-depot-text/80">Route &amp; schedule management</div>
        </div>
        <button class="ml-auto lg:hidden text-depot-text hover:text-white" @click="nav = false" aria-label="Close menu"><x-icon name="x" /></button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5">
        @foreach ($nav as $group => $items)
            @php $visible = collect($items)->filter(fn ($i) => ! $i[4] || auth()->user()->can($i[4])); @endphp
            @if ($visible->isNotEmpty())
                <div>
                    <div class="px-3 mb-1.5 text-[12px] font-medium text-depot-text/60">{{ $group }}</div>
                    <ul class="space-y-0.5">
                        @foreach ($visible as [$label, $routeName, $pattern, $icon])
                            <li>
                                <a href="{{ route($routeName) }}" class="nav-link" @if (request()->routeIs($pattern)) aria-current="page" @endif>
                                    <x-icon :name="$icon" />
                                    {{ $label }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-depot-line p-3">
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-md px-2 py-2 hover:bg-depot-raised">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-depot-raised text-white text-sm font-semibold">{{ auth()->user()->initials() }}</span>
            <span class="min-w-0 leading-tight">
                <span class="block truncate text-sm text-white">{{ auth()->user()->name }}</span>
                <span class="block truncate text-[12px] text-depot-text/75">{{ auth()->user()->role->label() }}</span>
            </span>
        </a>
        <form method="POST" action="{{ route('logout') }}" class="mt-1">
            @csrf
            <button class="nav-link w-full"><x-icon name="logout" /> Sign out</button>
        </form>
    </div>
</aside>

{{-- Main column --}}
<div class="lg:pl-64">
    <header class="app-header sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-panel/80 px-4 backdrop-blur-md sm:px-6" data-app-header>
        <span class="scroll-progress" data-scroll-progress aria-hidden="true"></span>
        <button class="btn-ghost btn btn-sm lg:hidden" @click="nav = true" aria-label="Open menu"><x-icon name="menu" size="20" /></button>

        <div class="flex min-w-0 items-center gap-2">
            <x-icon name="depot" class="text-muted hidden sm:block" />
            @if ($switchableDepots->count() > 1)
                <form method="POST" action="{{ route('depot.switch') }}">
                    @csrf
                    <label for="depot-switch" class="sr-only">Working depot</label>
                    <select id="depot-switch" name="depot_id" onchange="this.form.submit()"
                            class="rounded-md border-line-strong bg-panel py-1.5 pl-2.5 pr-8 text-sm font-medium text-ink focus:ring-signal/25">
                        @foreach ($switchableDepots as $depot)
                            <option value="{{ $depot->id }}" @selected($currentDepot?->id === $depot->id)>{{ $depot->name }}</option>
                        @endforeach
                    </select>
                </form>
            @else
                <span class="truncate text-sm font-medium">{{ $currentDepot?->name ?? 'No depot assigned' }}</span>
            @endif
        </div>

        <div class="ml-auto flex items-center gap-2 sm:gap-3">
            <div class="text-sm text-muted text-right leading-tight">
                <div class="font-medium text-ink whitespace-nowrap"><span class="sm:hidden">{{ now()->format('D j M') }}</span><span class="hidden sm:inline">{{ now()->format('l, j F Y') }}</span></div>
                <div class="hidden sm:block tabular-nums" data-live-clock data-now="{{ now()->getTimestampMs() }}" data-offset="{{ now()->utcOffset() }}">{{ now()->format('H:i') }}</div>
            </div>
            <x-display-toggles class="border-l border-line pl-1.5 sm:pl-2" />
        </div>
    </header>

    <main id="main" class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:py-8">
        <x-flash />
        {{ $slot }}
    </main>
</div>
</body>
</html>

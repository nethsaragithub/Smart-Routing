<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in – SRMSS</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper">
<x-backdrop />
<x-curtain />
<div class="grid min-h-screen lg:grid-cols-[1.1fr_1fr]" data-login>

    {{-- Departure board panel --}}
    <section class="relative hidden flex-col justify-between overflow-hidden bg-depot p-10 text-depot-text lg:flex" aria-hidden="true">
        {{-- Amber glow behind the board, like sodium lamps over a depot yard --}}
        <div class="pointer-events-none absolute -left-32 top-1/3 h-[34rem] w-[34rem] rounded-full bg-board-amber/10 blur-3xl"></div>

        <div class="relative flex items-center gap-3" data-login-intro>
            <span class="route-board h-9 px-2.5 text-xl">SR</span>
            <span class="font-display text-2xl font-semibold tracking-wide text-white">SRMSS</span>
        </div>

        <div class="relative">
            <div class="rounded-xl bg-board p-6 shadow-2xl ring-1 ring-white/5" data-login-board>
                <div class="mb-4 flex items-baseline justify-between font-display text-board-amber/70 text-sm tracking-[0.2em]">
                    <span>DEPARTURES</span>
                    <span class="tabular-nums" data-live-clock data-now="{{ now()->getTimestampMs() }}" data-offset="{{ now()->utcOffset() }}">{{ now()->format('H:i') }}</span>
                </div>
                @foreach ([['138', 'Pettah – Kottawa', '06:30', 'On time'], ['01', 'Colombo Fort – Kandy', '06:45', 'On time'], ['02', 'Colombo Fort – Matara', '07:10', 'Boarding'], ['122', 'Colombo Fort – Ratnapura', '07:25', 'Delayed 10']] as [$no, $to, $time, $state])
                    <div class="flex items-center gap-4 border-t border-white/5 py-3 font-display text-[1.45rem] tracking-wide text-board-amber" style="text-shadow: 0 0 8px rgb(255 184 28 / .35)" data-board-row>
                        <span class="w-14 tabular-nums" data-flap>{{ $no }}</span>
                        <span class="flex-1 truncate" data-flap>{{ $to }}</span>
                        <span class="tabular-nums" data-flap>{{ $time }}</span>
                        <span class="w-28 text-right text-base {{ str_starts_with($state, 'Delayed') ? 'text-orange-300' : 'text-board-amber/70' }}" data-flap @if ($state === 'Boarding') data-blink @endif>{{ $state }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Route 138, depot gate to the last stop --}}
            <svg class="mt-8 w-full" viewBox="0 0 560 104" data-login-route focusable="false">
                <path d="M20 64 H170 L206 28 H354 L390 64 H540" fill="none" stroke="#ffb81c" stroke-opacity=".55" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" data-route-path />
                {{-- [x, y, fraction along the path, name, label anchor, label above?] --}}
                @foreach ([[20, 64, 0, 'Depot', 'start', false], [110, 64, 0.1637, 'Nugegoda', 'middle', false], [280, 28, 0.5, 'Maharagama', 'middle', true], [460, 64, 0.8545, 'Pannipitiya', 'middle', true], [540, 64, 1, 'Kottawa', 'end', false]] as [$x, $y, $at, $name, $anchor, $above])
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $loop->first || $loop->last ? 8 : 6 }}" fill="#22272b" stroke="#ffb81c" stroke-width="3" data-route-stop="{{ $at }}" />
                    <text x="{{ $x }}" y="{{ $above ? $y - 16 : $y + 30 }}" text-anchor="{{ $anchor }}" fill="currentColor" opacity=".7" font-size="14" font-family="Barlow Condensed, sans-serif" letter-spacing=".04em" data-route-label>{{ $name }}</text>
                @endforeach
                <g data-route-bus style="visibility: hidden">
                    <circle r="18" fill="#ffb81c" opacity=".18" />
                    <rect x="-13" y="-6" width="26" height="12" rx="4" fill="#b3261e" />
                    <rect x="5" y="-4" width="5" height="8" rx="1.2" fill="#ffd77a" />
                </g>
            </svg>
        </div>

        <p class="relative max-w-md text-[15px] leading-relaxed" data-login-intro>
            Plan routes, build timetables without clashes and follow every trip from the depot gate to the last stop.
        </p>
    </section>

    {{-- Sign-in form --}}
    <main class="relative flex items-center justify-center px-5 py-12">
        {{-- Soft wash so the moving backdrop stays behind, not through, the form --}}
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,var(--color-paper)_35%,transparent_75%)]" aria-hidden="true"></div>
        <x-display-toggles class="absolute right-4 top-4" />

        <div class="relative w-full max-w-sm" data-login-form>
            <div class="mb-8 lg:hidden flex items-center gap-3">
                <span class="route-board h-9 px-2.5 text-xl">SR</span>
                <span class="font-display text-2xl font-semibold tracking-wide">SRMSS</span>
            </div>

            <h1 data-page-title class="font-display text-[2.2rem] font-semibold leading-none tracking-wide">Sign in</h1>
            <p class="mt-2 text-muted">Smart Route Management and Scheduling System for depot staff.</p>

            @if (session('status'))
                <div class="mt-6 rounded-lg border border-emerald-200 dark:border-emerald-400/30 bg-emerald-50 dark:bg-emerald-400/10 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-200" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf
                <x-form.input name="email" type="email" label="Email address" autocomplete="username" autofocus required />
                <x-form.input name="password" type="password" label="Password" autocomplete="current-password" required />
                <x-form.checkbox name="remember" label="Keep me signed in on this computer" />
                <button type="submit" class="btn btn-primary w-full py-2.5 text-[15px]">Sign in</button>
            </form>

            <p class="mt-8 text-[13px] text-muted">Forgotten your password? Ask your depot administrator to reset it.</p>
        </div>
    </main>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in – SRMSS</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper">
<div class="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">

    {{-- Departure board panel --}}
    <section class="relative hidden flex-col justify-between overflow-hidden bg-depot p-10 text-depot-text lg:flex" aria-hidden="true">
        <div class="flex items-center gap-3">
            <span class="route-board h-9 px-2.5 text-xl">SR</span>
            <span class="font-display text-2xl font-semibold tracking-wide text-white">SRMSS</span>
        </div>

        <div class="rounded-xl bg-board p-6 shadow-2xl ring-1 ring-white/5">
            <div class="mb-4 flex items-baseline justify-between font-display text-board-amber/70 text-sm tracking-[0.2em]">
                <span>DEPARTURES</span><span>{{ now()->format('H:i') }}</span>
            </div>
            @foreach ([['138', 'Pettah', 'Kottawa', '06:30', 'On time'], ['01', 'Colombo Fort', 'Kandy', '06:45', 'On time'], ['02', 'Colombo Fort', 'Matara', '07:10', 'Boarding'], ['122', 'Colombo Fort', 'Ratnapura', '07:25', 'Delayed 10']] as [$no, $from, $to, $time, $state])
                <div class="flex items-center gap-4 border-t border-white/5 py-3 font-display text-[1.45rem] tracking-wide text-board-amber" style="text-shadow: 0 0 8px rgb(255 184 28 / .35)">
                    <span class="w-14 tabular-nums">{{ $no }}</span>
                    <span class="flex-1 truncate">{{ $from }} – {{ $to }}</span>
                    <span class="tabular-nums">{{ $time }}</span>
                    <span class="w-28 text-right text-base {{ str_starts_with($state, 'Delayed') ? 'text-orange-300' : 'text-board-amber/70' }}">{{ $state }}</span>
                </div>
            @endforeach
        </div>

        <p class="max-w-md text-[15px] leading-relaxed">
            Plan routes, build timetables without clashes and follow every trip from the depot gate to the last stop.
        </p>
    </section>

    {{-- Sign-in form --}}
    <main class="flex items-center justify-center px-5 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 lg:hidden flex items-center gap-3">
                <span class="route-board h-9 px-2.5 text-xl">SR</span>
                <span class="font-display text-2xl font-semibold tracking-wide">SRMSS</span>
            </div>

            <h1 class="font-display text-[2.2rem] font-semibold leading-none tracking-wide">Sign in</h1>
            <p class="mt-2 text-muted">Smart Route Management and Scheduling System for depot staff.</p>

            @if (session('status'))
                <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('status') }}</div>
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

{{-- Animated transit-map backdrop. Decorative only: fixed behind the page, ignores the pointer.
     Layers carry a parallax depth (data-depth); buses run along the line paths (data-bus). --}}
@php
    $lines = [
        ['bd-l1', '#ffb81c', 'M-60 170 H360 L540 350 H880 L1060 170 H1500'],
        ['bd-l2', '#b3261e', 'M210 -60 V300 L430 520 H760 L940 700 V960'],
        ['bd-l3', 'currentColor', 'M-60 640 H260 L440 460 H1100 L1300 660 H1500'],
        ['bd-l4', '#2f8f83', 'M1250 -60 V240 L1010 480 V960'],
        ['bd-l5', '#2a78d6', 'M-60 820 H620 L780 660 H1180 L1340 820 H1500'],
        ['bd-l6', '#d18a00', 'M640 -60 V120 L820 300 V580 H1500'],
    ];
    $stations = [[210, 170], [405, 495], [900, 660], [1250, 170], [1030, 460], [1010, 660], [820, 350], [820, 460], [820, 580], [1010, 580], [1220, 580]];
    // [line, colour, seconds per run, start offset 0-1]
    $buses = [
        ['bd-l1', '#ffb81c', 34, 0], ['bd-l1', '#ffb81c', 34, 0.55],
        ['bd-l2', '#b3261e', 26, 0.2],
        ['bd-l3', '#ffb81c', 40, 0.1], ['bd-l3', '#ffb81c', 40, 0.62],
        ['bd-l4', '#2f8f83', 22, 0.4],
        ['bd-l5', '#2a78d6', 36, 0.3],
        ['bd-l6', '#d18a00', 28, 0.75],
    ];
@endphp
<div class="backdrop text-ink" data-backdrop aria-hidden="true">
    <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" focusable="false">
        <defs>
            <pattern id="bd-grid" width="32" height="32" patternUnits="userSpaceOnUse">
                <circle cx="2" cy="2" r="1.3" class="bd-dots" />
            </pattern>
            <radialGradient id="bd-glow-amber"><stop offset="0" stop-color="#ffb81c" stop-opacity=".5" /><stop offset="1" stop-color="#ffb81c" stop-opacity="0" /></radialGradient>
            <radialGradient id="bd-glow-signal"><stop offset="0" stop-color="#b3261e" stop-opacity=".32" /><stop offset="1" stop-color="#b3261e" stop-opacity="0" /></radialGradient>
            <radialGradient id="bd-halo"><stop offset="0" stop-color="#ffb81c" stop-opacity=".55" /><stop offset="1" stop-color="#ffb81c" stop-opacity="0" /></radialGradient>
        </defs>

        <g data-depth="0.02">
            <circle class="bd-glow" data-drift cx="260" cy="160" r="440" fill="url(#bd-glow-amber)" />
            <circle class="bd-glow" data-drift cx="1220" cy="780" r="500" fill="url(#bd-glow-signal)" />
        </g>

        <g data-depth="0.05">
            <rect x="-200" y="-300" width="1840" height="1600" fill="url(#bd-grid)" />
        </g>

        <g data-depth="0.12">
            @foreach ($lines as [$id, $colour, $d])
                <path id="{{ $id }}" class="bd-line" d="{{ $d }}" stroke="{{ $colour }}" />
            @endforeach
            @foreach ($stations as [$x, $y])
                <circle class="bd-station" cx="{{ $x }}" cy="{{ $y }}" r="7" />
            @endforeach
            @foreach ($buses as [$line, $colour, $seconds, $offset])
                <g class="bd-bus" data-bus="#{{ $line }}" data-duration="{{ $seconds }}" data-offset="{{ $offset }}" style="visibility: hidden">
                    <circle r="22" fill="url(#bd-halo)" />
                    <rect x="-12" y="-5.5" width="24" height="11" rx="4" fill="{{ $colour }}" />
                    <rect x="5" y="-3.5" width="4" height="7" rx="1.2" fill="#fff" opacity=".8" />
                </g>
            @endforeach
        </g>
    </svg>
</div>

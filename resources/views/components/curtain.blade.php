{{-- Page transition: the page blurs while a bus drives across (resources/js/motion/transitions.js).
     Starts blurred with the bus parked mid-screen; hides itself after 1.6s by CSS if the script never runs. --}}
<div class="curtain" data-curtain aria-hidden="true">
    <div class="curtain-blur" data-curtain-blur></div>
    <div class="curtain-road" data-curtain-road></div>

    <div class="curtain-bus" data-curtain-bus>
        <svg viewBox="0 0 260 110" focusable="false">
            <defs>
                <linearGradient id="cb-beam" x1="0" x2="1">
                    <stop offset="0" stop-color="#ffd77a" stop-opacity=".55" />
                    <stop offset="1" stop-color="#ffd77a" stop-opacity="0" />
                </linearGradient>
                <linearGradient id="cb-glass" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#3a4a54" />
                    <stop offset="1" stop-color="#1c252b" />
                </linearGradient>
            </defs>

            {{-- Speed lines trailing behind --}}
            <g data-curtain-trail stroke="currentColor" stroke-linecap="round" stroke-width="3" opacity=".35">
                <line x1="0" y1="40" x2="22" y2="40" />
                <line x1="8" y1="56" x2="26" y2="56" />
                <line x1="2" y1="72" x2="20" y2="72" />
            </g>

            {{-- Headlight beam ahead --}}
            <path d="M232 70 L260 58 V90 Z" fill="url(#cb-beam)" data-curtain-beam />

            {{-- Shadow on the road --}}
            <ellipse cx="132" cy="100" rx="100" ry="5" fill="#000" opacity=".18" />

            {{-- Body (leans on braking and pulling away) --}}
            <g data-curtain-body>
                <rect x="34" y="14" width="200" height="74" rx="12" fill="#b3261e" />
                <rect x="34" y="70" width="200" height="18" rx="6" fill="#8e1d17" />
                <rect x="34" y="62" width="200" height="6" fill="#ffb81c" />

                {{-- Destination board --}}
                <rect x="150" y="19" width="72" height="14" rx="3" fill="#22272b" />
                <text x="186" y="30.5" text-anchor="middle" fill="#ffb81c" font-size="11" font-weight="700" font-family="Barlow Condensed, sans-serif" letter-spacing=".08em">SRMSS</text>

                {{-- Windows, door and windscreen --}}
                <g fill="url(#cb-glass)">
                    <rect x="44" y="37" width="26" height="21" rx="3" />
                    <rect x="76" y="37" width="26" height="21" rx="3" />
                    <rect x="108" y="37" width="26" height="21" rx="3" />
                    <rect x="140" y="37" width="20" height="44" rx="3" />
                    <path d="M166 37 H218 Q230 37 231 49 V58 H166 Z" />
                </g>
                <line x1="150" y1="37" x2="150" y2="81" stroke="#8e1d17" stroke-width="1.5" />
                <g fill="#fff" opacity=".18">
                    <path d="M48 39 h8 l-6 17 h-4 z" />
                    <path d="M80 39 h8 l-6 17 h-4 z" />
                    <path d="M112 39 h8 l-6 17 h-4 z" />
                    <path d="M172 39 h10 l-7 17 h-5 z" />
                </g>

                {{-- Lights --}}
                <rect x="226" y="66" width="8" height="7" rx="2" fill="#ffd77a" />
                <rect x="34" y="66" width="5" height="7" rx="1.5" fill="#ff6b5e" />
            </g>

            {{-- Wheels --}}
            @foreach ([72, 196] as $cx)
                <g data-curtain-wheel>
                    <circle cx="{{ $cx }}" cy="88" r="13" fill="#1c252b" />
                    <circle cx="{{ $cx }}" cy="88" r="6" fill="#c6cfcc" />
                    <rect x="{{ $cx - 1.25 }}" y="77" width="2.5" height="22" rx="1" fill="#1c252b" opacity=".6" />
                </g>
            @endforeach
        </svg>
    </div>
</div>

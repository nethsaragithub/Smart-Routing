{{-- Animations on/off and day/night mode (resources/js/components/display-prefs.js). --}}
<div x-data="displayPrefs" {{ $attributes->merge(['class' => 'flex items-center gap-0.5']) }}>
    <button type="button" class="btn btn-ghost btn-sm h-9 w-9 px-0" @click="toggleMotion()"
            aria-label="Animations" :aria-pressed="motion.toString()" :title="motion ? 'Animations on. Click to turn them off' : 'Animations off. Click to turn them on'">
        <span class="relative" :class="motion ? 'text-signal dark:text-board-amber' : 'text-muted'">
            <x-icon name="sparkles" />
            {{-- Slash through the icon while animations are off --}}
            <span x-show="!motion" x-cloak class="absolute left-1/2 top-1/2 h-0.5 w-6 -translate-x-1/2 -translate-y-1/2 -rotate-45 rounded-full bg-current ring-2 ring-panel"></span>
        </span>
    </button>
    <button type="button" class="btn btn-ghost btn-sm h-9 w-9 px-0" @click="toggleTheme($event)"
            aria-label="Switch between day and night mode" :title="dark ? 'Switch to day mode' : 'Switch to night mode'">
        <span x-show="!dark"><x-icon name="moon" /></span>
        <span x-show="dark" x-cloak class="text-board-amber"><x-icon name="sun" /></span>
    </button>
</div>

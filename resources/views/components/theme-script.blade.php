{{-- Runs before first paint so the saved theme and motion preference never flash. --}}
<script>
    (function () {
        var root = document.documentElement;
        try {
            var theme = localStorage.getItem('srmss:theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) root.classList.add('dark');
            if (localStorage.getItem('srmss:motion') === 'off') root.classList.add('motion-off');
        } catch (e) {}
    })();
</script>

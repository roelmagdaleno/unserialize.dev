<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">

        @head

        <!-- Fonts -->
        <link href="https://fonts.bunny.net/css?family=geist-mono:400,500|inter:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css'])

        @if(Route::is('home', 'outputs'))
            @vite(['resources/css/code.css', 'resources/js/clipboard.js', 'resources/js/highlight.js'])
        @elseif(Route::is('developers'))
            @vite(['resources/css/code.css', 'resources/js/highlight.js'])
        @endif

        @fluxAppearance
    </head>
    <body class="min-h-screen bg-white font-sans text-zinc-950 antialiased dark:bg-zinc-900 dark:text-white">
    <main class="container mx-auto mt-8 p-8">
        <header class="flex items-start justify-between gap-6">
            <div>
                <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl dark:text-white">
                    <a href="{{ route('home') }}">Unserialize</a>
                </h1>
                <p class="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-300">
                    Convert PHP serialized data into clean, readable JSON.
                </p>
            </div>

            <flux:dropdown x-data align="end">
                <flux:button variant="subtle" square aria-label="Preferred color scheme">
                    <flux:icon.sun x-show="$flux.appearance === 'light'" variant="mini" />
                    <flux:icon.moon x-show="$flux.appearance === 'dark'" variant="mini" />
                    <flux:icon.sun x-show="$flux.appearance === 'system' && ! $flux.dark" variant="mini" />
                    <flux:icon.moon x-show="$flux.appearance === 'system' && $flux.dark" variant="mini" />
                </flux:button>

                <flux:menu>
                    <flux:menu.item icon="sun" x-on:click="$flux.appearance = 'light'">Light</flux:menu.item>
                    <flux:menu.item icon="moon" x-on:click="$flux.appearance = 'dark'">Dark</flux:menu.item>
                    <flux:menu.item icon="computer-desktop" x-on:click="$flux.appearance = 'system'">System</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </header>
        {{ $slot }}
        <footer>
            <p class="mt-8 text-sm text-gray-700 dark:text-gray-300">
                Built with ❤️ by <a href="https://github.com/roelmagdaleno" class="text-blue-900 dark:text-blue-300">Roel</a>.
                <a href="{{ route('privacy') }}" class="text-blue-900 dark:text-blue-300">Privacy</a>.
                <a href="{{ route('developers') }}" class="text-blue-900 dark:text-blue-300">API and MCP documentation</a>.
            </p>
        </footer>
    </main>

    @fluxScripts
    </body>
</html>

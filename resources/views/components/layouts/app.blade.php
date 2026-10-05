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
        @elseif(Route::is('developers', 'guides.*'))
            @vite(['resources/css/code.css', 'resources/js/highlight.js'])
        @endif

        @fluxAppearance
    </head>
    <body class="min-h-screen bg-white font-sans text-zinc-950 antialiased dark:bg-zinc-900 dark:text-white">
    <main class="container mx-auto mt-8 p-8">
        <header class="flex items-start justify-between gap-6">
            <div>
                <a href="{{ route('home') }}" class="text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl dark:text-white">Unserialize</a>
                {{-- Only the home page's tagline is its H1; every other page puts its own title in the H1. --}}
                @if(Route::is('home'))
                    <h1 class="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-300">
                        Unserialize PHP data online and convert it to clean, readable JSON.
                    </h1>
                @else
                    <p class="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-300">
                        Unserialize PHP data online and convert it to clean, readable JSON.
                    </p>
                @endif
            </div>

            <div class="flex items-center gap-1">
                <flux:button href="https://github.com/roelmagdaleno/unserialize.dev" target="_blank" rel="noopener" variant="subtle" square aria-label="Source on GitHub">
                    <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12" />
                    </svg>
                </flux:button>

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
            </div>
        </header>
        {{ $slot }}
        <footer>
            <p class="mt-8 text-sm text-gray-700 dark:text-gray-300">
                Built with ❤️ by <a href="https://github.com/roelmagdaleno" class="text-blue-900 dark:text-blue-300">Roel</a>.
                <a href="{{ route('guides.wordpress-serialized-data') }}" class="text-blue-900 dark:text-blue-300">WordPress serialized data</a>.
                <a href="{{ route('guides.broken-serialized-string') }}" class="text-blue-900 dark:text-blue-300">Fix a broken serialized string</a>.
                <a href="{{ route('privacy') }}" class="text-blue-900 dark:text-blue-300">Privacy</a>.
                <a href="{{ route('developers') }}" class="text-blue-900 dark:text-blue-300">API and MCP documentation</a>.
            </p>
        </footer>
    </main>

    @fluxScripts
    </body>
</html>

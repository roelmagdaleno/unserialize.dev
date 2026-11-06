<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">

        @head

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/theme.js'])

        @if(Route::is('home'))
            @vite(['resources/js/converter.js'])
        @endif

        @if(Route::is('home', 'outputs'))
            <link rel="preload" href="{{ Vite::asset('resources/fonts/geist-mono-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
            @vite(['resources/css/code.css', 'resources/js/clipboard.js', 'resources/js/highlight.js'])
        @elseif(Route::is('developers', 'guides.*'))
            @vite(['resources/css/code.css', 'resources/js/highlight.js'])
        @endif

        {{-- Runs before the first paint so a dark visitor never sees a light flash. See resources/js/theme.js for the storage key. --}}
        <script>
            (() => {
                const appearance = localStorage.getItem('flux.appearance');
                const isDark = appearance === 'dark' || (appearance !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);

                document.documentElement.classList.toggle('dark', isDark);
            })();
        </script>
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
                <a href="https://github.com/roelmagdaleno/unserialize.dev" target="_blank" rel="noopener" aria-label="Source on GitHub" class="relative inline-flex size-10 items-center justify-center rounded-lg bg-transparent text-sm font-medium text-zinc-500 hover:bg-zinc-800/5 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/15 dark:hover:text-white">
                    <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12" />
                    </svg>
                </a>

                <button type="button" class="relative inline-flex size-10 items-center justify-center rounded-lg bg-transparent text-sm font-medium text-zinc-500 hover:bg-zinc-800/5 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/15 dark:hover:text-white [anchor-name:--theme-menu]" aria-label="Preferred color scheme" aria-haspopup="menu" popovertarget="theme-menu">
                    <svg class="size-5 shrink-0 dark:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 2ZM10 15a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 15ZM10 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM15.657 5.404a.75.75 0 1 0-1.06-1.06l-1.061 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM6.464 14.596a.75.75 0 1 0-1.06-1.06l-1.06 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM18 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 18 10ZM5 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 5 10ZM14.596 15.657a.75.75 0 0 0 1.06-1.06l-1.06-1.061a.75.75 0 1 0-1.06 1.06l1.06 1.06ZM5.404 6.464a.75.75 0 0 0 1.06-1.06l-1.06-1.06a.75.75 0 1 0-1.061 1.06l1.06 1.06Z"/></svg>
                    <svg class="hidden size-5 shrink-0 dark:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.455 2.004a.75.75 0 0 1 .26.77 7 7 0 0 0 9.958 7.967.75.75 0 0 1 1.067.853A8.5 8.5 0 1 1 6.647 1.921a.75.75 0 0 1 .808.083Z"/></svg>
                </button>

                <div id="theme-menu" popover role="menu" aria-label="Preferred color scheme" data-theme-menu class="m-0 mt-1 min-w-48 rounded-lg border border-zinc-200 bg-white p-[.3125rem] shadow-xs [inset:auto] [position-anchor:--theme-menu] [right:anchor(right)] [top:anchor(bottom)] dark:border-zinc-600 dark:bg-zinc-700">
                        <button type="button" role="menuitem" class="flex w-full items-center rounded-md px-2 py-1.5 text-start text-sm font-medium text-zinc-800 select-none hover:bg-zinc-50 focus:bg-zinc-50 focus:outline-hidden dark:text-white dark:hover:bg-zinc-600 dark:focus:bg-zinc-600" data-theme-choice="light">
                            <svg class="me-2 size-5 shrink-0 text-zinc-400 dark:text-white/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 2ZM10 15a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 15ZM10 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM15.657 5.404a.75.75 0 1 0-1.06-1.06l-1.061 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM6.464 14.596a.75.75 0 1 0-1.06-1.06l-1.06 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM18 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 18 10ZM5 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 5 10ZM14.596 15.657a.75.75 0 0 0 1.06-1.06l-1.06-1.061a.75.75 0 1 0-1.06 1.06l1.06 1.06ZM5.404 6.464a.75.75 0 0 0 1.06-1.06l-1.06-1.06a.75.75 0 1 0-1.061 1.06l1.06 1.06Z"/></svg>
                            Light
                        </button>
                        <button type="button" role="menuitem" class="flex w-full items-center rounded-md px-2 py-1.5 text-start text-sm font-medium text-zinc-800 select-none hover:bg-zinc-50 focus:bg-zinc-50 focus:outline-hidden dark:text-white dark:hover:bg-zinc-600 dark:focus:bg-zinc-600" data-theme-choice="dark">
                            <svg class="me-2 size-5 shrink-0 text-zinc-400 dark:text-white/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.455 2.004a.75.75 0 0 1 .26.77 7 7 0 0 0 9.958 7.967.75.75 0 0 1 1.067.853A8.5 8.5 0 1 1 6.647 1.921a.75.75 0 0 1 .808.083Z"/></svg>
                            Dark
                        </button>
                        <button type="button" role="menuitem" class="flex w-full items-center rounded-md px-2 py-1.5 text-start text-sm font-medium text-zinc-800 select-none hover:bg-zinc-50 focus:bg-zinc-50 focus:outline-hidden dark:text-white dark:hover:bg-zinc-600 dark:focus:bg-zinc-600" data-theme-choice="system">
                            <svg class="me-2 size-5 shrink-0 text-zinc-400 dark:text-white/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v8.5A2.25 2.25 0 0 1 15.75 15h-3.105a3.501 3.501 0 0 0 1.1 1.677A.75.75 0 0 1 13.26 18H6.74a.75.75 0 0 1-.484-1.323A3.501 3.501 0 0 0 7.355 15H4.25A2.25 2.25 0 0 1 2 12.75v-8.5Zm1.5 0a.75.75 0 0 1 .75-.75h11.5a.75.75 0 0 1 .75.75v7.5a.75.75 0 0 1-.75.75H4.25a.75.75 0 0 1-.75-.75v-7.5Z"/></svg>
                            System
                        </button>
                </div>
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
    </body>
</html>

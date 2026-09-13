<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Unserialize - Convert your serialized data into a readable format' }}</title>

        <!-- Meta Tags -->
        <meta name="description" content="Unserialize your PHP serialized data into a readable format like JSON or an Array quickly and easily with the PHP unserialize function.">
        <meta property="og:title" content="Unserialize - Convert your serialized data into a readable format">
        <meta property="og:description" content="Unserialize your PHP serialized data into a readable format like JSON or an Array quickly and easily with the PHP unserialize function.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="https://unserialize.dev">
        <meta property="twitter:title" content="Unserialize - Convert serialized data into a readable format">
        <meta property="twitter:description" content="Unserialize your PHP serialized data into a readable format like JSON or an Array quickly and easily with the PHP unserialize function.">
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="https://unserialize.dev">
        <meta property="twitter:image" content="https://unserialize.dev/images/social.png">
        <meta property="twitter:site" content="@roelmagdaleno">
        <meta property="twitter:creator" content="@roelmagdaleno">

        <link rel="preconnect" href="https://www.googletagmanager.com">
        <link rel="preconnect" href="https://www.google-analytics.com">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css'])

        @if(Route::is('outputs'))
            @vite(['resources/css/code.css', 'resources/js/clipboard.js'])
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
                    Convert your serialized data into a readable format.
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
            </p>
        </footer>
    </main>

    @fluxScripts
    </body>
</html>

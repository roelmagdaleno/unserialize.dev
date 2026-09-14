@php
    $routeName = Route::currentRouteName();
    $metadata = match ($routeName) {
        'home' => [
            'title' => 'PHP Unserialize to JSON Converter | Unserialize',
            'description' => 'Convert PHP serialized data to readable JSON without storing your input. Includes tested mappings, limits, and object-safety guidance.',
            'canonical' => route('home'),
        ],
        'privacy' => [
            'title' => 'Privacy and Retention | Unserialize',
            'description' => 'Learn how Unserialize processes PHP serialized data, protects submitted values, and handles legacy output links.',
            'canonical' => route('privacy'),
        ],
        'guides.serialization' => [
            'title' => 'PHP Serialization Format Guide | Unserialize',
            'description' => 'A tested reference for PHP serialized syntax and its mapping to JSON values.',
            'canonical' => route('guides.serialization'),
        ],
        'security' => [
            'title' => 'PHP Unserialize Security Guide | Unserialize',
            'description' => 'Safely inspect untrusted PHP serialized data with object rejection, size limits, and private processing.',
            'canonical' => route('security'),
        ],
        'guides.wordpress' => [
            'title' => 'WordPress Serialized Data Guide | Unserialize',
            'description' => 'A practical, cautious workflow for inspecting serialized WordPress options and metadata.',
            'canonical' => route('guides.wordpress'),
        ],
        'developers' => [
            'title' => 'API and MCP Developer Guide | Unserialize',
            'description' => 'Integrate the stateless PHP serialized-data converter through its versioned JSON API or read-only MCP tool.',
            'canonical' => route('developers'),
        ],
        'outputs' => [
            'title' => 'Legacy conversion output | Unserialize',
            'description' => 'A private legacy conversion output.',
            'canonical' => null,
        ],
        default => [
            'title' => 'Unserialize',
            'description' => 'Convert PHP serialized data to readable JSON.',
            'canonical' => null,
        ],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $metadata['title'] }}</title>

        @if(Route::is('outputs'))
            <meta name="robots" content="noindex, nofollow, noarchive">
        @endif

        <meta name="description" content="{{ $metadata['description'] }}">
        <meta property="og:title" content="{{ $metadata['title'] }}">
        <meta property="og:description" content="{{ $metadata['description'] }}">
        <meta property="og:type" content="website">
        @if ($metadata['canonical'] !== null)
            <link rel="canonical" href="{{ $metadata['canonical'] }}">
            <meta property="og:url" content="{{ $metadata['canonical'] }}">
            <meta property="twitter:url" content="{{ $metadata['canonical'] }}">
        @endif
        <meta property="twitter:title" content="{{ $metadata['title'] }}">
        <meta property="twitter:description" content="{{ $metadata['description'] }}">
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:image" content="{{ asset('images/social.png') }}">
        <meta property="twitter:site" content="@roelmagdaleno">
        <meta property="twitter:creator" content="@roelmagdaleno">

        @if ($routeName === 'home')
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebApplication',
                'name' => 'Unserialize',
                'url' => route('home'),
                'applicationCategory' => 'DeveloperApplication',
                'operatingSystem' => 'Any operating system with a modern web browser',
                'description' => $metadata['description'],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => 0,
                    'priceCurrency' => 'USD',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif

        <link rel="preconnect" href="https://www.googletagmanager.com">
        <link rel="preconnect" href="https://www.google-analytics.com">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css'])

        @if(Route::is('home', 'outputs'))
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
        <nav aria-label="Primary" class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium">
            <a href="{{ route('home') }}" class="text-blue-900 hover:underline dark:text-blue-300">Converter</a>
            <a href="{{ route('guides.serialization') }}" class="text-blue-900 hover:underline dark:text-blue-300">Format guide</a>
            <a href="{{ route('guides.wordpress') }}" class="text-blue-900 hover:underline dark:text-blue-300">WordPress</a>
            <a href="{{ route('security') }}" class="text-blue-900 hover:underline dark:text-blue-300">Security</a>
            <a href="{{ route('privacy') }}" class="text-blue-900 hover:underline dark:text-blue-300">Privacy</a>
            <a href="{{ route('developers') }}" class="text-blue-900 hover:underline dark:text-blue-300">Developers</a>
        </nav>
        {{ $slot }}
        <footer>
            <p class="mt-8 text-sm text-gray-700 dark:text-gray-300">
                Built with ❤️ by <a href="https://github.com/roelmagdaleno" class="text-blue-900 dark:text-blue-300">Roel</a>.
                Source on <a href="https://github.com/roelmagdaleno/unserialize" class="text-blue-900 dark:text-blue-300">GitHub</a>.
                <a href="{{ route('developers') }}" class="text-blue-900 dark:text-blue-300">API and MCP documentation</a>.
            </p>
        </footer>
    </main>

    @fluxScripts
    </body>
</html>

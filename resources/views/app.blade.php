<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Unserialize</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @fluxAppearance
    </head>
    <body class="font-sans antialiased">
        <main class="container mx-auto p-8 mt-8">
            <header>
                <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl dark:text-white">
                    Unserialize
                </h1>
                <p class="mt-6 text-lg leading-8 text-gray-600">
                    Convert PHP serialized data into clean, readable JSON.
                </p>
            </header>
        </main>

        @fluxScripts
    </body>
</html>

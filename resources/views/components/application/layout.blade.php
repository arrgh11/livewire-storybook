@props([
    'head' => null,
    'footer' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @fluxStyles
    @livewireStyles

    {!! $head !!}
</head>

    <body >

        {{ $slot }}

        @stack('modals')

        <script defer src="https://unpkg.com/@alpinejs/ui@3.14.1-beta.0/dist/cdn.min.js"></script>
        <script defer src="https://unpkg.com/@alpinejs/focus@3.14.1/dist/cdn.min.js"></script>

{{--        <script>--}}
{{--            document.addEventListener('alpine:init', () => {--}}
{{--                @php--}}
{{--                    $tools = \Arrgh11\Atlas\Facades\Atlas::getTools();--}}
{{--                @endphp--}}

{{--                @foreach($tools as $tool)--}}
{{--                    {!! $tool['component'] !!}--}}
{{--                @endforeach--}}
{{--            })--}}
{{--        </script>--}}

        {!! $footer !!}

        @fluxScripts
        @livewireScripts
    </body>
</html>

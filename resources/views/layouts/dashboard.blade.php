@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#020617">

    <title>@yield('title', __('dashboard.dashboard')) | Raslo</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body class="m-0 min-h-screen overflow-x-hidden bg-slate-950 text-slate-100 antialiased">

    <div id="dashboard-shell" class="min-h-screen lg:grid" style="--sidebar-width: 256px;">

        @include('components.dashboard.sidebar')

        <div class="flex min-w-0 flex-col">

            @include('components.dashboard.header')

            <main id="dashboard-content" class="w-full min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8">

                @if (session('success'))
                    <div role="status"
                        class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-300">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div role="alert" class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-red-300">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="mx-auto w-full max-w-7xl">
                    @yield('content')
                </div>

            </main>

        </div>
    </div>

    @stack('scripts')
</body>

</html>

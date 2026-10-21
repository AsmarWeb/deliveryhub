@php
    $user = auth()->user();
    $locale = app()->getLocale();

    $role = match (true) {
        $user->hasRole('admin') => __('dashboard.administration'),
        $user->hasRole('merchant') => __('dashboard.merchant_workspace'),
        $user->hasRole('driver') => __('dashboard.driver_workspace'),
        default => __('dashboard.account'),
    };

    $isDashboard = request()->routeIs('web.dashboard');

    $pageTitle = match (true) {
        request()->routeIs('merchant.deliveries.create') => __('deliveries.create_delivery'),

        request()->routeIs('merchant.deliveries.index') => __('dashboard.my_deliveries'),

        request()->routeIs('web.dashboard') => __('dashboard.overview'),

        default => __('dashboard.dashboard'),
    };
@endphp

<header
    class="sticky top-0 z-30 w-full border-b border-slate-800 bg-slate-900/95 px-4 py-3 backdrop-blur sm:px-6 lg:px-8">

    <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">

        {{-- Left / Right heading area --}}
        <div class="flex min-w-0 flex-1 items-center gap-3">

            {{-- Mobile sidebar toggle --}}
            <button type="button" id="sidebar-open" aria-controls="dashboard-sidebar" aria-expanded="false"
                aria-label="{{ $locale === 'ar' ? 'فتح القائمة الجانبية' : 'Open sidebar' }}"
                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-700 bg-slate-800 text-slate-200 transition hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 lg:hidden">

                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="M4 6h16M4 12h16M4 18h16" />
                </svg>

            </button>

            {{-- Page heading --}}
            <div class="min-w-0 flex-1">

                <nav aria-label="{{ $locale === 'ar' ? 'مسار التنقل' : 'Breadcrumb' }}"
                    class="mb-1 flex flex-wrap items-center gap-2 text-xs text-slate-400">

                    <a href="{{ route('web.dashboard') }}" class="font-medium transition hover:text-blue-400">
                        Raslo
                    </a>

                    <span aria-hidden="true">/</span>

                    <span>{{ $role }}</span>

                    @unless ($isDashboard)
                        <span aria-hidden="true">/</span>

                        <span class="font-medium text-slate-200">
                            {{ $pageTitle }}
                        </span>
                    @endunless

                </nav>

                <h1 class="truncate text-lg font-bold tracking-tight text-white sm:text-2xl">
                    @yield('page-title', $pageTitle)
                </h1>

                <p class="mt-1 hidden text-xs text-slate-400 sm:block">
                    {{ __('dashboard.workspace') }}
                </p>

            </div>

        </div>

        {{-- Header actions --}}
        <div class="flex shrink-0 items-center gap-2 sm:gap-3">

            {{-- New delivery --}}
            @if (
                $user->hasRole('merchant') &&
                    Route::has('merchant.deliveries.create') &&
                    !request()->routeIs('merchant.deliveries.create'))
                <a href="{{ route('merchant.deliveries.create') }}"
                    class="hidden items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 xl:inline-flex">

                    <span aria-hidden="true" class="text-lg leading-none">+</span>

                    <span>{{ __('dashboard.new_delivery') }}</span>

                </a>
            @endif

            {{-- Language switcher --}}
            @if (Route::has('language.switch'))
                <nav aria-label="{{ __('dashboard.language') }}" dir="ltr"
                    class="flex items-center gap-1 rounded-xl border border-slate-700 bg-slate-800 p-1">

                    <a href="{{ route('language.switch', ['locale' => 'ar']) }}" lang="ar"
                        @if ($locale === 'ar') aria-current="page" @endif
                        class="inline-flex min-h-9 items-center justify-center rounded-lg px-2.5 py-2 text-xs font-semibold transition sm:px-3 sm:text-sm
                        {{ $locale === 'ar' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">

                        عربي

                    </a>

                    <a href="{{ route('language.switch', ['locale' => 'en']) }}" lang="en"
                        @if ($locale === 'en') aria-current="page" @endif
                        class="inline-flex min-h-9 items-center justify-center rounded-lg px-2.5 py-2 text-xs font-semibold transition sm:px-3 sm:text-sm
                        {{ $locale === 'en' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">

                        EN

                    </a>

                </nav>
            @endif

            {{-- User avatar --}}
            <div class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-700 bg-slate-800 text-sm font-bold text-blue-400 sm:flex"
                title="{{ $user->name }}" aria-label="{{ $user->name }}">

                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}

            </div>

        </div>

    </div>

</header>

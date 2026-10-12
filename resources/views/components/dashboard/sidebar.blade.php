@php
    $user = auth()->user();
    $role = match (true) {
        $user->hasRole('admin') => 'admin',
        $user->hasRole('merchant') => 'merchant',
        $user->hasRole('driver') => 'driver',
        default => null,
    };
    $items = [
        [
            'route' => 'web.dashboard',
            'active' => 'web.dashboard',
            'label' => __('dashboard.dashboard'),
            'icon' => 'home',
        ],
    ];
    if ($role === 'merchant') {
        $items[] = [
            'route' => 'merchant.deliveries.index',
            'active' => 'merchant.deliveries.index',
            'label' => __('dashboard.my_deliveries'),
            'icon' => 'package',
        ];
        $items[] = [
            'route' => 'merchant.deliveries.create',
            'active' => 'merchant.deliveries.create',
            'label' => __('dashboard.create_delivery'),
            'icon' => 'plus',
        ];
        $items[] = [
            'route' => 'merchant.addresses.index',
            'active' => 'merchant.addresses.*',
            'label' => __('dashboard.my_addresses'),
            'icon' => 'map-pin',
        ];
    }
    if ($role === 'driver') {
        $items[] = [
            'route' => 'driver.assignments.index',
            'active' => 'driver.assignments.*',
            'label' => __('dashboard.my_assignments'),
            'icon' => 'truck',
        ];
    }
    $items = array_values(array_filter($items, fn($item) => Route::has($item['route'])));

    $upcoming = match ($role) {
        'admin' => [__('dashboard.deliveries'), __('dashboard.merchants'), __('dashboard.drivers')],
        'merchant' => [],
        'driver' => [__('dashboard.delivery_history')],
        default => [],
    };

@endphp

<div id="sidebar-overlay" class="sidebar-overlay" hidden></div>
<aside id="dashboard-sidebar" class="dh-sidebar" aria-label="{{ __('dashboard.dashboard') }}">
    <div class="dh-sidebar-brand">
        <a href="{{ route('web.dashboard') }}" class="dh-brand-link" title="DeliveryHub">
            <span class="dh-logo">D</span>
            <span class="dh-label dh-brand-name">Delivery<span>Hub</span></span>
        </a>
        <button id="sidebar-collapse" class="dh-icon-button dh-desktop-toggle" type="button"
            aria-controls="dashboard-sidebar" aria-expanded="true"
            aria-label="{{ app()->getLocale() === 'ar' ? 'طي القائمة' : 'Collapse sidebar' }}">☰</button>
        <button id="sidebar-close" class="dh-icon-button dh-mobile-close" type="button"
            aria-label="{{ app()->getLocale() === 'ar' ? 'إغلاق القائمة' : 'Close sidebar' }}">✕</button>
    </div>

    <nav class="dh-sidebar-nav" aria-label="{{ app()->getLocale() === 'ar' ? 'التنقل الرئيسي' : 'Main navigation' }}">
        @foreach ($items as $item)
            @php $active = request()->routeIs($item['active']); @endphp
            <a href="{{ route($item['route']) }}" class="dh-nav-item {{ $active ? 'dh-active' : '' }}"
                title="{{ $item['label'] }}" @if ($active) aria-current="page" @endif>
                <span class="dh-nav-icon" aria-hidden="true">
                    @switch($item['icon'])
                        @case('home')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z" />
                            </svg>
                        @break

                        @case('package')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m12 2 9 5-9 5-9-5 9-5Zm-9 5v10l9 5 9-5V7M12 12v10" />
                            </svg>
                        @break

                        @case('plus')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                            </svg>
                        @break

                        @case('map-pin')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" />
                                <circle cx="12" cy="10" r="2.5" />
                            </svg>
                        @break

                        @case('truck')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12v12H3zM15 9h4l3 4v4h-7z" />
                                <circle cx="7.5" cy="18" r="2" />
                                <circle cx="18.5" cy="18" r="2" />
                            </svg>
                        @break
                    @endswitch
                </span>
                <span class="dh-label dh-nav-label">{{ $item['label'] }}</span>
            </a>
        @endforeach

        @if (count($upcoming))
            <div class="dh-separator"></div>
            <p class="dh-label dh-section-title">
                {{ match ($role) {
                    'admin' => __('dashboard.administration'),
                    'merchant' => __('dashboard.merchant_workspace'),
                    'driver' => __('dashboard.driver_workspace'),
                    default => '',
                } }}
            </p>
            @foreach ($upcoming as $label)
                <div class="dh-nav-item dh-disabled" title="{{ $label }}" aria-disabled="true">
                    <span class="dh-nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </span>
                    <span class="dh-label dh-nav-label">{{ $label }}</span>
                    <span class="dh-label dh-soon">{{ __('dashboard.soon') }}</span>
                </div>
            @endforeach
        @endif
    </nav>

    <div class="dh-sidebar-footer">
        <div class="dh-user" title="{{ $user->name }}">
            <span class="dh-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <div class="dh-label dh-user-info"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dh-nav-item dh-logout" title="{{ __('dashboard.logout') }}">
                <span class="dh-nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9" />
                    </svg>
                </span>
                <span class="dh-label dh-nav-label">{{ __('dashboard.logout') }}</span>
            </button>
        </form>
    </div>
</aside>

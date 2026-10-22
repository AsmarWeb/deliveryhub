@php
    $user = auth()->user();
    $locale = app()->getLocale();
    $isAr = $locale === 'ar';

    $role = match (true) {
        $user->hasRole('admin') => 'admin',
        $user->hasRole('merchant') => 'merchant',
        $user->hasRole('driver') => 'driver',
        default => null,
    };

    $items = [];

    if ($role === 'admin') {
        $items = [
            [
                'route' => 'admin.dashboard',
                'active' => 'admin.dashboard',
                'label' => $isAr ? 'لوحة الإدارة' : 'Admin Overview',
                'icon' => 'home',
            ],
            [
                'route' => 'admin.deliveries.index',
                'active' => 'admin.deliveries.*',
                'label' => $isAr ? 'إدارة الشحنات' : 'Manage Deliveries',
                'icon' => 'package',
            ],
            [
                'route' => 'admin.merchants.index',
                'active' => 'admin.merchants.*',
                'label' => $isAr ? 'إدارة التجار' : 'Manage Merchants',
                'icon' => 'users',
            ],
            [
                'route' => 'admin.drivers.index',
                'active' => 'admin.drivers.*',
                'label' => $isAr ? 'إدارة السائقين' : 'Manage Drivers',
                'icon' => 'truck',
            ],
            [
                'route' => 'admin.merchant-crm.index',
                'active' => 'admin.merchant-crm.*',
                'label' => $isAr ? 'إدارة علاقات التجار CRM' : 'Merchant CRM',
                'icon' => 'crm',
            ],
            [
                'route' => 'admin.pricing.edit',
                'active' => 'admin.pricing.*',
                'label' => $isAr ? 'إعدادات التسعير' : 'Pricing Settings',
                'icon' => 'pricing',
            ],
            [
                'route' => 'admin.subscriptions.index',
                'active' => 'admin.subscriptions.*',
                'label' => $isAr ? 'اشتراكات التجار' : 'Subscriptions',
                'icon' => 'subscription',
            ],
        ];
    }

    if ($role === 'merchant') {
        $items = [
            [
                'route' => 'web.dashboard',
                'active' => 'web.dashboard',
                'label' => __('dashboard.dashboard'),
                'icon' => 'home',
            ],
            [
                'route' => 'merchant.deliveries.index',
                'active' => 'merchant.deliveries.index',
                'label' => __('dashboard.my_deliveries'),
                'icon' => 'package',
            ],
            [
                'route' => 'merchant.deliveries.create',
                'active' => 'merchant.deliveries.create',
                'label' => __('dashboard.create_delivery'),
                'icon' => 'plus',
            ],
            [
                'route' => 'merchant.addresses.index',
                'active' => 'merchant.addresses.*',
                'label' => __('dashboard.my_addresses'),
                'icon' => 'map-pin',
            ],
        ];
    }

    if ($role === 'driver') {
        $items = [
            [
                'route' => 'web.dashboard',
                'active' => 'web.dashboard',
                'label' => __('dashboard.dashboard'),
                'icon' => 'home',
            ],
            [
                'route' => 'driver.assignments.index',
                'active' => 'driver.assignments.*',
                'label' => __('dashboard.my_assignments'),
                'icon' => 'truck',
            ],
        ];
    }

    // Show only links whose routes exist.
    $items = array_values(array_filter($items, fn($item) => \Illuminate\Support\Facades\Route::has($item['route'])));

    $upcoming = match ($role) {
        'admin' => [],
        'merchant' => [],
        'driver' => [__('dashboard.delivery_history')],
        default => [],
    };

    $homeRoute = $role === 'admin' ? 'admin.dashboard' : 'web.dashboard';
@endphp



<div id="sidebar-overlay" class="sidebar-overlay" hidden></div>
<aside id="dashboard-sidebar" class="dh-sidebar" aria-label="{{ __('dashboard.dashboard') }}">
    <div class="dh-sidebar-brand">
        <a href="{{ route($homeRoute) }}" class="dh-brand-link" title="Raslo">
            <span class="dh-logo"><img src="{{ asset('brand/raslo-mark.svg') }}" width="38" height="38" alt=""></span>
            <span class="dh-label dh-brand-name">{{ app()->getLocale() === 'ar' ? 'رسلو' : 'Raslo' }}</span>
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

                        @case('users')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round">

                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />

                                <circle cx="9" cy="7" r="4" />

                                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />

                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                        @break

                        @case('crm')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="3" />
                                <circle cx="9" cy="9" r="2" />
                                <path d="M5.5 16c.5-2 2-3 3.5-3s3 1 3.5 3" />
                                <path d="M15 8h3M15 12h3M15 16h3" />
                            </svg>
                        @break

                        @case('pricing')
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8"
                                 stroke-linecap="round"
                                 stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M12 6v12"/>
                                <path d="M15 9.5c-.6-.7-1.6-1-3-1-1.7 0-3 .8-3 2s1.3 2 3 2 3 .8 3 2-1.3 2-3 2c-1.4 0-2.4-.3-3-1"/>
                            </svg>
                        @break

                        @case('subscription')
                            <svg viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="2"/>
                                <path d="M3 10h18M7 15h4"/>
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

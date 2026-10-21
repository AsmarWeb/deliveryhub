@extends('layouts.dashboard')

@section('title', __('dashboard.dashboard'))
@section('page-title', __('dashboard.overview'))

@section('content')
    @php
        $role = $role ?? 'unknown';
        $stats = $stats ?? [];

        $definitions = match ($role) {
            'admin' => [
                ['total_deliveries', 'total_deliveries', '📦'],
                ['active_deliveries', 'active_deliveries', '🚚'],
                ['completed_deliveries', 'completed_deliveries', '✅'],
                ['pending_pricing', 'pending_pricing', '💰'],
                ['merchants', 'total_merchants', '🏪'],
                ['drivers', 'total_drivers', '🚗'],
                ['available_drivers', 'available_drivers', '🟢'],
                ['unread_notifications', 'unread_notifications', '🔔'],
            ],
            'merchant' => [
                ['total_deliveries', 'total_deliveries', '📦'],
                ['pending_deliveries', 'pending_deliveries', '⏳'],
                ['active_deliveries', 'active_deliveries', '🚚'],
                ['completed_deliveries', 'completed_deliveries', '✅'],
                ['unread_notifications', 'unread_notifications', '🔔'],
            ],
            'driver' => [
                ['pending_assignments', 'pending_assignments', '📋'],
                ['accepted_assignments', 'accepted_assignments', '🚚'],
                ['completed_deliveries', 'completed_deliveries', '✅'],
                ['availability', 'is_available', '🟢'],
                ['unread_notifications', 'unread_notifications', '🔔'],
            ],
            default => [],
        };

        $cards = array_map(function ($definition) use ($stats) {
            [$translationKey, $statKey, $icon] = $definition;
            $value = $stats[$statKey] ?? 0;

            if ($statKey === 'is_available') {
                $value = __('dashboard.' . ($value ? 'available' : 'busy'));
            }

            return [
                'label' => __('dashboard.' . $translationKey),
                'value' => $value,
                'icon' => $icon,
            ];
        }, $definitions);

        $roleTitle = __(
            'dashboard.' .
                match ($role) {
                    'admin' => 'administrator',
                    'merchant' => 'merchant',
                    'driver' => 'driver',
                    default => 'user',
                },
        );

        $recentDeliveries = $stats['recent_deliveries'] ?? [];
    @endphp

    <div class="space-y-8">
        <section class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
            <div class="min-w-0">
                <p class="mb-2 text-sm font-medium text-blue-400">
                    Raslo / {{ $roleTitle }}
                </p>
                <h2 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    {{ __('dashboard.welcome') }}، {{ auth()->user()->name }}
                </h2>
                <p class="mt-2 text-sm text-slate-400">
                    {{ __('dashboard.overview_description') }}
                </p>
            </div>

            @if ($role === 'merchant')
                <div class="flex flex-wrap gap-3">
                    @if (Route::has('merchant.deliveries.index'))
                        <a href="{{ route('merchant.deliveries.index') }}"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-700 bg-slate-900 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800">
                            {{ __('dashboard.my_deliveries') }}
                        </a>
                    @endif
                    @if (Route::has('merchant.deliveries.create'))
                        <a href="{{ route('merchant.deliveries.create') }}"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">
                            <span aria-hidden="true" class="text-lg">+</span>
                            {{ __('dashboard.create_delivery') }}
                        </a>
                    @endif
                </div>
            @endif
        </section>

        <section aria-labelledby="dashboard-stats-heading">
            <div class="mb-5">
                <h3 id="dashboard-stats-heading" class="text-lg font-semibold text-white">
                    {{ __('dashboard.overview') }}
                </h3>
                <p class="mt-1 text-sm text-slate-400">
                    {{ __('dashboard.statistics_description') }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($cards as $card)
                    <article
                        class="relative overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 p-6 transition hover:border-slate-700 hover:bg-slate-800/80">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-400">{{ $card['label'] }}</p>
                                <p class="mt-4 break-words text-3xl font-bold tracking-tight text-white">
                                    {{ is_numeric($card['value']) ? number_format((float) $card['value']) : $card['value'] }}
                                </p>
                            </div>
                            <div aria-hidden="true"
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-800 text-2xl">
                                {{ $card['icon'] }}
                            </div>
                        </div>
                        <div class="mt-5 h-1 w-12 rounded-full bg-blue-500/70"></div>
                    </article>
                @endforeach
            </div>
        </section>

        @if ($role === 'merchant')
            <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 p-6">
                    <div>
                        <h3 class="text-lg font-semibold text-white">{{ __('dashboard.recent_deliveries') }}</h3>
                        <p class="mt-1 text-sm text-slate-400">{{ __('dashboard.recent_deliveries_description') }}</p>
                    </div>
                    @if (Route::has('merchant.deliveries.index'))
                        <a href="{{ route('merchant.deliveries.index') }}"
                            class="text-sm font-medium text-blue-400 hover:text-blue-300">
                            {{ __('dashboard.view_all') }}
                            <span aria-hidden="true">{{ app()->getLocale() === 'ar' ? '←' : '→' }}</span>
                        </a>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-start text-sm">
                        <thead class="bg-slate-800/50 text-xs text-slate-400">
                            <tr>
                                <th scope="col" class="px-6 py-4">{{ __('dashboard.tracking_number') }}</th>
                                <th scope="col" class="px-6 py-4">{{ __('dashboard.status') }}</th>
                                <th scope="col" class="px-6 py-4">{{ __('dashboard.created') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentDeliveries as $delivery)
                                @php
                                    $status =
                                        $delivery->status instanceof \App\Enums\DeliveryStatus
                                            ? $delivery->status->value
                                            : (string) $delivery->status;
                                    $statusClasses = match ($status) {
                                        'completed',
                                        'delivered'
                                            => 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20',
                                        'draft',
                                        'pending_pricing'
                                            => 'bg-amber-500/10 text-amber-400 ring-amber-500/20',
                                        'cancelled',
                                        'delivery_failed',
                                        'pickup_failed'
                                            => 'bg-red-500/10 text-red-400 ring-red-500/20',
                                        default => 'bg-blue-500/10 text-blue-400 ring-blue-500/20',
                                    };
                                    $statusKey = 'dashboard.statuses.' . $status;
                                    $statusLabel = __($statusKey);
                                    if ($statusLabel === $statusKey) {
                                        $statusLabel = ucwords(str_replace('_', ' ', $status));
                                    }
                                @endphp
                                <tr class="border-t border-slate-800 transition hover:bg-slate-800/40">
                                    <td class="px-6 py-4 font-semibold text-white">
                                        <span dir="ltr" class="inline-block">{{ $delivery->tracking_number }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $statusClasses }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-slate-400">
                                        <time dir="ltr" class="inline-block"
                                            datetime="{{ $delivery->created_at?->toIso8601String() }}">
                                            {{ $delivery->created_at?->timezone('Asia/Dubai')->format('d M Y') ?? '—' }}
                                        </time>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-14 text-center">
                                        <div aria-hidden="true"
                                            class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-800 text-3xl">
                                            📦</div>
                                        <h4 class="font-semibold text-white">{{ __('dashboard.no_deliveries') }}</h4>
                                        <p class="mt-2 text-sm text-slate-400">
                                            {{ __('dashboard.empty_deliveries_description') }}</p>
                                        @if (Route::has('merchant.deliveries.create'))
                                            <a href="{{ route('merchant.deliveries.create') }}"
                                                class="mt-5 inline-flex rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500">
                                                {{ __('dashboard.create_first_delivery') }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($role === 'driver')
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">{{ __('dashboard.driver_availability') }}</h3>
                        <p class="mt-2 text-sm text-slate-400">{{ __('dashboard.driver_availability_description') }}</p>
                    </div>
                    @if ($stats['is_available'] ?? false)
                        <span
                            class="rounded-full bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-400 ring-1 ring-emerald-500/20">
                            ● {{ __('dashboard.available') }}
                        </span>
                    @else
                        <span
                            class="rounded-full bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-400 ring-1 ring-amber-500/20">
                            ● {{ __('dashboard.busy') }}
                        </span>
                    @endif
                </div>
            </section>
        @endif

        <footer class="border-t border-slate-800 pt-6 text-center text-xs text-slate-500">
            © {{ date('Y') }} Raslo — {{ __('dashboard.delivery_management') }}
        </footer>
    </div>
@endsection

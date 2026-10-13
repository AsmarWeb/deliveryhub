@extends('layouts.dashboard')

@section('title', app()->getLocale() === 'ar' ? 'لوحة الإدارة' : 'Admin Dashboard')

@section('page-title', app()->getLocale() === 'ar' ? 'لوحة الإدارة' : 'Admin Dashboard')

@section('content')
    @php
        $ar = app()->getLocale() === 'ar';

        $cards = [
            [
                'label' => $ar ? 'إجمالي الشحنات' : 'Total Deliveries',
                'value' => $stats['total_deliveries'] ?? 0,
                'icon' => '📦',
            ],
            [
                'label' => $ar ? 'الشحنات النشطة' : 'Active Deliveries',
                'value' => $stats['active_deliveries'] ?? 0,
                'icon' => '🚚',
            ],
            [
                'label' => $ar ? 'الشحنات المكتملة' : 'Completed Deliveries',
                'value' => $stats['completed_deliveries'] ?? 0,
                'icon' => '✅',
            ],
            ['label' => $ar ? 'التجار' : 'Merchants', 'value' => $stats['total_merchants'] ?? 0, 'icon' => '🏪'],
            ['label' => $ar ? 'السائقون' : 'Drivers', 'value' => $stats['total_drivers'] ?? 0, 'icon' => '🚗'],
            [
                'label' => $ar ? 'السائقون المتاحون' : 'Available Drivers',
                'value' => $stats['available_drivers'] ?? 0,
                'icon' => '🟢',
            ],
            [
                'label' => $ar ? 'بانتظار التسعير' : 'Pending Pricing',
                'value' => $stats['pending_pricing'] ?? 0,
                'icon' => '💰',
            ],
            [
                'label' => $ar ? 'إشعارات غير مقروءة' : 'Unread Notifications',
                'value' => $stats['unread_notifications'] ?? 0,
                'icon' => '🔔',
            ],
        ];

        $total = max(1, (int) ($stats['total_deliveries'] ?? 0));
    @endphp

    <div class="space-y-8">

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-white">
                    {{ $ar ? 'نظرة عامة على العمليات' : 'Operations Overview' }}
                </h2>
                <p class="mt-2 text-sm text-slate-400">
                    {{ $ar ? 'إحصائيات مباشرة من قاعدة البيانات' : 'Live statistics from your database' }}
                </p>
            </div>

            <a href="{{ route('admin.deliveries.index') }}"
                class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500">
                {{ $ar ? 'إدارة الشحنات' : 'Manage Deliveries' }}
            </a>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-slate-400">{{ $card['label'] }}</p>
                        <span class="text-2xl">{{ $card['icon'] }}</span>
                    </div>
                    <p class="mt-4 text-3xl font-bold text-white">
                        {{ number_format($card['value']) }}
                    </p>
                </article>
            @endforeach
        </section>


        <section class="space-y-6">

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-bold text-white">
                        {{ $ar ? 'تحليلات الشحنات' : 'Delivery Analytics' }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-400">
                        {{ $ar ? 'تحليل الأداء حسب الفترة الزمنية' : 'Performance analysis by date range' }}
                    </p>
                </div>

                <form method="GET" action="{{ route('admin.dashboard') }}">
                    <select name="days" onchange="this.form.submit()"
                        aria-label="{{ $ar ? 'الفترة الزمنية' : 'Date range' }}"
                        class="rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white">
                        @foreach ([7, 30, 90] as $days)
                            <option value="{{ $days }}" @selected($analytics['days'] === $days)>
                                {{ $ar ? "آخر {$days} يوم" : "Last {$days} days" }}
                            </option>
                        @endforeach
                    </select>
                    <noscript>
                        <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-white">
                            {{ $ar ? 'تطبيق' : 'Apply' }}
                        </button>
                    </noscript>
                </form>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ([[$ar ? 'شحنات جديدة' : 'New Deliveries', $analytics['total']], [$ar ? 'شحنات مكتملة' : 'Completed Deliveries', $analytics['completed']], [$ar ? 'نسبة الإكمال' : 'Completion Rate', $analytics['success_rate'] . '%']] as [$label, $value])
                    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                        <p class="text-sm text-slate-400">{{ $label }}</p>
                        <p class="mt-3 text-3xl font-bold text-white">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <h4 class="mb-6 text-lg font-semibold text-white">
                    {{ $ar ? 'اتجاه الشحنات اليومية' : 'Daily Delivery Trends' }}
                </h4>

                @php
                    $trends = $analytics['trends'];
                    $maxValue = max(
                        1,
                        ...array_merge(array_column($trends, 'created'), array_column($trends, 'completed')),
                    );
                @endphp

                <div class="mb-4 flex flex-wrap gap-5 text-xs text-slate-400">
                    <span><span class="text-blue-400">●</span>
                        {{ $ar ? 'المنشأة' : 'Created' }}</span>
                    <span><span class="text-emerald-400">●</span>
                        {{ $ar ? 'أحداث الإكمال المسجلة' : 'Recorded Completions' }}</span>
                </div>

                <div class="flex h-48 items-end gap-1 overflow-x-auto pb-2" role="img"
                    aria-label="{{ $ar ? 'مخطط الشحنات اليومية' : 'Daily delivery chart' }}">
                    @foreach ($trends as $day)
                        <div class="flex h-full min-w-2 flex-1 items-end gap-px"
                            title="{{ $day['date'] }}: {{ $day['created'] }} created, {{ $day['completed'] }} completed">
                            <div class="w-1/2 rounded-t bg-blue-500"
                                style="height: {{ ($day['created'] / $maxValue) * 100 }}%"></div>
                            <div class="w-1/2 rounded-t bg-emerald-500"
                                style="height: {{ ($day['completed'] / $maxValue) * 100 }}%"></div>
                        </div>
                    @endforeach
                </div>

                <p class="mt-3 text-xs text-slate-500">
                    {{ $ar
                        ? 'يعتمد الإكمال على أول انتقال مسجل إلى الحالة المكتملة.'
                        : 'Completions use the first recorded transition to completed.' }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <h4 class="mb-5 text-lg font-semibold text-white">
                    {{ $ar ? 'أداء السائقين' : 'Driver Performance' }}
                </h4>

                <div class="space-y-4">
                    @forelse ($analytics['driver_performance'] as $driver)
                        <div class="flex items-center justify-between gap-4 border-b border-slate-800 pb-3">
                            <span class="text-sm font-medium text-white">
                                {{ $driver->name }}
                            </span>
                            <span class="text-sm text-emerald-400">
                                {{ $driver->completed_deliveries }}
                                {{ $ar ? 'شحنة مكتملة' : 'completed' }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">
                            {{ $ar ? 'لا توجد بيانات للسائقين خلال الفترة' : 'No driver performance data for this period' }}
                        </p>
                    @endforelse
                </div>
            </div>

        </section>


        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <h3 class="mb-6 text-lg font-semibold text-white">
                {{ $ar ? 'توزيع حالات الشحنات' : 'Delivery Status Distribution' }}
            </h3>

            <div class="space-y-5">
                @forelse($statusCounts as $status => $count)
                    @php
                        $percent = round(($count / $total) * 100, 1);
                        $key = 'dashboard.statuses.' . $status;
                        $label = __($key);
                        if ($label === $key) {
                            $label = ucwords(str_replace('_', ' ', $status));
                        }
                    @endphp

                    <div>
                        <div class="mb-2 flex justify-between gap-3 text-sm">
                            <span class="text-slate-300">{{ $label }}</span>
                            <span class="text-slate-400">
                                {{ $count }} ({{ $percent }}%)
                            </span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-800">
                            <div class="h-full rounded-full bg-blue-500" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">
                        {{ $ar ? 'لا توجد شحنات بعد' : 'No deliveries yet' }}
                    </p>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
            <div class="flex items-center justify-between gap-3 p-6">
                <h3 class="text-lg font-semibold text-white">
                    {{ $ar ? 'أحدث الشحنات' : 'Recent Deliveries' }}
                </h3>
                <a href="{{ route('admin.deliveries.index') }}" class="text-sm text-blue-400 hover:text-blue-300">
                    {{ $ar ? 'عرض الكل' : 'View All' }}
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[650px] text-sm">
                    <thead class="bg-slate-800/50 text-slate-400">
                        <tr>
                            <th class="px-5 py-4 text-start">{{ $ar ? 'رقم التتبع' : 'Tracking' }}</th>
                            <th class="px-5 py-4 text-start">{{ $ar ? 'التاجر' : 'Merchant' }}</th>
                            <th class="px-5 py-4 text-start">{{ $ar ? 'الحالة' : 'Status' }}</th>
                            <th class="px-5 py-4 text-start">{{ $ar ? 'التاريخ' : 'Date' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentDeliveries as $delivery)
                            <tr class="border-t border-slate-800 hover:bg-slate-800/40">
                                <td class="px-5 py-4">
                                    <a href="{{ route('admin.deliveries.show', $delivery) }}"
                                        class="font-semibold text-blue-400 hover:underline">
                                        {{ $delivery->tracking_number }}
                                    </a>
                                </td>
                                <td class="px-5 py-4 text-slate-300">
                                    {{ $delivery->merchant?->business_name ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-slate-300">
                                    {{ $delivery->status->value }}
                                </td>
                                <td class="px-5 py-4 text-slate-400">
                                    {{ $delivery->created_at?->format('d M Y') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-slate-400">
                                    {{ $ar ? 'لا توجد شحنات' : 'No deliveries found' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </div>
@endsection

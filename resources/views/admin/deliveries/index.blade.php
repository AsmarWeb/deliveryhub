@extends('layouts.dashboard')

@section('title', app()->getLocale() === 'ar' ? 'إدارة الشحنات' : 'Delivery Management')

@section('page-title', app()->getLocale() === 'ar' ? 'إدارة الشحنات' : 'Delivery Management')

@section('content')
    @php
        $isAr = app()->getLocale() === 'ar';

        $statuses = [
            'pending' => $isAr ? 'قيد الانتظار' : 'Pending',
            'assigned' => $isAr ? 'تم التعيين' : 'Assigned',
            'accepted' => $isAr ? 'مقبولة' : 'Accepted',
            'pickup' => $isAr ? 'قيد الاستلام' : 'Pickup',
            'in_transit' => $isAr ? 'قيد النقل' : 'In Transit',
            'delivered' => $isAr ? 'تم التسليم' : 'Delivered',
            'completed' => $isAr ? 'مكتملة' : 'Completed',
            'cancelled' => $isAr ? 'ملغاة' : 'Cancelled',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">
                    {{ $isAr ? 'إدارة الشحنات' : 'Delivery Management' }}
                </h1>

                <p class="mt-1 text-sm text-slate-400">
                    {{ $isAr ? 'متابعة وإدارة جميع الشحنات في المنصة' : 'Monitor and manage all platform deliveries' }}
                </p>
            </div>

            <div class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-3">
                <span class="text-sm text-slate-400">
                    {{ $isAr ? 'إجمالي النتائج' : 'Total Results' }}
                </span>
                <strong class="ms-2 text-xl text-white">
                    {{ $deliveries->total() }}
                </strong>
            </div>
        </div>

        {{-- Search & Filters --}}
        <form method="GET" action="{{ route('admin.deliveries.index') }}"
            class="grid gap-3 rounded-2xl border border-slate-700 bg-slate-900 p-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto] lg:items-center">

            <input type="search" name="search" value="{{ request('search') }}"
                placeholder="{{ $isAr ? 'رقم التتبع أو اسم المستلم' : 'Tracking number or recipient' }}"
                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white placeholder-slate-400 focus:border-blue-500 focus:outline-none">

            <select name="status"
                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white focus:border-blue-500 focus:outline-none">
                <option value="">
                    {{ $isAr ? 'جميع الحالات' : 'All Statuses' }}
                </option>

                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <button type="submit"
                class="rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white transition hover:bg-blue-500">
                {{ $isAr ? 'بحث وتصفية' : 'Search & Filter' }}
            </button>
        </form>

        {{-- Deliveries Table --}}
        <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-800 text-slate-300">
                        <tr>
                            @foreach ([$isAr ? 'رقم التتبع' : 'Tracking', $isAr ? 'التاجر' : 'Merchant', $isAr ? 'المستلم' : 'Recipient', $isAr ? 'الحالة' : 'Status', $isAr ? 'رسوم التوصيل' : 'Delivery Fee', $isAr ? 'التاريخ' : 'Date', $isAr ? 'الإجراءات' : 'Actions'] as $heading)
                                <th class="px-5 py-4 text-start font-semibold">
                                    {{ $heading }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-800">
                        @forelse ($deliveries as $delivery)
                            @php
                                $status = $delivery->status->value;
                                $statusClass = match ($status) {
                                    'completed' => 'bg-emerald-500/10 text-emerald-400',
                                    'delivered' => 'bg-blue-500/10 text-blue-400',
                                    'cancelled' => 'bg-red-500/10 text-red-400',
                                    'in_transit' => 'bg-indigo-500/10 text-indigo-400',
                                    default => 'bg-amber-500/10 text-amber-400',
                                };
                            @endphp

                            <tr class="transition hover:bg-slate-800/60">
                                <td class="whitespace-nowrap px-5 py-4 font-semibold text-white">
                                    {{ $delivery->tracking_number }}
                                </td>

                                <td class="px-5 py-4 text-slate-300">
                                    {{ $delivery->merchant?->business_name ?? '—' }}
                                </td>

                                <td class="px-5 py-4 text-slate-300">
                                    {{ $delivery->recipient_name ?? '—' }}
                                </td>

                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $statuses[$status] ?? $status }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-slate-300">
                                    {{ number_format((float) $delivery->delivery_fee, 2) }}
                                    {{ $delivery->currency }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-slate-400">
                                    {{ $delivery->created_at?->format('Y-m-d') }}
                                </td>

                                <td class="px-5 py-4">
                                    <a href="{{ route('admin.deliveries.show', $delivery) }}"
                                        class="font-semibold text-blue-400 hover:text-blue-300">
                                        {{ $isAr ? 'عرض التفاصيل' : 'View Details' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-16 text-center text-slate-400">
                                    {{ $isAr ? 'لا توجد شحنات مطابقة للبحث' : 'No deliveries found' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        <div>
            {{ $deliveries->links() }}
        </div>
    </div>
@endsection

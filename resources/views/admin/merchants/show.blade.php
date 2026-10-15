@extends('layouts.dashboard')

@section('title', app()->getLocale() === 'ar' ? 'تفاصيل التاجر' : 'Merchant Details')

@section('page-title', app()->getLocale() === 'ar' ? 'تفاصيل التاجر' : 'Merchant Details')

@section('content')
    @php
        $isAr = app()->getLocale() === 'ar';

        $merchantStatuses = [
            'active' => $isAr ? 'نشط' : 'Active',
            'inactive' => $isAr ? 'غير نشط' : 'Inactive',
        ];

        $deliveryStatuses = [
            'draft' => $isAr ? 'مسودة' : 'Draft',
            'pending_pricing' => $isAr ? 'بانتظار التسعير' : 'Pending Pricing',
            'priced' => $isAr ? 'تم التسعير' : 'Priced',
            'matching' => $isAr ? 'البحث عن سائق' : 'Matching',
            'assigned' => $isAr ? 'تم التعيين' : 'Assigned',
            'accepted' => $isAr ? 'مقبولة' : 'Accepted',
            'pickup' => $isAr ? 'قيد الاستلام' : 'Pickup',
            'in_transit' => $isAr ? 'قيد النقل' : 'In Transit',
            'delivered' => $isAr ? 'تم التسليم' : 'Delivered',
            'completed' => $isAr ? 'مكتملة' : 'Completed',
            'cancelled' => $isAr ? 'ملغاة' : 'Cancelled',
            'pickup_failed' => $isAr ? 'فشل الاستلام' : 'Pickup Failed',
            'delivery_failed' => $isAr ? 'فشل التسليم' : 'Delivery Failed',
            'rescheduled' => $isAr ? 'أعيدت الجدولة' : 'Rescheduled',
            'expired' => $isAr ? 'منتهية الصلاحية' : 'Expired',
        ];

        $merchantStatusClass =
            $merchant->status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400';
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('admin.merchants.index') }}"
                    class="mb-3 inline-flex items-center text-sm font-medium text-blue-400 hover:text-blue-300">
                    {{ $isAr ? '← العودة إلى التجار' : '← Back to Merchants' }}
                </a>

                <h1 class="text-2xl font-bold text-white">
                    {{ $merchant->business_name }}
                </h1>

                <p class="mt-1 text-sm text-slate-400">
                    {{ $isAr ? 'معلومات الشركة والحساب والشحنات' : 'Business, account and delivery information' }}
                </p>
            </div>

            <span class="inline-flex w-fit rounded-full px-4 py-2 text-sm font-semibold {{ $merchantStatusClass }}">
                {{ $merchantStatuses[$merchant->status] ?? $merchant->status }}
            </span>
        </div>

        {{-- Statistics --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">
                    {{ $isAr ? 'إجمالي الشحنات' : 'Total Deliveries' }}
                </p>

                <p class="mt-2 text-3xl font-bold text-white">
                    {{ $merchant->deliveries_count }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">
                    {{ $isAr ? 'العناوين المسجلة' : 'Registered Addresses' }}
                </p>

                <p class="mt-2 text-3xl font-bold text-white">
                    {{ $merchant->addresses_count }}
                </p>
            </div>
        </div>

        {{-- Information Cards --}}
        <div class="grid gap-6 lg:grid-cols-2">

            {{-- Business --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                <h2 class="mb-5 text-lg font-bold text-white">
                    {{ $isAr ? 'معلومات الشركة' : 'Business Information' }}
                </h2>

                <dl class="space-y-4">
                    @foreach ([[$isAr ? 'اسم الشركة' : 'Business Name', $merchant->business_name], [$isAr ? 'البريد التجاري' : 'Business Email', $merchant->business_email], [$isAr ? 'رقم الهاتف' : 'Phone', $merchant->business_phone], [$isAr ? 'الرخصة التجارية' : 'Trade License', $merchant->trade_license_number], [$isAr ? 'الرقم الضريبي' : 'Tax Number', $merchant->tax_number]] as [$label, $value])
                        <div class="flex flex-col gap-1 border-b border-slate-800 pb-3">
                            <dt class="text-xs text-slate-400">{{ $label }}</dt>
                            <dd class="break-words text-sm font-medium text-slate-200">
                                {{ $value ?: '—' }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Account --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                <h2 class="mb-5 text-lg font-bold text-white">
                    {{ $isAr ? 'معلومات الحساب' : 'Account Information' }}
                </h2>

                <dl class="space-y-4">
                    @foreach ([[$isAr ? 'اسم المستخدم' : 'User Name', $merchant->user?->name], [$isAr ? 'البريد الإلكتروني' : 'Email', $merchant->user?->email], [$isAr ? 'تاريخ التسجيل' : 'Registered', $merchant->created_at?->format('Y-m-d')]] as [$label, $value])
                        <div class="flex flex-col gap-1 border-b border-slate-800 pb-3">
                            <dt class="text-xs text-slate-400">{{ $label }}</dt>
                            <dd class="break-words text-sm font-medium text-slate-200">
                                {{ $value ?: '—' }}
                            </dd>
                        </div>
                    @endforeach

                    <div>
                        <dt class="mb-2 text-xs text-slate-400">
                            {{ $isAr ? 'حالة التاجر' : 'Merchant Status' }}
                        </dt>

                        <dd>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $merchantStatusClass }}">
                                {{ $merchantStatuses[$merchant->status] ?? $merchant->status }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Notes --}}
        @if ($merchant->notes)
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                <h2 class="mb-3 text-lg font-bold text-white">
                    {{ $isAr ? 'ملاحظات إدارية' : 'Administrative Notes' }}
                </h2>

                <p class="whitespace-pre-line text-sm leading-7 text-slate-300">
                    {{ $merchant->notes }}
                </p>
            </div>
        @endif

        {{-- Recent Deliveries --}}
        <div class="space-y-4">
            <div>
                <h2 class="text-xl font-bold text-white">
                    {{ $isAr ? 'أحدث الشحنات' : 'Recent Deliveries' }}
                </h2>

                <p class="mt-1 text-sm text-slate-400">
                    {{ $isAr ? 'آخر عشر شحنات مسجلة لهذا التاجر' : 'Latest ten deliveries for this merchant' }}
                </p>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-800 text-slate-300">
                            <tr>
                                @foreach ([$isAr ? 'رقم التتبع' : 'Tracking', $isAr ? 'المستلم' : 'Recipient', $isAr ? 'الحالة' : 'Status', $isAr ? 'رسوم التوصيل' : 'Delivery Fee', $isAr ? 'التاريخ' : 'Date', $isAr ? 'الإجراءات' : 'Actions'] as $heading)
                                    <th class="whitespace-nowrap px-5 py-4 text-start font-semibold">
                                        {{ $heading }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-800">
                            @forelse ($recentDeliveries as $delivery)
                                @php
                                    $status = $delivery->status->value;

                                    $statusClass = match ($status) {
                                        'completed' => 'bg-emerald-500/10 text-emerald-400',
                                        'delivered' => 'bg-blue-500/10 text-blue-400',
                                        'cancelled', 'delivery_failed', 'pickup_failed' => 'bg-red-500/10 text-red-400',
                                        'in_transit' => 'bg-indigo-500/10 text-indigo-400',
                                        default => 'bg-amber-500/10 text-amber-400',
                                    };
                                @endphp

                                <tr class="transition hover:bg-slate-800/60">
                                    <td class="whitespace-nowrap px-5 py-4 font-semibold text-white">
                                        {{ $delivery->tracking_number }}
                                    </td>

                                    <td class="px-5 py-4 text-slate-300">
                                        {{ $delivery->recipient_name ?? '—' }}
                                    </td>

                                    <td class="px-5 py-4">
                                        <span
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                            {{ $deliveryStatuses[$status] ?? $status }}
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
                                    <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                        {{ $isAr ? 'لا توجد شحنات لهذا التاجر' : 'No deliveries for this merchant' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

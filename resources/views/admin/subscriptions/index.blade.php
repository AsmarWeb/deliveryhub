@extends('layouts.dashboard')

@section('title', app()->getLocale() === 'ar' ? 'اشتراكات التجار' : 'Merchant Subscriptions')
@section('page-title', app()->getLocale() === 'ar' ? 'اشتراكات التجار' : 'Merchant Subscriptions')

@section('content')
@php $isAr = app()->getLocale() === 'ar'; @endphp

<!-- DELIVERYHUB BILLING OVERVIEW -->
@php
    $billingStats = \App\Models\MerchantSubscription::query()
        ->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' AND starts_at <= CURRENT_TIMESTAMP AND ends_at > CURRENT_TIMESTAMP THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN cashier_subscription_id IS NOT NULL THEN 1 ELSE 0 END) as stripe_count,
            SUM(CASE WHEN cashier_subscription_id IS NULL THEN 1 ELSE 0 END) as manual_count,
            SUM(CASE WHEN status = 'active' AND ends_at > CURRENT_TIMESTAMP AND ends_at <= ? THEN 1 ELSE 0 END) as expiring_count
        ", [now()->addDays(7)])
        ->first();
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
    @foreach ([
        [
            'label' => $isAr ? 'الاشتراكات النشطة' : 'Active Subscriptions',
            'value' => $billingStats->active_count ?? 0,
            'color' => 'text-emerald-400',
        ],
        [
            'label' => $isAr ? 'اشتراكات Stripe' : 'Stripe Subscriptions',
            'value' => $billingStats->stripe_count ?? 0,
            'color' => 'text-blue-400',
        ],
        [
            'label' => $isAr ? 'الاشتراكات اليدوية' : 'Manual Subscriptions',
            'value' => $billingStats->manual_count ?? 0,
            'color' => 'text-amber-400',
        ],
        [
            'label' => $isAr ? 'تنتهي خلال 7 أيام' : 'Expiring in 7 Days',
            'value' => $billingStats->expiring_count ?? 0,
            'color' => 'text-rose-400',
        ],
    ] as $stat)
        <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-400">
                {{ $stat['label'] }}
            </p>

            <p class="mt-3 text-3xl font-bold {{ $stat['color'] }}">
                {{ number_format((int) $stat['value']) }}
            </p>
        </div>
    @endforeach
</div>

@php $isAr = app()->getLocale() === 'ar'; @endphp

<div class="mx-auto max-w-7xl space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-white">
            {{ $isAr ? 'إدارة اشتراكات التجار' : 'Merchant Subscriptions' }}
        </h1>
        <p class="mt-2 text-sm text-slate-400">
            {{ $isAr ? 'تخصيص الباقات ومتابعة الاشتراكات' : 'Manage merchant plans and subscriptions' }}
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-700 bg-emerald-500/10 p-4 text-emerald-400">
            {{ $isAr ? 'تم تفعيل الاشتراك بنجاح' : session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-700 bg-red-500/10 p-4 text-red-400">
            <ul class="list-disc ps-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
        <h2 class="mb-5 text-lg font-semibold text-white">
            {{ $isAr ? 'تفعيل اشتراك جديد' : 'Activate Subscription' }}
        </h2>

        <form method="POST" action="{{ route('admin.subscriptions.store') }}"
              class="grid gap-4 md:grid-cols-3">
            @csrf

            <div>
                <label class="mb-2 block text-sm text-slate-300">
                    {{ $isAr ? 'التاجر' : 'Merchant' }}
                </label>
                <select name="merchant_id" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-white">
                    <option value="">{{ $isAr ? 'اختر التاجر' : 'Select Merchant' }}</option>
                    @foreach($merchants as $merchant)
                        <option value="{{ $merchant->id }}" @selected(old('merchant_id') == $merchant->id)>
                            {{ $merchant->business_name }} — {{ $merchant->user?->email }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-2 block text-sm text-slate-300">
                    {{ $isAr ? 'الباقة' : 'Subscription Plan' }}
                </label>
                <select name="subscription_plan_id" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-white">
                    <option value="">{{ $isAr ? 'اختر الباقة' : 'Select Plan' }}</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" @selected(old('subscription_plan_id') == $plan->id)>
                            {{ $plan->name }} — {{ $plan->monthly_price }} AED
                            ({{ $plan->commission_rate }}%)
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-2 block text-sm text-slate-300">
                    {{ $isAr ? 'المدة بالأشهر' : 'Duration (Months)' }}
                </label>
                <input type="number" name="months" min="1" max="12"
                       value="{{ old('months', 1) }}" required
                       class="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-white">
            </div>

            <div class="md:col-span-3">
                <button type="submit"
                        onclick="return confirm(@js($isAr ? 'هل تريد تفعيل هذه الباقة للتاجر؟ سيتم إنهاء اشتراكه النشط السابق.' : 'Activate this plan? The merchant current active subscription will be replaced.'))"
                        class="rounded-xl bg-cyan-600 px-6 py-3 font-semibold text-white hover:bg-cyan-500">
                    {{ $isAr ? 'تفعيل الاشتراك' : 'Activate Subscription' }}
                </button>
            </div>
        </form>
        <p class="mt-3 text-xs text-amber-400">
            {{ $isAr
                ? 'تنبيه: التفعيل الإداري مباشر ولا يعني تحصيل رسوم الاشتراك.'
                : 'Note: Administrative activation does not collect subscription payment.' }}
        </p>
    </div>


<!-- DELIVERYHUB SUBSCRIPTION FILTERS -->
<form method="GET"
      action="{{ route('admin.subscriptions.index') }}"
      class="mb-5 grid gap-4 rounded-2xl border border-slate-700 bg-slate-900 p-5 md:grid-cols-4">

    <div>
        <label class="mb-2 block text-sm text-slate-300">
            {{ $isAr ? 'بحث عن تاجر' : 'Search Merchant' }}
        </label>
        <input type="search"
               name="search"
               value="{{ request('search') }}"
               placeholder="{{ $isAr ? 'اسم التاجر أو البريد الإلكتروني' : 'Business name or email' }}"
               class="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-white">
    </div>

    <div>
        <label class="mb-2 block text-sm text-slate-300">
            {{ $isAr ? 'حالة الاشتراك' : 'Subscription Status' }}
        </label>
        <select name="status"
                class="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-white">
            <option value="">{{ $isAr ? 'جميع الحالات' : 'All Statuses' }}</option>
            <option value="active" @selected(request('status') === 'active')>
                {{ $isAr ? 'نشط' : 'Active' }}
            </option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>
                {{ $isAr ? 'ملغى' : 'Cancelled' }}
            </option>
            <option value="expired" @selected(request('status') === 'expired')>
                {{ $isAr ? 'منتهي' : 'Expired' }}
            </option>
        </select>
    </div>

    <div>
        <label class="mb-2 block text-sm text-slate-300">
            {{ $isAr ? 'مصدر الاشتراك' : 'Subscription Source' }}
        </label>
        <select name="source"
                class="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-white">
            <option value="">{{ $isAr ? 'جميع المصادر' : 'All Sources' }}</option>
            <option value="stripe" @selected(request('source') === 'stripe')>
                Stripe
            </option>
            <option value="manual" @selected(request('source') === 'manual')>
                {{ $isAr ? 'يدوي' : 'Manual' }}
            </option>
        </select>
    </div>

    <div class="flex items-end gap-2">
        <button type="submit"
                class="rounded-xl bg-cyan-600 px-5 py-3 font-semibold text-white hover:bg-cyan-500">
            {{ $isAr ? 'تطبيق' : 'Apply' }}
        </button>

        <a href="{{ route('admin.subscriptions.index') }}"
           class="rounded-xl border border-slate-600 px-4 py-3 text-slate-300 hover:bg-slate-800">
            {{ $isAr ? 'مسح' : 'Reset' }}
        </a>
    </div>
</form>

    <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[750px] text-sm">
                <thead class="bg-slate-800 text-slate-300">
                    <tr>
                        @foreach([
                            $isAr ? 'التاجر' : 'Merchant',
                            $isAr ? 'الباقة' : 'Plan',
                            $isAr ? 'العمولة' : 'Commission',
                            $isAr ? 'الحالة' : 'Status',
                            $isAr ? 'المصدر' : 'Source',
                            $isAr ? 'حالة Stripe' : 'Stripe Status',
                            $isAr ? 'البداية' : 'Starts',
                            $isAr ? 'النهاية' : 'Ends'
                        ] as $heading)
                            <th class="px-5 py-4 text-start">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($subscriptions as $subscription)
                        <tr>
                            <td class="px-5 py-4 text-white">
                                {{ $subscription->merchant?->business_name }}
                            </td>
                            <td class="px-5 py-4 text-slate-300">
                                {{ $subscription->plan?->name }}
                            </td>
                            <td class="px-5 py-4 text-cyan-400">
                                {{ $subscription->plan?->commission_rate }}%
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold
                                    {{ $subscription->status === 'active'
                                        ? 'bg-emerald-500/10 text-emerald-400'
                                        : 'bg-slate-700 text-slate-300' }}">
                                    {{ ucfirst($subscription->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-slate-300">
                                {{ $subscription->cashier_subscription_id
                                    ? 'Stripe'
                                    : ($isAr ? 'يدوي' : 'Manual') }}
                            </td>
                            <td class="px-5 py-4 text-slate-300">
                                {{ $subscription->cashier_subscription_id
                                    ? ($subscription->cashierSubscription?->stripe_status ?? '—')
                                    : '—' }}
                            </td>
                            <td class="px-5 py-4 text-slate-400">
                                {{ $subscription->starts_at?->format('Y-m-d') ?? '—' }}
                            </td>
                            <td class="px-5 py-4 text-slate-400">
                                {{ $subscription->ends_at?->format('Y-m-d') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-slate-400">
                                {{ $isAr ? 'لا توجد اشتراكات بعد' : 'No subscriptions yet' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $subscriptions->links() }}
</div>
@endsection

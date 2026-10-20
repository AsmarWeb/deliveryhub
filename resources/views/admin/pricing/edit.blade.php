@extends('layouts.dashboard')

@section('content')
@php
    $isAr = app()->getLocale() === 'ar';
@endphp

<div class="mx-auto max-w-5xl space-y-8" dir="{{ $isAr ? 'rtl' : 'ltr' }}">

    <div>
        <h1 class="text-3xl font-bold text-white">
            {{ $isAr ? 'إعدادات تسعير التوصيل' : 'Delivery Pricing Settings' }}
        </h1>
        <p class="mt-2 text-slate-400">
            {{ $isAr
                ? 'إدارة أسعار التوصيل وقواعد التسعير التلقائي.'
                : 'Manage delivery rates and automatic pricing rules.' }}
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-red-300">
            <ul class="list-disc px-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ route('admin.pricing.update') }}"
          class="space-y-6 rounded-2xl border border-white/10 bg-slate-900 p-6 shadow-xl">

        @csrf
        @method('PUT')

        <div class="grid gap-6 md:grid-cols-2">

            @foreach([
                'base_fee' => [$isAr ? 'السعر الأساسي' : 'Base Fee', 'AED'],
                'price_per_km' => [$isAr ? 'سعر الكيلومتر' : 'Price per KM', 'AED'],
                'minimum_fee' => [$isAr ? 'الحد الأدنى للسعر' : 'Minimum Fee', 'AED'],
                'maximum_distance_km' => [$isAr ? 'الحد الأقصى للمسافة' : 'Maximum Distance', 'KM'],
            ] as $field => [$label, $unit])

                <div>
                    <label for="{{ $field }}" class="mb-2 block text-sm font-medium text-slate-300">
                        {{ $label }} ({{ $unit }})
                    </label>

                    <input
                        id="{{ $field }}"
                        type="number"
                        name="{{ $field }}"
                        step="0.01"
                        min="{{ $field === 'maximum_distance_km' ? '0.01' : '0' }}"
                        required
                        value="{{ old($field, $settings->{$field}) }}"
                        class="w-full rounded-xl border border-white/10 bg-slate-800 px-4 py-3 text-white outline-none focus:border-cyan-400"
                    >
                </div>

            @endforeach

        </div>

        <div class="rounded-xl border border-cyan-500/20 bg-cyan-500/5 p-5">

            <input type="hidden" name="auto_pricing_enabled" value="0">

            <label class="flex cursor-pointer items-center gap-4">
                <input
                    type="checkbox"
                    name="auto_pricing_enabled"
                    value="1"
                    @checked(old('auto_pricing_enabled', $settings->auto_pricing_enabled))
                    class="h-5 w-5 rounded border-slate-600 accent-cyan-500"
                >

                <span>
                    <span class="block font-semibold text-white">
                        {{ $isAr ? 'تفعيل التسعير التلقائي' : 'Enable Automatic Pricing' }}
                    </span>

                    <span class="mt-1 block text-sm text-slate-400">
                        {{ $isAr
                            ? 'عند التفعيل، يتم حساب سعر الطلب تلقائياً عند إرساله.'
                            : 'Automatically calculate delivery prices when orders are submitted.' }}
                    </span>
                </span>
            </label>

        </div>

        <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 text-sm text-amber-200">
            {{ $isAr
                ? 'تنبيه: المسافة الحالية محسوبة بخط مستقيم وليست مسافة القيادة الفعلية. لا تفعّل التسعير التلقائي للطلبات الحقيقية قبل اعتماد سياسة التسعير.'
                : 'Warning: Current distances are straight-line estimates, not actual driving distances. Do not enable automatic pricing for live orders until the pricing policy is approved.' }}
        </div>

        <button
            type="submit"
            class="rounded-xl bg-cyan-500 px-6 py-3 font-bold text-slate-950 transition hover:bg-cyan-400"
        >
            {{ $isAr ? 'حفظ الإعدادات' : 'Save Settings' }}
        </button>

    </form>
</div>
@endsection

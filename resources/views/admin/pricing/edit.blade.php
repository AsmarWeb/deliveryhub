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

        @php
            $emirates = [
                'abu_dhabi' => ['أبوظبي', 'Abu Dhabi'], 'dubai' => ['دبي', 'Dubai'],
                'sharjah' => ['الشارقة', 'Sharjah'], 'ajman' => ['عجمان', 'Ajman'],
                'umm_al_quwain' => ['أم القيوين', 'Umm Al Quwain'],
                'ras_al_khaimah' => ['رأس الخيمة', 'Ras Al Khaimah'], 'fujairah' => ['الفجيرة', 'Fujairah'],
            ];
        @endphp
        <section class="space-y-4 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-5">
            <input type="hidden" name="emirate_pricing_enabled" value="0">
            <label class="flex items-center gap-3 font-semibold text-white">
                <input type="checkbox" name="emirate_pricing_enabled" value="1"
                    @checked(old('emirate_pricing_enabled', $settings->emirate_pricing_enabled))>
                {{ $isAr ? 'التسعير حسب الإمارة عند غياب الإحداثيات' : 'Use emirate rates when coordinates are missing' }}
            </label>
            <p class="text-sm text-slate-300">{{ $isAr
                ? 'يتطلب تفعيل التسعير التلقائي أعلاه. أدخل سعر المسار كاملًا بالدرهم؛ يطبق الحد الأدنى العام. الفراغ يعني التسعير اليدوي. لكل اتجاه سعر مستقل. عند توفر الإحداثيات يستخدم حساب المسافة أولًا.'
                : 'Requires automatic pricing above. Enter the full route price in AED; the global minimum applies. Blank means manual pricing. Each direction has its own rate. Coordinates take priority for distance pricing.' }}</p>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach($emirates as $from => $fromLabel)
                    <fieldset class="min-w-0 space-y-3 rounded-xl border border-white/10 p-4">
                        <legend class="px-2 font-semibold text-white">{{ ($isAr ? 'الاستلام من: ' : 'Pickup: ').$fromLabel[$isAr ? 0 : 1] }}</legend>
                        @foreach($emirates as $to => $toLabel)
                            <div class="flex items-center justify-between gap-3">
                                <label class="text-sm text-slate-300" for="rate-{{ $from }}-{{ $to }}">{{ ($isAr ? 'إلى ' : 'To ').$toLabel[$isAr ? 0 : 1] }}</label>
                                <input id="rate-{{ $from }}-{{ $to }}" type="number" min="0" max="999999" step="0.01"
                                    name="emirate_rates[{{ $from }}][{{ $to }}]"
                                    value="{{ old('emirate_rates.'.$from.'.'.$to, $settings->emirate_rates[$from][$to] ?? '') }}"
                                    placeholder="{{ $isAr ? 'يدوي' : 'Manual' }}"
                                    class="w-28 rounded-lg border border-white/10 bg-slate-800 px-3 py-2 text-white">
                            </div>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
            <p class="text-sm text-slate-300">{{ $isAr ? 'يلزم تحديد إمارة عنوان الاستلام وعنوان المستلم. تسعير رسلو مستقل عن رسم الشحن في WooCommerce، وتبقى موافقة التاجر مطلوبة.' : 'Both addresses need an emirate. Raslo pricing is separate from the WooCommerce shipping charge; merchant approval is still required.' }}</p>
        </section>

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

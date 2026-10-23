@extends('layouts.dashboard')
@section('content')
@php
    $ar = app()->getLocale() === 'ar';
    $labels = ['all' => $ar ? 'الكل' : 'All', 'delivered' => $ar ? 'تم التسليم' : 'Delivered', 'completed' => $ar ? 'مكتملة' : 'Completed'];
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    <header>
        <h1 class="text-2xl font-bold">{{ $ar ? 'سجل التوصيلات' : 'Delivery History' }}</h1>
        <p class="mt-2 text-sm opacity-70">{{ $ar ? 'التوصيلات التي سلّمتها، بما فيها المكتملة إداريًا. المهام الجارية والعروض المرفوضة لا تظهر هنا.' : 'Your delivered shipments, including administratively completed deliveries. Active tasks and declined offers are excluded.' }}</p>
    </header>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($labels as $key => $label)
        <a class="rounded-xl border p-4 {{ $status === $key ? 'border-emerald-600' : 'border-slate-300' }}" href="{{ route('driver.history.index', ['status' => $key, 'q' => $search]) }}" @if($status === $key) aria-current="page" @endif>
            <div class="text-sm opacity-70">{{ $label }}</div><div class="mt-2 text-2xl font-bold">{{ $counts[$key] }}</div>
        </a>
        @endforeach
    </div>
    <form method="get" action="{{ route('driver.history.index') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-300 p-4">
        <label class="min-w-0 flex-1"><span class="block text-sm">{{ $ar ? 'رقم التتبع' : 'Tracking number' }}</span><input class="mt-2 w-full rounded-lg border border-slate-300 p-3" name="q" value="{{ $search }}" maxlength="100"></label>
        <label><span class="block text-sm">{{ $ar ? 'الحالة' : 'Status' }}</span><select name="status" class="mt-2 rounded-lg border border-slate-300 p-3">@foreach($labels as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></label>
        <button class="rounded-lg bg-emerald-800 px-5 py-3 text-white">{{ $ar ? 'بحث' : 'Search' }}</button>
        <a class="px-2 py-3 underline" href="{{ route('driver.history.index') }}">{{ $ar ? 'مسح' : 'Reset' }}</a>
    </form>
    @error('q')<p role="alert">{{ $message }}</p>@enderror
    @error('status')<p role="alert">{{ $message }}</p>@enderror
    <div class="space-y-4">
    @forelse($deliveries as $delivery)
        <article class="rounded-xl border border-slate-300 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-bold break-all">{{ $delivery->tracking_number }}</h2><span class="rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-900">{{ $labels[$delivery->status->value] }}</span></div>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                <div><dt class="opacity-70">{{ $ar ? 'الاستلام' : 'Pickup' }}</dt><dd class="mt-1 break-words">{{ $delivery->pickupAddress?->city ?? '—' }}</dd></div>
                <div><dt class="opacity-70">{{ $ar ? 'التسليم' : 'Drop-off' }}</dt><dd class="mt-1 break-words">{{ $delivery->dropoffAddress?->city ?? '—' }}</dd></div>
                <div><dt class="opacity-70">{{ $ar ? 'وقت التسليم بتوقيت الإمارات' : 'Delivered at UAE time' }}</dt><dd class="mt-1" dir="ltr">{{ $delivery->delivered_at?->timezone('Asia/Dubai')->format('Y-m-d H:i') ?? '—' }}</dd></div>
            </dl>
            <a class="mt-4 inline-block rounded-lg bg-emerald-800 px-4 py-2 text-sm text-white" href="{{ route('driver.assignments.show', $delivery->assignments->first()) }}">{{ $ar ? 'تفاصيل المهمة' : 'Assignment details' }}</a>
        </article>
    @empty
        <p class="rounded-xl border border-slate-300 p-8 text-center">{{ $ar ? 'لا توجد توصيلات تطابق البحث. ستظهر التوصيلات هنا بعد تسجيل التسليم.' : 'No matching deliveries. Deliveries appear here after delivery is recorded.' }}</p>
    @endforelse
    </div>
    {{ $deliveries->links() }}
</div>
@endsection

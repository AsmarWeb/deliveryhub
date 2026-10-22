@extends('layouts.dashboard')
@section('title', app()->getLocale() === 'ar' ? 'ملف السائق والمستندات' : 'Driver profile & documents')
@section('content')
@php $ar = app()->getLocale() === 'ar'; @endphp
<div class="mx-auto max-w-6xl space-y-6">
    <a href="{{ route('admin.drivers.index') }}" class="font-semibold text-emerald-400">{{ $ar ? 'العودة إلى السائقين' : 'Back to drivers' }}</a>
    <header class="rounded-2xl bg-emerald-900 p-6 text-white">
        <p class="text-xs font-bold text-amber-200">RASLO · {{ $ar ? 'مراجعة السائق' : 'DRIVER REVIEW' }}</p>
        <h1 class="mt-3 text-3xl font-bold break-words">{{ $driver->user->name }}</h1>
        <p class="mt-2 break-all">{{ $driver->user->email }}</p>
        <dl class="mt-5 grid gap-4 sm:grid-cols-3">
            @foreach([[$ar ? 'الهاتف' : 'Phone', $driver->phone], [$ar ? 'المركبة واللوحة' : 'Vehicle & plate', $driver->vehicle_type.' · '.$driver->vehicle_plate], [$ar ? 'رقم الرخصة' : 'Licence number', $driver->license_number]] as [$label, $value])
            <div><dt class="text-sm text-emerald-200">{{ $label }}</dt><dd class="mt-1 break-words font-bold">{{ $value ?: '—' }}</dd></div>
            @endforeach
        </dl>
        <p class="mt-4 text-sm">{{ $ar ? 'اعتماد المستندات لا يغيّر حالة تشغيل السائق تلقائيًا.' : 'Document approval does not automatically change driver operational status.' }}</p>
    </header>
    @if($errors->any())<div role="alert" class="rounded-xl bg-red-500/10 p-4 text-red-400">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @include('driver-documents.partials', ['adminView' => true])
    <section class="rounded-2xl border border-white/10 bg-slate-900 p-5 space-y-4">
        <h2 class="text-xl font-bold text-white">{{ $ar ? 'سجل النسخ والمراجعات' : 'Versions & review history' }}</h2>
        @forelse($history as $version)
        <article class="border-t border-white/10 pt-4 text-sm text-slate-300 space-y-2">
            <p class="font-semibold">{{ \App\Services\Drivers\DriverDocumentService::TYPES[$version->type][$ar ? 0 : 1] }} · {{ $version->created_at }} · {{ $version->superseded_at ? ($ar ? 'نسخة سابقة' : 'Previous version') : ($ar ? 'النسخة الحالية' : 'Current version') }}</p>
            <a href="{{ route('driver.documents.file', $version) }}" target="_blank" rel="noopener noreferrer" class="text-emerald-400 underline">{{ $ar ? 'عرض هذه النسخة' : 'View this version' }}</a>
            @foreach($version->reviews as $review)
                <p>{{ $review->created_at }} · {{ $review->reviewer?->name }} · {{ $review->status === 'approved' ? ($ar ? 'مقبول' : 'Approved') : ($ar ? 'مرفوض' : 'Rejected') }}</p>
                @if($review->reason)<p class="break-words">{{ $review->reason }}</p>@endif
            @endforeach
        </article>
        @empty <p class="text-slate-400">{{ $ar ? 'لم تُرفع مستندات بعد.' : 'No documents uploaded yet.' }}</p> @endforelse
        {{ $history->links() }}
    </section>
</div>
@endsection

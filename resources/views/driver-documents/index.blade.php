@extends('layouts.dashboard')
@section('title', app()->getLocale() === 'ar' ? 'مستنداتي' : 'My documents')
@section('content')
@php $ar = app()->getLocale() === 'ar'; @endphp
<div class="mx-auto max-w-5xl space-y-6">
    <div class="rounded-2xl bg-emerald-900 p-6 text-white">
        <p class="text-xs font-bold tracking-widest text-amber-200">RASLO · {{ $ar ? 'ملف السائق' : 'DRIVER FILE' }}</p>
        <h1 class="mt-3 text-3xl font-bold">{{ $ar ? 'مستنداتك، في مكان واحد' : 'Your documents, together' }}</h1>
        <p class="mt-3 text-sm leading-7">{{ $ar ? 'ارفع صورًا واضحة أو PDF بحد أقصى 5MB لكل مستند. المراجعة منفصلة عن تفعيل حساب التشغيل. رفع نسخة بديلة يعيد المستند للمراجعة.' : 'Upload clear images or PDFs, up to 5MB each. Review is separate from operational account approval. Replacements return to review.' }}</p>
    </div>
    @if($errors->any())<div role="alert" class="rounded-xl bg-red-500/10 p-4 text-red-400">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @include('driver-documents.partials')
</div>
@endsection

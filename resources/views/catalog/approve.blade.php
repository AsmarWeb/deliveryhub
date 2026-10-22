@extends('layouts.dashboard')
@php
    $ar = app()->getLocale() === 'ar';
    $t = fn($arText, $enText) => $ar ? $arText : $enText;
@endphp
@section('title', $t('مراجعة المتاجر','Store review'))
@section('page-title', $t('مراجعة المتاجر','Store review'))
@push('styles')<link rel="stylesheet" href="{{ asset('brand/integrations.css') }}">@endpush
@section('content')
<div class="ic">
    <div class="topline"><div><span class="eyebrow">RASLO · {{ $t('الإدارة','ADMINISTRATION') }}</span><h2>{{ $t('مراجعة واعتماد متاجر الكتالوج','Review catalog stores') }}</h2><p class="muted">{{ $t('راجع بيانات المتجر ومنتجاته قبل الموافقة. الاعتماد يشمل المنتجات الحالية والمستقبلية؛ لا يمنح اشتراك Enterprise أو يلغي شروط المخزون وحالة الربط.','Review the store and products before approval. Approval covers current and future products; it does not grant Enterprise access or bypass stock and connection-status rules.') }}</p></div><a class="button secondary" href="{{ route('catalog.index') }}" target="_blank" rel="noopener">{{ $t('معاينة الكتالوج العام','Preview public catalog') }} ↗</a></div>
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="grid two"><div class="metric"><span class="muted">{{ $t('بانتظار المراجعة','Awaiting review') }}</span><strong>{{ $pendingCount }}</strong></div><div class="metric"><span class="muted">{{ $t('متاجر معتمدة','Approved stores') }}</span><strong>{{ $approvedCount }}</strong></div></div>
    <section class="panel"><form class="filters" method="GET" action="{{ route('admin.catalog.index') }}"><input type="search" name="q" maxlength="100" value="{{ request('q') }}" placeholder="{{ $t('ابحث باسم المتجر أو التاجر','Search store or merchant') }}" aria-label="{{ $t('اسم المتجر أو التاجر','Store or merchant name') }}"><select name="review" aria-label="{{ $t('حالة المراجعة','Review status') }}"><option value="">{{ $t('جميع الحالات','All statuses') }}</option><option value="pending" @selected(request('review') === 'pending')>{{ $t('بانتظار المراجعة','Awaiting review') }}</option><option value="approved" @selected(request('review') === 'approved')>{{ $t('معتمد','Approved') }}</option></select><button class="button">{{ $t('تصفية','Filter') }}</button><a class="button secondary" href="{{ route('admin.catalog.index') }}">{{ $t('إعادة ضبط','Reset') }}</a></form></section>
    @forelse($connections as $connection)
        <article class="panel"><div class="topline"><div><h2>{{ $connection->name }}</h2><p class="muted">{{ $connection->merchant->business_name }} · {{ $connection->mode === 'feed' ? 'Feed' : 'Push API' }} · {{ $t('المنتجات المستوردة','Imported products') }}: {{ $connection->products_count }}</p></div><div class="actions"><span class="badge {{ $connection->approved_at ? 'ok' : 'wait' }}">{{ $connection->approved_at ? $t('معتمد للنشر','Approved') : $t('بانتظار المراجعة','Awaiting review') }}</span><span class="badge">{{ $connection->status === 'active' ? $t('الربط مفعّل','Connection active') : $t('الربط معطّل','Connection disabled') }}</span></div></div>
            <details><summary>{{ $t('معاينة آخر المنتجات المستوردة','Preview recently imported products') }}</summary><div class="preview">@forelse($connection->products as $product)<article>@if($product->image_url)<img class="product-photo" src="{{ $product->image_url }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif<h3>{{ $product->name }}</h3><p><bdi>{{ number_format($product->price_minor / 100, 2) }}</bdi> {{ $t('د.إ','AED') }} · {{ $product->status }}</p><p class="muted">{{ $t('المخزون','Stock') }}: {{ $product->stock_quantity }}</p></article>@empty<p class="muted">{{ $t('لم تُستورد منتجات بعد. الاعتماد الآن سيشمل المنتجات القادمة أيضًا.','No products imported yet. Approval now also covers future products.') }}</p>@endforelse</div></details>
            <form method="POST" action="{{ route('admin.catalog.approval', $connection) }}">@csrf<input type="hidden" name="approved" value="{{ $connection->approved_at ? 0 : 1 }}"><button class="button {{ $connection->approved_at ? 'danger' : '' }}">{{ $connection->approved_at ? $t('إيقاف نشر المتجر','Revoke publication approval') : $t('اعتماد نشر المتجر','Approve store publication') }}</button></form>
        </article>
    @empty<section class="empty"><h2>{{ $t('لا توجد متاجر مطابقة','No matching stores') }}</h2><p>{{ $t('جرّب تغيير البحث أو حالة المراجعة.','Try changing the search or review status.') }}</p></section>@endforelse
    {{ $connections->links() }}
</div>
@endsection

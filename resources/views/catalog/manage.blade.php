@extends('layouts.dashboard')
@php
    $ar = app()->getLocale() === 'ar';
    $t = fn($arText, $enText) => $ar ? $arText : $enText;
    $stateLabels = ['active'=>$t('مفعّل','Active'), 'disabled'=>$t('معطّل','Disabled')];
    $runLabels = ['queued'=>$t('في الانتظار','Queued'), 'running'=>$t('قيد المعالجة','Running'), 'succeeded'=>$t('نجحت','Succeeded'), 'failed'=>$t('فشلت','Failed')];
@endphp
@section('title', $t('متاجري والتكاملات','My stores & integrations'))
@section('page-title', $t('متاجري والتكاملات','My stores & integrations'))
@push('styles')<link rel="stylesheet" href="{{ asset('brand/integrations.css') }}">@endpush
@section('content')
<div class="ic">
    @include('catalog.partials.nav')
    @include('catalog.partials.notices')
    <div class="topline"><div><span class="eyebrow">RASLO ENTERPRISE</span><h2>{{ $t('متاجرك، في مساحة عملك','Your stores, in your workspace') }}</h2><p class="muted">{{ $t('تابع الربط والمزامنة واعتماد النشر. يبدأ ربط WooCommerce من مركز الإضافات.','Manage connection, sync and publication status. Start WooCommerce setup in the connector centre.') }}</p></div><a class="button" href="{{ route('merchant.catalog.platforms') }}">{{ $t('ربط متجر جديد','Connect a store') }} +</a></div>
    <div class="grid"><div class="metric"><span class="muted">{{ $t('المتاجر المربوطة','Connected stores') }}</span><strong>{{ $connections->count() }}</strong></div><div class="metric"><span class="muted">{{ $t('المنتجات المستوردة','Imported products') }}</span><strong>{{ $connections->sum('products_count') }}</strong></div><div class="metric"><span class="muted">{{ $t('بانتظار مراجعة الإدارة','Awaiting review') }}</span><strong>{{ $connections->whereNull('approved_at')->count() }}</strong></div></div>
    @if(session('catalog_key'))<section class="notice" role="status"><h2>{{ $t('مفتاح رسلو — يظهر مرة واحدة','Raslo key — shown once') }}</h2><code dir="ltr">{{ session('catalog_key') }}</code><p>{{ $t('هذا مفتاح Push لخادم المتجر، وليس رمز Feed الصادر عن WooCommerce. احفظه في خادمك ولا تضعه في المتصفح أو تطبيق العميل.','This is the Raslo Push key for the store server, not the WooCommerce Feed token. Save it on your server, never in browser or client-app code.') }}</p></section>@endif
    @if($canConnect)<details class="panel" @if($errors->any()) open @endif><summary>{{ $t('إنشاء ربط عام API أو Feed','Create a generic API or Feed connection') }}</summary>@include('catalog.partials.create', ['woo'=>false])</details>@endif
    @forelse($connections as $connection)
        <section class="panel" id="connection-{{ $connection->id }}">
            <div class="topline"><div><h2>{{ $connection->name }}</h2><div class="actions"><span class="badge {{ $connection->status === 'active' ? 'ok' : 'off' }}">{{ $stateLabels[$connection->status] ?? $connection->status }}</span><span class="badge">{{ $connection->mode === 'feed' ? 'Feed' : 'Push API' }}</span><span class="badge {{ $connection->approved_at ? 'ok' : 'wait' }}">{{ $connection->approved_at ? $t('معتمد للنشر','Publication approved') : $t('بانتظار مراجعة الإدارة','Awaiting admin review') }}</span></div></div><span class="muted">{{ $t('المنتجات المستوردة','Imported products') }}: {{ $connection->products_count }}</span></div>
            <div class="status-row"><span class="muted">{{ $t('آخر مزامنة ناجحة','Last successful sync') }}</span><bdi>{{ $connection->last_synced_at?->timezone('Asia/Dubai')->format('Y-m-d H:i') ?? $t('لم تتم بعد','Not synced yet') }}</bdi></div>
            @if($connection->mode === 'feed')<div class="status-row"><span class="muted">{{ $t('المزامنة القادمة المجدولة','Next scheduled sync') }}</span><bdi>{{ $connection->next_sync_at?->timezone('Asia/Dubai')->format('Y-m-d H:i') ?? '—' }}</bdi></div><p class="muted">{{ $t('الأوقات بتوقيت الإمارات. تتطلب المزامنة تشغيل عامل الطابور والمجدول.','Times use UAE time. Synchronization requires a running queue worker and scheduler.') }}</p><div class="codebox"><code>{{ $connection->feed_url }}</code><button type="button" class="button secondary" data-copy-value="{{ $connection->feed_url }}">{{ $t('نسخ الرابط','Copy URL') }}</button></div>@endif
            @if($connection->last_sync_error)<div class="notice error"><strong>{{ $t('تعذرت آخر مزامنة','Last synchronization failed') }}</strong><p>{{ $t('افحص تفعيل الإضافة ورمز التغذية وفهرسة المنتجات ثم أعد المحاولة.','Check plugin activation, feed credentials and product indexing, then retry.') }}</p><code>{{ $connection->last_sync_error }}</code></div>@endif
            @if(session('connection_check.id') === $connection->id)
                <div class="notice {{ session('connection_check.ok') ? 'ok' : 'error' }}" role="status">
                    @if(session('connection_check.ok'))<strong>{{ $t('نجح فحص الاتصال وبنية البيانات','Connection and data format check passed') }}</strong><p>{{ $t('سجلات الصفحة الأولى','Records on the first page') }}: {{ session('connection_check.count') }}. {{ $t('الفحص لا يستورد المنتجات ولا يوافق على نشرها.','This check does not import products or approve publication.') }}</p>@if(session('connection_check.count') === 0)<p>{{ $t('التغذية فارغة؛ راجع فهرسة المنتجات في المتجر.','The feed is empty; check store product indexing.') }}</p>@endif
                    @else<strong>{{ $t('تعذر التحقق من الاتصال','Connection check failed') }}</strong><p>{{ $t('راجع HTTPS واعتماد النطاق وتفعيل الإضافة والرمز وبنية التغذية. لا تعرض رسلو تفاصيل سرية من خادم المتجر.','Check HTTPS, approved hostname, plugin activation, token and feed format. Private upstream details are not displayed.') }}</p>@endif
                </div>
            @endif
            @if($canConnect)<div class="actions space">
                <a class="button secondary" href="{{ route('merchant.catalog.deliveries', $connection) }}">{{ $t('ربط طلبات التوصيل', 'Delivery-order integration') }}</a>
                @if($connection->mode === 'feed' && $connection->status === 'active')
                    <form method="POST" action="{{ route('merchant.catalog.check', $connection) }}">@csrf<button class="button secondary">{{ $t('فحص الاتصال','Check connection') }}</button></form>
                    <form method="POST" action="{{ route('merchant.catalog.sync', $connection) }}">@csrf<button class="button">{{ $t('مزامنة الآن','Sync now') }}</button></form>
                @endif
                <form method="POST" action="{{ route('merchant.catalog.status', $connection) }}">@csrf<input type="hidden" name="status" value="{{ $connection->status === 'active' ? 'disabled' : 'active' }}"><button class="button secondary">{{ $connection->status === 'active' ? $t('تعطيل الربط وإخفاء المنتجات','Disable & hide products') : $t('تفعيل الربط','Enable connection') }}</button></form>
            </div>
            @if($connection->mode === 'feed')<details><summary>{{ $t('تحديث رابط أو رمز التغذية','Update feed URL or token') }}</summary><p class="muted">{{ $t('بعد تدوير الرمز في WooCommerce، احفظ الرمز الجديد هنا. لا يمكن عرض الرمز الحالي.','After rotating the token in WooCommerce, save the new token here. The current token cannot be displayed.') }}</p><div class="grid two">
                <form method="POST" action="{{ route('merchant.catalog.status', $connection) }}">@csrf<label for="token-{{ $connection->id }}">{{ $t('رمز التغذية الجديد','New feed token') }}</label><input id="token-{{ $connection->id }}" name="feed_token" type="password" dir="ltr" required minlength="16" maxlength="500" autocomplete="new-password"><button class="button secondary space">{{ $t('حفظ الرمز','Save token') }}</button></form>
                <form method="POST" action="{{ route('merchant.catalog.status', $connection) }}">@csrf<label for="url-{{ $connection->id }}">{{ $t('رابط التغذية','Feed URL') }}</label><input id="url-{{ $connection->id }}" name="feed_url" type="url" dir="ltr" required maxlength="2048" value="{{ $connection->feed_url }}"><button class="button secondary space">{{ $t('حفظ الرابط','Save URL') }}</button></form>
            </div></details>@endif
            <details><summary>{{ $t('خيارات مفتاح Push API','Push API key options') }}</summary><p class="muted">{{ $t('إصدار بديل يلغي المفتاح الحالي فورًا. لا يغيّر رمز التغذية الخاص بـ WooCommerce.','Replacing a key immediately revokes the current key. This does not rotate the WooCommerce Feed token.') }}</p><code>{{ $connection->key_prefix }}…</code><form method="POST" action="{{ route('merchant.catalog.rotate', $connection) }}">@csrf<button class="button danger space">{{ $t('إلغاء المفتاح وإصدار بديل','Revoke key & issue replacement') }}</button></form></details>
            @endif
            <details><summary>{{ $t('سجل المزامنة الأخير','Recent synchronization history') }}</summary><div class="table-scroll"><table><thead><tr><th>{{ $t('المزامنة · توقيت الإمارات','Sync · UAE time') }}</th><th>{{ $t('الحالة','Status') }}</th><th>{{ $t('تمت المعالجة','Processed') }}</th><th>{{ $t('تغيرت','Changed') }}</th><th>{{ $t('الخطأ','Error') }}</th></tr></thead><tbody>@forelse($connection->runs as $run)<tr><td><bdi>{{ $run->created_at->timezone('Asia/Dubai')->format('Y-m-d H:i') }}</bdi></td><td>{{ $runLabels[$run->status] ?? $run->status }}</td><td>{{ $run->processed }}</td><td>{{ $run->changed }}</td><td><code>{{ $run->error_code ?? '—' }}</code></td></tr>@empty<tr><td colspan="5">{{ $t('لا توجد محاولات بعد','No attempts yet') }}</td></tr>@endforelse</tbody></table></div></details>
        </section>
    @empty
        <section class="empty"><h2>{{ $t('أضف أول متجر لك','Connect your first store') }}</h2><p>{{ $t('نزّل إضافة WooCommerce أو اختر الربط العام من مركز الربط.','Download WooCommerce or choose a generic integration in the connector centre.') }}</p><a class="button space" href="{{ route('merchant.catalog.platforms') }}">{{ $t('استعرض طرق الربط','Explore connection methods') }}</a></section>
    @endforelse
    <p id="integration-copy-status" class="muted" role="status" aria-live="polite"></p>
    @include('catalog.partials.scripts')
</div>
@endsection

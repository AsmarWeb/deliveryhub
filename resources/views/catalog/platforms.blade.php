@extends('layouts.dashboard')
@php
    $ar = app()->getLocale() === 'ar';
    $t = fn($arText, $enText) => $ar ? $arText : $enText;
@endphp
@section('title', $t('مركز الربط والإضافات','Connectors & plugins'))
@section('page-title', $t('مركز الربط والإضافات','Connectors & plugins'))
@push('styles')<link rel="stylesheet" href="{{ asset('brand/integrations.css') }}">@endpush
@section('content')
<div class="ic">
    @include('catalog.partials.nav')
    @include('catalog.partials.notices')
    <section class="panel accent"><span class="eyebrow">RASLO ENTERPRISE</span><h2>{{ $t('متجرك أقرب إلى رسلو','Bring your store closer to Raslo') }}</h2><p class="muted">{{ $t('اختر منصة متجرك. الربط الحالي يزامن المنتجات والأسعار والمخزون؛ الشراء والدفع يبقيان لدى المتجر. ربط التوصيل متاح اختياريًا للطلبات المدفوعة بعد إعداد مفتاح مستقل.','Choose your store platform. Current connections sync products, prices and stock; checkout and payment stay with the store. Prepaid delivery-order forwarding is available separately with a dedicated key.') }}</p></section>
    <div class="grid">
        <article class="panel"><div class="platform-icon" aria-hidden="true">W</div><span class="badge ok">{{ $t('إضافة متاحة · تجريبية','Plugin available · pilot') }}</span><h2 class="space">WooCommerce</h2><p class="muted">{{ $t('نزّل إضافة رسلو، فعّلها في WordPress، ثم أدخل رابط التغذية والرمز هنا. لا تحتاج إلى مشاركة كلمة مرور مدير المتجر.','Download Raslo, activate it in WordPress, then add its feed URL and token here. No store administrator password is shared.') }}</p><a class="button space" href="{{ route('merchant.catalog.guide', 'woocommerce') }}">{{ $t('إعداد WooCommerce','Set up WooCommerce') }} ↗</a></article>
        <article class="panel"><div class="platform-icon" aria-hidden="true">{ }</div><span class="badge ok">{{ $t('متاح للمطورين','Available for developers') }}</span><h2 class="space">API / Feed</h2><p class="muted">{{ $t('اربط Laravel أو نظامك الخاص أو أي منصة تستطيع إرسال صيغة رسلو أو تقديم تغذية متوافقة. يتطلب إعدادًا في خادم المتجر.','Connect Laravel, a custom system or any platform that can send Raslo data or expose a compatible feed. Store-side implementation is required.') }}</p><a class="button secondary space" href="{{ route('merchant.catalog.guide', 'api') }}">{{ $t('دليل API وملف OpenAPI','API guide & OpenAPI file') }} ↗</a></article>
        <article class="panel"><div class="platform-icon" aria-hidden="true">S</div><span class="badge wait">{{ $t('لا توجد إضافة جاهزة بعد','No ready-made connector yet') }}</span><h2 class="space">Shopify / {{ $t('منصات أخرى','Other platforms') }}</h2><p class="muted">{{ $t('لا يكفي إدخال رابط المتجر لجلب منتجاته. يلزم موصل أو تطبيق مخصص يحوّل المنتجات إلى API أو Feed رسلو.','A store URL alone cannot import its products. An adapter or custom app must map products to the Raslo API or Feed contract.') }}</p><a class="button secondary space" href="{{ route('merchant.catalog.guide', 'api') }}">{{ $t('استعرض متطلبات الربط','View integration requirements') }}</a></article>
    </div>
</div>
@endsection

@extends('layouts.dashboard')
@php
    $ar = app()->getLocale() === 'ar';
    $t = fn($arText, $enText) => $ar ? $arText : $enText;
    $woo = $platform === 'woocommerce';
@endphp
@section('title', $woo ? 'WooCommerce' : $t('دليل API','API guide'))
@section('page-title', $woo ? $t('ربط WooCommerce','Connect WooCommerce') : $t('الربط العام API / Feed','API / Feed integration'))
@push('styles')<link rel="stylesheet" href="{{ asset('brand/integrations.css') }}">@endpush
@section('content')
<div class="ic">
    @include('catalog.partials.nav')
    @include('catalog.partials.notices')
    @if($woo)
        <div class="grid two">
            <section class="panel"><span class="eyebrow">WOOCOMMERCE · {{ $pluginVersion }}</span><h2>{{ $t('إضافة رسلو لمتجرك','Raslo for your store') }}</h2><p class="muted">{{ $t('WordPress 6.5+ · PHP 8.1+ · WooCommerce · عملة AED · HTTPS. يدعم الموصل المنتجات المادية البسيطة والمتغيرات.','WordPress 6.5+ · PHP 8.1+ · WooCommerce · AED currency · HTTPS. Supports simple physical products and physical variations.') }}</p>
                @if($canConnect && !$pluginChecksum)<p class="notice error">{{ $t('ملف الإضافة غير متاح مؤقتًا؛ تواصل مع مدير رسلو.','The plugin file is temporarily unavailable; contact the Raslo operator.') }}</p>@endif
                @if($canConnect && $pluginChecksum)<a class="button space" href="{{ route('merchant.catalog.download') }}">{{ $t('تنزيل إضافة WooCommerce ZIP','Download WooCommerce plugin ZIP') }} ↓</a>@endif
                <p class="muted">{{ $t('هذه نسخة تجريبية. اختبر تحديث السعر والمخزون والحذف قبل استخدامها على متجر إنتاجي.','Pilot release. Test price, inventory and deletion updates before production use.') }}</p>
                @if($pluginChecksum)<details><summary>{{ $t('بصمة ملف التنزيل SHA-256','Download SHA-256 checksum') }}</summary><code dir="ltr">{{ $pluginChecksum }}</code></details>@endif
            </section>
            <section class="panel"><h2>{{ $t('خطوات الربط','Connection steps') }}</h2><ol class="steps">
                <li><strong>{{ $t('ثبّت الإضافة','Install the plugin') }}</strong><p class="muted">{{ $t('في WordPress: الإضافات ← أضف جديدًا ← رفع إضافة. ارفع ZIP ثم فعّله بعد WooCommerce.','WordPress: Plugins → Add New → Upload Plugin. Upload the ZIP and activate after WooCommerce.') }}</p></li>
                <li><strong>{{ $t('فعّل التغذية وانسخ البيانات','Enable the feed and copy its details') }}</strong><p class="muted">{{ $t('WooCommerce ← Raslo ← Enable / issue new token. احفظ الرمز الذي يظهر مرة واحدة ورابط Feed. فعّل الروابط الدائمة واترك فهرسة المنتجات تعمل.','WooCommerce → Raslo → Enable / issue new token. Save the one-time token and Feed URL. Enable pretty permalinks and let product indexing run.') }}</p></li>
                <li><strong>{{ $t('أضف المتجر في رسلو','Add the store to Raslo') }}</strong><p class="muted">{{ $t('أدخل البيانات أدناه. إن لم يكن النطاق معتمدًا، اطلب من مدير رسلو إضافته أولًا. ثم افحص الاتصال واطلب المزامنة من صفحة متاجري.','Enter the details below. If the hostname is not approved, ask the Raslo operator to approve it first. Then check the connection and sync from My stores.') }}</p></li>
                <li><strong>{{ $t('اعتماد النشر','Publication approval') }}</strong><p class="muted">{{ $t('تراجع الإدارة المتجر قبل ظهور منتجاته للعامة. يستمر الاشتراك الساري والمخزون المتاح شرطًا للظهور.','Administration reviews the store before public publication. A current subscription and available stock remain required.') }}</p></li>
            </ol></section>
        </div>
        @if($canConnect)<section class="panel"><h2>{{ $t('ربط متجر WooCommerce','Connect your WooCommerce store') }}</h2>@include('catalog.partials.create', ['woo' => true])</section>@endif
        <section class="panel"><h2>{{ $t('حلول سريعة','Quick troubleshooting') }}</h2><ul class="error-list"><li>{{ $t('401 في المتصفح طبيعي دون رمز. إذا فشل الفحص، تأكد من تفعيل التغذية والرمز، دون كلمة Bearer.','401 in a browser is expected without credentials. If the connection check fails, verify feed activation and the token without Bearer.') }}</li><li>{{ $t('صفر منتجات: راجع Indexed records في الإضافة وفهرسة WooCommerce، وتأكد من نشر المنتجات ومخزونها.','Zero products: check Indexed records, WooCommerce indexing, published status and stock.') }}</li><li>{{ $t('المزامنة تستغرق عادةً حتى 15 دقيقة بعد فهرسة المتجر. يجب تشغيل طابور ومجدول رسلو باستمرار، وWP-Cron أو عامل Action Scheduler في المتجر.','Sync normally takes up to 15 minutes after store indexing. Keep the Raslo queue/scheduler and store WP-Cron or Action Scheduler runner active.') }}</li><li>{{ $t('لا تشارك كلمة مرور WordPress أو مفاتيح Stripe. تعطيل إضافة المتجر وحدها لا يخفي المنتجات المستوردة؛ عطّل الاتصال في رسلو أيضًا.','Do not share WordPress passwords or Stripe keys. Disabling the plugin alone does not hide imported products; disable the Raslo connection too.') }}</li></ul></section>
    @else
        <section class="panel accent"><h2>{{ $t('عقد واحد لمختلف المنصات','One contract for different platforms') }}</h2><p class="muted">{{ $t('اختر Push لإرسال المنتجات من خادم المتجر، أو Feed ليقرأ رسلو تغذية HTTPS خاصة. كلاهما يحتاج تنفيذًا متوافقًا على جهة المتجر؛ لا يعمل مع رابط متجر عادي أو API مختلف تلقائيًا.','Use Push to send products from the store server, or Feed for a private HTTPS feed pulled by Raslo. Both require a compatible store-side implementation; a normal storefront URL or unrelated API will not work automatically.') }}</p>
            @if($canConnect)<a class="button" href="{{ route('merchant.catalog.contract') }}">{{ $t('تنزيل عقد OpenAPI JSON','Download OpenAPI JSON') }} ↓</a>@endif
        </section>
        <div class="grid two"><section class="panel"><h2>Push API</h2><p class="muted">{{ $t('أنشئ اتصال Push من صفحة متاجري. خزّن المفتاح الذي يظهر مرة واحدة في خادم المتجر، ثم أرسل دفعات حتى 100 منتج. لا تضع المفتاح في كود المتصفح.','Create a Push connection in My stores. Store the one-time key on the store server and send batches of up to 100 products. Never put the key in browser code.') }}</p><div class="codebox"><code>{{ url('/api/integrations/v1/products/batch') }}</code><button type="button" class="button secondary" data-copy-value="{{ url('/api/integrations/v1/products/batch') }}">{{ $t('نسخ الرابط','Copy URL') }}</button></div><pre>POST /api/integrations/v1/products/batch
Authorization: Bearer YOUR_RASLO_KEY
Content-Type: application/json

{"products":[{"external_id":"sku-001","name":"Product","price_minor":14900,"currency":"AED","stock_quantity":4,"status":"active","source_updated_at":"CURRENT_RFC3339_TIMESTAMP"}]}</pre><p class="muted">{{ $t('استبدل الوقت بوقت المصدر الفعلي بصيغة RFC3339 وبثوانٍ كاملة؛ لا ترسل وقتًا مستقبليًا. 14900 فلس = 149 درهمًا.','Replace the timestamp with the real source RFC3339 time at whole-second precision, never in the future. 14900 fils = AED 149.') }}</p></section>
        <section class="panel"><h2>Feed</h2><p class="muted">{{ $t('قدّم رابط HTTPS مع رمز Bearer خاص، يعيد products وnext_cursor. حتى 100 سجل في الصفحة، مع صفحات محدودة الحجم. احصل على موافقة نطاق المصدر من مدير رسلو.','Expose an HTTPS endpoint with a private Bearer token returning products and next_cursor. Up to 100 records per bounded-size page. Ask the Raslo operator to approve the source hostname.') }}</p><pre>{"products":[],"next_cursor":null}</pre><p class="muted">{{ $t('مثال بنية استجابة فارغة. السجلات الفعلية تستخدم نفس حقول منتجات Push. أرسل الحذف كحالة archived بوقت أحدث، ولا تعتبر الصفحة الجزئية قائمة حذف.','Example empty response structure. Actual records use the same product fields as Push. Send deletions as archived records with a newer timestamp; a partial page is not a deletion list.') }}</p><a class="button secondary space" href="{{ route('merchant.catalog.index') }}">{{ $t('إدارة اتصالاتي','Manage my connections') }}</a></section></div>
        <p id="integration-copy-status" class="muted" role="status" aria-live="polite"></p>
        @include('catalog.partials.scripts')
    @endif
</div>
@endsection

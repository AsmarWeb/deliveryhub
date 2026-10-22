<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#123D35">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="description" content="{{ $description }}">
    <meta name="keywords" content="{{ $isAr ? 'منصة توصيل, إدارة التوصيل, تطبيق سائق, تتبع الطلبات, إثبات التسليم, توصيل الشركات, رسلو' : 'Raslo, B2B delivery management, delivery dispatch software, driver app, proof of delivery, open source logistics' }}">
    <meta name="author" content="Raslo">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="alternate" hreflang="en" href="{{ url('/') }}">
    <link rel="alternate" hreflang="ar" href="{{ url('/?lang=ar') }}">
    <link rel="alternate" hreflang="x-default" href="{{ url('/') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Raslo">
    <meta property="og:locale" content="{{ $isAr ? 'ar_AE' : 'en_US' }}">
    <meta property="og:locale:alternate" content="{{ $isAr ? 'en_US' : 'ar_AE' }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ asset('brand/raslo-social.png') }}">
    <meta property="og:image:secure_url" content="{{ asset('brand/raslo-social.png') }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Raslo — Open-source B2B delivery platform">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ asset('brand/raslo-social.png') }}">
    <title>{{ $title }}</title>
    <link rel="icon" href="{{ asset('brand/raslo-icon.png') }}" type="image/png">
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="icon" href="{{ asset('brand/raslo-mark.svg') }}" type="image/svg+xml"><link rel="stylesheet" href="{{ asset('brand/raslo.css') }}">
</head>
<body class="brand-shell">
@php
 $t = fn($ar,$en) => $isAr ? $ar : $en;
 $emirates = ['abu_dhabi'=>'أبوظبي','dubai'=>'دبي','sharjah'=>'الشارقة','ajman'=>'عجمان','umm_al_quwain'=>'أم القيوين','ras_al_khaimah'=>'رأس الخيمة','fujairah'=>'الفجيرة'];
 $emiratesEn = ['abu_dhabi'=>'Abu Dhabi','dubai'=>'Dubai','sharjah'=>'Sharjah','ajman'=>'Ajman','umm_al_quwain'=>'Umm Al Quwain','ras_al_khaimah'=>'Ras Al Khaimah','fujairah'=>'Fujairah'];
 $businesses = ['ecommerce'=>['تجارة إلكترونية','E-commerce'],'retail'=>['متجر تجزئة','Retail'],'restaurant'=>['مطاعم وأغذية','Restaurants & food'],'wholesale'=>['تجارة جملة','Wholesale'],'logistics'=>['لوجستيات','Logistics'],'other'=>['نشاط آخر','Other']];
@endphp
<a class="skip" href="#main">{{ $t('تجاوز إلى المحتوى','Skip to content') }}</a>
<header class="brand-nav brand-container">
 <a class="brand-link" href="{{ route('home') }}">@include('components.brand')</a>
 <nav aria-label="{{ $t('القائمة الرئيسية','Main navigation') }}"><a href="{{ route('catalog.index') }}">{{ $t('اكتشف المتاجر','Discover stores') }}</a><a href="#benefits">{{ $t('لأعمالك','For your business') }}</a><a href="#how-it-works">{{ $t('كيف نعمل','How it works') }}</a></nav>
 <div class="brand-nav-actions"><a href="{{ $isAr ? url('/') : url('/?lang=ar') }}" lang="{{ $isAr ? 'en' : 'ar' }}">{{ $isAr ? 'EN' : 'العربية' }}</a><a class="brand-button" href="{{ route('login') }}">{{ $t('دخول الشركاء','Partner sign in') }}</a></div>
</header>
<main id="main">
 <section class="brand-hero brand-container">
  <div><span class="brand-eyebrow">{{ $t('من الإمارات، لأعمالك','FOR BUSINESS IN THE EMIRATES') }}</span><h1>{{ $t('طموح متجرك.','Your ambition.') }}<br><em>{{ $t('وجهتنا القادمة.','Our next destination.') }}</em></h1><p>{{ $t('رسلو تجمع متجرك ومنتجاتك وعمليات التوصيل في تجربة واحدة. مساحة لتنمية أعمالك، ورؤية أوضح لكل خطوة على الطريق.','Bring your store, products and delivery operations together. Raslo gives your business room to grow, with a clearer view of every step along the way.') }}</p><div class="brand-actions"><a class="brand-button" href="#join">{{ $t('انضم كتاجر','Become a merchant') }} <span aria-hidden="true">↗</span></a><a class="brand-button secondary" href="{{ route('catalog.index') }}">{{ $t('اكتشف المنتجات','Discover products') }}</a></div><p class="brand-note">{{ $t('تجربة بالعربية والإنجليزية · أسعار بالدرهم الإماراتي','Arabic & English · Prices in UAE dirhams') }}</p></div>
  <div class="brand-scene" role="img" aria-label="{{ $t('رسم توضيحي لمسار التوصيل بين المتجر والعميل','Illustration of a delivery route from store to customer') }}">
   <div class="scene-heading"><strong>{{ $t('كل خطوة، أقرب.','Every step, closer.') }}</strong><span>RASLO / UAE</span></div>
   <svg class="route-art" viewBox="0 0 400 235" aria-hidden="true"><path d="M62 170C62 65 180 220 210 115S345 50 345 50"/><circle cx="62" cy="170" r="10"/><circle cx="210" cy="115" r="7"/><circle cx="345" cy="50" r="10"/><text x="62" y="205" text-anchor="middle">{{ $t('المتجر','Store') }}</text><text x="330" y="25" text-anchor="middle">{{ $t('العميل','Customer') }}</text></svg>
   <div class="scene-card"><span class="package" aria-hidden="true">↗</span><div><strong>{{ $t('من متجرك إلى باب عميلك','From your store to their door') }}</strong><small>{{ $t('منتجات · طلبات توصيل · متابعة','Products · Deliveries · Visibility') }}</small></div></div><div class="scene-caption">{{ $t('تصوّر توضيحي للتجربة؛ لا يعرض طلبًا فعليًا','Experience illustration; not a live delivery') }}</div>
  </div>
 </section>
 <div class="brand-container brand-strip"><span>{{ $t('متجرك، بحضور أجمل','A better home for your store') }}</span><span>{{ $t('إدارة توصيل أكثر وضوحًا','Clarity for every delivery') }}</span><span>{{ $t('تجربة ثنائية اللغة','A bilingual experience') }}</span></div>
 <section class="brand-section brand-container" id="benefits"><span class="brand-eyebrow">{{ $t('مساحة لطموحك','MADE FOR YOUR NEXT CHAPTER') }}</span><h2>{{ $t('تجارتك تتحرك. رسلو معك.','Your business moves. Raslo moves with it.') }}</h2><div class="brand-grid">
 @foreach([['01','للتجار','For merchants','اعرض منتجاتك وأدر طلبات التوصيل من مساحة عمل واحدة. مزامنة الكتالوج متاحة ضمن Enterprise.','Showcase products and manage deliveries from one workspace. Catalog synchronization is available with Enterprise.'],['02','للمتسوقين','For shoppers','اكتشف منتجات المتاجر المنشورة، واطّلع على تفاصيلها ثم أكمل الشراء لدى المتجر.','Explore published store products, discover the details and continue your purchase with the store.'],['03','لشركاء التوصيل','For delivery partners','تابع المهام وحدّث حالة الطلب ووثّق التسليم من التطبيق.','Follow assignments, update delivery statuses and capture proof of delivery in the app.']] as $f)
 <article class="brand-card"><span class="number">{{ $f[0] }}</span><h3>{{ $t($f[1],$f[2]) }}</h3><p>{{ $t($f[3],$f[4]) }}</p></article>@endforeach
 </div></section>
 <section class="brand-section brand-container" id="how-it-works"><span class="brand-eyebrow">{{ $t('خطوات واضحة','A CLEAR WAY FORWARD') }}</span><h2>{{ $t('ابدأ بأعمالك. نرتّب الباقي معك.','Start with your business. Build the next steps with us.') }}</h2><div class="brand-grid">@foreach([['01','عرّفنا بمتجرك','Introduce your store','أرسل بيانات نشاطك واحتياجات التوصيل.','Share your business and delivery requirements.'],['02','حدّد طريقة العمل','Find your workflow','نناقش التوافر والتكامل والشروط المناسبة.','Discuss availability, integration and suitable terms.'],['03','أدر التجربة','Manage the experience','تابع المنتجات والتوصيلات من مساحة عملك بعد التفعيل.','Follow products and deliveries in your workspace after activation.']] as $f)<article class="brand-card"><span class="number">{{ $f[0] }}</span><h3>{{ $t($f[1],$f[2]) }}</h3><p>{{ $t($f[3],$f[4]) }}</p></article>@endforeach</div></section>
 <section class="brand-section brand-container" id="join"><div class="join-grid"><div class="join-copy"><div class="brand-eyebrow">{{ $t('انضم إلى رسلو','Join Raslo') }}</div><h2>{{ $t('هل شركتك جاهزة لتطوير إدارة التوصيل؟','Ready to improve your delivery workflow?') }}</h2><p>{{ $t('اترك بيانات نشاطك التجاري وسنتواصل معك لمناقشة كيفية الاستفادة من المنصة. هذا النموذج لاستقبال طلبات الشركات والتجار في الإمارات وليس لإنشاء حساب تلقائي.','Tell us about your business. We will reach out to discuss whether Raslo fits your needs. This is an enquiry, not automatic account creation.') }}</p><ul><li>{{ $t('للشركات والمتاجر والمطاعم والتجارة الإلكترونية.','For businesses, stores, restaurants and e-commerce.') }}</li><li>{{ $t('لجميع إمارات الدولة.','Across all seven UAE emirates.') }}</li><li>{{ $t('لا يلزم تحميل التطبيق لتقديم الطلب.','No app download required to enquire.') }}</li></ul><div class="region">@foreach(($isAr ? $emirates : $emiratesEn) as $em)<span>{{ $em }}</span>@endforeach</div></div>
 <form class="lead-form" action="{{ route('merchant-leads.store') }}" method="POST" accept-charset="UTF-8">
 @csrf
 <h3>{{ $t('طلب انضمام شركة أو تاجر','Business partnership enquiry') }}</h3><p class="sub">{{ $t('الحقول المشار إليها بـ * مطلوبة.','Fields marked * are required.') }}</p>
 @if(session('lead_success'))<div class="notice" role="status">{{ $t('تم استلام طلبك بنجاح. سنتواصل معك عند مراجعة البيانات.','Your enquiry was received. We will contact you after review.') }}</div>@endif
 @if($errors->any())<div class="notice" style="border-color:#dd8c8c;background:#40252e" role="alert">{{ $t('يرجى مراجعة البيانات المدخلة.','Please correct the highlighted fields.') }}</div>@endif
 <input type="hidden" name="preferred_language" value="{{ $locale }}"><div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
 <div class="fields">
 <div class="field wide"><label for="company_name">{{ $t('اسم الشركة / المتجر','Company / store name') }} *</label><input id="company_name" name="company_name" required maxlength="150" autocomplete="organization" value="{{ old('company_name') }}">@error('company_name')<span class="err">{{ $message }}</span>@enderror</div>
 <div class="field"><label for="contact_name">{{ $t('اسم مسؤول التواصل','Contact person') }} *</label><input id="contact_name" name="contact_name" required maxlength="150" autocomplete="name" value="{{ old('contact_name') }}">@error('contact_name')<span class="err">{{ $message }}</span>@enderror</div>
 <div class="field"><label for="phone">{{ $t('رقم الهاتف / واتساب','Phone / WhatsApp') }} *</label><input id="phone" name="phone" type="tel" inputmode="tel" required maxlength="30" placeholder="+971 5X XXX XXXX" autocomplete="tel" value="{{ old('phone') }}">@error('phone')<span class="err">{{ $message }}</span>@enderror</div>
 <div class="field wide"><label for="email">{{ $t('البريد الإلكتروني للعمل','Business email') }} *</label><input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="{{ old('email') }}">@error('email')<span class="err">{{ $message }}</span>@enderror</div>
 <div class="field"><label for="emirate">{{ $t('الإمارة','Emirate') }} *</label><select id="emirate" name="emirate" required><option value="">{{ $t('اختر الإمارة','Select emirate') }}</option>@foreach($emirates as $key=>$ar)<option value="{{ $key }}" @selected(old('emirate')===$key)>{{ $isAr ? $ar : $emiratesEn[$key] }}</option>@endforeach</select>@error('emirate')<span class="err">{{ $message }}</span>@enderror</div>
 <div class="field"><label for="business_type">{{ $t('نوع النشاط','Business type') }} *</label><select id="business_type" name="business_type" required><option value="">{{ $t('اختر النشاط','Select type') }}</option>@foreach($businesses as $key=>$labels)<option value="{{ $key }}" @selected(old('business_type')===$key)>{{ $labels[$isAr ? 0 : 1] }}</option>@endforeach</select>@error('business_type')<span class="err">{{ $message }}</span>@enderror</div>
 <div class="field wide"><label for="monthly_deliveries">{{ $t('عدد التوصيلات المتوقع شهرياً','Estimated monthly deliveries') }}</label><select id="monthly_deliveries" name="monthly_deliveries"><option value="">{{ $t('غير محدد','Not sure yet') }}</option>@foreach(['0_50'=>'0–50','51_200'=>'51–200','201_1000'=>'201–1,000','1000_plus'=>'1,000+'] as $key=>$label)<option value="{{ $key }}" @selected(old('monthly_deliveries')===$key)>{{ $label }}</option>@endforeach</select></div>
 <div class="field wide"><label for="message">{{ $t('كيف يمكن أن نساعد نشاطك؟','How can we help your business?') }}</label><textarea id="message" name="message" maxlength="2000" placeholder="{{ $t('أخبرنا عن احتياجاتك وتحديات التوصيل الحالية...','Tell us about your delivery challenges and needs...') }}">{{ old('message') }}</textarea>@error('message')<span class="err">{{ $message }}</span>@enderror</div>
 </div>
 <label class="consent"><input type="checkbox" name="consent" value="1" required {{ old('consent') ? 'checked' : '' }}><span>{{ $t('أوافق على استخدام البيانات المقدمة للتواصل معي بخصوص طلب الانضمام. لن تُنشر معلوماتي علناً.','I consent to the use of my details to contact me about this enquiry. My information will not be published publicly.') }} *</span></label>@error('consent')<span class="err">{{ $message }}</span>@enderror
 <button class="btn" type="submit">{{ $t('إرسال طلب الانضمام ←','Send partnership enquiry →') }}</button><p class="footnote">{{ $t('لا تُدخل كلمات مرور أو بيانات عملاء في هذا النموذج.','Do not include passwords or customer data in this form.') }}</p>
 </form></div></section>

 <section class="brand-section brand-container" id="faq"><div class="center"><div class="brand-eyebrow">FAQ</div><h2>{{ $t('أسئلة يطرحها التجار والشركات','Frequently asked questions') }}</h2><p class="intro">{{ $t('إجابات واضحة قبل أن تقرر التواصل معنا.','Clear answers before you get in touch.') }}</p></div><div class="faq-list">@foreach($faqs as $faq)<details><summary>{{ $faq['q'] }}</summary><p>{{ $faq['a'] }}</p></details>@endforeach</div></section>

 <section class="brand-section brand-container" id="mobile"><div class="brand-download"><span class="brand-eyebrow">{{ $t('رسلو، أينما كنت','RASLO, WHEREVER YOU GO') }}</span><h2>{{ $t('من المكتب إلى الطريق.','From your desk to the road.') }}</h2><p>{{ $t('تابع أعمالك ومهام التوصيل عبر تطبيق رسلو.','Keep your business and delivery assignments in view with the Raslo app.') }}</p><div class="brand-actions"><a class="brand-button gold" href="{{ $apkUrl ?: $releasesUrl }}" rel="noopener noreferrer">{{ $apkUrl ? $t('تحميل Android','Download Android') : $t('استعرض إصدارات التطبيق','Explore app releases') }}</a><a class="brand-button gold" href="https://github.com/AsmarWeb/deliveryhub">{{ $t('المشروع المفتوح المصدر','Open-source project') }}</a></div></div></section>
</main><footer class="brand-footer brand-container">@include('components.brand')<span>© {{ date('Y') }} {{ $t('رسلو · الإمارات العربية المتحدة','Raslo · United Arab Emirates') }}</span><a href="#faq">{{ $t('الأسئلة الشائعة','Frequently asked questions') }}</a></footer>
</body></html>

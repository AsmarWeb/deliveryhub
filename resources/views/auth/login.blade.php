@php
 $isAr = request('lang', app()->getLocale()) === 'ar';
 app()->setLocale($isAr ? 'ar' : 'en');
 $t = fn($ar, $en) => $isAr ? $ar : $en;
@endphp
<!DOCTYPE html><html lang="{{ $isAr ? 'ar' : 'en' }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#123D35"><title>{{ $t('تسجيل الدخول | رسلو','Sign in | Raslo') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="icon" href="{{ asset('brand/raslo-mark.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('brand/raslo.css') }}"></head>
<body class="brand-shell"><main class="auth-layout"><section class="auth-story"><a class="brand-link" href="{{ route('home') }}">@include('components.brand')</a><div><span class="brand-eyebrow" style="color:#D9B875">{{ $t('مساحة عملك، بطموحك','YOUR WORKSPACE, YOUR AMBITION') }}</span><h1>{{ $t('كل يوم، خطوة أقرب لأعمالك.','Every day, a step closer to your business.') }}</h1><p>{{ $t('منتجاتك وتوصيلاتك ومتابعة أعمالك، من مكان واحد.','Your products, deliveries and business visibility, together.') }}</p></div><span>{{ $t('رسلو · الإمارات العربية المتحدة','Raslo · United Arab Emirates') }}</span></section>
<section class="auth-form"><a class="language" href="{{ request()->fullUrlWithQuery(['lang'=>$isAr ? 'en' : 'ar']) }}">{{ $isAr ? 'EN' : 'العربية' }}</a><h2>{{ $t('أهلًا بعودتك','Welcome back') }}</h2><p class="brand-note">{{ $t('سجّل دخولك إلى مساحة عملك في رسلو.','Sign in to your Raslo workspace.') }}</p>
@if($errors->any())<div class="notice" role="alert">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.store') }}">@csrf
<label for="email">{{ $t('البريد الإلكتروني','Email address') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" inputmode="email">
<label for="password">{{ $t('كلمة المرور','Password') }}</label><input id="password" type="password" name="password" required autocomplete="current-password">
<button type="submit" class="brand-button">{{ $t('تسجيل الدخول','Sign in') }}</button></form><a class="auth-home" href="{{ $isAr ? url('/?lang=ar') : route('home') }}">{{ $t('العودة إلى الرئيسية','Back to home') }}</a></section></main></body></html>

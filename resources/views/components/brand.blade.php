@php($brandArabic = app()->getLocale() === 'ar')
<span class="raslo-wordmark" aria-label="{{ $brandArabic ? 'رسلو' : 'Raslo' }}">
    <img src="{{ $brandMarkUrl ?? asset('brand/raslo-mark.svg') }}" width="42" height="42" alt="" aria-hidden="true">
    <span>{{ $brandArabic ? 'رسلو' : 'Raslo' }}<small>{{ $brandArabic ? 'أقرب لأعمالك' : 'Closer to your business' }}</small></span>
</span>

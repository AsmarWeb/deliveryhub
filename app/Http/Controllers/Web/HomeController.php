<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $releasesUrl = 'https://github.com/AsmarWeb/deliveryhub-mobile/releases';
        $apkUrl = Cache::remember('deliveryhub.mobile.latest_apk', now()->addMinutes(15), function () {
            try {
                $response = Http::timeout(3)
                    ->withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'DeliveryHub-Website'])
                    ->get('https://api.github.com/repos/AsmarWeb/deliveryhub-mobile/releases/latest');
                if (! $response->successful()) {
                    return null;
                }
                foreach ($response->json('assets', []) as $asset) {
                    if (str_ends_with(strtolower($asset['name'] ?? ''), '.apk')) {
                        return $asset['browser_download_url'] ?? null;
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
            return null;
        });

        $locale = $request->query('lang') === 'ar' ? 'ar' : 'en';
        $isAr = $locale === 'ar';
        app()->setLocale($locale);

        $title = $isAr
            ? 'DeliveryHub الإمارات | انضم كشركة أو تاجر لإدارة التوصيل'
            : 'DeliveryHub UAE | Join as a Merchant or Business';
        $description = $isAr
            ? 'انضم إلى DeliveryHub في الإمارات: سجّل اهتمام شركتك أو متجرك لإدارة طلبات التوصيل والسائقين وتتبع العمليات وإثبات التسليم من منصة واحدة.'
            : 'Join DeliveryHub in the UAE. Register your business interest in delivery management, driver assignments, tracking and proof of delivery on one platform.';
        $canonicalUrl = $isAr ? url('/?lang=ar') : url('/');

        $structuredData = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => url('/').'#organization',
                    'name' => 'DeliveryHub',
                    'url' => url('/'),
                    'logo' => asset('images/deliveryhub-icon.png'),
                    'sameAs' => [
                        'https://github.com/AsmarWeb/deliveryhub',
                        'https://github.com/AsmarWeb/deliveryhub-mobile',
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => url('/').'#website',
                    'url' => url('/'),
                    'name' => 'DeliveryHub',
                    'inLanguage' => $locale,
                    'publisher' => ['@id' => url('/').'#organization'],
                ],
                [
                    '@type' => 'SoftwareApplication',
                    'name' => 'DeliveryHub',
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web, Android',
                    'description' => $description,
                    'url' => $canonicalUrl,
                    'isAccessibleForFree' => true,
                    'downloadUrl' => $apkUrl ?: $releasesUrl,
                ],
            ],
        ];

        $faqs = $isAr ? [
            ['q' => 'هل يمكن للشركات والمتاجر في الإمارات طلب الانضمام؟', 'a' => 'نعم. أرسل بيانات شركتك ونوع نشاطك وحجم الطلبات المتوقع، وسنتواصل معك لمناقشة الملاءمة والخطوات التالية.'],
            ['q' => 'ما الفائدة التي سأحصل عليها؟', 'a' => 'تنظيم الطلبات وإسنادها للسائقين ومتابعة الحالات وتوثيق التسليم في نظام واحد بدلاً من المتابعة اليدوية المتفرقة.'],
            ['q' => 'هل لديكم أسعار أو باقات ثابتة؟', 'a' => 'لا نعلن حالياً عن أسعار أو باقات نهائية. تُناقش التفاصيل حسب احتياج كل نشاط ومدى جاهزية الخدمة.'],
            ['q' => 'هل التسجيل يعني تفعيل الحساب فوراً؟', 'a' => 'لا. النموذج هو طلب اهتمام أو شراكة، وليس وعداً بالتفعيل الفوري أو توفير أسطول توصيل.'],
            ['q' => 'هل تدعم المنصة تطبيقاً للسائقين؟', 'a' => 'يوجد مشروع تطبيق للسائقين، ويعتمد توفر نسخة قابلة للتنزيل على نشر إصدار Android رسمي.'],
        ] : [
            ['q' => 'Can UAE merchants and companies request to join?', 'a' => 'Yes. Share your business details and estimated delivery volume. We will contact you to discuss fit and next steps.'],
            ['q' => 'What can my business gain?', 'a' => 'Manage deliveries, driver assignments, status updates and proof of delivery in one workflow rather than scattered manual follow-ups.'],
            ['q' => 'Do you have fixed pricing plans?', 'a' => 'No final public plans or prices are advertised at this stage. Commercial terms depend on requirements and service readiness.'],
            ['q' => 'Does submitting the form activate my account?', 'a' => 'No. This is an expression of interest, not a guarantee of immediate activation or access to a delivery fleet.'],
            ['q' => 'Is there a driver mobile app?', 'a' => 'A driver app project exists. An installable Android build is available only when an official release is published.'],
        ];
        $structuredData['@graph'][] = [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($faq) => [
                '@type' => 'Question', 'name' => $faq['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
            ], $faqs),
        ];

        return view('home', compact(
            'apkUrl', 'releasesUrl', 'locale', 'isAr',
            'title', 'description', 'canonicalUrl', 'structuredData', 'faqs'
        ));
    }
}

<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationDeliveryOrder;
use Illuminate\Http\Request;

class PublicDeliveryTrackingController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $order = IntegrationDeliveryOrder::with('delivery')->where('tracking_hash', hash('sha256', $token))
            ->where('created_at', '>=', now()->subDays(config('delivery_integrations.tracking_days')))->firstOrFail();
        $locale = $request->query('lang') === 'en' ? 'en' : 'ar';
        app()->setLocale($locale);

        return response()->view('tracking.public', ['delivery' => $order->delivery, 'ar' => $locale === 'ar'])
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}

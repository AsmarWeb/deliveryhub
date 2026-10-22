<?php

namespace App\Services\Catalog;

use App\Models\Merchant;
use App\Services\Billing\MerchantSubscriptionAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class EnterpriseCatalogAccess
{
    public function allows(Merchant $merchant): bool
    {
        if ($merchant->status !== 'active') {
            return false;
        }
        $subscription = app(MerchantSubscriptionAccess::class)->current($merchant, true);

        return $subscription?->plan?->slug === 'enterprise' && (float) $subscription->plan->monthly_price > 0;
    }

    public function assert(Merchant $merchant): void
    {
        abort_unless($this->allows($merchant), 403, 'A confirmed paid Enterprise subscription is required.');
    }

    private function paidSubscriptions(): Builder
    {
        return DB::table('merchant_subscriptions as ms')
            ->join('subscription_plans as sp', 'sp.id', '=', 'ms.subscription_plan_id')
            ->join('subscriptions as cs', 'cs.id', '=', 'ms.cashier_subscription_id')
            ->where('ms.status', 'active')->where('sp.is_active', true)
            ->where('ms.starts_at', '<=', now())->where('ms.ends_at', '>', now())
            ->whereIn('cs.stripe_status', ['active', 'past_due', 'canceled']);
    }

    // Match the most recent currently paid subscription, including a later downgrade.
    public function eligibleMerchants()
    {
        $current = $this->paidSubscriptions()->select('ms.id')
            ->whereColumn('ms.merchant_id', 'merchants.id')->orderByDesc('ms.starts_at')->orderByDesc('ms.id')->limit(1);

        return Merchant::query()->where('merchants.status', 'active')->whereExists(
            $this->paidSubscriptions()->selectRaw('1')->whereColumn('ms.merchant_id', 'merchants.id')
                ->where('sp.slug', 'enterprise')->where('sp.monthly_price', '>', 0)->where('ms.id', '=', $current)
        );
    }
}

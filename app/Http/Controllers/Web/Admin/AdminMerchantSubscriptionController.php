<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\MerchantSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminMerchantSubscriptionController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['active', 'cancelled', 'expired'])],
            'source' => ['nullable', Rule::in(['stripe', 'manual'])],
        ]);

        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? '';
        $source = $filters['source'] ?? '';

        $subscriptions = MerchantSubscription::query()
            ->with(['merchant.user', 'plan', 'cashierSubscription'])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('merchant', function ($merchantQuery) use ($search) {
                    $merchantQuery
                        ->where('business_name', 'like', '%' . $search . '%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('email', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($status === 'active', function ($query) {
                $query->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>', now());
            })
            ->when($status === 'cancelled', function ($query) {
                $query->where('status', 'cancelled');
            })
            ->when($status === 'expired', function ($query) {
                $query->where('ends_at', '<=', now())
                    ->where('status', '!=', 'cancelled');
            })
            ->when($source === 'stripe', function ($query) {
                $query->whereNotNull('cashier_subscription_id');
            })
            ->when($source === 'manual', function ($query) {
                $query->whereNull('cashier_subscription_id');
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $merchants = Merchant::query()
            ->with('user:id,name,email')
            ->orderBy('business_name')
            ->get();

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('slug', '!=', 'enterprise')
            ->orderBy('monthly_price')
            ->get();

        return view('admin.subscriptions.index', compact(
            'subscriptions',
            'merchants',
            'plans'
        ));
    }

    public function store(
        Request $request,
        MerchantSubscriptionService $service
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'merchant_id' => [
                'required',
                'integer',
                Rule::exists('merchants', 'id'),
            ],
            'subscription_plan_id' => [
                'required',
                'integer',
                Rule::exists('subscription_plans', 'id'),
            ],
            'months' => ['required', 'integer', 'between:1,12'],
        ]);

        $merchant = Merchant::findOrFail($data['merchant_id']);
        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);

        $service->activate(
            $merchant,
            $plan,
            (int) $data['months']
        );

        return redirect()
            ->route('admin.subscriptions.index')
            ->with('success', 'Merchant subscription activated successfully.');
    }
}

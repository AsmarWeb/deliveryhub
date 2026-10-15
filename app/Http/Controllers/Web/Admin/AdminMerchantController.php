<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminMerchantController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole('admin'),
            403
        );
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        $merchants = Merchant::query()
            ->with('user:id,name,email')
            ->withCount(['deliveries', 'addresses'])
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('business_name', 'like', "%{$search}%")
                            ->orWhere('business_email', 'like', "%{$search}%")
                            ->orWhere('business_phone', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($user) use ($search) {
                                $user->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
                }
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) =>
                    $query->where('status', $status)
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.merchants.index', compact('merchants'));
    }

    public function show(
        Request $request,
        Merchant $merchant
    ): View {
        $this->authorizeAdmin($request);

        $merchant->load('user')
            ->loadCount(['deliveries', 'addresses']);

        $recentDeliveries = $merchant->deliveries()
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.merchants.show', [
            'merchant' => $merchant,
            'recentDeliveries' => $recentDeliveries,
        ]);
    }

    public function updateStatus(
        Request $request,
        Merchant $merchant
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        DB::transaction(function () use ($merchant, $data) {
            $merchant = Merchant::query()
                ->lockForUpdate()
                ->findOrFail($merchant->id);

            if ($data['status'] === 'inactive') {
                $hasOngoingDeliveries = $merchant->deliveries()
                    ->whereNotIn('status', [
                        'completed',
                        'cancelled',
                    ])
                    ->exists();

                if ($hasOngoingDeliveries) {
                    throw ValidationException::withMessages([
                        'status' => 'Merchant has ongoing deliveries.',
                    ]);
                }
            }

            $merchant->update([
                'status' => $data['status'],
            ]);
        });

        return redirect()
            ->route('admin.merchants.index')
            ->with('success', 'Merchant status updated successfully.');
    }
}

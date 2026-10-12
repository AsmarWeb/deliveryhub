<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Merchant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantAddressController extends Controller
{
    private function merchant(Request $request): Merchant
    {
        abort_unless($request->user()->hasRole('merchant'), 403);

        return Merchant::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function ownedAddress(
        Merchant $merchant,
        Address $address
    ): Address {
        abort_unless(
            (int) $address->merchant_id === (int) $merchant->id,
            404
        );

        return $address;
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'country' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'address_line' => ['required', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_instructions' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    public function index(Request $request): View
    {
        $merchant = $this->merchant($request);

        $addresses = $merchant->addresses()
            ->withCount(['pickupDeliveries', 'dropoffDeliveries'])
            ->latest()
            ->paginate(12);

        return view('merchant.addresses.index', compact('addresses'));
    }

    public function create(Request $request): View
    {
        $this->merchant($request);

        return view('merchant.addresses.form', [
            'address' => new Address(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $merchant = $this->merchant($request);

        $data = $this->validatedData($request);

        $merchant->addresses()->create($data);

        return redirect()
            ->route('merchant.addresses.index')
            ->with('success', __('addresses.created_successfully'));
    }

    public function edit(Request $request, Address $address): View
    {
        $merchant = $this->merchant($request);

        $this->ownedAddress($merchant, $address);

        return view('merchant.addresses.form', compact('address'));
    }

    public function update(
        Request $request,
        Address $address
    ): RedirectResponse {
        $merchant = $this->merchant($request);

        $this->ownedAddress($merchant, $address);

        $address->update($this->validatedData($request));

        return redirect()
            ->route('merchant.addresses.index')
            ->with('success', __('addresses.updated_successfully'));
    }

    public function destroy(
        Request $request,
        Address $address
    ): RedirectResponse {
        $merchant = $this->merchant($request);

        $this->ownedAddress($merchant, $address);

        if (
            $address->pickupDeliveries()->exists() ||
            $address->dropoffDeliveries()->exists()
        ) {
            return redirect()
                ->route('merchant.addresses.index')
                ->with('error', __('addresses.cannot_delete_used'));
        }

        $address->delete();

        return redirect()
            ->route('merchant.addresses.index')
            ->with('success', __('addresses.deleted_successfully'));
    }
}

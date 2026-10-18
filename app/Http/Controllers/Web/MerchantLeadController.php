<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MerchantLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\View\View;

class MerchantLeadController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Honeypot: ignore automated submissions without revealing detection.
        if ($request->filled('website')) {
            return redirect()->to(url('/').'?lang='.($request->input('preferred_language') === 'en' ? 'en' : 'ar').'#join')
                ->with('lead_success', true);
        }

        $data = $request->validate([
            'company_name' => ['required','string','max:150'],
            'contact_name' => ['required','string','max:150'],
            'email' => ['required','email','max:190'],
            'phone' => ['required','regex:/^\+?[0-9\s()\-]{7,25}$/'],
            'emirate' => ['required','in:abu_dhabi,dubai,sharjah,ajman,umm_al_quwain,ras_al_khaimah,fujairah'],
            'business_type' => ['required','in:ecommerce,retail,restaurant,wholesale,logistics,other'],
            'monthly_deliveries' => ['nullable','in:0_50,51_200,201_1000,1000_plus'],
            'message' => ['nullable','string','max:2000'],
            'preferred_language' => ['required','in:ar,en'],
            'consent' => ['accepted'],
        ]);
        unset($data['consent']);
        $lead = MerchantLead::create($data);

        // Optional notification: lead remains stored even when email delivery fails.
        try {
            $recipient = config('services.deliveryhub.leads_email');
            if ($recipient) {
                Mail::raw(
                    "New DeliveryHub merchant lead #{$lead->id}\nCompany: {$lead->company_name}\nContact: {$lead->contact_name}\nEmail: {$lead->email}\nPhone: {$lead->phone}\nEmirate: {$lead->emirate}\nBusiness: {$lead->business_type}\nVolume: {$lead->monthly_deliveries}\nMessage: {$lead->message}",
                    fn ($mail) => $mail->to($recipient)->subject('DeliveryHub | New UAE merchant lead #'.$lead->id)
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Merchant lead email notification failed', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
        }

        return redirect()->to(url('/').'?lang='.$data['preferred_language'].'#join')
            ->with('lead_success', true);
    }

    public function index(): View
    {
        return view('admin.merchant-leads.index', [
            'leads' => MerchantLead::latest()->paginate(25),
        ]);
    }
}

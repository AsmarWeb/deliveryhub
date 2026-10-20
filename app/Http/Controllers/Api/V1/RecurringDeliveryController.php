<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\AutomationSubscriptionPolicy;
use App\Models\Merchant;
use App\Models\RecurringDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringDeliveryController extends Controller
{
    private function merchant(Request $request): Merchant
    {
        abort_unless($request->user()->hasRole('merchant'), 403);

        return Merchant::where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function index(Request $request): JsonResponse
    {
        $merchant = $this->merchant($request);

        return response()->json(
            RecurringDelivery::query()
                ->where('merchant_id', $merchant->id)
                ->with(['pickupAddress', 'dropoffAddress'])
                ->latest()
                ->paginate(15)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $merchant = $this->merchant($request);

        app(AutomationSubscriptionPolicy::class)
            ->assertCanCreate($merchant);

        $data = $request->validate([
            'pickup_address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')
                    ->where('merchant_id', $merchant->id),
            ],
            'dropoff_address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')
                    ->where('merchant_id', $merchant->id),
            ],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'package_type' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'special_instructions' => ['nullable', 'string'],
            'frequency' => ['required', Rule::in(['daily', 'weekly'])],
            'timezone' => ['required', 'timezone'],
            'day_of_week' => [
                'nullable',
                'integer',
                'between:0,6',
                'required_if:frequency,weekly',
            ],
            'execution_time' => [
                'required',
                'date_format:H:i',
            ],
        ]);

        $timezone = $data['timezone'];
        $now = CarbonImmutable::now($timezone);

        [$hour, $minute] = array_map(
            'intval',
            explode(':', $data['execution_time'])
        );

        $next = $now->setTime($hour, $minute);

        if ($data['frequency'] === 'weekly') {
            $targetDay = (int) $data['day_of_week'];

            while ($next->dayOfWeek !== $targetDay) {
                $next = $next->addDay();
            }
        }

        if ($next->lessThanOrEqualTo($now)) {
            $next = $data['frequency'] === 'daily'
                ? $next->addDay()
                : $next->addWeek();
        }

        $recurring = RecurringDelivery::create([
            ...$data,
            'merchant_id' => $merchant->id,
            'next_run_at' => $next->utc(),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Recurring delivery created successfully.',
            'data' => $recurring,
        ], 201);
    }

    public function updateStatus(
        Request $request,
        RecurringDelivery $recurringDelivery
    ): JsonResponse {
        $merchant = $this->merchant($request);

        abort_unless(
            $recurringDelivery->merchant_id === $merchant->id,
            403
        );

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        if ($data['is_active'] && ! $recurringDelivery->is_active) {
            app(AutomationSubscriptionPolicy::class)
                ->assertCanCreate($merchant);

            $timezone = $recurringDelivery->timezone;
            $now = CarbonImmutable::now($timezone);

            [$hour, $minute] = array_map(
                'intval',
                explode(':', $recurringDelivery->execution_time)
            );

            $next = $now->setTime($hour, $minute);

            if ($recurringDelivery->frequency === 'weekly') {
                $targetDay = (int) $recurringDelivery->day_of_week;

                while ($next->dayOfWeek !== $targetDay) {
                    $next = $next->addDay();
                }
            }

            if ($next->lessThanOrEqualTo($now)) {
                $next = $recurringDelivery->frequency === 'daily'
                    ? $next->addDay()
                    : $next->addWeek();
            }

            $data['next_run_at'] = $next->utc();
        }

        $recurringDelivery->update($data);

        return response()->json([
            'message' => 'Recurring delivery updated successfully.',
            'data' => $recurringDelivery->fresh(),
        ]);
    }
}

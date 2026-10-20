<?php

namespace App\Services\Billing;

use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;

class StripeInvoicePeriod
{
    public function forPrice(array $invoice, string $price): ?array
    {
        $lines = $invoice['lines']['data'] ?? [];
        if ($invoice['lines']['has_more'] ?? false) {
            $lines = [];
            foreach (Cashier::stripe()->invoices->allLines($invoice['id'], ['limit' => 100])->autoPagingIterator() as $line) {
                $lines[] = $line->toArray();
            }
        }

        $period = null;
        foreach ($lines as $line) {
            $linePrice = $line['price']['id'] ?? $line['pricing']['price_details']['price'] ?? null;
            $subscriptionLine = ($line['type'] ?? null) === 'subscription'
                || ($line['parent']['type'] ?? null) === 'subscription_item_details';
            $start = $line['period']['start'] ?? null;
            $end = $line['period']['end'] ?? null;
            if (! $subscriptionLine || $linePrice !== $price || ($line['amount'] ?? -1) < 0
                || ! is_numeric($start) || ! is_numeric($end) || $end <= $start) {
                continue;
            }
            if ($period === null || $end > $period[1]->timestamp) {
                $period = [Carbon::createFromTimestampUTC((int) $start), Carbon::createFromTimestampUTC((int) $end)];
            }
        }

        return $period;
    }
}

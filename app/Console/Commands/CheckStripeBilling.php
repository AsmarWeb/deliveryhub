<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckStripeBilling extends Command
{
    protected $signature = 'billing:check';
    protected $description = 'Check Stripe billing configuration without displaying secrets';

    public function handle(): int
    {
        $checks = [
            'Stripe secret key configured' => str_starts_with((string) config('cashier.secret'), 'sk_'),
            'Webhook signing secret configured' => str_starts_with((string) config('cashier.webhook.secret'), 'whsec_'),
            'Growth price configured' => str_starts_with((string) config('services.stripe.prices.growth'), 'price_'),
            'Business price configured' => str_starts_with((string) config('services.stripe.prices.business'), 'price_'),
            'Plan prices are distinct' => config('services.stripe.prices.growth') !== config('services.stripe.prices.business'),
            'Application URL configured' => filter_var(config('app.url'), FILTER_VALIDATE_URL) !== false,
        ];
        foreach ($checks as $name => $ok) {
            $this->line(($ok ? 'OK: ' : 'MISSING: ') . $name);
        }
        $this->comment('Checks local configuration only; no payment or Stripe API request is made.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}

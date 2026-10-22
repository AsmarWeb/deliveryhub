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
        $enterprise = (string) config('services.stripe.prices.enterprise');
        if ($enterprise !== '') {
            $checks['Enterprise price configured'] = str_starts_with($enterprise, 'price_');
            $prices = array_filter(config('services.stripe.prices', []));
            $checks['Plan prices are distinct'] = count($prices) === count(array_unique($prices));
        } else {
            $this->comment('INFO: Enterprise price is not configured; Enterprise checkout is unavailable.');
        }
        if (in_array(config('queue.default'), ['sync', 'null', 'deferred', 'background'], true)) {
            $this->warn('WARNING: Use a durable asynchronous queue for billing webhook processing.');
        }
        foreach ($checks as $name => $ok) {
            $this->line(($ok ? 'OK: ' : 'MISSING: ').$name);
        }
        $this->comment('Checks local configuration only; no payment or Stripe API request is made.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}

<?php

namespace App\Services\Billing;

use RuntimeException;

class StripeSyncPending extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Stripe subscription synchronization is waiting for related state: '.$reason);
    }
}

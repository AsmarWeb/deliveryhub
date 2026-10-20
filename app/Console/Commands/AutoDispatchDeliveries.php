<?php
namespace App\Console\Commands;
use App\Services\Dispatch\AutoDispatcher;
use Illuminate\Console\Command;
class AutoDispatchDeliveries extends Command
{
    protected $signature = 'deliveries:auto-dispatch';
    protected $description = 'Offer matching deliveries to eligible available drivers and retry expired automatic offers';
    public function handle(AutoDispatcher $dispatcher): int
    {
        $counts = $dispatcher->run();
        $this->line(json_encode($counts));
        return $counts['failed'] ? self::FAILURE : self::SUCCESS;
    }
}

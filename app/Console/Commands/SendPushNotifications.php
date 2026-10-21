<?php
namespace App\Console\Commands;
use App\Services\Push\PushOutbox;
use Illuminate\Console\Command;
class SendPushNotifications extends Command
{
    protected $signature = 'notifications:send-push';
    protected $description = 'Send bounded Expo push outbox batches and check provider receipts';
    public function handle(PushOutbox $outbox): int
    {
        $this->line(json_encode($outbox->run()));
        return self::SUCCESS;
    }
}

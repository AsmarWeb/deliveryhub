<?php

namespace App\Http\Middleware;

use App\Models\CatalogConnection;
use App\Services\Catalog\EnterpriseCatalogAccess;
use Closure;
use Illuminate\Http\Request;

class AuthenticateDeliveryConnection
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(strlen($request->getContent()) > 65536, 413);
        $token = $request->bearerToken();
        abort_unless(is_string($token) && preg_match('/^raslo_delivery_[a-f0-9]{64}$/', $token), 401, 'Invalid delivery integration key.');
        $connection = CatalogConnection::with('merchant.user')->where('delivery_key_hash', hash('sha256', $token))->first();
        abort_unless($connection && $connection->delivery_enabled && $connection->status === 'active', 401, 'Invalid delivery integration key.');
        app(EnterpriseCatalogAccess::class)->assert($connection->merchant);
        abort_unless($connection->merchant->user->hasRole('merchant'), 403);
        $request->attributes->set('delivery_connection', $connection);

        return $next($request);
    }
}

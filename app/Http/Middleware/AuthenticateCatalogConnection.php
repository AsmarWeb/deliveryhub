<?php

namespace App\Http\Middleware;

use App\Models\CatalogConnection;
use App\Services\Catalog\EnterpriseCatalogAccess;
use Closure;
use Illuminate\Http\Request;

class AuthenticateCatalogConnection
{
    public function handle(Request $request, Closure $next, string $scope)
    {
        abort_if(strlen($request->getContent()) > 2097152, 413, 'Integration request is too large.');
        $token = $request->bearerToken();
        abort_unless(is_string($token) && strlen($token) >= 40 && strlen($token) <= 200, 401, 'Invalid integration key.');
        $connection = CatalogConnection::with('merchant')->where('key_hash', hash('sha256', $token))->first();
        abort_unless($connection && $connection->status === 'active', 401, 'Invalid integration key.');
        abort_unless(in_array($scope, $connection->scopes ?? [], true), 403, 'Integration key lacks the required scope.');
        app(EnterpriseCatalogAccess::class)->assert($connection->merchant);
        $request->attributes->set('catalog_connection', $connection);
        $connection->update(['last_used_at' => now()]);

        return $next($request);
    }
}

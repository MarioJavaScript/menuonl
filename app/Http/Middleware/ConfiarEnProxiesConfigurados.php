<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * Confía solo en los proxies listados en config('menuonl.trusted_proxies').
 *
 * A diferencia del middleware de Laravel, una lista vacía significa "ningún proxy" y nunca
 * cae en '*' (el original lo hace en hosts Forge/Vapor/Cloud cuando no hay lista).
 */
class ConfiarEnProxiesConfigurados extends TrustProxies
{
    protected function setTrustedProxyIpAddresses(Request $request): void
    {
        /** @var array<int, string> $proxies */
        $proxies = (array) config('menuonl.trusted_proxies', []);

        if ($proxies === []) {
            $this->setTrustedProxyIpAddressesToSpecificIps($request, []);

            return;
        }

        $this->setTrustedProxyIpAddressesToSpecificIps($request, $proxies);
    }
}

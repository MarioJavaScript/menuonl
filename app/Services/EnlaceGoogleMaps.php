<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Enlace de Google Maps que pega el dueño: se valida (https y dominios de Google Maps, nada más) y se convierte en
 * latitud y longitud, que es lo que guarda el restaurante.
 *
 * Los enlaces cortos de "Compartir" (maps.app.goo.gl) solo se siguen en ese dominio fijo y cada salto se vuelve a
 * validar, así que ningún enlace del dueño puede llevar al servidor a pedir otra dirección.
 */
class EnlaceGoogleMaps
{
    private const int MAX_SALTOS = 2;

    private const int ESPERA_SEGUNDOS = 4;

    private const int ESPERA_CONEXION_SEGUNDOS = 2;

    private const int MAX_BYTES_RESPUESTA = 65536;

    /**
     * Dominios permitidos para un enlace completo de Google Maps. Lista fija: nada de comodines ni de "termina en".
     *
     * @var list<string>
     */
    private const array DOMINIOS_MAPA = [
        'google.com', 'www.google.com', 'maps.google.com',
        'google.com.mx', 'www.google.com.mx', 'google.com.co', 'www.google.com.co',
        'google.com.ar', 'www.google.com.ar', 'google.cl', 'www.google.cl',
        'google.com.pe', 'www.google.com.pe', 'google.com.br', 'www.google.com.br',
        'google.es', 'www.google.es',
    ];

    /**
     * Dominios de los enlaces cortos de "Compartir": son los únicos a los que el servidor llega a pedir algo.
     *
     * @var list<string>
     */
    private const array DOMINIOS_CORTOS = ['maps.app.goo.gl', 'goo.gl'];

    /**
     * Resolutor de nombres (solo para pruebas); null usa el DNS real.
     */
    private static ?Closure $resolutor = null;

    private const string NUMERO = '-?\d{1,3}(?:\.\d+)?';

    /**
     * Enlace completo de Google Maps (https, sin usuario ni puerto, dominio de Google y ruta de mapas).
     */
    public static function esEnlaceDeMapa(string $url): bool
    {
        $partes = self::partes($url);

        if ($partes === null) {
            return false;
        }

        ['host' => $host, 'path' => $ruta] = $partes;

        if (! in_array($host, self::DOMINIOS_MAPA, true)) {
            return false;
        }

        return $host === 'maps.google.com' || $ruta === '/maps' || str_starts_with($ruta, '/maps/');
    }

    /**
     * Enlace corto que genera el botón "Compartir" de Google Maps.
     */
    public static function esEnlaceCorto(string $url): bool
    {
        $partes = self::partes($url);

        if ($partes === null) {
            return false;
        }

        return in_array($partes['host'], self::DOMINIOS_CORTOS, true)
            && ($partes['host'] === 'maps.app.goo.gl' || str_starts_with($partes['path'], '/maps'));
    }

    public static function esValido(string $url): bool
    {
        return self::esEnlaceDeMapa($url) || self::esEnlaceCorto($url);
    }

    /**
     * Latitud y longitud del lugar que señala el enlace, o null si no se pudieron encontrar.
     *
     * @return array{latitud: float, longitud: float}|null
     */
    public static function coordenadas(string $url): ?array
    {
        $url = trim($url);

        for ($salto = 0; $salto <= self::MAX_SALTOS; $salto++) {
            if (self::esEnlaceDeMapa($url)) {
                return self::extraer($url);
            }

            if (! self::esEnlaceCorto($url)) {
                return null;
            }

            $url = self::destinoDelEnlaceCorto($url);

            if ($url === null) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return array{latitud: float, longitud: float}|null
     */
    private static function extraer(string $url): ?array
    {
        $texto = rawurldecode($url);
        $n = self::NUMERO;

        foreach (["/!3d({$n})!4d({$n})/", "/@({$n}),({$n})/", "/[?&](?:q|query|ll|center|destination)=({$n}),\s*({$n})(?:[&#]|\z)/"] as $patron) {
            if (preg_match($patron, $texto, $m) === 1) {
                $latitud = round((float) $m[1], 6);
                $longitud = round((float) $m[2], 6);

                if (abs($latitud) <= 90 && abs($longitud) <= 180) {
                    return ['latitud' => $latitud, 'longitud' => $longitud];
                }
            }
        }

        return null;
    }

    private static function destinoDelEnlaceCorto(string $url): ?string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ips = self::ipsPublicas($host);

        if ($ips === null) {
            return null;
        }

        try {
            $respuesta = self::cliente($host, $ips[0])->get($url);
        } catch (ConnectionException|\RuntimeException) {
            return null;
        }

        if ((int) $respuesta->header('Content-Length') > self::MAX_BYTES_RESPUESTA) {
            return null;
        }

        $destino = trim($respuesta->header('Location'));

        return $respuesta->redirect() && self::esValido($destino) ? $destino : null;
    }

    /**
     * Cliente para pedir un enlace corto: sin redirecciones automáticas (se siguen a mano), tiempos cortos, respuesta que
     * no se descarga y conexión fijada a la IP ya comprobada, para que otra consulta de DNS no pueda cambiarla.
     */
    public static function cliente(string $host, string $ip): PendingRequest
    {
        return Http::timeout(self::ESPERA_SEGUNDOS)
            ->connectTimeout(self::ESPERA_CONEXION_SEGUNDOS)
            ->withoutRedirecting()
            ->withOptions([
                'stream' => true,
                'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$ip}"]],
                'on_headers' => function ($respuesta): void {
                    if ((int) $respuesta->getHeaderLine('Content-Length') > self::MAX_BYTES_RESPUESTA) {
                        throw new \RuntimeException('Respuesta demasiado grande.');
                    }
                },
            ]);
    }

    /**
     * IPs a las que resuelve el dominio, o null si no resuelve o si alguna no es pública (privada, loopback, enlace local...).
     *
     * @return non-empty-list<string>|null
     */
    private static function ipsPublicas(string $host): ?array
    {
        $ips = (self::$resolutor ?? self::resolverDns(...))($host);

        if ($ips === [] || array_filter($ips, fn (string $ip): bool => ! self::esIpPublica($ip)) !== []) {
            return null;
        }

        return array_values($ips);
    }

    public static function esIpPublica(string $ip): bool
    {
        if (preg_match('/\A::ffff:(\d{1,3}(?:\.\d{1,3}){3})\z/i', $ip, $m) === 1) {
            $ip = $m[1];
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @return list<string>
     */
    private static function resolverDns(string $host): array
    {
        $registros = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn (array $r): ?string => $r['ip'] ?? $r['ipv6'] ?? null, $registros)));
    }

    /**
     * Solo para pruebas: cambia cómo se resuelven los nombres (null vuelve al DNS real).
     *
     * @param  (Closure(string): list<string>)|null  $resolutor
     */
    public static function usarResolutor(?Closure $resolutor): void
    {
        self::$resolutor = $resolutor;
    }

    /**
     * @return array{host: string, path: string}|null
     */
    private static function partes(string $url): ?array
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return null;
        }

        $partes = parse_url($url);

        if ($partes === false
            || strtolower((string) ($partes['scheme'] ?? '')) !== 'https'
            || isset($partes['user'])
            || isset($partes['pass'])
            || (isset($partes['port']) && $partes['port'] !== 443)
            || ! isset($partes['host'])) {
            return null;
        }

        return ['host' => strtolower($partes['host']), 'path' => $partes['path'] ?? '/'];
    }
}

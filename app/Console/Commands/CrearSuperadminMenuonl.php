<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Crea el primer superadmin real (docs/despliegue.md, punto 7) sin seeders ni tinker.
 *
 * Todo se pide de forma interactiva: la contraseña nunca viaja como argumento u opción (quedaría en el historial de la
 * terminal), no se imprime y no se registra. Se guarda con el mismo hash que el resto de la aplicación (cast `hashed`
 * de User, driver de config/hashing.php). El historial de cambios no aplica: sus filas pertenecen a un restaurante y
 * un superadmin no tiene ninguno; queda constancia en el registro de la aplicación, sin datos secretos.
 */
class CrearSuperadminMenuonl extends Command
{
    public const int LONGITUD_MINIMA_CONTRASENA = 12;

    protected $signature = 'menuonl:crear-superadmin
        {--confirmo-otro-superadmin : Permite crear otro superadmin aunque ya exista uno}';

    protected $description = 'Crea un superadmin real pidiendo correo, nombre y contraseña de forma interactiva';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            return $this->rechazar('Este comando es interactivo: la contraseña no se acepta por argumento ni por entorno. Ejecútalo sin --no-interaction.');
        }

        if (User::query()->where('es_superadmin', true)->exists() && ! $this->option('confirmo-otro-superadmin')) {
            return $this->rechazar('Ya existe un superadmin. Si de verdad quieres crear otro, vuelve a ejecutar con --confirmo-otro-superadmin.');
        }

        $correo = mb_strtolower(trim((string) $this->ask('Correo del superadmin')));
        $nombre = trim((string) $this->ask('Nombre completo'));

        if (($error = $this->errorDeDatos($correo, $nombre)) !== null) {
            return $this->rechazar($error);
        }

        $contrasena = (string) $this->secret('Contraseña (mínimo '.self::LONGITUD_MINIMA_CONTRASENA.' caracteres, no se muestra)');

        if (mb_strlen($contrasena) < self::LONGITUD_MINIMA_CONTRASENA) {
            return $this->rechazar('La contraseña debe tener al menos '.self::LONGITUD_MINIMA_CONTRASENA.' caracteres.');
        }

        if (! hash_equals($contrasena, (string) $this->secret('Confirma la contraseña'))) {
            return $this->rechazar('Las contraseñas no coinciden.');
        }

        try {
            $usuario = (new User)->forceFill([
                'name' => $nombre,
                'email' => $correo,
                'password' => $contrasena,
                'es_superadmin' => true,
                'activo' => true,
                'email_verified_at' => now(),
            ]);
            $usuario->save();
        } catch (QueryException) {
            return $this->rechazar('No se pudo crear la cuenta: el correo ya está registrado.');
        }

        Log::info('menuonl:crear-superadmin - Superadmin creado.', ['usuario_id' => $usuario->getKey(), 'correo' => $correo]);

        $this->info("Superadmin creado: {$correo}");
        $this->line('Al iniciar sesión en /admin se te pedirá configurar la app de autenticación (2FA). Guarda los códigos de recuperación.');

        return self::SUCCESS;
    }

    /**
     * Mensaje de error de los datos que no son la contraseña, o null si son válidos.
     */
    private function errorDeDatos(string $correo, string $nombre): ?string
    {
        $validador = Validator::make(
            ['email' => $correo, 'name' => $nombre],
            ['email' => ['required', 'email:rfc', 'max:255'], 'name' => ['required', 'string', 'max:255']],
            [],
            ['email' => 'correo', 'name' => 'nombre'],
        );

        if ($validador->fails()) {
            return (string) $validador->errors()->first();
        }

        if (User::query()->whereRaw('lower(email) = ?', [$correo])->exists()) {
            return 'Ya existe una cuenta con ese correo.';
        }

        return null;
    }

    private function rechazar(string $mensaje): int
    {
        $this->error($mensaje);

        return self::FAILURE;
    }
}

<?php

namespace Tests\Feature;

use App\Mail\InvitacionMail;
use App\Mail\RespaldoVencidoMail;
use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Services\TextoMarkdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Los correos pasan por Markdown: lo que escriben los usuarios (nombre del restaurante) debe verse siempre como texto
 * literal, nunca como énfasis, enlaces, imágenes ni HTML.
 */
class CorreoInvitacionMarkdownSeguroTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const string TOKEN = 'TokenDePruebaTokenDePruebaTokenDePruebaTokenDePrueba';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nombresHostiles(): array
    {
        return [
            'asteriscos' => ["Niño's *Tacos*"],
            'negritas' => ['**Tacos** de la __esquina__'],
            'enlace markdown' => ['[Confirma aquí](https://sitio-malo.example)'],
            'imagen markdown' => ['![x](https://x.example/a.png)'],
            'html' => ['<b>x</b>'],
            'html con enlace' => ['<a href="https://sitio-malo.example">Confirma</a>'],
            'html con imagen' => ['<img src="https://x.example/a.png">'],
            'backticks' => ['Tacos `rm -rf` y ```bloque```'],
            'guiones bajos' => ['_Tacos_ y snake_case_nombre'],
            'url suelta' => ['https://sitio-malo.example/entra'],
            'www suelto' => ['www.sitio-malo.example'],
            'correo suelto' => ['admin@sitio-malo.example'],
            'autoenlace' => ['<https://sitio-malo.example>'],
            'título' => ['# Gran título'],
            'viñeta' => ['- Tacos'],
            'lista numerada' => ['1. Tacos'],
            'cita' => ['> Tacos'],
            'tachado' => ['~~Tacos~~'],
            'referencia' => ["[x]: https://sitio-malo.example\n[x]"],
            'barra invertida' => ['Tacos \\*literal\\*'],
            'saltos de línea' => ["Tacos\n\n# Segunda línea"],
            'ampersand y comillas' => ['Pérez & "Hijos" <Ltda>'],
        ];
    }

    private function correo(string $nombre): InvitacionMail
    {
        $restaurante = Restaurante::factory()->create(['nombre' => $nombre]);

        $invitacion = Invitacion::withoutEvents(fn (): Invitacion => Invitacion::create([
            'restaurante_id' => $restaurante->id,
            'email' => 'dueno@ejemplo.com',
            'rol' => 'propietario',
            'token' => hash('sha256', Str::random(40)),
            'expira_en' => now()->addDays(7),
        ]));

        return new InvitacionMail($invitacion, $restaurante, self::TOKEN);
    }

    /**
     * Number of generated tags by kind, to compare a hostile name against a harmless one.
     *
     * @return array<string, int>
     */
    private static function etiquetas(string $html): array
    {
        $cuenta = [];

        foreach (['a', 'img', 'em', 'strong', 'code', 'pre', 'h1', 'h2', 'ul', 'ol', 'li', 'blockquote', 'del', 'p', 'br'] as $etiqueta) {
            $cuenta[$etiqueta] = preg_match_all('/<'.$etiqueta.'[\s>\/]/i', $html);
        }

        return $cuenta;
    }

    private static function textoVisible(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    #[DataProvider('nombresHostiles')]
    public function test_el_nombre_se_ve_como_texto_literal_sin_generar_enlaces_ni_enfasis(string $nombre): void
    {
        $esperado = trim((string) preg_replace('/\s+/u', ' ', $nombre));

        $html = $this->correo($nombre)->render();
        $base = $this->correo('Taquería La Esquina')->render();

        $this->assertSame(
            self::etiquetas($base),
            self::etiquetas($html),
            'El nombre generó etiquetas que un nombre inofensivo no genera.',
        );
        $this->assertStringContainsString('Te invitaron a '.$esperado, self::textoVisible($html));
        $this->assertStringContainsString('de '.$esperado.' con el rol de', self::textoVisible($html));
    }

    #[DataProvider('nombresHostiles')]
    public function test_el_nombre_no_agrega_destinos_de_enlace_ni_de_imagen(string $nombre): void
    {
        $html = $this->correo($nombre)->render();

        $this->assertStringNotContainsString('sitio-malo.example', implode(' ', $this->destinos($html)));
        $this->assertStringNotContainsString('x.example', implode(' ', $this->destinos($html)));
        $this->assertSame(
            $this->destinos($this->correo('Taquería La Esquina')->render()),
            $this->destinos($html),
        );
    }

    /**
     * href and src values of the real <a> and <img> elements (text that merely looks like a tag does not count).
     *
     * @return list<string>
     */
    private function destinos(string $html): array
    {
        $documento = new \DOMDocument;
        @$documento->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($documento);

        $destinos = [];
        foreach ($xpath->query('//a/@href | //img/@src') as $atributo) {
            $destinos[] = $atributo->value;
        }

        return $destinos;
    }

    public function test_el_unico_enlace_de_accion_es_el_de_la_invitacion(): void
    {
        $html = $this->correo('[Confirma aquí](https://sitio-malo.example)')->render();

        $this->assertContains(route('invitaciones.mostrar', ['token' => self::TOKEN]), $this->destinos($html));
        $this->assertSame([], array_values(array_filter($this->destinos($html), fn (string $destino): bool => str_contains($destino, 'sitio-malo.example'))));
        $this->assertSame($this->destinos($this->correo('Taquería La Esquina')->render()), $this->destinos($html));
    }

    public function test_el_asunto_conserva_el_nombre_tal_cual(): void
    {
        $this->assertSame(
            'Te invitaron a administrar [Confirma aquí](https://sitio-malo.example)',
            $this->correo('[Confirma aquí](https://sitio-malo.example)')->envelope()->subject,
        );
    }

    #[DataProvider('nombresHostiles')]
    public function test_escapar_deja_una_sola_linea_y_no_deja_puntuacion_sin_escapar(string $nombre): void
    {
        $escapado = TextoMarkdown::escapar($nombre);

        $this->assertStringNotContainsString("\n", $escapado);

        // Quitados los pares "\carácter", no debe quedar ninguna puntuación Markdown suelta.
        $sinEscapes = (string) preg_replace('/\\\\./s', '', $escapado);
        $this->assertSame(0, preg_match('/[!#()*+\-.:\/;=?@\[\]^_`{|}~\\\\]/', $sinEscapes), 'Quedó puntuación Markdown sin escapar.');
    }

    public function test_los_nombres_normales_no_cambian_al_leerse(): void
    {
        foreach (['Taquería La Esquina', "D'Amore, O'Conner and Sons", 'Café Ñandú 100% mexicano'] as $nombre) {
            $this->assertStringContainsString($nombre, self::textoVisible($this->correo($nombre)->render()));
        }
    }

    /**
     * El nombre de la app es configuración del operador, no datos de usuario; aun así el cuerpo del correo lo escapa.
     * (El pie estándar de Laravel lo interpreta como Markdown, igual que en InvitacionMail: no es parte de nuestra plantilla.)
     */
    public function test_el_correo_del_respaldo_escapa_el_nombre_de_la_app_en_su_cuerpo(): void
    {
        config(['app.name' => '[Confirma aquí](https://sitio-malo.example) <b>x</b>']);

        $html = (new RespaldoVencidoMail('vencido', 40, 36))->render();

        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertSame(1, preg_match('/Gracias,<br>\s*\[Confirma aquí\]\(https:\/\/sitio-malo\.example\)/u', $html), 'La firma del cuerpo debe mostrar el nombre como texto, no como enlace.');
    }

    public function test_no_hay_otras_plantillas_de_correo_ni_notificaciones_con_datos_de_usuario_sin_revisar(): void
    {
        $mailables = collect(glob(app_path('Mail/*.php')))->map(fn (string $ruta): string => basename($ruta, '.php'))->sort()->values()->all();
        $plantillas = collect(glob(resource_path('views/emails/*.blade.php')))->map(fn (string $ruta): string => basename($ruta))->sort()->values()->all();

        // Una plantilla o notificación nueva con datos de usuario debe usar TextoMarkdown::escapar() y tener su prueba aquí.
        $this->assertSame(['InvitacionMail', 'RespaldoVencidoMail'], $mailables);
        $this->assertSame(['invitacion.blade.php', 'respaldo-vencido.blade.php'], $plantillas);
        $this->assertSame([], glob(app_path('Notifications/*.php')) ?: []);
    }
}

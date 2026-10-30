<?php

namespace App\Services;

/**
 * Marcadores de texto pendiente que la portada y las páginas legales muestran mientras no se escriba el contenido real
 * (resaltados con la clase `mark`). `menuonl:verificar-produccion` avisa si alguno sigue visible.
 */
class MarcadoresPendientes
{
    /**
     * @var list<string>
     */
    public const LISTA = [
        '[PRECIO]',
        '[CONFIRMAR]',
        '[CAPTURA DEL MENÚ]',
        '[CAPTURA DEL PANEL]',
        '[TESTIMONIO]',
        '[NOMBRE Y RESTAURANTE]',
        '[PÁGINA POR ESCRIBIR]',
    ];

    /**
     * Si el aviso de privacidad sigue sin escribirse (muestra algún marcador pendiente). Mientras sea así, los formularios
     * de reservas no lo enlazan y `menuonl:verificar-produccion` avisa si hay reservas activas.
     *
     * @param  string  $vista  Vista del aviso; solo cambia en pruebas.
     */
    public function privacidadPendiente(string $vista = 'landing.privacidad'): bool
    {
        return $this->enHtml(view($vista, ['soporte' => app(ContactoSoporte::class)])->render()) !== [];
    }

    /**
     * @return list<string> Los marcadores de la lista que aparecen en el HTML.
     */
    public function enHtml(string $html): array
    {
        return array_values(array_filter(self::LISTA, fn (string $marcador): bool => str_contains($html, $marcador)));
    }

    /**
     * Renderiza las páginas públicas con marcadores y devuelve los que siguen visibles, por ruta.
     *
     * @return array<string, list<string>>
     */
    public function enPaginasPublicas(): array
    {
        $soporte = app(ContactoSoporte::class);
        $paginas = [
            '/' => view('landing.inicio', ['soporte' => $soporte, 'demoUrl' => null]),
            '/privacidad' => view('landing.privacidad', ['soporte' => $soporte]),
            '/terminos' => view('landing.terminos', ['soporte' => $soporte]),
        ];

        $encontrados = [];

        foreach ($paginas as $ruta => $vista) {
            $marcadores = $this->enHtml($vista->render());

            if ($marcadores !== []) {
                $encontrados[$ruta] = $marcadores;
            }
        }

        return $encontrados;
    }
}

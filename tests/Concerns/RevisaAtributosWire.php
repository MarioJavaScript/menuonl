<?php

namespace Tests\Concerns;

use DOMDocument;

/**
 * Revisa solo los atributos y el HTML que escriben nuestras vistas. Livewire y Filament meten JSON.parse(...) y atob(...)
 * en wire:snapshot, wire:effects y en sus <script>, así que esas partes se excluyen: de otro modo cualquier búsqueda
 * amplia de "JSON.parse" falla con texto que no es nuestro.
 *
 * Mira wire:click, wire:submit, wire:keydown, wire:poll* (con sus modificadores), x-on:* y @click. No mira wire:snapshot,
 * wire:effects, wire:id, wire:name ni el contenido de los <script>.
 */
trait RevisaAtributosWire
{
    protected function documento(string $html): DOMDocument
    {
        $documento = new DOMDocument;
        libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
        libxml_clear_errors();

        return $documento;
    }

    /**
     * Valores de los atributos de eventos que escribe nuestra vista. Se ignoran wire:snapshot, wire:effects y los
     * <script>, que traen JSON.parse(...) de Livewire y de Filament.
     *
     * @return list<string>
     */
    protected function valoresDeAtributosDeEventos(string $html): array
    {
        $valores = [];
        foreach ((new \DOMXPath($this->documento($html)))->query('//*[not(self::script)]/@*') as $atributo) {
            $nombre = $atributo->nodeName;
            if (preg_match('/^(wire:(click|submit|keydown|poll)|x-on:|@click)/', $nombre) === 1) {
                $valores[] = $atributo->nodeValue;
            }
        }

        return $valores;
    }

    /**
     * El HTML visible: sin <script> ni los atributos wire:snapshot y wire:effects.
     */
    protected function htmlVisible(string $html): string
    {
        $documento = $this->documento($html);
        $xpath = new \DOMXPath($documento);

        foreach (iterator_to_array($xpath->query('//script')) as $script) {
            $script->parentNode->removeChild($script);
        }
        foreach ($xpath->query('//*[@*[name()="wire:snapshot" or name()="wire:effects"]]') as $elemento) {
            $elemento->removeAttribute('wire:snapshot');
            $elemento->removeAttribute('wire:effects');
        }

        return (string) $documento->saveHTML($documento->getElementsByTagName('body')->item(0));
    }

    /**
     * @param  list<string>  $valores
     * @return list<string>
     */
    protected function codigoProhibidoEn(array $valores): array
    {
        $encontrados = [];
        foreach ($valores as $valor) {
            foreach (['atob(', 'JSON.parse(', '@js(', '@json('] as $texto) {
                if (str_contains($valor, $texto)) {
                    $encontrados[] = $texto;
                }
            }
        }

        return $encontrados;
    }

    /**
     * @return list<string>
     */
    protected function directivasSinCompilarEn(string $htmlVisible): array
    {
        return array_values(array_filter(
            ['@js(', '@json(', '@php', '@if (', '@foreach', '@class('],
            fn (string $texto): bool => str_contains($htmlVisible, $texto),
        ));
    }

    /**
     * Falla si algún atributo de evento nuestro trae código que el analizador de wire:* no soporta (JSON.parse, atob,
     *
     * @js o @json) o una directiva Blade sin compilar.
     */
    protected function assertAtributosDeEventosLimpios(string $html): void
    {
        $this->assertSame([], $this->codigoProhibidoEn($this->valoresDeAtributosDeEventos($html)));
        $this->assertSame([], $this->directivasSinCompilarEn($this->htmlVisible($html)));
    }
}

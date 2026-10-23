<?php

namespace Tests\Concerns;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Lee el HTML de menu.publico como lo ve el navegador: atributos data-* ya decodificados (sin entidades) y sin script en línea del pedido.
 * El pedido ya no viaja en un script ni en onclick; lo anima resources/js/menu-pedido.js con estos atributos.
 */
trait LeeElPedidoDelMenuPublico
{
    /**
     * @return array<string, string> Atributos del contenedor [data-mo-pedido]: numero, restaurante, moneda, locale, mesa, clave, clave-antigua.
     */
    private function atributosDelContenedorDePedido(string $html): array
    {
        $nodos = $this->nodosDelMenu($html, '//*[@data-mo-pedido]');
        $this->assertCount(1, $nodos, 'Debe haber exactamente un contenedor [data-mo-pedido].');

        $atributos = [];
        foreach (['numero', 'restaurante', 'moneda', 'locale', 'mesa', 'clave', 'clave-antigua'] as $nombre) {
            $atributos[$nombre] = $nodos[0]->getAttribute('data-'.$nombre);
        }

        return $atributos;
    }

    /**
     * @return list<array<string, string>> Un arreglo por botón [data-mo-agregar]: producto, variante, nombre, nombre-variante, precio.
     */
    private function botonesDeAgregar(string $html): array
    {
        return array_map(function (DOMElement $boton): array {
            $atributos = [];
            foreach (['producto', 'variante', 'nombre', 'nombre-variante', 'precio'] as $nombre) {
                $atributos[$nombre] = $boton->getAttribute('data-'.$nombre);
            }

            return $atributos;
        }, $this->nodosDelMenu($html, '//button[@data-mo-agregar]'));
    }

    /**
     * @return list<DOMElement>
     */
    private function nodosDelMenu(string $html, string $consulta): array
    {
        $documento = new DOMDocument;
        libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        $resultado = [];
        foreach ((new DOMXPath($documento))->query($consulta) ?: [] as $nodo) {
            if ($nodo instanceof DOMElement) {
                $resultado[] = $nodo;
            }
        }

        return $resultado;
    }

    /**
     * Ningún script en línea que no sea el de apariencia del layout (compartido por todas las vistas) y ningún manejador en atributos.
     */
    private function assertSinScriptNiManejadoresDelPedido(string $html): void
    {
        preg_match_all('#<script\b(?![^>]*\bsrc=)[^>]*>(.*?)</script>#s', $html, $bloques);
        foreach ($bloques[1] as $bloque) {
            $this->assertStringContainsString('filament:theme:mode', $bloque, 'Solo se admite el script de apariencia del layout.');
            $this->assertStringNotContainsString('CART_KEY', $bloque);
            $this->assertStringNotContainsString('wa.me', $bloque);
        }

        $this->assertSame([], $this->nodosDelMenu($html, '//*[@*[starts-with(name(), "on")]]'), 'Ningún elemento debe llevar manejadores en línea (onclick, oninput...).');
    }
}

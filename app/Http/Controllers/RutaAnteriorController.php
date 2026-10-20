<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Old public addresses (/m/{slug} and /s/{slug}): they only redirect (301) to /{slug}/menu and /{slug}.
 */
class RutaAnteriorController extends Controller
{
    public function menu(Request $request, string $slug): RedirectResponse
    {
        return redirect()->route('menu.publico', [...$request->query(), 'slug' => $slug], 301);
    }

    public function sitio(Request $request, string $slug): RedirectResponse
    {
        return redirect()->route('sitio.publico', [...$request->query(), 'slug' => $slug], 301);
    }
}

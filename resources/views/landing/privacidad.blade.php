@extends('landing.layout')

@section('titulo', 'Aviso de privacidad | '.config('app.name'))

@section('meta')
<meta name="robots" content="noindex">
@endsection

@section('contenido')
<main id="contenido">
  <section class="section">
    <div class="wrap">
      <h1>Aviso de privacidad</h1>
      <p><span class="mark">[PÁGINA POR ESCRIBIR]</span></p>
      <p><a class="btn btn--secondary" href="{{ route('portada') }}">Volver al inicio</a></p>
    </div>
  </section>
</main>
@endsection

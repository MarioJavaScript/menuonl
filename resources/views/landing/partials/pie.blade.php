<footer class="site-footer on-dark">
  <div class="wrap site-footer__bar">
    <img src="{{ asset('brand/menubly-horizontal-negativo.svg') }}" alt="{{ config('app.name') }}" width="153" height="32">
    <nav aria-label="Información legal y contacto">
      <ul class="footer-links">
        <li><a href="{{ route('privacidad') }}">Privacidad</a></li>
        <li><a href="{{ route('terminos') }}">Términos</a></li>
        @if ($url = $soporte->enlaceWhatsapp('Hola, tengo una pregunta sobre '.config('app.name').'.'))
          <li><a href="{{ $url }}" rel="noopener noreferrer">Contacto por WhatsApp</a></li>
        @endif
        <li><a href="{{ url('/admin') }}">Entrar</a></li>
      </ul>
    </nav>
    <p class="footer-copy">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
  </div>
</footer>

// Sitio público (/{slug}): reserva en línea. Sin dependencias.
const form = document.getElementById('form-reserva');

if (form) {
    const mensaje = document.getElementById('reserva-msg');
    const fecha = form.elements.fecha;
    const personas = form.elements.personas;
    const hora = form.elements.hora;
    const boton = form.querySelector('[type=submit]');

    const mostrar = (texto, error = false, enlace = null) => {
        mensaje.hidden = false;
        mensaje.classList.toggle('error', error);
        mensaje.textContent = '';
        const p = document.createElement('p');
        p.style.margin = '0';
        p.textContent = texto;
        mensaje.append(p);

        if (enlace) {
            const a = document.createElement('a');
            a.href = enlace;
            a.rel = 'noopener';
            a.textContent = 'Avisar por WhatsApp';
            a.className = 'btn btn-alt';
            a.style.marginTop = '12px';
            mensaje.append(a);
        }

        mensaje.focus();
    };

    const cargarHoras = async () => {
        hora.disabled = true;

        if (!fecha.value) {
            hora.innerHTML = '<option value="">Primero elige la fecha</option>';
            return;
        }

        hora.innerHTML = '<option value="">Buscando horarios…</option>';

        try {
            const r = await fetch(`${form.dataset.disp}?fecha=${encodeURIComponent(fecha.value)}&personas=${encodeURIComponent(personas.value)}`, { headers: { Accept: 'application/json' } });
            const j = await r.json();
            const libres = (j.data?.franjas ?? []).filter((f) => f.disponible);

            hora.innerHTML = '';

            if (!r.ok || libres.length === 0) {
                hora.innerHTML = '<option value="">Sin horarios disponibles ese día</option>';
                return;
            }

            hora.append(new Option('Elige una hora', ''));
            libres.forEach((f) => hora.append(new Option(f.hora, f.hora)));
            hora.disabled = false;
        } catch {
            hora.innerHTML = '<option value="">No pudimos cargar los horarios</option>';
        }
    };

    fecha.addEventListener('change', cargarHoras);
    personas.addEventListener('change', cargarHoras);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        mensaje.hidden = true;

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        boton.disabled = true;

        try {
            const r = await fetch(form.dataset.url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.fromEntries(new FormData(form))),
            });
            const j = await r.json().catch(() => ({}));

            if (r.status === 429) {
                mostrar('Demasiados intentos. Espera un momento e inténtalo de nuevo.', true);
            } else if (!r.ok) {
                mostrar(j.errors ? Object.values(j.errors)[0][0] : (j.message ?? 'No pudimos enviar tu solicitud.'), true);
                if (j.errors?.hora) {
                    cargarHoras();
                }
            } else {
                form.reset();
                hora.disabled = true;
                hora.innerHTML = '<option value="">Primero elige la fecha</option>';
                mostrar(`${j.data.mensaje} ${j.data.fecha} a las ${j.data.hora}, ${j.data.personas} personas.`, false, j.data.whatsapp_url);
            }
        } catch {
            mostrar('Sin conexión. Revisa tu internet e inténtalo de nuevo.', true);
        } finally {
            boton.disabled = false;
        }
    });
}

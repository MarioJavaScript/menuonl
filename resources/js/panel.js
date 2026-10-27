const $=(s,r=document)=>r.querySelector(s);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const same=(a,b)=>String(a)===String(b);

/* ---------- API (misma sesión que /admin: cookie + CSRF) ---------- */
let slug=null;
const xsrf=()=>decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)||[])[1]||'');
const api=async(method,path,body)=>{const j=await apiRaw(method,path,body);return j===null?null:j.data!==undefined?j.data:j};
async function apiRaw(method,path,body){
  const r=await fetch('/api/v1/panel'+path,{method,credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-XSRF-TOKEN':xsrf()},body:body?JSON.stringify(body):undefined});
  if(r.status===401){location.href='/panel';throw new Error('Sesión expirada')}
  if(r.status===419)throw new Error('La página caducó, recárgala');
  if(r.status===204)return null;
  const j=await r.json().catch(()=>({}));
  if(!r.ok)throw new Error(j.errors?Object.values(j.errors)[0][0]:(j.message||'No se pudo completar la acción'));
  return j;
}
const P=p=>`/${slug}${p}`;
/* Reduce la foto en el navegador (los celulares dan 4-8 MB): el servidor recorta, convierte a WebP y genera los tamaños. */
async function reducirImagen(file){
  try{
    const bmp=await createImageBitmap(file),k=Math.min(1,2200/Math.max(bmp.width,bmp.height));
    const c=document.createElement('canvas');c.width=Math.round(bmp.width*k);c.height=Math.round(bmp.height*k);
    c.getContext('2d').drawImage(bmp,0,0,c.width,c.height);
    const blob=await new Promise(r=>c.toBlob(r,'image/jpeg',.86));
    return blob&&blob.size<file.size?new File([blob],'foto.jpg',{type:'image/jpeg'}):file;
  }catch(e){return file}
}
async function subir(path,file){
  const fd=new FormData();fd.append('imagen',await reducirImagen(file));
  const r=await fetch('/api/v1/panel'+path,{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-XSRF-TOKEN':xsrf()},body:fd});
  if(r.status===401){location.href='/panel';throw new Error('Sesión expirada')}
  const j=await r.json().catch(()=>({}));
  if(!r.ok)throw new Error(j.errors?Object.values(j.errors)[0][0]:(j.message||'No se pudo subir la foto'));
  return j.data;
}

const ROLES={propietario:'Propietario',gerente:'Gerente',editor:'Editor',recepcion:'Recepción'};
/* un rol que el panel no conoce (p. ej. uno retirado) se muestra tal cual, nunca "undefined" */
const rolEtiqueta=r=>ROLES[r]||String(r||'');
let S={aj:null,reservas:[],prox:[],resDia:[],hoy:'',resModo:'proximas',resFecha:'',menu:[],resumen:{},equipo:{miembros:[],invitaciones:[],roles_asignables:[],roles_modificables:[]},modulos:[],ok:false};
const has=m=>S.modulos.includes(m);
/* cada carga pertenece a un módulo; un rol sin permiso nunca la pide (la API respondería 403) */
const MODULO_DE={reservas:'reservas',menu:'menu',resumen:'inicio',equipo:'ajustes',ajustes:'ajustes'};
const L={
  /* hoy (resumen y pestaña "Hoy"), de hoy en adelante (pestaña "Próximas", avisos) y, si se eligió, un día concreto */
  async reservas(){
    const fila=r=>({id:r.id,f:r.fecha,n:r.nombre,p:r.personas,h:r.hora,s:r.estado,nota:r.nota||'',tel:r.telefono||'',o:r.origen});
    const dia=S.resModo==='fecha'&&S.resFecha?apiRaw('GET',P('/reservas?fecha='+S.resFecha)):null;
    const [hoy,prox,otro]=await Promise.all([apiRaw('GET',P('/reservas')),apiRaw('GET',P('/reservas?proximas=1')),dia]);
    S.reservas=hoy.data.map(fila);S.prox=prox.data.map(fila);S.hoy=prox.hoy||hoy.hoy||S.hoy;S.resDia=otro?otro.data.map(fila):[];
  },
  async menu(){S.menu=(await api('GET',P('/menu'))).map(c=>({id:c.id,nombre:c.nombre,items:c.productos.map(i=>({id:i.id,n:i.nombre,pr:i.precio,disp:i.disponible,vis:i.visible,img:i.imagen}))}))},
  async resumen(){S.resumen=await api('GET',P('/resumen'))},
  async equipo(){S.equipo=await api('GET',P('/equipo'))},
  async ajustes(){S.aj=await api('GET',P('/ajustes'))}
};
async function sync(...keys){await Promise.all(keys.filter(k=>has(MODULO_DE[k])).map(k=>L[k]()));render()}

let tt;function toast(m){const t=$('#toast');t.textContent=m;t.classList.add('on');clearTimeout(tt);tt=setTimeout(()=>t.classList.remove('on'),2400)}
function confirmar(msg,ok,label='Confirmar'){const d=$('#dlg');$('#dlg-msg').textContent=msg;$('#dlg-yes').textContent=label;
  $('#dlg-yes').onclick=()=>{d.close();ok()};$('#dlg-no').onclick=()=>d.close();d.showModal()}
/* ejecuta una acción; si falla avisa y vuelve a pintar con los datos del servidor */
async function run(fn){try{await fn()}catch(e){toast(e.message);render()}}

const HR=[];for(let h=12;h<=23;h++){HR.push(String(h).padStart(2,'0')+':00');HR.push(String(h).padStart(2,'0')+':30')}
const findItem=id=>{for(const c of S.menu){const i=c.items.find(x=>same(x.id,id));if(i)return [c,i]}return []};
const byHora=(a,b)=>a.h<b.h?-1:a.h>b.h?1:0;
const byFechaHora=(a,b)=>a.f<b.f?-1:a.f>b.f?1:byHora(a,b);
const fechaCorta=f=>new Date(f+'T12:00:00').toLocaleDateString('es-MX',{weekday:'short',day:'numeric',month:'short'});
const fechaLarga=f=>{const t=new Date(f+'T12:00:00').toLocaleDateString('es-MX',{weekday:'long',day:'numeric',month:'long'});return t.charAt(0).toUpperCase()+t.slice(1)};
const findRes=id=>[S.prox,S.reservas,S.resDia].flatMap(l=>l).find(x=>same(x.id,id));

/* ---------- vistas ---------- */
const V={};
function resRow(r,compact){
  const c=r.s==='cancelada';
  const chip={pendiente:'<span class="chip alert">Por confirmar</span>',confirmada:'<span class="chip">Confirmada</span>',llego:'<span class="chip done">Ya llegó</span>',cancelada:'<span class="chip soft">Cancelada</span>'}[r.s];
  let act='';
  if(r.s==='pendiente')act=`<button class="btn primary sm" data-act="res" data-id="${r.id}" data-v="confirmada">Confirmar</button>`;
  if(r.s==='confirmada')act=`<button class="btn primary sm" data-act="res" data-id="${r.id}" data-v="llego">Llegó</button>`;
  if(c)act=`<button class="btn sm" data-act="res" data-id="${r.id}" data-v="pendiente">Restaurar</button>`;
  const horas=HR.includes(r.h)?HR:[r.h,...HR].sort();
  const extra=compact||c?'':`<select class="sm" style="width:auto;min-height:44px" aria-label="Mover ${esc(r.n)} a otra hora" data-f="res.h" data-id="${r.id}">${horas.map(h=>`<option ${h===r.h?'selected':''}>${h}</option>`).join('')}</select>
    ${r.s==='llego'?'':`<button class="btn danger sm" data-act="res-cancel" data-id="${r.id}">Cancelar</button>`}`;
  return `<li class="${c?'cancelled':''}"><span class="time">${esc(r.h)}</span>
    <div class="grow"><strong>${esc(r.n)}</strong><div class="muted">${compact&&r.f&&r.f!==S.hoy?esc(fechaCorta(r.f))+' · ':''}${r.p} personas${r.nota?' · '+esc(r.nota):''}${r.tel?` · <a class="tel" href="tel:${esc(r.tel.replace(/[^0-9+]/g,''))}">${esc(r.tel)}</a>`:''}</div></div>
    ${r.o==='sitio'?'<span class="chip soft">Sitio web</span>':''}
    ${chip}${act}${extra}</li>`;
}
V.inicio=()=>{
  const prox=[...S.prox].filter(r=>r.s==='pendiente'||r.s==='confirmada').sort(byFechaHora).slice(0,4);
  const agot=S.menu.flatMap(c=>c.items).filter(i=>!i.disp&&i.vis);
  const R=S.resumen;
  const stats=[];
  if(R.comensales_esperados!==undefined)stats.push(`<div class="stat"><b>${R.comensales_esperados}</b><span>Comensales esperados</span></div>`);
  if(R.reservas_por_confirmar!==undefined)stats.push(`<div class="stat"><b>${R.reservas_por_confirmar}</b><span>Por confirmar</span></div>`);
  return `<div class="inner"><div class="head"><div><h1>Inicio</h1><p class="muted">Resumen del servicio de hoy</p></div></div>
  <div class="stats" style="grid-template-columns:repeat(${Math.max(stats.length,1)},1fr)">${stats.join('')}</div>
  ${has('reservas')?`<section class="card"><h2>Próximas reservas</h2>${prox.length?`<ul class="list">${prox.map(r=>resRow(r,true)).join('')}</ul>`:'<p class="empty">Sin reservas pendientes.</p>'}
   <p style="margin-top:12px"><a href="#reservas" class="btn ghost">Ver todas las reservas</a></p></section>`:''}
  ${has('menu')?`<section class="card"><h2>Platillos agotados</h2>${agot.length?`<ul class="list">${agot.map(i=>`<li><div class="grow"><strong>${esc(i.n)}</strong></div><button class="btn primary sm" data-act="disp" data-id="${i.id}">Volver a disponible</button></li>`).join('')}</ul>`:'<p class="empty">Todo el menú está disponible.</p>'}</section>`:''}</div>`;
};
V.reservas=()=>{
  const modo=S.resModo,hoy=S.hoy;
  const l=[...(modo==='hoy'?S.reservas:modo==='fecha'?S.resDia:S.prox)].sort(byFechaHora);
  const donde={hoy:'hoy',fecha:'el '+(S.resFecha?fechaCorta(S.resFecha):'día elegido'),proximas:'de hoy en adelante'}[modo];
  const vacio={hoy:'No hay reservas para hoy.',fecha:'No hay reservas para ese día.',proximas:'No hay reservas próximas.'}[modo];
  const grupos=[];for(const r of l){const g=grupos[grupos.length-1];if(g&&g.f===r.f)g.l.push(r);else grupos.push({f:r.f,l:[r]})}
  const lista=g=>`${modo==='proximas'?`<h2 class="dia">${esc(g.f===hoy?'Hoy · '+fechaCorta(g.f):fechaLarga(g.f))}</h2>`:''}<ul class="list">${g.l.map(r=>resRow(r)).join('')}</ul>`;
  const nueva=modo==='fecha'&&S.resFecha?S.resFecha:hoy;
  return `<div class="inner"><div class="head"><div><h1>Reservas</h1><p class="muted">${l.filter(r=>r.s!=='cancelada').length} activas ${donde}</p></div></div>
  <div class="tabs fechas" role="group" aria-label="Qué reservas ver">
   <button data-act="res-modo" data-v="proximas" aria-pressed="${modo==='proximas'}">Próximas</button>
   <button data-act="res-modo" data-v="hoy" aria-pressed="${modo==='hoy'}">Hoy</button>
   <label class="fecha-sel" data-activo="${modo==='fecha'}"><span class="sr">Ver otro día</span><input id="rf" type="date" data-f="res.fecha" value="${modo==='fecha'?esc(S.resFecha):''}" aria-label="Ver otro día"></label></div>
  <form class="card addform" data-form="res" aria-label="Nueva reserva">
   <div class="wide"><label class="f" for="rn">Nombre</label><input id="rn" name="n" required maxlength="100" autocomplete="off"></div>
   <div><label class="f" for="rp">Personas</label><input id="rp" name="p" type="number" min="1" max="30" value="2" required inputmode="numeric"></div>
   <div><label class="f" for="rd">Día</label><input id="rd" name="f" type="date" value="${esc(nueva)}" min="${esc(hoy)}" required></div>
   <div><label class="f" for="rh">Hora</label><select id="rh" name="h">${HR.map(h=>`<option ${h==='20:00'?'selected':''}>${h}</option>`).join('')}</select></div>
   <button class="btn primary wide" type="submit">Agregar reserva</button></form>
  <section class="card">${l.length?grupos.map(lista).join(''):`<p class="empty">${vacio}</p>`}</section></div>`;
};
V.menu=()=>`<div class="inner"><div class="head"><div><h1>Menú</h1><p class="muted">Edita el nombre o el precio directamente. Los cambios se guardan solos.</p></div></div>
 ${S.menu.length?S.menu.map(c=>`<section class="card cat"><header><h2>${esc(c.nombre)}</h2><span class="muted">${c.items.length} platillos</span></header>
  ${c.items.map(i=>`<div class="item ${i.vis?'':'hidden-item'}">
    <label class="switch"><input type="checkbox" data-act="disp" data-id="${i.id}" ${i.disp?'checked':''} aria-label="${esc(i.n)} disponible"><i></i><span>${i.disp?'Disponible':'Agotado'}</span></label>
    <input class="inline" value="${esc(i.n)}" aria-label="Nombre del platillo" data-f="item.n" data-id="${i.id}" maxlength="150">
    <input class="inline price" value="${i.pr}" aria-label="Precio de ${esc(i.n)}" data-f="item.pr" data-id="${i.id}" inputmode="decimal">
    <div class="acts">${i.img?`<img class="thumb" src="${esc(i.img)}" alt="Foto de ${esc(i.n)}" width="44" height="55">`:'<span class="thumb vacio" role="img" aria-label="Sin foto"></span>'}
     <label class="btn sm">${i.img?'Cambiar foto':'Foto'}<input type="file" accept="image/jpeg,image/png,image/webp" class="sr" data-f="item.img" data-id="${i.id}"></label>
     ${i.img?`<button class="btn sm ghost" data-act="img-quitar" data-id="${i.id}">Quitar foto</button>`:''}
     <button class="btn sm" data-act="dup" data-id="${i.id}">Duplicar</button>
     <button class="btn sm" data-act="vis" data-id="${i.id}">${i.vis?'Ocultar':'Mostrar'}</button>
     <button class="btn danger sm" data-act="item-del" data-id="${i.id}">Eliminar</button></div>
    ${i.vis?'':'<div class="state muted">Oculto en el sitio</div>'}</div>`).join('')}
  <form class="addrow addform" data-form="item" data-cid="${c.id}" aria-label="Agregar platillo a ${esc(c.nombre)}">
   <div class="wide"><label class="f" for="n${c.id}">Nuevo platillo</label><input id="n${c.id}" name="n" required maxlength="150" autocomplete="off"></div>
   <div><label class="f" for="p${c.id}">Precio</label><input id="p${c.id}" name="pr" required inputmode="decimal"></div>
   <button class="btn primary" type="submit">Agregar</button></form></section>`).join(''):'<p class="empty">Aún no hay categorías. Créalas en la administración avanzada.</p>'}</div>`;
const campo=(id,etiqueta,k,extra='')=>`<div><label class="f" for="${id}">${etiqueta}</label><input id="${id}" value="${esc(S.aj[k]??'')}" data-f="aj.${k}" ${extra}></div>`;
V.ajustes=()=>{const E=S.equipo,A=S.aj;return `<div class="inner"><div class="head"><div><h1>Ajustes</h1><p class="muted">${esc(S.nombre||'')} · se guardan al salir de cada campo</p></div></div>
 <section class="card"><h2>Sitio público</h2><p class="muted" style="margin-bottom:12px">Elige la plantilla. El sitio cambia en unos segundos.</p>
  <div class="tabs" role="group" aria-label="Plantilla del sitio" style="flex-wrap:wrap">${A.plantillas.map(t=>`<button data-act="plantilla" data-v="${t.clave}" aria-pressed="${A.plantilla===t.clave}" style="min-width:140px"><strong>${t.clave.toUpperCase()}</strong> · ${esc(t.nombre)}</button>`).join('')}</div>
  <p style="margin-top:12px"><a class="btn ghost" href="/${esc(slug)}" target="_blank" rel="noopener">Ver sitio</a></p></section>
 <section class="card"><h2>Fotos del sitio</h2>
  <p class="muted" style="margin-bottom:8px">Portada (16:9)</p>
  ${A.hero?`<img class="hero-prev" src="${esc(A.hero)}" alt="Portada actual" width="480" height="270">`:'<div class="hero-prev vacio" role="img" aria-label="Sin portada">Sin portada</div>'}
  <div class="row" style="margin:8px 0 16px"><label class="btn">${A.hero?'Cambiar portada':'Subir portada'}<input type="file" accept="image/jpeg,image/png,image/webp" class="sr" data-f="aj.hero"></label>${A.hero?'<button class="btn danger" data-act="hero-quitar">Quitar</button>':''}</div>
  <p class="muted" style="margin-bottom:8px">Galería (${A.galeria.length}/${A.galeria_max})</p>
  <div class="gal-grid">${A.galeria.map(f=>`<figure><img src="${esc(f.url)}" alt="Foto de la galería" width="96" height="120"><button class="btn sm danger" data-act="gal-quitar" data-id="${f.id}" aria-label="Quitar foto de la galería">Quitar</button></figure>`).join('')}</div>
  ${A.galeria.length<A.galeria_max?'<div class="row" style="margin-top:8px"><label class="btn primary">Agregar fotos<input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr" data-f="aj.galeria"></label></div>':''}</section>
 <section class="card"><h2>Datos del restaurante</h2><div class="addform">
  <div class="wide">${campo('a-nombre','Nombre','nombre','maxlength="150"')}</div>
  <div class="wide">${campo('a-eslogan','Eslogan','eslogan','maxlength="120"')}</div>
  ${campo('a-wa','WhatsApp (reservas y pedidos)','whatsapp','inputmode="tel" maxlength="20" placeholder="+52 55 1234 5678"')}
  ${campo('a-tel','Teléfono','telefono','inputmode="tel" maxlength="255"')}
  <div class="wide">${campo('a-dir','Dirección','direccion','maxlength="255"')}</div>
  ${campo('a-ig','Instagram','instagram','maxlength="255" placeholder="@mirestaurante"')}
  ${campo('a-fb','Facebook','facebook','maxlength="255"')}
  ${campo('a-lat','Latitud (mapa)','latitud','inputmode="decimal" placeholder="19.4326"')}
  ${campo('a-lng','Longitud (mapa)','longitud','inputmode="decimal" placeholder="-99.1332"')}</div></section>
 <section class="card"><h2>Horarios</h2><ul class="list">${A.horarios.map(h=>`<li><div class="grow"><strong>${h.nombre}</strong>${h.varios?'<div class="muted">Varios turnos: edítalo en la administración avanzada.</div>':''}</div>
   ${h.varios?'':`<label class="switch"><input type="checkbox" data-f="hor.abierto" data-dia="${h.dia}" ${h.cerrado?'':'checked'} aria-label="${h.nombre} abierto"><i></i><span>${h.cerrado?'Cerrado':'Abierto'}</span></label>
   ${h.cerrado?'':`<input type="time" style="width:auto" aria-label="${h.nombre} abre" value="${h.abre||''}" data-f="hor.abre" data-dia="${h.dia}"><input type="time" style="width:auto" aria-label="${h.nombre} cierra" value="${h.cierra||''}" data-f="hor.cierra" data-dia="${h.dia}">`}`}</li>`).join('')}</ul></section>
 <section class="card"><h2>Reservas del sitio</h2><div class="addform">
  <label class="switch"><input type="checkbox" data-f="aj.reservas_activas" ${A.reservas_activas?'checked':''}><i></i><span>${A.reservas_activas?'Aceptando reservas':'Reservas pausadas'}</span></label>
  <div><label class="f" for="a-cupo">Cupo por franja (personas)</label><input id="a-cupo" type="number" min="1" max="500" value="${A.reservas_cupo}" data-f="aj.reservas_cupo" inputmode="numeric"></div>
  <div><label class="f" for="a-franja">Duración de la franja</label><select id="a-franja" data-f="aj.reservas_franja_min">${[30,60,90,120].map(m=>`<option value="${m}" ${A.reservas_franja_min===m?'selected':''}>${m} min</option>`).join('')}</select></div></div></section>
 <section class="card"><h2>Equipo</h2><ul class="list">${E.miembros.map(u=>`<li class="${u.activo?'':'cancelled'}"><div class="grow"><strong>${esc(u.nombre)}</strong>${u.es_yo?' <span class="chip soft">Tú</span>':''}<div class="muted">${esc(u.email)}${u.activo?'':' · Desactivado'}</div></div>
   ${u.es_yo||!(E.roles_modificables||E.roles_asignables).includes(u.rol)?`<span class="chip">${esc(rolEtiqueta(u.rol))}</span>`:`${E.roles_asignables.length>1?`<select style="width:auto" aria-label="Rol de ${esc(u.nombre)}" data-f="usr.rol" data-id="${u.id}">${E.roles_asignables.map(r=>`<option value="${r}" ${r===u.rol?'selected':''}>${rolEtiqueta(r)}</option>`).join('')}</select>`:`<span class="chip">${esc(rolEtiqueta(u.rol))}</span>`}
   <button class="btn sm ${u.activo?'danger':'primary'}" data-act="usr-activo" data-id="${u.id}" data-v="${u.activo?0:1}">${u.activo?'Desactivar':'Activar'}</button>`}</li>`).join('')}</ul>
  ${E.invitaciones.length?`<h2 style="margin-top:16px">Invitaciones pendientes</h2><ul class="list">${E.invitaciones.map(i=>`<li><div class="grow"><strong>${esc(i.email)}</strong><div class="muted">${esc(rolEtiqueta(i.rol))}</div></div><button class="btn sm danger" data-act="inv-revocar" data-id="${i.id}">Revocar</button></li>`).join('')}</ul>`:''}
  ${E.roles_asignables.length?`<form class="addform" data-form="usr" style="margin-top:12px"><div class="wide"><label class="f" for="ue">Invitar por correo</label><input id="ue" name="email" type="email" required maxlength="190" autocomplete="off"></div>
   <div><label class="f" for="ur">Rol</label><select id="ur" name="rol">${E.roles_asignables.map(r=>`<option value="${r}" ${r==='recepcion'?'selected':''}>${rolEtiqueta(r)}</option>`).join('')}</select></div>
   <button class="btn primary" type="submit">Enviar invitación</button></form>`:''}</section>
 <section class="card"><h2>Administración avanzada</h2><p class="muted" style="margin-bottom:12px">Idiomas, QR, turnos partidos e historial de cambios.</p>
  <div class="row"><a class="btn" href="/admin">Abrir administración</a><a class="btn ghost" href="/${esc(slug)}/menu" target="_blank" rel="noopener">Ver menú público</a></div></section>
 ${S.restaurantes.length>1?`<section class="card"><h2>Cambiar de restaurante</h2><select aria-label="Restaurante" data-f="rest">${S.restaurantes.map(r=>`<option value="${esc(r.slug)}" ${r.slug===slug?'selected':''}>${esc(r.nombre)}</option>`).join('')}</select></section>`:''}</div>`};

/* ---------- render / ruteo ---------- */
function route(){const r=(location.hash||'').slice(1);return V[r]&&has(r)?r:S.modulos[0]||'inicio'}
const TITULOS={inicio:'Inicio',reservas:'Reservas',menu:'Menú',ajustes:'Ajustes'};
function render(){
  if(!S.ok)return;
  const r=route(), foco=document.activeElement&&document.activeElement.id;
  $('#main').innerHTML=V[r]();
  document.querySelectorAll('.nav a').forEach(a=>{if(a.dataset.r===r)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current')});
  document.querySelectorAll('.nav a[data-modulo]').forEach(a=>{a.hidden=!has(a.dataset.modulo)});
  const pend=S.prox.filter(x=>x.s==='pendiente').length;
  const b1=$('#b-res');b1.hidden=!pend||!has('reservas');b1.textContent=pend;
  document.title=TITULOS[r]+' · '+(S.nombre||'Panel');
  if(foco&&$('#'+foco))$('#'+foco).focus();
}
addEventListener('hashchange',()=>{render();scrollTo(0,0)});

/* ---------- acciones ---------- */
const A={
  res(el){return run(async()=>{const v=el.dataset.v;await api('PATCH',P('/reservas/'+el.dataset.id),{estado:v});
    await sync('reservas','resumen');toast({confirmada:'Reserva confirmada',llego:'Marcada como llegada',pendiente:'Reserva restaurada'}[v])})},
  'res-modo'(el){S.resModo=el.dataset.v;S.resFecha='';return run(async()=>{await sync('reservas')})},
  'res-cancel'(el){const r=findRes(el.dataset.id);
    confirmar(`¿Cancelar la reserva de ${r.n} ${r.f===S.hoy?'a las':'el '+fechaCorta(r.f)+' a las'} ${r.h}?`,()=>run(async()=>{await api('PATCH',P('/reservas/'+r.id),{estado:'cancelada'});await sync('reservas','resumen');toast('Reserva cancelada')}),'Sí, cancelar')},
  disp(el){const [,i]=findItem(el.dataset.id);const nuevo=!i.disp;return run(async()=>{await api('PATCH',P('/productos/'+i.id),{disponible:nuevo});await sync('menu');toast(nuevo?`${i.n} disponible`:`${i.n} agotado`)})},
  dup(el){return run(async()=>{await api('POST',P('/productos/'+el.dataset.id+'/duplicar'));await sync('menu');toast('Platillo duplicado')})},
  vis(el){const [,i]=findItem(el.dataset.id);const nuevo=!i.vis;return run(async()=>{await api('PATCH',P('/productos/'+i.id),{visible:nuevo});await sync('menu');toast(nuevo?'Visible en el sitio':'Oculto en el sitio')})},
  'item-del'(el){const [,i]=findItem(el.dataset.id);confirmar(`¿Eliminar "${i.n}" del menú?`,()=>run(async()=>{await api('DELETE',P('/productos/'+i.id));await sync('menu');toast('Platillo eliminado')}),'Eliminar')},
  'img-quitar'(el){const [,i]=findItem(el.dataset.id);return run(async()=>{await api('DELETE',P('/productos/'+i.id+'/imagen'));await sync('menu');toast('Foto quitada')})},
  'hero-quitar'(){confirmar('¿Quitar la portada del sitio?',()=>run(async()=>{await api('DELETE',P('/ajustes/hero'));await sync('ajustes');toast('Portada quitada')}),'Quitar')},
  'gal-quitar'(el){confirmar('¿Quitar esta foto de la galería?',()=>run(async()=>{await api('DELETE',P('/ajustes/galeria/'+el.dataset.id));await sync('ajustes');toast('Foto quitada')}),'Quitar')},
  plantilla(el){return run(async()=>{S.aj=await api('PATCH',P('/ajustes'),{plantilla:el.dataset.v});render();toast('Plantilla '+el.dataset.v.toUpperCase()+' activada')})},
  'usr-activo'(el){const on=el.dataset.v==='1';return run(async()=>{await api('PATCH',P('/equipo/usuarios/'+el.dataset.id),{activo:on});await sync('equipo');toast(on?'Usuario activado':'Usuario desactivado')})},
  'inv-revocar'(el){return run(async()=>{await api('DELETE',P('/equipo/invitaciones/'+el.dataset.id));await sync('equipo');toast('Invitación revocada')})}
};
document.addEventListener('click',e=>{const el=e.target.closest('[data-act]');if(!el||el.type==='checkbox')return;e.preventDefault();A[el.dataset.act]&&A[el.dataset.act](el)});
document.addEventListener('change',e=>{const el=e.target;
  if(el.type==='checkbox'&&el.dataset.act){A[el.dataset.act](el);return}
  const f=el.dataset.f;if(!f)return;const id=el.dataset.id;
  if(f==='rest'){location.search='?r='+encodeURIComponent(el.value);return}
  if(el.type==='file'){const files=[...el.files];el.value='';if(!files.length)return;
    return run(async()=>{toast('Subiendo…');
      if(f==='item.img'){await subir(P('/productos/'+id+'/imagen'),files[0]);await sync('menu')}
      if(f==='aj.hero'){await subir(P('/ajustes/hero'),files[0]);await sync('ajustes')}
      if(f==='aj.galeria'){for(const fl of files)await subir(P('/ajustes/galeria'),fl);await sync('ajustes')}
      toast('Foto guardada')})}
  if(f.startsWith('aj.')||f.startsWith('hor.'))return ajusteCambiado(el,f);
  if(f==='usr.rol')return run(async()=>{await api('PATCH',P('/equipo/usuarios/'+id),{rol:el.value});await sync('equipo');toast('Rol actualizado')});
  if(f==='res.fecha'){if(!el.value)return;S.resModo='fecha';S.resFecha=el.value;return run(async()=>{await sync('reservas')})}
  if(f==='res.h')return run(async()=>{await api('PATCH',P('/reservas/'+id),{hora:el.value});await sync('reservas');toast('Reserva movida a las '+el.value)});
  if(f==='item.n'){const [,i]=findItem(id);const v=el.value.trim();if(!v){el.value=i.n;return toast('El nombre no puede estar vacío')}
    return run(async()=>{await api('PATCH',P('/productos/'+id),{nombre:v});i.n=v;toast('Nombre guardado')})}
  if(f==='item.pr'){const [,i]=findItem(id);const v=parseFloat(String(el.value).replace(/[$,]/g,''));
    if(!(v>=0)||v>100000){el.value=i.pr;return toast('Precio no válido')}
    return run(async()=>{await api('PATCH',P('/productos/'+id),{precio:v});i.pr=v;el.value=v;toast('Precio guardado')})}
});
function ajusteCambiado(el,f){
  if(f.startsWith('hor.')){const fila=el.closest('li'),dia=el.dataset.dia;
    const abierto=f==='hor.abierto'?el.checked:true;
    const abre=fila.querySelector('[data-f="hor.abre"]'),cierra=fila.querySelector('[data-f="hor.cierra"]');
    const body=abierto?{cerrado:false,abre:(abre&&abre.value)||'13:00',cierra:(cierra&&cierra.value)||'23:00'}:{cerrado:true};
    return run(async()=>{const h=await api('PUT',P('/ajustes/horarios/'+dia),body);S.aj.horarios=h;if(f==='hor.abierto')render();toast('Horario guardado')})}
  const k=f.slice(3);let body;
  if(k==='latitud'||k==='longitud'){const n=v=>{v=String(v).trim().replace(',','.');return v===''?null:Number(v)};
    body={latitud:n($('#a-lat').value),longitud:n($('#a-lng').value)}}
  else if(k==='reservas_activas')body={reservas_activas:el.checked};
  else if(k==='reservas_cupo'||k==='reservas_franja_min')body={[k]:parseInt(el.value,10)};
  else{const v=el.value.trim();if(k==='nombre'&&!v){el.value=S.aj.nombre;return toast('El nombre no puede estar vacío')}body={[k]:v===''?null:v}}
  return run(async()=>{S.aj=await api('PATCH',P('/ajustes'),body);if(k==='nombre'){S.nombre=S.aj.nombre;$('#rest-nombre').textContent=S.nombre}
    if(k==='reservas_activas')render();toast('Guardado')})
}
document.addEventListener('submit',e=>{const f=e.target.dataset.form;if(!f)return;e.preventDefault();
  const form=e.target,d=Object.fromEntries(new FormData(form)),btn=form.querySelector('[type=submit]');btn.disabled=true;
  run(async()=>{
    if(f==='res'){await api('POST',P('/reservas'),{nombre:d.n.trim(),personas:parseInt(d.p)||1,hora:d.h,fecha:d.f||undefined});await sync('reservas','resumen');toast('Reserva agregada')}
    if(f==='item'){const pr=parseFloat(String(d.pr).replace(/[$,]/g,''));if(!(pr>=0))throw new Error('Precio no válido');
      await api('POST',P('/categorias/'+form.dataset.cid+'/productos'),{nombre:d.n.trim(),precio:pr});await sync('menu');toast('Platillo agregado')}
    if(f==='usr'){await api('POST',P('/equipo/invitaciones'),{email:d.email.trim(),rol:d.rol});await sync('equipo');toast('Invitación enviada')}
  }).finally(()=>{btn.disabled=false});
});

/* ---------- arranque ---------- */
async function init(){
  try{
    S.restaurantes=await api('GET','');
    if(!S.restaurantes.length){$('#main').innerHTML='<p class="empty">Tu acceso está desactivado. Pídele al propietario del restaurante que lo revise.</p>';return}
    const pedido=new URLSearchParams(location.search).get('r');
    const r=S.restaurantes.find(x=>x.slug===pedido)||S.restaurantes[0];slug=r.slug;S.nombre=r.nombre;S.modulos=r.modulos;$('#rest-nombre').textContent=r.nombre;
    await Promise.all(Object.keys(L).filter(k=>has(MODULO_DE[k])).map(k=>L[k]()));S.ok=true;render();
    /* casi en vivo: refresca reservas si no se está escribiendo */
    setInterval(()=>{if(document.hidden||document.activeElement.matches('input,select,textarea')||$('#dlg').open)return;sync('reservas','resumen').catch(()=>{})},30000);
  }catch(e){$('#main').innerHTML=`<p class="empty">${esc(e.message)}</p>`}
}
init();

const $=(s,r=document)=>r.querySelector(s);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money=n=>'$'+Number(n).toLocaleString('es-MX',{minimumFractionDigits:0,maximumFractionDigits:2});
const same=(a,b)=>String(a)===String(b);

/* ---------- API (misma sesión que /admin: cookie + CSRF) ---------- */
let slug=null;
const xsrf=()=>decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)||[])[1]||'');
async function api(method,path,body){
  const r=await fetch('/api/v1/panel'+path,{method,credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-XSRF-TOKEN':xsrf()},body:body?JSON.stringify(body):undefined});
  if(r.status===401){location.href='/panel';throw new Error('Sesión expirada')}
  if(r.status===419)throw new Error('La página caducó, recárgala');
  if(r.status===204)return null;
  const j=await r.json().catch(()=>({}));
  if(!r.ok)throw new Error(j.errors?Object.values(j.errors)[0][0]:(j.message||'No se pudo completar la acción'));
  return j.data!==undefined?j.data:j;
}
const P=p=>`/${slug}${p}`;

let S={reservas:[],menu:[],pedidos:[],resumen:{ventas_hoy:0},ok:false};
const L={
  async reservas(){S.reservas=(await api('GET',P('/reservas'))).map(r=>({id:r.id,n:r.nombre,p:r.personas,h:r.hora,s:r.estado,nota:r.nota||''}))},
  async pedidos(){const now=Date.now();S.pedidos=(await api('GET',P('/pedidos'))).map(p=>({id:p.id,mesa:p.mesa,its:p.detalle,tot:p.total,e:p.estado,t:now-p.minutos*60000}))},
  async menu(){S.menu=(await api('GET',P('/menu'))).map(c=>({id:c.id,nombre:c.nombre,items:c.productos.map(i=>({id:i.id,n:i.nombre,pr:i.precio,disp:i.disponible,vis:i.visible}))}))},
  async resumen(){S.resumen=await api('GET',P('/resumen'))}
};
async function sync(...keys){await Promise.all(keys.map(k=>L[k]()));render()}

let tt;function toast(m){const t=$('#toast');t.textContent=m;t.classList.add('on');clearTimeout(tt);tt=setTimeout(()=>t.classList.remove('on'),2400)}
function confirmar(msg,ok,label='Confirmar'){const d=$('#dlg');$('#dlg-msg').textContent=msg;$('#dlg-yes').textContent=label;
  $('#dlg-yes').onclick=()=>{d.close();ok()};$('#dlg-no').onclick=()=>d.close();d.showModal()}
/* ejecuta una acción; si falla avisa y vuelve a pintar con los datos del servidor */
async function run(fn){try{await fn()}catch(e){toast(e.message);render()}}

const HR=[];for(let h=12;h<=23;h++){HR.push(String(h).padStart(2,'0')+':00');HR.push(String(h).padStart(2,'0')+':30')}
const findItem=id=>{for(const c of S.menu){const i=c.items.find(x=>same(x.id,id));if(i)return [c,i]}return []};
const mins=t=>Math.max(0,Math.round((Date.now()-t)/60000));
const activos=()=>S.pedidos.filter(p=>p.e!=='entregado');
const byHora=(a,b)=>a.h<b.h?-1:a.h>b.h?1:0;

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
    <div class="grow"><strong>${esc(r.n)}</strong><div class="muted">${r.p} personas${r.nota?' · '+esc(r.nota):''}</div></div>
    ${chip}${act}${extra}</li>`;
}
V.inicio=()=>{
  const prox=[...S.reservas].filter(r=>r.s==='pendiente'||r.s==='confirmada').sort(byHora).slice(0,4);
  const agot=S.menu.flatMap(c=>c.items).filter(i=>!i.disp&&i.vis);
  const act=activos(), late=act.filter(p=>mins(p.t)>=15).length, R=S.resumen;
  return `<div class="inner"><div class="head"><div><h1>Inicio</h1><p class="muted">Resumen del servicio de hoy</p></div></div>
  <div class="stats"><div class="stat"><b>${money(R.ventas_hoy||0)}</b><span>Ventas de hoy</span></div>
   <div class="stat"><b>${R.comensales_esperados||0}</b><span>Comensales esperados</span></div>
   <div class="stat"><b>${act.length}</b><span>Pedidos activos${late?` · <b style="display:inline;font-size:inherit;color:var(--accent)">${late} tarde</b>`:''}</span></div></div>
  <section class="card"><h2>Próximas reservas</h2>${prox.length?`<ul class="list">${prox.map(r=>resRow(r,true)).join('')}</ul>`:'<p class="empty">Sin reservas pendientes.</p>'}
   <p style="margin-top:12px"><a href="#reservas" class="btn ghost">Ver todas las reservas</a></p></section>
  <section class="card"><h2>Platillos agotados</h2>${agot.length?`<ul class="list">${agot.map(i=>`<li><div class="grow"><strong>${esc(i.n)}</strong></div><button class="btn primary sm" data-act="disp" data-id="${i.id}">Volver a disponible</button></li>`).join('')}</ul>`:'<p class="empty">Todo el menú está disponible.</p>'}</section></div>`;
};
V.reservas=()=>{
  const l=[...S.reservas].sort(byHora);
  return `<div class="inner"><div class="head"><div><h1>Reservas</h1><p class="muted">${l.filter(r=>r.s!=='cancelada').length} activas hoy</p></div></div>
  <form class="card addform" data-form="res" aria-label="Nueva reserva">
   <div class="wide"><label class="f" for="rn">Nombre</label><input id="rn" name="n" required maxlength="100" autocomplete="off"></div>
   <div><label class="f" for="rp">Personas</label><input id="rp" name="p" type="number" min="1" max="30" value="2" required inputmode="numeric"></div>
   <div><label class="f" for="rh">Hora</label><select id="rh" name="h">${HR.map(h=>`<option ${h==='20:00'?'selected':''}>${h}</option>`).join('')}</select></div>
   <button class="btn primary wide" type="submit">Agregar reserva</button></form>
  <section class="card">${l.length?`<ul class="list">${l.map(r=>resRow(r)).join('')}</ul>`:'<p class="empty">No hay reservas para hoy.</p>'}</section></div>`;
};
V.menu=()=>`<div class="inner"><div class="head"><div><h1>Menú</h1><p class="muted">Edita el nombre o el precio directamente. Los cambios se guardan solos.</p></div></div>
 ${S.menu.length?S.menu.map(c=>`<section class="card cat"><header><h2>${esc(c.nombre)}</h2><span class="muted">${c.items.length} platillos</span></header>
  ${c.items.map(i=>`<div class="item ${i.vis?'':'hidden-item'}">
    <label class="switch"><input type="checkbox" data-act="disp" data-id="${i.id}" ${i.disp?'checked':''} aria-label="${esc(i.n)} disponible"><i></i><span>${i.disp?'Disponible':'Agotado'}</span></label>
    <input class="inline" value="${esc(i.n)}" aria-label="Nombre del platillo" data-f="item.n" data-id="${i.id}" maxlength="150">
    <input class="inline price" value="${i.pr}" aria-label="Precio de ${esc(i.n)}" data-f="item.pr" data-id="${i.id}" inputmode="decimal">
    <div class="acts"><button class="btn sm" data-act="dup" data-id="${i.id}">Duplicar</button>
     <button class="btn sm" data-act="vis" data-id="${i.id}">${i.vis?'Ocultar':'Mostrar'}</button>
     <button class="btn danger sm" data-act="item-del" data-id="${i.id}">Eliminar</button></div>
    ${i.vis?'':'<div class="state muted">Oculto en el sitio</div>'}</div>`).join('')}
  <form class="addrow addform" data-form="item" data-cid="${c.id}" aria-label="Agregar platillo a ${esc(c.nombre)}">
   <div class="wide"><label class="f" for="n${c.id}">Nuevo platillo</label><input id="n${c.id}" name="n" required maxlength="150" autocomplete="off"></div>
   <div><label class="f" for="p${c.id}">Precio</label><input id="p${c.id}" name="pr" required inputmode="decimal"></div>
   <button class="btn primary" type="submit">Agregar</button></form></section>`).join(''):'<p class="empty">Aún no hay categorías. Créalas en la administración avanzada.</p>'}</div>`;
const COLS=[['nuevo','Nuevo','A cocina'],['cocina','En cocina','Marcar listo'],['listo','Listo','Entregar'],['entregado','Entregado','']];
let colSel=null;
V.pedidos=()=>{
  const first=COLS.find(c=>S.pedidos.some(p=>p.e===c[0]));colSel=colSel||(first?first[0]:'nuevo');
  return `<div class="inner"><div class="head"><div><h1>Pedidos</h1><p class="muted">Un toque avanza el pedido al siguiente paso</p></div></div>
  <form class="card addform" data-form="ped" aria-label="Nuevo pedido">
   <div><label class="f" for="pm">Mesa</label><input id="pm" name="mesa" required maxlength="20" autocomplete="off"></div>
   <div><label class="f" for="pt">Total</label><input id="pt" name="tot" required inputmode="decimal"></div>
   <div class="wide"><label class="f" for="pi">Platillos</label><input id="pi" name="its" required maxlength="255" placeholder="2× Risotto, 1× Tiramisú" autocomplete="off"></div>
   <button class="btn primary wide" type="submit">Enviar a cocina</button></form>
  <div class="tabs" role="group" aria-label="Estado">${COLS.map(c=>`<button data-act="col" data-v="${c[0]}" aria-pressed="${colSel===c[0]}">${c[1]} (${S.pedidos.filter(p=>p.e===c[0]).length})</button>`).join('')}</div>
  <div class="board">${COLS.map((c,ci)=>{const l=S.pedidos.filter(p=>p.e===c[0]);
   return `<div class="col ${colSel===c[0]?'on':''}"><h2>${c[1]} <span>${l.length}</span></h2>${l.length?l.map(p=>`<div class="tk ${c[0]!=='entregado'&&mins(p.t)>=15?'late':''}"><div class="top"><span>Mesa ${esc(p.mesa)}</span><span>${mins(p.t)} min</span></div><div class="its">${esc(p.its)}</div>
     ${c[2]?`<button class="btn ${ci<3?'primary':''}" data-act="next" data-id="${p.id}">${c[2]} →</button>`:`<span class="chip done">${money(p.tot)} cobrado</span>`}</div>`).join(''):'<div class="empty">Nada aquí</div>'}</div>`}).join('')}</div></div>`;
};
V.ajustes=()=>`<div class="inner"><div class="head"><div><h1>Ajustes</h1><p class="muted">${esc(S.nombre||'')}</p></div></div>
 <section class="card"><h2>Restaurante y usuarios</h2><p class="muted" style="margin-bottom:12px">Datos del restaurante, horarios, idiomas, usuarios e invitaciones se gestionan en la administración avanzada.</p>
  <div class="row"><a class="btn primary" href="/admin">Abrir administración</a><a class="btn ghost" href="/m/${esc(slug)}" target="_blank" rel="noopener">Ver menú público</a></div></section>
 ${S.restaurantes.length>1?`<section class="card"><h2>Cambiar de restaurante</h2><select aria-label="Restaurante" data-f="rest">${S.restaurantes.map(r=>`<option value="${esc(r.slug)}" ${r.slug===slug?'selected':''}>${esc(r.nombre)}</option>`).join('')}</select></section>`:''}</div>`;

/* ---------- render / ruteo ---------- */
function route(){const r=(location.hash||'#inicio').slice(1);return V[r]?r:'inicio'}
const TITULOS={inicio:'Inicio',reservas:'Reservas',menu:'Menú',pedidos:'Pedidos',ajustes:'Ajustes'};
function render(){
  if(!S.ok)return;
  const r=route(), foco=document.activeElement&&document.activeElement.id;
  $('#main').innerHTML=V[r]();
  document.querySelectorAll('.nav a').forEach(a=>{if(a.dataset.r===r)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current')});
  const pend=S.reservas.filter(x=>x.s==='pendiente').length, act=activos().length;
  const b1=$('#b-res'),b2=$('#b-ped');b1.hidden=!pend;b1.textContent=pend;b2.hidden=!act;b2.textContent=act;
  document.title=TITULOS[r]+' · '+(S.nombre||'Panel');
  if(foco&&$('#'+foco))$('#'+foco).focus();
}
addEventListener('hashchange',()=>{render();scrollTo(0,0)});

/* ---------- acciones ---------- */
const A={
  res(el){return run(async()=>{const v=el.dataset.v;await api('PATCH',P('/reservas/'+el.dataset.id),{estado:v});
    await sync('reservas','resumen');toast({confirmada:'Reserva confirmada',llego:'Marcada como llegada',pendiente:'Reserva restaurada'}[v])})},
  'res-cancel'(el){const r=S.reservas.find(x=>same(x.id,el.dataset.id));
    confirmar(`¿Cancelar la reserva de ${r.n} a las ${r.h}?`,()=>run(async()=>{await api('PATCH',P('/reservas/'+r.id),{estado:'cancelada'});await sync('reservas','resumen');toast('Reserva cancelada')}),'Sí, cancelar')},
  disp(el){const [,i]=findItem(el.dataset.id);const nuevo=!i.disp;return run(async()=>{await api('PATCH',P('/productos/'+i.id),{disponible:nuevo});await sync('menu');toast(nuevo?`${i.n} disponible`:`${i.n} agotado`)})},
  dup(el){return run(async()=>{await api('POST',P('/productos/'+el.dataset.id+'/duplicar'));await sync('menu');toast('Platillo duplicado')})},
  vis(el){const [,i]=findItem(el.dataset.id);const nuevo=!i.vis;return run(async()=>{await api('PATCH',P('/productos/'+i.id),{visible:nuevo});await sync('menu');toast(nuevo?'Visible en el sitio':'Oculto en el sitio')})},
  'item-del'(el){const [,i]=findItem(el.dataset.id);confirmar(`¿Eliminar "${i.n}" del menú?`,()=>run(async()=>{await api('DELETE',P('/productos/'+i.id));await sync('menu');toast('Platillo eliminado')}),'Eliminar')},
  next(el){return run(async()=>{const p=await api('POST',P('/pedidos/'+el.dataset.id+'/avanzar'));colSel=colSel;
    if(!S.pedidos.some(x=>x.e===colSel&&!same(x.id,p.id)))colSel=p.estado;await sync('pedidos','resumen');toast('Pedido → '+p.estado)})},
  col(el){colSel=el.dataset.v;render()}
};
document.addEventListener('click',e=>{const el=e.target.closest('[data-act]');if(!el||el.type==='checkbox')return;e.preventDefault();A[el.dataset.act]&&A[el.dataset.act](el)});
document.addEventListener('change',e=>{const el=e.target;
  if(el.type==='checkbox'&&el.dataset.act){A[el.dataset.act](el);return}
  const f=el.dataset.f;if(!f)return;const id=el.dataset.id;
  if(f==='rest'){location.search='?r='+encodeURIComponent(el.value);return}
  if(f==='res.h')return run(async()=>{await api('PATCH',P('/reservas/'+id),{hora:el.value});await sync('reservas');toast('Reserva movida a las '+el.value)});
  if(f==='item.n'){const [,i]=findItem(id);const v=el.value.trim();if(!v){el.value=i.n;return toast('El nombre no puede estar vacío')}
    return run(async()=>{await api('PATCH',P('/productos/'+id),{nombre:v});i.n=v;toast('Nombre guardado')})}
  if(f==='item.pr'){const [,i]=findItem(id);const v=parseFloat(String(el.value).replace(/[$,]/g,''));
    if(!(v>=0)||v>100000){el.value=i.pr;return toast('Precio no válido')}
    return run(async()=>{await api('PATCH',P('/productos/'+id),{precio:v});i.pr=v;el.value=v;toast('Precio guardado')})}
});
document.addEventListener('submit',e=>{const f=e.target.dataset.form;if(!f)return;e.preventDefault();
  const form=e.target,d=Object.fromEntries(new FormData(form)),btn=form.querySelector('[type=submit]');btn.disabled=true;
  run(async()=>{
    if(f==='res'){await api('POST',P('/reservas'),{nombre:d.n.trim(),personas:parseInt(d.p)||1,hora:d.h});await sync('reservas','resumen');toast('Reserva agregada')}
    if(f==='item'){const pr=parseFloat(String(d.pr).replace(/[$,]/g,''));if(!(pr>=0))throw new Error('Precio no válido');
      await api('POST',P('/categorias/'+form.dataset.cid+'/productos'),{nombre:d.n.trim(),precio:pr});await sync('menu');toast('Platillo agregado')}
    if(f==='ped'){const tot=parseFloat(String(d.tot).replace(/[$,]/g,''));if(!(tot>=0))throw new Error('Total no válido');
      await api('POST',P('/pedidos'),{mesa:d.mesa.trim(),detalle:d.its.trim(),total:tot,estado:'cocina'});colSel='cocina';await sync('pedidos','resumen');toast('Pedido enviado a cocina')}
  }).finally(()=>{btn.disabled=false});
});

/* ---------- arranque ---------- */
async function init(){
  try{
    S.restaurantes=await api('GET','');
    if(!S.restaurantes.length){$('#main').innerHTML='<p class="empty">Tu cuenta no tiene restaurantes asignados.</p>';return}
    const pedido=new URLSearchParams(location.search).get('r');
    const r=S.restaurantes.find(x=>x.slug===pedido)||S.restaurantes[0];slug=r.slug;S.nombre=r.nombre;$('#rest-nombre').textContent=r.nombre;
    await Promise.all(Object.values(L).map(f=>f()));S.ok=true;render();
    /* tablero casi en vivo: refresca reservas y pedidos si no se está escribiendo */
    setInterval(()=>{if(document.hidden||document.activeElement.matches('input,select,textarea')||$('#dlg').open)return;sync('reservas','pedidos','resumen').catch(()=>{})},30000);
  }catch(e){$('#main').innerHTML=`<p class="empty">${esc(e.message)}</p>`}
}
init();

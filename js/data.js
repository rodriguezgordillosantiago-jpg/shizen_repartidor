/* ── Shizen — shared data layer (localStorage) ─────────────────── */

const _DEFAULTS = {
  activos: [
    { id:"P001", cliente:"Laura Gómez",     avatar:"https://i.pravatar.cc/150?img=5",  origen:"McDonald's Chapinero",    destino:"Cra 13 #67-45, Chapinero",   items:["Big Mac x2","Papas grandes","Coca-Cola"],           total:52000, estado:"aceptado",  tipo:"asap",      hora:"Hace 8 min",  distancia:"1.2 km", ganancia:4800 },
    { id:"P002", cliente:"Carlos Ruiz",     avatar:"https://i.pravatar.cc/150?img=12", origen:"Éxito Suba",              destino:"Av. Suba #115-30, Suba",      items:["Mercado básico","Productos de aseo"],               total:87000, estado:"en_camino", tipo:"asap",      hora:"Hace 22 min", distancia:"3.5 km", ganancia:6200 },
    { id:"P003", cliente:"Sofía Martínez",  avatar:"https://i.pravatar.cc/150?img=9",  origen:"Farmacia Cruz Verde",     destino:"Cll 100 #14-23, Usaquén",     items:["Medicamentos fórmula","Vitaminas"],                 total:34500, estado:"aceptado",  tipo:"scheduled", hora:"12:30 PM",    distancia:"2.1 km", ganancia:3900 },
  ],
  disponibles: [
    { id:"P004", cliente:"Andrés Torres",   avatar:"https://i.pravatar.cc/150?img=3",  origen:"Dominos Pizza Teusaquillo", destino:"Cra 24 #45-10, Teusaquillo", items:["Pizza Personal","Alitas x8","Gaseosa 2L"],          total:68000, estado:"disponible", tipo:"asap",      hora:"Ahora",       distancia:"1.8 km", ganancia:5500 },
    { id:"P005", cliente:"Valentina Herrera",avatar:"https://i.pravatar.cc/150?img=16",origen:"Rappi Turbo Kennedy",      destino:"Av. 68 #38-72, Kennedy",      items:["Encomienda pequeña"],                               total:15000, estado:"disponible", tipo:"asap",      hora:"Hace 2 min",  distancia:"0.9 km", ganancia:3200 },
    { id:"P006", cliente:"Diego Morales",   avatar:"https://i.pravatar.cc/150?img=7",  origen:"Subway Engativá",         destino:"Cll 68 #93-15, Engativá",     items:["Sub 30cm Pollo","Sub 15cm Atún","Cookies x2"],      total:45000, estado:"disponible", tipo:"scheduled", hora:"1:15 PM",     distancia:"2.7 km", ganancia:4100 },
  ],
  historial: [
    { id:"H001", cliente:"María López",     avatar:"https://i.pravatar.cc/150?img=20", origen:"KFC Chapinero",           destino:"Cra 11 #72-30",               items:["Balde familiar"],                                  total:74000, estado:"entregado",  tipo:"asap", hora:"Hoy 10:15 AM", distancia:"2.0 km", ganancia:5800 },
    { id:"H002", cliente:"Julián Castro",   avatar:"https://i.pravatar.cc/150?img=33", origen:"Presto Suba",             destino:"Av. Suba #91-10",             items:["Combo 2 personas"],                                total:38000, estado:"cancelado",  tipo:"asap", hora:"Hoy 9:40 AM",  distancia:"1.5 km", ganancia:0 },
    { id:"H003", cliente:"Ana Jiménez",     avatar:"https://i.pravatar.cc/150?img=44", origen:"Drogas La Rebaja",        destino:"Cll 45 #27-18",               items:["Antibióticos","Suero"],                             total:22000, estado:"entregado",  tipo:"asap", hora:"Hoy 9:05 AM",  distancia:"0.8 km", ganancia:2900 },
    { id:"H004", cliente:"Roberto Peña",    avatar:"https://i.pravatar.cc/150?img=52", origen:"El Corral Usaquén",       destino:"Cra 7 #115-40",               items:["Burger + Papas x3"],                               total:93000, estado:"entregado",  tipo:"asap", hora:"Ayer 7:30 PM", distancia:"3.2 km", ganancia:7200 },
  ],
  perfil: {
    nombre:"Santiago", apellido:"Vargas", telefono:"310 456 7890",
    localidad:"Chapinero", vehiculo:"Moto", calificacion:4.8,
    entregasHoy:6, gananciasHoy:25800,
  },
  mensajes: [
    { id:1, de:"soporte", texto:"¡Hola! ¿En qué te podemos ayudar hoy?",                                  hora:"9:00" },
    { id:2, de:"soporte", texto:"Recuerda que puedes reportar cualquier inconveniente con un pedido aquí.", hora:"9:01" },
  ],
};

function _get(key) {
  try {
    const s = localStorage.getItem('shizen_' + key);
    return s ? JSON.parse(s) : JSON.parse(JSON.stringify(_DEFAULTS[key]));
  } catch { return JSON.parse(JSON.stringify(_DEFAULTS[key])); }
}
function _set(key, val) {
  localStorage.setItem('shizen_' + key, JSON.stringify(val));
}

// Accessors
function getActivos()     { return _get('activos'); }
function setActivos(v)    { _set('activos', v); }
function getDisponibles() { return _get('disponibles'); }
function setDisponibles(v){ _set('disponibles', v); }
function getHistorial()   { return _get('historial'); }
function setHistorial(v)  { _set('historial', v); }
function getPerfil()      { return _get('perfil'); }
function setPerfil(v)     { _set('perfil', v); }
function getMensajes()    { return _get('mensajes'); }
function setMensajes(v)   { _set('mensajes', v); }

// Estado de disponibilidad del repartidor (persistente entre pantallas).
function repartidorEstaActivo() {
  return localStorage.getItem('shizen_repartidor_activo') !== 'false';
}

async function cambiarEstadoRepartidor() {
  try {
    const response = await fetch('../php/entregas.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'toggle' }),
      credentials: 'same-origin',
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'No fue posible cambiar la disponibilidad.');
    localStorage.setItem('shizen_repartidor_activo', String(result.online));
    actualizarBotonEstadoRepartidor();
    document.dispatchEvent(new CustomEvent('estadoRepartidorCambiado', { detail: { activo: result.online } }));
    return result.online;
  } catch (error) {
    alert(error.message);
    return repartidorEstaActivo();
  }
}

function actualizarBotonEstadoRepartidor() {
  const boton = document.getElementById('delivery-status-toggle');
  if (!boton) return;

  const activo = repartidorEstaActivo();
  boton.classList.toggle('is-online', activo);
  boton.setAttribute('aria-pressed', String(activo));
  boton.setAttribute('aria-label', activo ? 'Desactivar estado de repartidor' : 'Activar estado de repartidor');
  boton.querySelector('.nav-status-label').textContent = activo ? 'Activo' : 'Inactivo';
}

function iniciarBotonEstadoRepartidor() {
  const boton = document.getElementById('delivery-status-toggle');
  if (!boton) return;
  boton.addEventListener('click', cambiarEstadoRepartidor);
  actualizarBotonEstadoRepartidor();
}

// Helpers
const MAX_ACTIVOS = 4;
async function cargarEntregas(tipo) {
  const response = await fetch('../php/entregas.php?type=' + encodeURIComponent(tipo), { credentials: 'same-origin' });
  if (!response.ok) throw new Error('No fue posible cargar las entregas.');
  return response.json();
}

async function actualizarEntrega(action, id) {
  const body = new URLSearchParams({ action, id_entrega: String(id) });
  const response = await fetch('../php/entregas.php', { method: 'POST', body, credentials: 'same-origin' });
  const result = await response.json();
  if (!response.ok || result.updated === false) throw new Error(result.error || 'No fue posible actualizar la entrega.');
  return result;
}
function countActivos() {
  return getActivos().filter(p => p.estado === 'aceptado' || p.estado === 'en_camino').length;
}
function cupoLleno() { return countActivos() >= MAX_ACTIVOS; }

function fmt(n) { return '$' + Number(n).toLocaleString('es-CO'); }

function nowTime() {
  return new Date().toLocaleTimeString('es-CO', { hour:'2-digit', minute:'2-digit' });
}

// Actions
function aceptarPedido(id) {
  if (cupoLleno()) return false;
  const disp = getDisponibles();
  const pedido = disp.find(p => p.id === id);
  if (!pedido) return false;
  setDisponibles(disp.filter(p => p.id !== id));
  const activos = getActivos();
  activos.push({ ...pedido, estado: 'aceptado' });
  setActivos(activos);
  return true;
}

function rechazarPedido(id) {
  setDisponibles(getDisponibles().filter(p => p.id !== id));
}

function avanzarEstado(id) {
  const activos = getActivos();
  const idx = activos.findIndex(p => p.id === id);
  if (idx === -1) return;
  const p = activos[idx];
  if (p.estado === 'aceptado') {
    activos[idx] = { ...p, estado: 'en_camino' };
    setActivos(activos);
  } else if (p.estado === 'en_camino') {
    const terminado = { ...p, estado: 'entregado', hora: 'Ahora' };
    setHistorial([terminado, ...getHistorial()]);
    setActivos(activos.filter(a => a.id !== id));
    const perfil = getPerfil();
    setPerfil({ ...perfil, entregasHoy: perfil.entregasHoy + 1, gananciasHoy: perfil.gananciasHoy + p.ganancia });
  }
}

function cancelarPedido(id) {
  const activos = getActivos();
  const p = activos.find(a => a.id === id);
  if (!p) return;
  setHistorial([{ ...p, estado: 'cancelado', hora: 'Ahora' }, ...getHistorial()]);
  setActivos(activos.filter(a => a.id !== id));
}

// Update nav badges on any page that has them
function updateNavBadges() {
  const ba = document.getElementById('badge-activos');
  const bp = document.getElementById('badge-pedidos');
  const ca = countActivos();
  const cd = getDisponibles().length;
  if (ba) { ba.textContent = ca > 9 ? '9+' : ca; ba.style.display = ca > 0 ? 'flex' : 'none'; }
  if (bp) { bp.textContent = cd > 9 ? '9+' : cd; bp.style.display = cd > 0 ? 'flex' : 'none'; }
}

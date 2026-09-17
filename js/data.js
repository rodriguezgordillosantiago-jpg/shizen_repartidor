/* ── Shizen — shared data layer (localStorage) ─────────────────── */

const _DEFAULTS = {
  activos: [],
  disponibles: [],
  historial: [],
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

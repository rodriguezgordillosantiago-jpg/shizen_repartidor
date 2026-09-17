/* ── Shizen — floating chat panel ─────────────────────────────── */

function initChat() {
  // Inject HTML
  const root = document.getElementById('chat-root');
  if (!root) return;
  root.innerHTML = `
    <!-- FAB button -->
    <button id="chat-fab" onclick="openChat()"
      style="position:absolute;bottom:76px;right:14px;width:52px;height:52px;
             background:linear-gradient(135deg,#4c9540,#2d6b22);
             border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;
             box-shadow:0 4px 16px rgba(76,149,64,.45);cursor:pointer;z-index:50;
             transition:transform .2s,box-shadow .2s">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
      </svg>
      <span id="chat-notif" style="position:absolute;top:-2px;right:-2px;width:14px;height:14px;
        background:#f97316;border-radius:50%;border:2px solid white;display:block"></span>
    </button>

    <!-- Chat panel -->
    <div id="chat-panel"
      style="position:absolute;bottom:68px;left:0;right:0;height:400px;background:white;
             border-radius:20px 20px 0 0;box-shadow:0 -8px 32px rgba(0,0,0,.15);
             display:none;flex-direction:column;z-index:51;overflow:hidden">

      <!-- Header -->
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid rgba(0,0,0,.07)">
        <div style="display:flex;align-items:center;gap:10px">
          <img src="../assets/logo-color.png" alt="Shizen" style="height:30px;width:80px;object-fit:contain">
          <div>
            <p style="margin:0;font-size:13px;font-weight:700;color:#1a1a1a">Soporte Shizen</p>
            <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#22c55e;font-weight:600">
              <span style="width:7px;height:7px;border-radius:50%;background:#22c55e;display:inline-block"></span>
              En línea
            </span>
          </div>
        </div>
        <button onclick="closeChat()" style="background:none;border:none;cursor:pointer;padding:4px;border-radius:50%;line-height:0">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#6b7280" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Messages -->
      <div id="chat-msgs" style="flex:1;overflow-y:auto;padding:12px 14px;display:flex;flex-direction:column;gap:10px;
        -ms-overflow-style:none;scrollbar-width:none"></div>

      <!-- Input -->
      <div style="padding:10px 12px;border-top:1px solid rgba(0,0,0,.07);display:flex;gap:8px;align-items:center">
        <input id="chat-input" type="text" placeholder="Escribe un mensaje..."
          onkeydown="if(event.key==='Enter')sendMsg()"
          style="flex:1;background:#f4f4f5;border:none;border-radius:24px;padding:8px 14px;
                 font-size:13px;font-family:'Nunito',sans-serif;outline:none">
        <button onclick="sendMsg()"
          style="width:36px;height:36px;border-radius:50%;background:#4c9540;border:none;cursor:pointer;
                 display:flex;align-items:center;justify-content:center;shrink-0;
                 transition:background .2s">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
          </svg>
        </button>
      </div>
    </div>
  `;

  renderMessages();
}

function renderMessages() {
  const container = document.getElementById('chat-msgs');
  if (!container) return;
  const msgs = getMensajes();
  container.innerHTML = msgs.map(m => {
    const isMe = m.de === 'yo';
    return `
      <div style="display:flex;justify-content:${isMe ? 'flex-end' : 'flex-start'}">
        <div style="max-width:75%;padding:8px 12px;border-radius:${isMe ? '16px 16px 4px 16px' : '16px 16px 16px 4px'};
          background:${isMe ? '#4c9540' : '#f1f1f1'};color:${isMe ? 'white' : '#1a1a1a'}">
          <p style="margin:0;font-size:12px;line-height:1.4">${m.texto}</p>
          <p style="margin:2px 0 0;font-size:9px;color:${isMe ? 'rgba(255,255,255,.6)' : '#9ca3af'}">${m.hora}</p>
        </div>
      </div>`;
  }).join('');
  container.scrollTop = container.scrollHeight;
}

function openChat() {
  document.getElementById('chat-fab').style.display = 'none';
  document.getElementById('chat-panel').style.display = 'flex';
  renderMessages();
}

function closeChat() {
  document.getElementById('chat-panel').style.display = 'none';
  document.getElementById('chat-fab').style.display = 'flex';
}

function sendMsg() {
  const input = document.getElementById('chat-input');
  const text = input.value.trim();
  if (!text) return;
  const msgs = getMensajes();
  msgs.push({ id: Date.now(), de: 'yo', texto: text, hora: nowTime() });
  setMensajes(msgs);
  input.value = '';
  renderMessages();
  setTimeout(() => {
    const m2 = getMensajes();
    m2.push({ id: Date.now() + 1, de: 'soporte', texto: 'Recibido, en breve te atendemos. ¡Gracias! 🙌', hora: nowTime() });
    setMensajes(m2);
    renderMessages();
  }, 900);
}

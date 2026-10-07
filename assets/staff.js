/* Staff app: polls the feed, rings on new requests, push notifications, rooms and codes. */
(function () {
  'use strict';
  var W = window.STAFF;
  var $ = function (id) { return document.getElementById(id); };
  var state = { requests: [], scheduled: [], rooms: [], zones: [], departments: [], following: '' };
  var fetchedAt = Date.now(), seen = null, audio = null, wakeLock = null, pollTimer = null, failures = 0;

  // ---------------------------------------------------------------- API
  function get(action) {
    return fetch(W.api + '?a=' + action, { credentials: 'same-origin', cache: 'no-store' }).then(handle);
  }
  function post(action, data) {
    data = data || {}; data.a = action;
    return fetch(W.api, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': W.csrf },
      body: JSON.stringify(data)
    }).then(handle);
  }
  function handle(r) {
    if (r.status === 401) { location.reload(); throw new Error('login'); }
    return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || 'error'); return j; });
  }
  function toast(msg) {
    var t = $('toast'); t.textContent = msg; t.hidden = false;
    clearTimeout(toast.h); toast.h = setTimeout(function () { t.hidden = true; }, 3000);
  }

  // ---------------------------------------------------------------- feed
  function apply(data) {
    if (!data || !data.requests) return;
    var ring = null;
    if (seen) {
      data.requests.forEach(function (c) {
        var prev = seen[c.id];
        if (c.status === 'open' && (prev === undefined || c.bump > prev)) ring = ring === 'urgent' || c.urgent ? 'urgent' : 'normal';
      });
    }
    seen = {};
    data.requests.forEach(function (c) { seen[c.id] = c.bump; });
    state = data; fetchedAt = Date.now();
    render();
    if (ring) alertNew(ring);
  }
  function refresh() {
    return get('feed').then(function (d) { failures = 0; setConn(true); apply(d); })
      .catch(function () { failures++; if (failures > 1) setConn(false); });
  }
  function schedule() {
    clearTimeout(pollTimer);
    pollTimer = setTimeout(function () { refresh().then(schedule, schedule); }, document.hidden ? 15000 : 3000);
  }
  function setConn(ok) { $('conn').className = 'conn ' + (ok ? 'ok' : 'bad'); }
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) { refresh(); requestWakeLock(); }
    schedule();
  });

  // ---------------------------------------------------------------- render
  function ago(sec) {
    sec += Math.round((Date.now() - fetchedAt) / 1000);
    if (sec < 60) return 'adesso';
    var m = Math.floor(sec / 60);
    return m < 60 ? m + ' min fa' : Math.floor(m / 60) + ' h ' + (m % 60) + ' min fa';
  }
  function dueText(c) {
    if (!c.due) return '';
    var d = c.due.slice(11), today = new Date().toISOString().slice(0, 10) === c.due.slice(0, 10);
    var t = (today ? 'oggi' : 'domani') + ' alle ' + d;
    var secs = c.due_in - Math.round((Date.now() - fetchedAt) / 1000);
    if (secs > 0 && secs < 3600 * 6) t += ' (tra ' + Math.max(1, Math.round(secs / 60)) + ' min)';
    return t;
  }
  function rname(label) { return /^\d+[A-Za-z]?$/.test(label) ? 'Camera ' + label : label; }
  function money(v) { return '€ ' + v.toFixed(2).replace('.', ','); }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }

  function card(c, isSched) {
    var age = c.age + Math.round((Date.now() - fetchedAt) / 1000);
    var cls = 'call ' + c.status + (c.urgent ? ' urgent' : '') + (c.escalated ? ' late escalated' : c.status === 'open' && age > 600 ? ' late' : c.status === 'open' && age > 240 ? ' slow' : '');
    var box = el('article', cls);
    if (c.urgent) box.appendChild(el('div', 'call-alert', '🚨 URGENTE: avvisato tutto il personale'));
    else if (c.escalated) box.appendChild(el('div', 'call-alert', '⚠️ Nessuno ha risposto: avvisato tutto il personale'));
    var head = el('div', 'call-head');
    head.appendChild(el('span', 'call-table', c.label));
    if (c.zone) head.appendChild(el('span', 'call-zone', c.zone));
    if (c.guest) head.appendChild(el('span', 'call-guest', c.guest));
    if (c.dnd) head.appendChild(el('span', 'call-dnd', '🔕 non disturbare'));
    box.appendChild(head);
    var what = el('div', 'call-what', (c.icon ? c.icon + ' ' : '') + c.what);
    if (c.dept) what.appendChild(el('span', 'call-dept', (c.dept_icon ? c.dept_icon + ' ' : '') + c.dept));
    box.appendChild(what);
    if (c.due) box.appendChild(el('div', 'call-due', '🕒 ' + dueText(c)));
    if (c.items.length) {
      var ul = el('ul', 'call-items');
      c.items.forEach(function (i) { ul.appendChild(el('li', null, i.qty + '× ' + i.name + (i.price != null ? ' · ' + money(i.price * i.qty) : ''))); });
      if (c.total != null) ul.appendChild(el('li', 'call-total', 'Totale ' + money(c.total)));
      box.appendChild(ul);
    }
    if (c.note) box.appendChild(el('div', 'call-note', '“' + c.note + '”'));
    if (c.reply) box.appendChild(el('div', 'call-reply', '💬 ' + c.reply));
    var meta = isSched ? 'inviata ' + ago(c.age) : ago(c.age);
    if (c.repeat) meta += ' · sollecitato ' + c.repeat + '×';
    if (c.status === 'taken') meta += ' · preso da ' + (c.mine ? 'te' : c.taken_name);
    box.appendChild(el('div', 'call-meta', meta));
    var btns = el('div', 'call-btns');
    if (c.status === 'open') {
      var take = el('button', 'btn', 'Prendo io');
      take.onclick = function () { take.disabled = true; post('take', { id: c.id }).then(apply).catch(errToast); };
      btns.appendChild(take);
    }
    var reply = el('button', 'btn', '💬');
    reply.title = 'Rispondi all\'ospite';
    reply.onclick = function () { openReply(c); };
    btns.appendChild(reply);
    var done = el('button', 'btn primary', 'Fatto');
    done.onclick = function () { done.disabled = true; post('done', { id: c.id }).then(apply).catch(errToast); };
    btns.appendChild(done);
    box.appendChild(btns);
    return box;
  }

  function render() {
    var list = $('tab-calls'); list.innerHTML = '';
    var open = state.requests.filter(function (c) { return c.status === 'open'; }).length;
    $('callCount').textContent = open; $('callCount').hidden = !open;
    $('schedCount').textContent = state.scheduled.length; $('schedCount').hidden = !state.scheduled.length;
    document.title = (open ? '(' + open + ') ' : '') + 'RoomHotel';
    var line = $('followLine');
    line.hidden = !state.following || state.following === 'Tutti i reparti';
    line.textContent = '📍 Segui: ' + (state.following || '') + ' · cambia';

    if (!state.requests.length) list.appendChild(el('p', 'empty', 'Nessuna richiesta in attesa 👌'));
    state.requests.forEach(function (c) { list.appendChild(card(c, false)); });

    var sl = $('tab-sched'); sl.innerHTML = '';
    if (!state.scheduled.length) sl.appendChild(el('p', 'empty', 'Nessuna richiesta programmata. Qui vedi sveglie, colazioni e altre richieste con un orario: arrivano tra le richieste al momento giusto.'));
    state.scheduled.forEach(function (c) { sl.appendChild(card(c, true)); });
    renderRooms();
  }

  function renderRooms() {
    var grid = $('roomGrid'), q = $('roomSearch').value.trim().toLowerCase();
    grid.innerHTML = '';
    var active = {};
    state.requests.forEach(function (c) { active[c.room_id] = (active[c.room_id] || '') + (c.icon || '•'); });
    var zone = null;
    state.rooms.forEach(function (r) {
      if (q && (r.label + ' ' + (r.zone || '') + ' ' + (r.guest || '')).toLowerCase().indexOf(q) < 0) return;
      if ((r.zone || '') !== zone) {
        zone = r.zone || '';
        if (state.zones.length) grid.appendChild(el('h3', 'zone-title', zone || 'Senza piano'));
      }
      var c = el('button', 'tcard' + (active[r.id] ? ' busy' : '') + (r.dnd ? ' dnd' : ''));
      c.appendChild(el('span', 'tlabel', r.label));
      c.appendChild(el('span', 'tguest', r.guest || ''));
      c.appendChild(el('span', 'tflag', (r.dnd ? '🔕 ' : '') + (active[r.id] || '')));
      c.onclick = function () { openRoom(r); };
      grid.appendChild(c);
    });
    if (!grid.children.length) grid.appendChild(el('p', 'empty', 'Nessuna camera.'));
  }

  // Codes list (menu › Codici camere): what reception reads to the guest at check-in.
  function renderCodes() {
    var box = $('codesList'), q = $('codeSearch').value.trim().toLowerCase();
    box.innerHTML = '';
    var zone = null;
    state.rooms.forEach(function (r) {
      if (q && (r.label + ' ' + (r.zone || '') + ' ' + (r.guest || '')).toLowerCase().indexOf(q) < 0) return;
      if ((r.zone || '') !== zone) {
        zone = r.zone || '';
        if (state.zones.length) box.appendChild(el('h3', 'zone-title', zone || 'Senza piano'));
      }
      var row = el('div', 'code-row');
      row.appendChild(el('span', 'code-room', rname(r.label)));
      row.appendChild(el('span', 'code-guest', r.guest || ''));
      row.appendChild(el('span', 'code-val', r.code || '—'));
      box.appendChild(row);
    });
    if (!box.children.length) box.appendChild(el('p', 'empty', 'Nessuna camera.'));
  }
  $('codeSearch').oninput = renderCodes;
  $('codesBtn').onclick = function () {
    $('menuDialog').close();
    $('codeSearch').value = '';
    renderCodes();
    $('codesDialog').showModal();
  };

  function errToast() { toast('Operazione non riuscita, riprova.'); refresh(); }
  document.querySelectorAll('[data-close]').forEach(function (b) { b.onclick = function () { b.closest('dialog').close(); }; });

  document.querySelectorAll('.w-tabs button').forEach(function (b) {
    b.onclick = function () {
      document.querySelectorAll('.w-tabs button').forEach(function (x) { x.classList.toggle('on', x === b); });
      $('tab-calls').hidden = b.dataset.tab !== 'calls';
      $('tab-sched').hidden = b.dataset.tab !== 'sched';
      $('tab-rooms').hidden = b.dataset.tab !== 'rooms';
    };
  });
  $('roomSearch').oninput = renderRooms;
  setInterval(function () { if (!document.hidden) render(); }, 30000);

  // ---------------------------------------------------------------- reply to the guest
  var replyFor = null;
  function openReply(c) {
    replyFor = c;
    $('replyTitle').textContent = 'Rispondi a ' + rname(c.label);
    $('replyText').value = '';
    $('replyDialog').showModal();
  }
  $('quickReplies').querySelectorAll('[data-reply]').forEach(function (b) {
    b.onclick = function () { $('replyText').value = b.dataset.reply; };
  });
  $('replySend').onclick = function () {
    var text = $('replyText').value.trim();
    if (!text || !replyFor) return;
    $('replyDialog').close();
    post('reply', { id: replyFor.id, text: text }).then(function (d) { apply(d); toast('Risposta inviata'); }).catch(errToast);
  };

  // ---------------------------------------------------------------- rooms: check-in / check-out
  var roomFor = null;
  function openRoom(r) {
    roomFor = r;
    $('roomTitle').textContent = rname(r.label) + (r.zone ? ' · ' + r.zone : '');
    $('roomInfo').textContent = 'Codice attuale: ' + (r.code || '—') + (r.code_age >= 60 ? ' · da ' + Math.floor(r.code_age / 60) + ' h' : '') + (r.dnd ? ' · 🔕 non disturbare' : '');
    $('roomGuest').value = r.guest || '';
    $('roomDialog').showModal();
  }
  $('roomSaveGuest').onclick = function () {
    if (!roomFor) return;
    $('roomDialog').close();
    post('checkin', { room_id: roomFor.id, guest_name: $('roomGuest').value }).then(function (d) { apply(d); toast('Ospite salvato'); }).catch(errToast);
  };
  $('roomCheckout').onclick = function () {
    if (!roomFor) return;
    if (!confirm('Check-out di ' + rname(roomFor.label) + '? Il codice ' + roomFor.code + ' smette di funzionare, le richieste aperte vengono chiuse e la camera riceve un nuovo codice.')) return;
    $('roomDialog').close();
    post('checkout', { room_id: roomFor.id }).then(function (d) {
      apply(d);
      var nr = d.rooms.filter(function (x) { return x.id === roomFor.id; })[0];
      if (nr) toast(rname(nr.label) + ': nuovo codice ' + nr.code);
    }).catch(errToast);
  };

  // ---------------------------------------------------------------- sound
  function tone(freq, start, dur) {
    var o = audio.createOscillator(), g = audio.createGain();
    o.type = 'sine'; o.frequency.value = freq;
    g.gain.setValueAtTime(0.0001, audio.currentTime + start);
    g.gain.exponentialRampToValueAtTime(0.6, audio.currentTime + start + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + start + dur);
    o.connect(g); g.connect(audio.destination);
    o.start(audio.currentTime + start); o.stop(audio.currentTime + start + dur + 0.05);
  }
  function alertNew(kind) {
    if (navigator.vibrate) navigator.vibrate(kind === 'urgent' ? [400, 100, 400, 100, 400] : [250, 100, 250]);
    if (!audio) return;
    if (audio.state === 'suspended') audio.resume();
    if (kind === 'urgent') { for (var i = 0; i < 4; i++) { tone(1200, i * 0.3, 0.12); tone(900, i * 0.3 + 0.15, 0.12); } }
    else { tone(988, 0, 0.35); tone(784, 0.4, 0.5); }
  }
  function requestWakeLock() {
    if (!audio || !('wakeLock' in navigator) || document.hidden) return;
    navigator.wakeLock.request('screen').then(function (l) { wakeLock = l; }).catch(function () {});
  }

  // ---------------------------------------------------------------- push
  var pushSupported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  var isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;

  function b64ToBytes(s) {
    var pad = '='.repeat((4 - s.length % 4) % 4), raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }
  function pushState() {
    if (!pushSupported) {
      return Promise.resolve(isIos && !standalone
        ? 'Su iPhone le notifiche funzionano solo dall\'app sulla schermata Home: vedi «Installa l\'app su iPhone».'
        : 'Questo browser non supporta le notifiche push: tieni l\'app aperta per sentire le richieste.');
    }
    if (Notification.permission === 'denied') return Promise.resolve('Notifiche bloccate: abilitale nelle impostazioni del browser per questo sito.');
    return navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); })
      .then(function (sub) { return sub ? 'Notifiche attive su questo dispositivo ✓' : 'Notifiche non ancora attive.'; });
  }
  function subscribe() {
    if (!pushSupported) return Promise.resolve(false);
    return Notification.requestPermission().then(function (perm) {
      if (perm !== 'granted') return false;
      return navigator.serviceWorker.ready.then(function (reg) {
        return reg.pushManager.getSubscription().then(function (sub) {
          return sub || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(W.vapid) });
        });
      }).then(function (sub) { return post('push_subscribe', sub.toJSON()).then(function () { return true; }); });
    }).catch(function () { return false; });
  }
  $('enableBtn').onclick = function () {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      audio.resume(); tone(880, 0, 0.12);
    } catch (e) { audio = null; }
    requestWakeLock();
    subscribe().then(function (ok) {
      $('enable').hidden = true;
      toast(ok ? 'Suono e notifiche attivi' : 'Suono attivo (tieni l\'app aperta)');
    });
  };
  if (pushSupported) navigator.serviceWorker.register('sw.js').catch(function () {});
  if (isIos && !standalone) $('iosInstall').hidden = false;
  $('enable').hidden = false;
  pushState().then(function (txt) {
    if (/attive/.test(txt)) $('enableText').textContent = 'Tocca per attivare il suono delle richieste.';
  });

  // ---------------------------------------------------------------- options: departments and floors followed
  function chip(value, text, checked, kind) {
    var lab = el('label', 'chip ' + kind), cb = el('input');
    cb.type = 'checkbox'; cb.value = value; cb.checked = checked; cb.dataset.kind = kind;
    lab.appendChild(cb); lab.appendChild(document.createTextNode(' ' + text));
    return lab;
  }
  function saveFollow() {
    var box = $('followList'), depts = [], zones = [];
    box.querySelectorAll('input:checked').forEach(function (cb) {
      if (cb.dataset.kind === 'dept') depts.push(+cb.value); else zones.push(cb.value);
    });
    post('set_follow', { departments: depts, zones: zones }).then(function (d) { seen = null; apply(d); }).catch(errToast);
  }
  function openOptions() {
    var box = $('followList');
    get('follow').then(function (f) {
      box.innerHTML = '';
      var g = el('div', 'follow-zone');
      g.appendChild(el('div', 'follow-zone-head', 'Reparti'));
      var l = el('div', 'chips');
      f.departments.forEach(function (d) { l.appendChild(chip(d.id, (d.icon ? d.icon + ' ' : '') + d.name, f.my_departments.indexOf(d.id) >= 0, 'dept')); });
      g.appendChild(l); box.appendChild(g);
      if (f.zones.length) {
        var z = el('div', 'follow-zone');
        z.appendChild(el('div', 'follow-zone-head', 'Piani'));
        var zl = el('div', 'chips');
        f.zones.forEach(function (name) { zl.appendChild(chip(name, name, f.my_zones.indexOf(name) >= 0, 'zone')); });
        z.appendChild(zl); box.appendChild(z);
      }
    }).catch(errToast);
    pushState().then(function (t) { $('pushState').textContent = t; });
    $('menuDialog').showModal();
  }
  $('followList').addEventListener('change', saveFollow);
  $('followAll').onclick = function () {
    $('followList').querySelectorAll('input').forEach(function (cb) { cb.checked = false; });
    saveFollow();
  };
  $('menuBtn').onclick = openOptions;
  $('followLine').onclick = openOptions;
  $('pushTest').onclick = function () {
    subscribe().then(function (ok) {
      if (!ok) { pushState().then(toast); return; }
      post('push_test').then(function (r) {
        toast(r.sent ? 'Notifica inviata: dovrebbe arrivare tra pochi secondi' : 'Invio non riuscito');
        pushState().then(function (t) { $('pushState').textContent = t; });
      }).catch(errToast);
    });
  };

  refresh().then(schedule, schedule);
})();

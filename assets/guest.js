/* Guest page of a room: code, requests to each department, live status, do not disturb. */
(function () {
  'use strict';
  var G = window.GUEST, T = G.t;
  var $ = function (id) { return document.getElementById(id); };
  var codeBox = $('codeBox'), actions = $('actions'), statusBox = $('status'), toastEl = $('toast');
  var pollTimer = null, requests = [], openMap = {}, dnd = G.dnd, current = null, qty = {};

  function api(data) {
    data.k = G.token;
    return fetch(G.api, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }).then(function (r) {
      return r.json().catch(function () { return { error: 'error' }; });
    });
  }

  function toast(msg, isErr) {
    toastEl.textContent = msg;
    toastEl.className = 'toast' + (isErr ? ' err' : '');
    toastEl.hidden = false;
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { toastEl.hidden = true; }, 3500);
  }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  function money(v) { return '€ ' + v.toFixed(2).replace('.', ','); }

  // ---------------------------------------------------------------- code
  function showCode(expired) {
    actions.hidden = true;
    statusBox.innerHTML = '';
    codeBox.hidden = false;
    if (expired && !$('expiredMsg')) {
      var p = el('p', 'notice', T.expired); p.id = 'expiredMsg';
      codeBox.insertBefore(p, codeBox.firstChild);
    }
    $('code').value = '';
    stopPoll();
  }
  function showActions() {
    codeBox.hidden = true;
    actions.hidden = false;
    startPoll();
  }
  function handleError(res) {
    if (res.error === 'expired') { showCode(true); return true; }
    if (res.error === 'code') { showCode(false); return true; }
    return false;
  }

  $('codeForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = $('codeBtn'), err = $('codeErr');
    btn.disabled = true; err.hidden = true;
    api({ a: 'verify', code: $('code').value }).then(function (res) {
      btn.disabled = false;
      if (res.ok) {
        var ex = $('expiredMsg'); if (ex) ex.remove();
        showActions(); apply(res);
      } else {
        err.textContent = T[res.error] || T.error; err.hidden = false;
        $('code').select();
      }
    }).catch(function () { btn.disabled = false; err.textContent = T.error; err.hidden = false; });
  });

  // ---------------------------------------------------------------- departments and buttons
  function buildDepts() {
    var box = $('deptList'); box.innerHTML = '';
    G.depts.forEach(function (d) {
      var sec = el('section', 'dept'); sec.dataset.id = d.id;
      var head = el('div', 'dept-head');
      head.appendChild(el('span', 'dept-name', (d.icon ? d.icon + ' ' : '') + d.name));
      if (d.hours) head.appendChild(el('span', 'dept-hours', T.hours.replace('{hours}', d.hours)));
      sec.appendChild(head);
      var grid = el('div', 'guest-actions');
      d.types.forEach(function (t) {
        var b = el('button', 'btn action' + (t.urgent ? ' urgent' : ''));
        b.type = 'button'; b.dataset.type = t.id;
        b.appendChild(el('span', 'ico', t.icon || '•'));
        b.appendChild(document.createTextNode(t.name));
        b.onclick = function () { openRequest(d, t); };
        grid.appendChild(b);
      });
      sec.appendChild(grid);
      box.appendChild(sec);
    });
    openMap = {};
    G.depts.forEach(function (d) { openMap[d.id] = d.open; });
    syncOpen();
  }
  function syncOpen() {
    document.querySelectorAll('.dept').forEach(function (sec) {
      var open = openMap[sec.dataset.id] !== false;
      sec.classList.toggle('closed', !open);
      var hrs = sec.querySelector('.dept-hours');
      if (hrs && !open) hrs.textContent = T.closed_now + ' · ' + hrs.textContent.replace(/^.*?· /, '');
    });
  }

  // ---------------------------------------------------------------- status
  function apply(res) {
    if (res.requests) requests = res.requests;
    if (res.open) { openMap = res.open; syncOpen(); }
    if (typeof res.dnd === 'boolean') dnd = res.dnd;
    render();
  }
  // "Fatto" rows the guest closed with ✕ (they would disappear by themselves after 3 minutes anyway).
  var dismissed = {};
  try { (JSON.parse(localStorage.getItem('rh_dismissed') || '[]')).forEach(function (id) { dismissed[id] = true; }); } catch (e) {}
  function dismiss(id) {
    dismissed[id] = true;
    try { localStorage.setItem('rh_dismissed', JSON.stringify(Object.keys(dismissed).slice(-50))); } catch (e) {}
    render();
  }

  function render() {
    statusBox.innerHTML = '';
    var visible = requests.filter(function (r) { return !(r.status === 'done' && dismissed[r.id]); });
    if (visible.length) statusBox.appendChild(el('h2', 'status-h', T.your_requests));
    visible.forEach(function (r) {
      var div = el('div', 'status-item ' + r.status + (r.urgent ? ' urgent' : ''));
      var main = el('div', 'status-main');
      main.appendChild(el('strong', null, (r.icon ? r.icon + ' ' : '') + r.name));
      var text = r.status === 'done' ? T.st_done
        : r.status === 'scheduled' ? T.st_scheduled.replace('{time}', r.due || '')
        : r.status === 'taken' ? (r.staff ? T.st_taken.replace('{name}', r.staff) : T.st_taken_x)
        : T.st_open;
      if (r.status === 'open' && r.due) text = T.st_scheduled.replace('{time}', r.due) + ' ' + text;
      main.appendChild(el('span', 'status-text', text));
      if (r.items.length) main.appendChild(el('span', 'status-detail', r.items.join(', ') + (r.total != null ? ' · ' + T.total + ' ' + money(r.total) : '')));
      if (r.note) main.appendChild(el('span', 'status-detail', '“' + r.note + '”'));
      if (r.reply) main.appendChild(el('span', 'status-reply', '💬 ' + r.reply));
      div.appendChild(main);
      if (r.status === 'open' || r.status === 'scheduled') {
        var b = el('button', 'link', T.cancel); b.type = 'button';
        b.onclick = function () {
          api({ a: 'cancel', id: r.id }).then(function (res) { if (!handleError(res)) apply(res); });
        };
        div.appendChild(b);
      } else if (r.status === 'done') {
        var x = el('button', 'close-x', '✕'); x.type = 'button'; x.setAttribute('aria-label', 'OK');
        x.onclick = function () { dismiss(r.id); };
        div.appendChild(x);
      }
      statusBox.appendChild(div);
    });
    $('dndBtn').classList.toggle('on', dnd);
    $('dndText').textContent = dnd ? T.dnd_on : T.dnd;
  }

  function refresh() {
    if (document.hidden) return;
    api({ a: 'status' }).then(function (res) {
      if (!handleError(res) && res.ok) apply(res);
    }).catch(function () {});
  }
  function startPoll() { stopPoll(); refresh(); pollTimer = setInterval(refresh, 6000); }
  function stopPoll() { if (pollTimer) clearInterval(pollTimer); pollTimer = null; }
  document.addEventListener('visibilitychange', function () { if (!document.hidden && pollTimer) refresh(); });

  // ---------------------------------------------------------------- do not disturb
  $('dndBtn').onclick = function () {
    api({ a: 'dnd', on: !dnd }).then(function (res) { if (!handleError(res)) apply(res); }).catch(function () { toast(T.error, true); });
  };

  // ---------------------------------------------------------------- request sheet
  var dialog = $('reqDialog');
  function openRequest(dept, type) {
    if (openMap[dept.id] === false) { toast(T.closed_msg.replace('{hours}', dept.hours), true); return; }
    current = { dept: dept, type: type }; qty = {};
    $('reqTitle').textContent = (type.icon ? type.icon + ' ' : '') + type.name;
    $('reqUrgent').hidden = !type.urgent;
    $('reqErr').hidden = true;
    $('reqNote').value = '';
    $('reqNote').placeholder = type.hint || T.note_ph;
    $('reqTime').hidden = !type.time;
    if (type.time) {
      dialog.querySelector('input[name=when][value=now]').checked = true;
      var d = new Date(); d.setMinutes(d.getMinutes() + 30);
      $('reqClock').value = ('0' + d.getHours()).slice(-2) + ':' + ('0' + (Math.ceil(d.getMinutes() / 5) * 5 % 60)).slice(-2);
    }
    var box = $('reqItems'); box.innerHTML = ''; box.hidden = !type.items;
    if (type.items) {
      box.appendChild(el('label', 'req-label', T.items_label));
      var cat = null;
      dept.items.forEach(function (it) {
        if ((it.category || '') !== cat) { cat = it.category || ''; if (cat) box.appendChild(el('div', 'item-cat', cat)); }
        var row = el('div', 'item-row');
        var name = el('span', 'item-name', it.name);
        if (it.price != null) name.appendChild(el('small', null, ' ' + money(it.price)));
        row.appendChild(name);
        var ctl = el('span', 'item-qty');
        var minus = el('button', 'qty-btn', '−'), num = el('span', 'qty-num', '0'), plus = el('button', 'qty-btn', '+');
        minus.type = plus.type = 'button';
        minus.onclick = function () { qty[it.id] = Math.max(0, (qty[it.id] || 0) - 1); num.textContent = qty[it.id]; row.classList.toggle('picked', qty[it.id] > 0); updateTotal(); };
        plus.onclick = function () { qty[it.id] = Math.min(20, (qty[it.id] || 0) + 1); num.textContent = qty[it.id]; row.classList.add('picked'); updateTotal(); };
        ctl.appendChild(minus); ctl.appendChild(num); ctl.appendChild(plus);
        row.appendChild(ctl);
        box.appendChild(row);
      });
      var tot = el('div', 'item-total'); tot.id = 'reqTotal'; box.appendChild(tot);
      updateTotal();
    }
    dialog.showModal();
  }
  function updateTotal() {
    var tot = $('reqTotal'); if (!tot || !current) return;
    var sum = 0, any = false, priced = false;
    current.dept.items.forEach(function (it) {
      var q = qty[it.id] || 0;
      if (q) { any = true; if (it.price != null) { priced = true; sum += q * it.price; } }
    });
    tot.textContent = any && priced ? T.total + ': ' + money(sum) : '';
  }
  // When the chosen time is earlier than now, the guest means tomorrow.
  $('reqClock').addEventListener('change', function () {
    var now = new Date(), v = $('reqClock').value;
    var hm = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
    var when = v && v < hm ? 'tomorrow' : 'today';
    dialog.querySelector('input[name=when][value=' + when + ']').checked = true;
  });
  dialog.querySelectorAll('input[name=when]').forEach(function (r) {
    r.addEventListener('change', function () { if (r.value !== 'now') $('reqClock').focus(); });
  });

  $('reqForm').addEventListener('submit', function (e) {
    if (e.submitter && e.submitter.value === 'cancel') return;
    e.preventDefault();
    if (!current) return;
    var t = current.type, err = $('reqErr');
    var data = { a: 'request', type_id: t.id, note: $('reqNote').value };
    if (t.time) {
      data.when = dialog.querySelector('input[name=when]:checked').value;
      data.time = $('reqClock').value;
      if (data.when !== 'now' && !data.time) { err.textContent = T.pick_time; err.hidden = false; return; }
    }
    if (t.items) {
      data.items = [];
      Object.keys(qty).forEach(function (id) { if (qty[id] > 0) data.items.push({ id: +id, qty: qty[id] }); });
      if (!data.items.length) { err.textContent = T.pick_items; err.hidden = false; return; }
    }
    var btn = $('reqSend'); btn.disabled = true; err.hidden = true;
    api(data).then(function (res) {
      btn.disabled = false;
      if (handleError(res)) { dialog.close(); return; }
      if (!res.ok) {
        if (res.error === 'closed') { dialog.close(); toast(T.closed_msg.replace('{hours}', res.hours || ''), true); refresh(); return; }
        err.textContent = T[res.error] || T.error; err.hidden = false; return;
      }
      dialog.close();
      apply(res);
      toast(res.result === 'wait' ? T.wait : res.result === 'repeated' ? T.reminded : res.result === 'scheduled' ? T.scheduled : T.sent);
      if (navigator.vibrate) navigator.vibrate(60);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }).catch(function () { btn.disabled = false; err.textContent = T.error; err.hidden = false; });
  });

  // ---------------------------------------------------------------- voice request (Web Speech API)
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  var NUMS = { it: { un: 1, uno: 1, una: 1, due: 2, tre: 3, quattro: 4, cinque: 5, sei: 6 },
               en: { a: 1, an: 1, one: 1, two: 2, three: 3, four: 4, five: 5, six: 6 },
               de: { ein: 1, eine: 1, einen: 1, zwei: 2, drei: 3, vier: 4, 'fünf': 5, sechs: 6 },
               fr: { un: 1, une: 1, deux: 2, trois: 3, quatre: 4, cinq: 5, six: 6 },
               es: { un: 1, una: 1, uno: 1, dos: 2, tres: 3, cuatro: 4, cinco: 5, seis: 6 } }[G.lang] || {};
  function norm(s) { return (' ' + s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, ' ') + ' ').replace(/\s+/g, ' '); }
  function has(text, word) { return text.indexOf(' ' + norm(word).trim() + ' ') >= 0; }
  // Score every button against the sentence: keywords (multi-word ones weigh more) and the button's own names.
  function matchType(text) {
    var best = null, bestScore = 0;
    G.depts.forEach(function (d) {
      d.types.forEach(function (t) {
        var score = 0;
        t.kw.forEach(function (k) { if (has(text, k)) score += k.split(/\s+/).length; });
        t.names.forEach(function (n) {
          norm(n).trim().split(' ').forEach(function (w) { if (w.length >= 4 && has(text, w)) score += 0.5; });
        });
        if (score > bestScore) { bestScore = score; best = { dept: d, type: t }; }
      });
    });
    return best;
  }
  function qtyBefore(text, idx) {
    var before = text.slice(0, idx).trim().split(' ');
    var w = before[before.length - 1] || '';
    if (/^\d+$/.test(w)) return Math.min(20, +w);
    return NUMS[w] || 1;
  }
  // "due insalate" picks "Insalata mista": words are compared by their stem (plurals, endings).
  function pickItems(dept, text) {
    var picked = {}, spoken = text.trim().split(' ');
    dept.items.forEach(function (it) {
      var words = norm(it.name).trim().split(' ').filter(function (w) { return w.length >= 4; });
      for (var i = 0; i < words.length && !picked[it.id]; i++) {
        var stem = words[i].slice(0, Math.max(4, words[i].length - 2));
        for (var j = 0; j < spoken.length; j++) {
          if (spoken[j].indexOf(stem) === 0) { picked[it.id] = qtyBefore(text, text.indexOf(' ' + spoken[j] + ' ')); break; }
        }
      }
    });
    return picked;
  }
  function pickTime(text) {
    var m = text.match(/ (?:alle|at|um|a|à|las|la) (\d{1,2})(?: (?:e|and|et|y) (mezza|mezzo|half|quarto|quarter|un quarto|\d{1,2}))? /) || text.match(/ (\d{1,2})[:.h](\d{2}) /);
    if (!m) return null;
    var h = +m[1], mm = m[2] === undefined ? 0 : /mezz|half/.test(m[2]) ? 30 : /quart/.test(m[2]) ? 15 : +m[2];
    if (h > 23 || mm > 59) return null;
    var tomorrow = / (domani|tomorrow|morgen|demain|manana) /.test(text);
    return { time: ('0' + h).slice(-2) + ':' + ('0' + mm).slice(-2), when: tomorrow ? 'tomorrow' : 'today' };
  }
  function handleSpeech(transcript) {
    var text = norm(transcript);
    var m = matchType(text);
    if (!m) {
      var fb = null;
      G.depts.forEach(function (d) { d.types.forEach(function (t) { if (t.id === G.fallback) fb = { dept: d, type: t }; }); });
      if (!fb) { toast(T.no_match, true); return; }
      m = fb; toast(T.no_match);
    } else {
      toast(T.heard.replace('{text}', transcript));
    }
    openRequest(m.dept, m.type);
    if (!dialog.open) return;
    $('reqNote').value = '🎤 ' + transcript;
    if (m.type.items) {
      var picked = pickItems(m.dept, text);
      Object.keys(picked).forEach(function (id) { qty[id] = picked[id]; });
      $('reqItems').querySelectorAll('.item-row').forEach(function (row, i) {
        var it = m.dept.items[i];
        if (it && picked[it.id]) { row.querySelector('.qty-num').textContent = picked[it.id]; row.classList.add('picked'); }
      });
      updateTotal();
    }
    if (m.type.time) {
      var tm = pickTime(text);
      if (tm) {
        $('reqClock').value = tm.time;
        var now = new Date(), hm = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
        dialog.querySelector('input[name=when][value=' + (tm.when === 'tomorrow' || tm.time < hm ? 'tomorrow' : 'today') + ']').checked = true;
      }
    }
  }
  function listen(onResult, btn) {
    if (!SR) return;
    var rec = new SR();
    rec.lang = G.speechLang; rec.interimResults = false; rec.maxAlternatives = 1;
    var got = false;
    btn.classList.add('listening');
    toast(T.listening);
    rec.onresult = function (e) {
      got = true;
      var t = e.results[0][0].transcript.trim();
      if (t) onResult(t);
    };
    rec.onerror = function (e) {
      btn.classList.remove('listening');
      toast(e.error === 'not-allowed' || e.error === 'service-not-allowed' ? T.mic_denied : T.not_heard, true);
    };
    rec.onend = function () { btn.classList.remove('listening'); if (!got) { toastEl.hidden = true; } };
    try { rec.start(); } catch (e) { btn.classList.remove('listening'); toast(T.not_heard, true); }
  }
  window.__rh = { handleSpeech: handleSpeech, matchType: function (s) { var m = matchType(norm(s)); return m && m.type.name; } };
  $('speakBtn').hidden = false;
  if (SR) {
    $('noteMic').hidden = false;
    $('speakBtn').onclick = function () { listen(handleSpeech, $('speakBtn')); };
    $('noteMic').onclick = function () {
      listen(function (t) { var n = $('reqNote'); n.value = (n.value ? n.value + ' ' : '🎤 ') + t; }, $('noteMic'));
    };
  } else {
    // Firefox and others: no Web Speech recognition. Say why instead of hiding the button.
    $('speakBtn').classList.add('off');
    $('speakBtn').onclick = function () { toast(T.no_sr, true); };
  }

  buildDepts();
  if (G.verified) showActions();
  else setTimeout(function () { $('code').focus(); }, 300);
})();

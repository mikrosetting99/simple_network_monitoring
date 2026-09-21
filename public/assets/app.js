(function () {
  'use strict';

  var PAGE_SIZE = 100;
  var POLL_MS = 30000;
  var state = { devices: [], prev: null, status: 'all', q: '', grp: '', page: 1, sound: false, serverOffset: 0 };
  var rows = new Map(); // id -> <tr>
  var tbody = document.getElementById('rows');
  var soundBtn = document.getElementById('sound');
  var audioCtx = null;

  function fmtDuration(sec) {
    sec = Math.max(0, Math.floor(sec));
    if (sec < 60) return sec + 'd';
    var d = Math.floor(sec / 86400), h = Math.floor((sec % 86400) / 3600), m = Math.floor((sec % 3600) / 60);
    var p = [];
    if (d) p.push(d + 'h');
    if (h) p.push(h + 'j');
    if (m && p.length < 2) p.push(m + 'm');
    return p.join(' ');
  }
  function nowTs() { return Math.floor(Date.now() / 1000) + state.serverOffset; }

  // Urutan: down (terlama dulu), unknown, up (nama).
  var RANK = { down: 0, unknown: 1, up: 2 };
  function compare(a, b) {
    if (a.status !== b.status) return RANK[a.status] - RANK[b.status];
    if (a.status === 'down') return (a.since || 0) - (b.since || 0) || a.name.localeCompare(b.name);
    return a.name.localeCompare(b.name);
  }

  function visible() {
    var q = state.q.toLowerCase();
    return state.devices.filter(function (d) {
      if (state.status !== 'all' && d.status !== state.status) return false;
      if (state.grp && String(d.gid) !== state.grp) return false;
      if (q && d.name.toLowerCase().indexOf(q) === -1 && d.ip.toLowerCase().indexOf(q) === -1) return false;
      return true;
    }).sort(compare);
  }

  function signature(d) {
    return [d.status, d.maint, d.unseen, d.name, d.ip, d.latency].join('|');
  }

  function buildRow(d) {
    var tr = document.createElement('tr');
    tr.className = 'st-' + d.status;
    tr.dataset.sig = signature(d);

    var tdS = document.createElement('td');
    var b = document.createElement('span');
    var cls = d.maint ? 'maint' : d.status;
    b.className = 'badge-st ' + cls;
    b.textContent = d.maint ? 'MAINT' : d.status === 'up' ? 'HIDUP' : d.status === 'down' ? 'MATI' : '—';
    tdS.appendChild(b);
    if (d.unseen) {
      var f = document.createElement('span');
      f.className = 'new-flag';
      f.textContent = 'baru';
      tdS.appendChild(f);
    }

    var tdN = document.createElement('td');
    var a = document.createElement('a');
    a.href = 'device.php?id=' + d.id;
    a.textContent = d.name;
    a.className = 'text-decoration-none';
    tdN.appendChild(a);

    var tdI = document.createElement('td');
    tdI.className = 'ip';
    tdI.textContent = d.ip;

    var tdL = document.createElement('td');
    tdL.className = 'text-end';
    tdL.textContent = d.latency == null ? '–' : d.latency.toFixed(1) + ' ms';

    var tdD = document.createElement('td');
    tdD.className = 'text-end down-for';
    if (d.status === 'down' && d.since) tdD.dataset.since = d.since;

    tr.append(tdS, tdN, tdI, tdL, tdD);
    return tr;
  }

  function tickDurations() {
    var t = nowTs();
    tbody.querySelectorAll('td[data-since]').forEach(function (td) {
      td.textContent = fmtDuration(t - Number(td.dataset.since));
    });
  }

  function render() {
    var list = visible();
    var pages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
    if (state.page > pages) state.page = pages;
    var slice = list.slice((state.page - 1) * PAGE_SIZE, state.page * PAGE_SIZE);

    var keep = new Set();
    slice.forEach(function (d, i) {
      keep.add(d.id);
      var tr = rows.get(d.id);
      // Gambar ulang hanya baris yang berubah supaya tabel tidak berkedip.
      if (!tr || tr.dataset.sig !== signature(d)) {
        var fresh = buildRow(d);
        if (tr && tr.parentNode === tbody) tbody.replaceChild(fresh, tr);
        tr = fresh;
        rows.set(d.id, tr);
      }
      if (tbody.children[i] !== tr) tbody.insertBefore(tr, tbody.children[i] || null);
    });
    rows.forEach(function (tr, id) {
      if (!keep.has(id)) {
        if (tr.parentNode === tbody) tbody.removeChild(tr);
        rows.delete(id);
      }
    });

    tickDurations();
    document.getElementById('count').textContent = list.length + ' perangkat' + (list.length !== state.devices.length ? ' (dari ' + state.devices.length + ')' : '');
    document.getElementById('page').textContent = state.page + ' / ' + pages;
    document.getElementById('prev').disabled = state.page <= 1;
    document.getElementById('next').disabled = state.page >= pages;

    var counts = { up: 0, down: 0, unknown: 0 };
    state.devices.forEach(function (d) { counts[d.status]++; });
    ['up', 'down', 'unknown'].forEach(function (k) { document.getElementById('n-' + k).textContent = counts[k]; });
    document.querySelectorAll('.stat-card[data-filter]').forEach(function (c) {
      c.classList.toggle('active', c.dataset.filter === state.status);
    });
    var od = document.getElementById('only-down');
    od.classList.toggle('btn-danger', state.status === 'down');
    od.classList.toggle('btn-outline-danger', state.status !== 'down');
  }

  function beep() {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      var o = audioCtx.createOscillator(), g = audioCtx.createGain();
      o.type = 'square';
      o.frequency.value = 880;
      g.gain.value = 0.08;
      o.connect(g);
      g.connect(audioCtx.destination);
      o.start();
      o.stop(audioCtx.currentTime + 0.35);
    } catch (e) { /* audio tidak tersedia */ }
  }

  function detectNewDown(devices) {
    if (!state.prev) return false;
    return devices.some(function (d) {
      return d.status === 'down' && !d.maint && state.prev[d.id] !== 'down';
    });
  }

  function poll() {
    fetch('api/status.php', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        state.serverOffset = data.now - Math.floor(Date.now() / 1000);
        if (state.sound && detectNewDown(data.devices)) beep();
        var prev = {};
        data.devices.forEach(function (d) { prev[d.id] = d.status; });
        state.prev = prev;
        state.devices = data.devices;

        soundBtn.classList.toggle('d-none', !data.sound);
        var hw = document.getElementById('worker-health');
        if (hw) {
          hw.textContent = data.worker.text + (data.worker.ok ? '' : ' — data mungkin basi');
          hw.className = 'navbar-text small ' + (data.worker.ok ? 'text-secondary' : 'text-warning fw-bold');
        }
        document.getElementById('updated').textContent = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID');
        render();
      })
      .catch(function () {
        document.getElementById('updated').textContent = 'Gagal memuat data, mencoba lagi...';
      });
  }

  // Filter dan pencarian
  document.getElementById('q').addEventListener('input', function (e) { state.q = e.target.value; state.page = 1; render(); });
  document.getElementById('grp').addEventListener('change', function (e) { state.grp = e.target.value; state.page = 1; render(); });
  function setStatus(s) { state.status = state.status === s ? 'all' : s; state.page = 1; render(); }
  document.getElementById('only-down').addEventListener('click', function () { setStatus('down'); });
  document.querySelectorAll('.stat-card[data-filter]').forEach(function (c) {
    c.addEventListener('click', function () { setStatus(c.dataset.filter); });
  });
  document.getElementById('prev').addEventListener('click', function () { state.page--; render(); });
  document.getElementById('next').addEventListener('click', function () { state.page++; render(); });

  // Suara: default mati, pilihan disimpan per browser.
  function loadSound() { try { return localStorage.getItem('pingSound') === '1'; } catch (e) { return false; } }
  function saveSound(v) { try { localStorage.setItem('pingSound', v ? '1' : '0'); } catch (e) { /* abaikan */ } }
  function paintSound() { soundBtn.textContent = 'Suara: ' + (state.sound ? 'hidup' : 'mati'); soundBtn.classList.toggle('btn-secondary', state.sound); soundBtn.classList.toggle('btn-outline-secondary', !state.sound); }
  state.sound = loadSound();
  paintSound();
  soundBtn.addEventListener('click', function () {
    state.sound = !state.sound;
    saveSound(state.sound);
    paintSound();
    if (state.sound) beep(); // klik pengguna = izin audio dari browser
  });

  poll();
  setInterval(poll, POLL_MS);
  setInterval(tickDurations, 10000);
})();

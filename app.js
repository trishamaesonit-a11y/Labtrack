/* LabTrack front-end: theme toggle, dialogs, mobile nav and tiny SVG charts (no libraries). */
(function () {
  var NS = 'http://www.w3.org/2000/svg', uid = 0;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function el(tag, attrs, parent) {
    var n = document.createElementNS(NS, tag);
    for (var k in attrs) n.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(n);
    return n;
  }
  function J(node, key, fallback) { try { return JSON.parse(node.getAttribute('data-' + key)); } catch (e) { return fallback; } }

  /* ---- show/hide password ---- */
  $$('.password-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      var field = button.parentNode.querySelector('input');
      if (!field) return;
      var visible = field.type === 'password';
      field.type = visible ? 'text' : 'password';
      button.classList.toggle('is-visible', visible);
      button.setAttribute('aria-pressed', visible ? 'true' : 'false');
      button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    });
  });

  /* ---- appearance preferences ---- */
  var root = document.documentElement, userId = root.getAttribute('data-user-id'), savedBackground = null;
  function parseHex(color) {
    if (!/^#[0-9a-f]{6}$/i.test(color)) return null;
    return [parseInt(color.slice(1, 3), 16), parseInt(color.slice(3, 5), 16), parseInt(color.slice(5, 7), 16)];
  }
  function toHex(rgb) {
    return '#' + rgb.map(function (value) { return Math.round(value).toString(16).padStart(2, '0'); }).join('');
  }
  function adjustColor(rgb, amount) {
    return rgb.map(function (value) {
      return amount < 0 ? value * (1 + amount) : value + (255 - value) * amount;
    });
  }
  function applyBackground(color) {
    var rgb = parseHex(color);
    if (!rgb) return false;
    var luminance = (0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2]) / 255;
    var ink = luminance > 0.48 ? [15, 42, 27] : [233, 255, 242];
    root.style.setProperty('--bg1', color);
    root.style.setProperty('--bg2', toHex(adjustColor(rgb, root.dataset.theme === 'light' ? 0.12 : -0.18)));
    root.style.setProperty('--text', luminance > 0.48 ? '#0f2a1b' : '#e9fff2');
    root.style.setProperty('--muted', 'rgba(' + ink.join(',') + ',.68)');
    root.style.setProperty('--faint', 'rgba(' + ink.join(',') + ',.46)');
    root.style.setProperty('--track', 'rgba(' + ink.join(',') + ',.12)');
    root.style.setProperty('--input', 'rgba(' + ink.join(',') + ',.08)');
    return true;
  }

  /* ---- theme ---- */
  $$('.theme-toggle').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = t;
      try { localStorage.setItem('lt-theme', t); } catch (e) {}
      if (savedBackground) applyBackground(savedBackground);
    });
  });

  /* ---- accent color ---- */
  function applyAccent(color) {
    if (!/^#[0-9a-f]{6}$/i.test(color)) return false;
    var r = parseInt(color.slice(1, 3), 16), g = parseInt(color.slice(3, 5), 16), b = parseInt(color.slice(5, 7), 16);
    var rgb = [r, g, b], luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
    function shade(amount) {
      return '#' + rgb.map(function (value) {
        var result = amount < 0 ? value * (1 + amount) : value + (255 - value) * amount;
        return Math.round(result).toString(16).padStart(2, '0');
      }).join('');
    }
    root.style.setProperty('--accent', color);
    root.style.setProperty('--accent2', shade(-0.15));
    root.style.setProperty('--lime', shade(0.25));
    root.style.setProperty('--accent-rgb', rgb.join(','));
    root.style.setProperty('--accent-ink', luminance > 0.48 ? '#04210f' : '#ffffff');
    root.style.setProperty('--blob1', 'rgba(' + rgb.join(',') + ',.28)');
    root.style.setProperty('--blob2', 'rgba(' + rgb.join(',') + ',.18)');
    root.style.setProperty('--blob3', 'rgba(' + rgb.join(',') + ',.12)');
    return true;
  }
  if (userId) {
    var accentKey = 'lt-accent-' + userId, savedAccent = null;
    try { savedAccent = localStorage.getItem(accentKey); } catch (e) {}
    if (savedAccent) applyAccent(savedAccent);
    $$('.accent-input').forEach(function (input) {
      input.value = savedAccent && /^#[0-9a-f]{6}$/i.test(savedAccent)
        ? savedAccent
        : getComputedStyle(root).getPropertyValue('--accent').trim();
      input.addEventListener('input', function () {
        applyAccent(input.value);
        try { localStorage.setItem(accentKey, input.value); } catch (e) {}
      });
    });
    $$('.accent-reset').forEach(function (button) {
      button.addEventListener('click', function () {
        try { localStorage.removeItem(accentKey); } catch (e) {}
        var defaultAccent = root.dataset.theme === 'light' ? '#16a34a' : '#22c55e';
        applyAccent(defaultAccent);
        $$('.accent-input').forEach(function (input) { input.value = defaultAccent; });
      });
    });

    var backgroundKey = 'lt-background-' + userId;
    try { savedBackground = localStorage.getItem(backgroundKey); } catch (e) {}
    if (savedBackground) applyBackground(savedBackground);
    $$('.color-preference-input[data-preference="background"]').forEach(function (input) {
      input.value = savedBackground && parseHex(savedBackground)
        ? savedBackground
        : getComputedStyle(root).getPropertyValue('--bg1').trim();
      input.addEventListener('input', function () {
        if (!applyBackground(input.value)) return;
        try { localStorage.setItem(backgroundKey, input.value); } catch (e) {}
      });
    });
    $$('[data-reset-preference="background"]').forEach(function (button) {
      button.addEventListener('click', function () {
        try { localStorage.removeItem(backgroundKey); } catch (e) {}
        savedBackground = null;
        ['--bg1', '--bg2', '--text', '--muted', '--faint', '--track', '--input'].forEach(function (name) { root.style.removeProperty(name); });
        $$('.color-preference-input[data-preference="background"]').forEach(function (input) {
          input.value = getComputedStyle(root).getPropertyValue('--bg1').trim();
        });
      });
    });

    var glassKey = 'lt-glass-' + userId, savedGlass = null;
    try { savedGlass = localStorage.getItem(glassKey); } catch (e) {}
    if (savedGlass) {
      var glassRgb = parseHex(savedGlass);
      if (glassRgb) root.style.setProperty('--glass-rgb', glassRgb.join(','));
    }
    $$('.color-preference-input[data-preference="glass"]').forEach(function (input) {
      var currentGlass = getComputedStyle(root).getPropertyValue('--glass-rgb').trim().split(',').map(Number);
      input.value = savedGlass && parseHex(savedGlass) ? savedGlass : toHex(currentGlass);
      input.addEventListener('input', function () {
        var rgb = parseHex(input.value);
        if (!rgb) return;
        root.style.setProperty('--glass-rgb', rgb.join(','));
        try { localStorage.setItem(glassKey, input.value); } catch (e) {}
      });
    });
    $$('[data-reset-preference="glass"]').forEach(function (button) {
      button.addEventListener('click', function () {
        try { localStorage.removeItem(glassKey); } catch (e) {}
        root.style.removeProperty('--glass-rgb');
        $$('.color-preference-input[data-preference="glass"]').forEach(function (input) {
          input.value = toHex(getComputedStyle(root).getPropertyValue('--glass-rgb').trim().split(',').map(Number));
        });
      });
    });
  }

  /* ---- mobile nav ---- */
  var mb = $('.menu-btn');
  function setNav(open) {
    document.body.classList.toggle('nav-open', open);
    if (mb) mb.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (mb) mb.addEventListener('click', function () { setNav(!document.body.classList.contains('nav-open')); });
  var bd = $('.nav-backdrop'); if (bd) bd.addEventListener('click', function () { setNav(false); });
  $$('.uside a, .side nav a').forEach(function (a) { a.addEventListener('click', function () { setNav(false); }); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setNav(false); });
  window.addEventListener('resize', function () { if (window.innerWidth > 900) setNav(false); });

  /* ---- dialogs: <button data-dialog="id" data-fill='{"field":"value"}'> ---- */
  $$('[data-dialog]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dlg = document.getElementById(btn.getAttribute('data-dialog')); if (!dlg) return;
      var form = $('form', dlg); form.reset();
      $$('input[type=hidden]:not([name=_csrf]):not([name=action])', form).forEach(function (h) { h.value = ''; });
      var data = J(btn, 'fill', {});
      for (var k in data) { var f = form.elements[k]; if (f && f.type !== 'password') f.value = data[k] === null ? '' : data[k]; }
      var t = $('[data-title]', dlg); if (t && btn.getAttribute('data-heading')) t.textContent = btn.getAttribute('data-heading');
      dlg.showModal();
    });
  });
  $$('[data-close]').forEach(function (b) { b.addEventListener('click', function () { b.closest('dialog').close(); }); });
  $$('dialog').forEach(function (d) { d.addEventListener('click', function (ev) { if (ev.target === d) d.close(); }); });
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) { if (!confirm(f.getAttribute('data-confirm'))) ev.preventDefault(); });
  });

  /* ---- tooltip ---- */
  var tip = $('#tip');
  function bindTip(node, text) {
    node.addEventListener('mousemove', function (e) { tip.textContent = text; tip.style.left = e.clientX + 'px'; tip.style.top = e.clientY + 'px'; tip.classList.add('on'); });
    node.addEventListener('mouseleave', function () { tip.classList.remove('on'); });
  }

  /* ---- smooth path (Catmull-Rom -> Bezier) ---- */
  function smooth(p) {
    if (p.length < 2) return 'M0,0';
    var d = 'M' + p[0][0] + ',' + p[0][1];
    for (var i = 0; i < p.length - 1; i++) {
      var p0 = p[i - 1] || p[i], p1 = p[i], p2 = p[i + 1], p3 = p[i + 2] || p2;
      d += ' C' + (p1[0] + (p2[0] - p0[0]) / 6) + ',' + (p1[1] + (p2[1] - p0[1]) / 6) + ' ' + (p2[0] - (p3[0] - p1[0]) / 6) + ',' + (p2[1] - (p3[1] - p1[1]) / 6) + ' ' + p2[0] + ',' + p2[1];
    }
    return d;
  }
  function gradient(svg, id, color, a0, a1) {
    var defs = $('defs', svg) || el('defs', {}, svg);
    var g = el('linearGradient', { id: id, x1: 0, y1: 0, x2: 0, y2: 1 }, defs);
    el('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': a0 }, g);
    el('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': a1 }, g);
  }

  /* ---- sparkline ---- */
  $$('.spark').forEach(function (n) {
    var v = J(n, 'values', []), c = n.getAttribute('data-color') || '#22c55e', W = 200, H = 90;
    var max = Math.max.apply(null, v.concat([1])), id = 'sp' + (++uid);
    var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, preserveAspectRatio: 'none' }, n); svg.style.cssText = 'width:100%;height:100%';
    gradient(svg, id, c, .45, 0);
    var pts = v.map(function (y, i) { return [i * W / Math.max(v.length - 1, 1), H - 8 - (y / max) * (H - 24)]; });
    el('path', { d: smooth(pts) + ' L' + W + ',' + H + ' L0,' + H + ' Z', fill: 'url(#' + id + ')' }, svg);
    el('path', { d: smooth(pts), fill: 'none', stroke: c, 'stroke-width': 2.5, 'vector-effect': 'non-scaling-stroke' }, svg);
  });

  /* ---- multi-series line chart ---- */
  $$('.linechart').forEach(function (n) {
    var labels = J(n, 'labels', []), series = J(n, 'series', []), W = 640, H = 250, L = 34, B = 24, T = 10, R = 8;
    var max = 1; series.forEach(function (s) { max = Math.max.apply(null, [max].concat(s.values)); });
    max = Math.ceil(max / 4) * 4 || 4;
    var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H }, n);
    for (var g = 0; g <= 4; g++) {
      var y = T + (H - T - B) * g / 4;
      el('line', { x1: L, x2: W - R, y1: y, y2: y, 'class': 'gridl' }, svg);
      var t = el('text', { x: 0, y: y + 3, 'class': 'ax' }, svg); t.textContent = Math.round(max - max * g / 4);
    }
    var step = (W - L - R) / Math.max(labels.length - 1, 1);
    labels.forEach(function (lb, i) { var t = el('text', { x: L + i * step, y: H - 6, 'class': 'ax', 'text-anchor': 'middle' }, svg); t.textContent = lb; });
    series.forEach(function (s) {
      var id = 'ln' + (++uid);
      gradient(svg, id, s.color, .35, 0);
      var pts = s.values.map(function (v, i) { return [L + i * step, T + (H - T - B) * (1 - v / max)]; });
      el('path', { d: smooth(pts) + ' L' + pts[pts.length - 1][0] + ',' + (H - B) + ' L' + L + ',' + (H - B) + ' Z', fill: 'url(#' + id + ')' }, svg);
      el('path', { d: smooth(pts), fill: 'none', stroke: s.color, 'stroke-width': 3, 'stroke-linecap': 'round' }, svg);
      pts.forEach(function (p, i) {
        var c = el('circle', { cx: p[0], cy: p[1], r: 9, fill: 'transparent' }, svg);
        var d = el('circle', { cx: p[0], cy: p[1], r: 4, fill: s.color, opacity: 0, 'pointer-events': 'none' }, svg);
        bindTip(c, s.name + ' · ' + labels[i] + ': ' + s.values[i]);
        c.addEventListener('mouseenter', function () { d.setAttribute('opacity', 1); });
        c.addEventListener('mouseleave', function () { d.setAttribute('opacity', 0); });
      });
    });
  });

  /* ---- donut ---- */
  $$('.donut').forEach(function (n) {
    var v = J(n, 'values', []), lb = J(n, 'labels', []), col = J(n, 'colors', []), sub = n.getAttribute('data-sub') || 'Total';
    var total = v.reduce(function (a, b) { return a + b; }, 0), R = 70, C = 2 * Math.PI * R, off = 0;
    var svg = el('svg', { viewBox: '0 0 200 200' }, n);
    el('circle', { cx: 100, cy: 100, r: R, fill: 'none', stroke: 'rgba(128,128,128,.15)', 'stroke-width': 24 }, svg);
    v.forEach(function (x, i) {
      if (!total || !x) return;
      var len = x / total * C, gap = v.length > 1 ? 3 : 0;
      var c = el('circle', { cx: 100, cy: 100, r: R, fill: 'none', stroke: col[i % col.length], 'stroke-width': 24, 'stroke-dasharray': Math.max(len - gap, 0.1) + ' ' + C, 'stroke-dashoffset': -off, transform: 'rotate(-90 100 100)', style: 'cursor:pointer' }, svg);
      bindTip(c, lb[i] + ': ' + x + ' (' + Math.round(x / total * 100) + '%)'); off += len;
    });
    var a = el('text', { x: 100, y: 108, 'class': 'dc' }, svg); a.textContent = total;
    var b = el('text', { x: 100, y: 88, 'class': 'dl' }, svg); b.textContent = sub;
  });

  /* ---- vertical bars ---- */
  $$('.barchart').forEach(function (n) {
    var v = J(n, 'values', []), lb = J(n, 'labels', []), unit = n.getAttribute('data-unit') || '', W = 640, H = 220, B = 24, bw = (W / v.length) * 0.62;
    var max = Math.max.apply(null, v.concat([1])), hi = v.indexOf(Math.max.apply(null, v)), id = 'br' + (++uid);
    var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H }, n); gradient(svg, id, '#22c55e', 1, .55);
    var gid = 'bh' + uid; gradient(svg, gid, '#a3e635', 1, .6);
    v.forEach(function (x, i) {
      var cx = (i + .5) * W / v.length, h = (H - B - 6) * x / max;
      el('rect', { x: cx - bw / 2, y: 0, width: bw, height: H - B, rx: bw / 2, fill: 'rgba(128,128,128,.14)' }, svg);
      var r = el('rect', { x: cx - bw / 2, y: H - B - Math.max(h, 6), width: bw, height: Math.max(h, 6), rx: bw / 2, fill: 'url(#' + (i === hi ? gid : id) + ')', style: 'cursor:pointer' }, svg);
      bindTip(r, lb[i] + ': ' + x + unit);
      var t = el('text', { x: cx, y: H - 6, 'class': 'ax', 'text-anchor': 'middle' }, svg); t.textContent = lb[i];
    });
  });
})();

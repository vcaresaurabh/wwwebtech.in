/* Wwwebtech analytics. First-party, cookieless, ~1KB.
   Sets nothing on your device and sends no identifier. See /legal/privacy/. */
(function () {
  'use strict';
  var E = '/api/hit.php', sent = 0, t0 = Date.now();

  function q(n) { try { return new URLSearchParams(location.search).get(n) || ''; } catch (e) { return ''; } }

  function send(ev, detail) {
    if (sent > 40) return;                       // a runaway page cannot flood the table
    sent++;
    var d = new URLSearchParams();
    d.set('p', location.pathname);
    d.set('e', ev);
    if (detail) d.set('d', String(detail).slice(0, 200));
    if (ev === 'pageview') {
      if (document.referrer) d.set('r', document.referrer);
      ['utm_source', 'utm_medium', 'utm_campaign'].forEach(function (k) {
        var v = q(k); if (v) d.set(k, v.slice(0, 120));
      });
    }
    var body = d.toString();
    /* sendBeacon survives the page being closed, which is the whole point
       for the engagement ping. fetch(keepalive) is the fallback. */
    if (navigator.sendBeacon) {
      navigator.sendBeacon(E, new Blob([body], { type: 'application/x-www-form-urlencoded' }));
    } else if (window.fetch) {
      fetch(E, { method: 'POST', body: body, keepalive: true, credentials: 'omit',
                 headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }).catch(function () {});
    }
  }

  send('pageview');

  /* One "this was a real read, not a bounce" signal, once. */
  var engaged = 0;
  function markEngaged() {
    if (engaged) return;
    engaged = 1;
    send('engaged', Math.round((Date.now() - t0) / 1000) + 's');
  }
  setTimeout(markEngaged, 15000);
  addEventListener('scroll', function onScroll() {
    if (scrollY > innerHeight * 0.6) { removeEventListener('scroll', onScroll); markEngaged(); }
  }, { passive: true });

  /* Outbound and mailto/tel clicks — the things that mean intent. */
  addEventListener('click', function (ev) {
    var a = ev.target && ev.target.closest && ev.target.closest('a');
    if (!a || !a.href) return;
    if (/^(mailto|tel):/i.test(a.href)) { send('click', a.href.split(':')[0]); return; }
    if (a.hostname && a.hostname !== location.hostname) send('outbound', a.hostname);
  }, { passive: true, capture: true });
})();

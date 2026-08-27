// ─── Auto-dismiss alerts ──────────────────────────────────────────────────────
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity 0.4s ease';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 400);
  }, 300000); // 5 minutes
});

// ─── Confirm delete ───────────────────────────────────────────────────────────
document.querySelectorAll('.confirm-delete').forEach(btn => {
  btn.addEventListener('click', function(e) {
    if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
      e.preventDefault();
    }
  });
});

// ─── Table row clickable ──────────────────────────────────────────────────────
document.querySelectorAll('[data-href]').forEach(row => {
  row.style.cursor = 'pointer';
  row.addEventListener('click', function() {
    window.location.href = this.dataset.href;
  });
});

// ─── Sidebar toggle (mobile drawer / desktop collapse) ────────────────────────
// One hamburger button, injected into every topbar, with two behaviors:
//  - below 900px the sidebar is an off-canvas drawer opened over an overlay;
//  - at 901px+ it collapses the sidebar into an icon-only rail, remembered in
//    localStorage and pre-applied by an inline script in partials/head so the
//    page renders in the saved state without a flash.
(function () {
  const topbar  = document.querySelector('.topbar');
  const sidebar = document.querySelector('.sidebar');
  if (!topbar || !sidebar) return;

  const isMobile = () => window.matchMedia('(max-width: 900px)').matches;

  // Tooltips for the icon-only rail (and generally useful on hover)
  sidebar.querySelectorAll('.nav-item').forEach(a => {
    if (!a.title) a.title = a.textContent.trim();
  });

  const btn = document.createElement('button');
  btn.className = 'menu-toggle';
  btn.type = 'button';
  btn.setAttribute('aria-label', 'Open navigation menu');
  btn.setAttribute('aria-expanded', 'false');
  // Three independent bars (not one <path>) so each can be transformed on its
  // own into an X when the drawer/rail is open — see .menu-toggle .bar in CSS,
  // driven purely by the aria-expanded attribute this file already maintains.
  btn.innerHTML =
    '<svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
    '<line class="bar bar-top" x1="4" y1="6" x2="20" y2="6"/>' +
    '<line class="bar bar-mid" x1="4" y1="12" x2="20" y2="12"/>' +
    '<line class="bar bar-bottom" x1="4" y1="18" x2="20" y2="18"/></svg>';
  topbar.insertBefore(btn, topbar.firstChild);

  // Mounted inside .app (not <body>): .app creates a stacking context, so the
  // overlay must live in it for the sidebar's higher z-index to win.
  const overlay = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  (document.querySelector('.app') || document.body).appendChild(overlay);

  function setOpen(open) {
    document.body.classList.toggle('sidebar-open', open);
    btn.setAttribute('aria-expanded', String(open));
    btn.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
  }

  function setCollapsed(collapsed) {
    document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
    btn.setAttribute('aria-expanded', String(!collapsed));
    btn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    try { localStorage.setItem('cbams-sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (e) {}
  }

  btn.addEventListener('click', () => {
    if (isMobile()) {
      setOpen(!document.body.classList.contains('sidebar-open'));
    } else {
      setCollapsed(!document.documentElement.classList.contains('sidebar-collapsed'));
    }
  });
  overlay.addEventListener('click', () => setOpen(false));

  // In-rail expand button that sits where the logo was (desktop only, see CSS)
  const expandBtn = sidebar.querySelector('.sidebar-expand-btn');
  if (expandBtn) expandBtn.addEventListener('click', () => setCollapsed(false));

  // Collapse button in the brand row (desktop) — folds the sidebar to the rail
  const collapseBtn = sidebar.querySelector('.sidebar-collapse-btn');
  if (collapseBtn) collapseBtn.addEventListener('click', () => setCollapsed(true));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') setOpen(false); });
  sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setOpen(false)));

  // Reset state if the viewport grows back to desktop while the drawer is open
  const mq = window.matchMedia('(min-width: 901px)');
  const onResize = e => { if (e.matches) setOpen(false); };
  if (mq.addEventListener) mq.addEventListener('change', onResize);
  else mq.addListener(onResize);
})();

// ─── Content Protection (only on authenticated app pages) ────────────────────
(function () {
  // Only activate on pages with the app shell (logged-in pages)
  if (!document.querySelector('.app')) return;

  // Get user info from meta tag if available
  const userMeta  = document.querySelector('meta[name="auth-user"]');
  const userName  = userMeta ? userMeta.getAttribute('data-name')  : 'Unknown User';

  // ── 1. Warning toast ───────────────────────────────────────────────────────
  function showWarning(msg) {
    let el = document.getElementById('cbams-warn');
    if (!el) {
      el = document.createElement('div');
      el.id = 'cbams-warn';
      Object.assign(el.style, {
        position:     'fixed',
        top:          '24px',
        left:         '50%',
        transform:    'translateX(-50%)',
        background:   '#1e293b',
        color:        '#fff',
        padding:      '12px 24px',
        borderRadius: '10px',
        fontSize:     '14px',
        fontWeight:   '600',
        zIndex:       '2147483647',
        boxShadow:    '0 6px 30px rgba(0,0,0,0.5)',
        pointerEvents:'none',
        whiteSpace:   'nowrap',
        fontFamily:   'Inter, sans-serif',
        transition:   'opacity 0.3s ease',
      });
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.opacity = '1';
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.style.opacity = '0'; }, 3500);
  }

  // ── 2. Disable right-click ────────────────────────────────────────────────
  document.addEventListener('contextmenu', function (e) {
    e.preventDefault();
    showWarning('Right-click is disabled — content is protected.');
  });

  // ── 3. Block keyboard shortcuts (what the browser allows blocking) ────────
  document.addEventListener('keydown', function (e) {
    const ctrl = e.ctrlKey || e.metaKey;
    const blocked =
      (ctrl && ['p', 's', 'u'].includes(e.key.toLowerCase())) || // Print, Save, Source
      (ctrl && e.shiftKey && ['i', 'j', 'c'].includes(e.key.toLowerCase())); // DevTools

    if (blocked) {
      e.preventDefault();
      e.stopImmediatePropagation();
      showWarning('This action is disabled for content protection.');
    }

    // Note: PrintScreen is captured by Windows before the browser — cannot be blocked by JS.
    // The watermark below ensures any screenshot shows the user's identity.
  });

  // ── 4. Disable text selection on content areas ────────────────────────────
  const noSelect = '.bluebook-abstract,.bluebook-detail,.card-body,tbody,td';
  document.querySelectorAll(noSelect).forEach(el => {
    el.style.userSelect       = 'none';
    el.style.webkitUserSelect = 'none';
    el.style.msUserSelect     = 'none';
  });

  // ── 5. Dynamic repeating watermark (CSPC logo) ─────────────────────────────
  // Only render while actually viewing a document (not on every app page).
  const viewingDocument = !!document.getElementById('bluebook-detail');

  if (viewingDocument) {
    const logoImg = new Image();
    logoImg.src = '/images/cspc-logo.png';

    function buildWatermark() {
      if (!logoImg.complete || logoImg.naturalWidth === 0) return;

      const old = document.getElementById('cbams-wm');
      if (old) old.remove();

      const canvas  = document.createElement('canvas');
      canvas.width  = 380;
      canvas.height = 180;
      const ctx     = canvas.getContext('2d');

      ctx.save();
      ctx.translate(canvas.width / 2, canvas.height / 2);
      ctx.rotate(-22 * Math.PI / 180);

      // Semi-transparent logo
      ctx.globalAlpha = 0.09;
      const logoSize = 110;
      ctx.drawImage(logoImg, -logoSize / 2, -logoSize / 2, logoSize, logoSize);

      ctx.restore();

      const wm = document.createElement('div');
      wm.id = 'cbams-wm';
      Object.assign(wm.style, {
        position:        'fixed',
        inset:           '0',
        zIndex:          '9998',
        pointerEvents:   'none',
        backgroundImage: 'url(' + canvas.toDataURL() + ')',
        backgroundRepeat:'repeat',
        backgroundSize:  '380px 180px',
      });
      document.body.appendChild(wm);
    }

    logoImg.onload = buildWatermark;
    buildWatermark();
    // Rebuild on visibility change so screenshot attempts get fresh watermark
    document.addEventListener('visibilitychange', buildWatermark);
  }

  // ── 6. Screen share / recording detection ─────────────────────────────────
  if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
    const _orig = navigator.mediaDevices.getDisplayMedia.bind(navigator.mediaDevices);
    navigator.mediaDevices.getDisplayMedia = async function (opts) {
      const stream = await _orig(opts);

      // Blur main content while sharing
      const main = document.querySelector('.main');
      if (main) main.style.filter = 'blur(14px)';

      // Red banner
      const banner = document.createElement('div');
      banner.id = 'cbams-share-banner';
      Object.assign(banner.style, {
        position:   'fixed', top: '0', left: '0', right: '0',
        background: '#dc2626', color: '#fff',
        padding:    '14px', textAlign: 'center',
        fontWeight: '700', fontSize: '15px',
        zIndex:     '2147483646', letterSpacing: '0.02em',
      });
      banner.textContent = 'Screen sharing detected — content has been blurred to protect confidential data.';
      document.body.appendChild(banner);

      stream.getVideoTracks()[0].addEventListener('ended', () => {
        if (main) main.style.filter = '';
        banner.remove();
      });

      return stream;
    };
  }

  // ── 7. Disable image/link drag ────────────────────────────────────────────
  document.addEventListener('dragstart', e => {
    if (e.target.matches('img, a')) e.preventDefault();
  });

  // ── 8. Bluebook capture deterrence (PrintScreen + focus-loss) ─────────────
  // Note: a web page cannot actually intercept the OS PrintScreen key or stop
  // external recording tools (OBS, Game Bar, a phone camera, etc.) — that's
  // outside what browser JS is allowed to do. This section only deters casual
  // capture attempts (blur + warning) and records an audit-log entry so staff
  // can see who triggered it and on which document.
  const bookEl = document.getElementById('bluebook-detail');
  if (bookEl) {
    const bookId = bookEl.dataset.bluebookId;
    let lastFlag = 0;

    function flagCapture() {
      const now = Date.now();
      if (now - lastFlag < 3000) return; // throttle repeated triggers
      lastFlag = now;

      const csrf = document.querySelector('meta[name="csrf-token"]');
      if (csrf) {
        fetch('/student/bluebooks/' + bookId + '/flag-capture', {
          method:  'POST',
          headers: {
            'X-CSRF-TOKEN': csrf.getAttribute('content'),
            'Accept':       'application/json',
          },
        }).catch(() => {});
      }
    }

    function showCaptureBanner(msg) {
      let banner = document.getElementById('cbams-capture-banner');
      if (!banner) {
        banner = document.createElement('div');
        banner.id = 'cbams-capture-banner';
        Object.assign(banner.style, {
          position:      'fixed', top: '0', left: '0', right: '0',
          background:    '#dc2626', color: '#fff',
          padding:       '14px', textAlign: 'center',
          fontWeight:    '700', fontSize: '15px',
          zIndex:        '2147483646', letterSpacing: '0.02em',
        });
        document.body.appendChild(banner);
      }
      banner.textContent = msg;
    }

    function hideCaptureBanner() {
      const banner = document.getElementById('cbams-capture-banner');
      if (banner) banner.remove();
    }

    function blurBook(on) {
      bookEl.style.filter = on ? 'blur(16px)' : '';
    }

    // PrintScreen: best-effort clipboard clear (can't block the OS capture itself)
    document.addEventListener('keyup', function (e) {
      if (e.key !== 'PrintScreen') return;

      blurBook(true);
      showCaptureBanner('Screenshot attempt detected — this access has been logged.');
      flagCapture();

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText('').catch(() => {});
      }

      setTimeout(() => { blurBook(false); hideCaptureBanner(); }, 4000);
    });

    // Losing window focus often means switching to a recording tool. On
    // mobile this also fires for ordinary reasons (switching apps, a
    // notification, the app-switcher gesture) that have nothing to do with
    // capture — a known false-positive tradeoff, kept on for parity with
    // desktop protection rather than leaving mobile unprotected.
    window.addEventListener('blur', function () {
      blurBook(true);
      showCaptureBanner('Window lost focus — content hidden for protection.');
      flagCapture();
    });

    window.addEventListener('focus', function () {
      blurBook(false);
      hideCaptureBanner();
    });
  }

})();

// ─── Sidebar menu filter ──────────────────────────────────────────────────────
// Types in the sidebar search box narrow the visible nav items; empty shows all.
// Section labels hide when none of their items match.
(function () {
  const input = document.querySelector('.sidebar-search input[data-nav-filter]');
  if (!input) return;

  const sections = [...document.querySelectorAll('.sidebar-section')];

  input.addEventListener('input', function () {
    const q = input.value.trim().toLowerCase();
    sections.forEach(section => {
      let anyVisible = false;
      section.querySelectorAll('.nav-item').forEach(item => {
        const match = !q || item.textContent.trim().toLowerCase().includes(q);
        item.style.display = match ? '' : 'none';
        if (match) anyVisible = true;
      });
      const label = section.querySelector('.sidebar-label');
      if (label) label.style.display = anyVisible ? '' : 'none';
    });
  });
})();

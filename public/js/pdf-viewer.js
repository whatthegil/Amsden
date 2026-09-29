/**
 * Renders a bluebook to canvas with PDF.js.
 *
 * The document used to be handed to the browser in an <iframe>. Desktop Chrome
 * has a built-in PDF viewer so that worked there, but Android's WebView ships
 * none, and the frame was simply blank inside the wrapper app. Rendering the
 * pages ourselves works everywhere, drops the built-in viewer's download and
 * print controls, and puts the pages under the watermark overlay rather than
 * inside a frame the overlay cannot cover.
 *
 * Documents in this archive run past 200 pages, so pages are rendered only as
 * they approach the viewport and released again once well clear of it. Every
 * page keeps its true aspect ratio from the start, so the scrollbar is honest
 * before anything has been drawn.
 */
(function () {
  const view = document.getElementById('pdf-view');
  if (!view) return;

  const pagesEl  = document.getElementById('pdf-pages');
  const statusEl = document.getElementById('pdf-status');

  // .pdf-pages is the scroller, so these margins are measured against a 75vh
  // box rather than against the window.
  const RENDER_MARGIN = '1600px 0px';  // draw a page or two ahead of the scroll
  const KEEP_MARGIN   = '4000px 0px';  // let go only well past that
  // A ceiling on canvases regardless of what the margins admit. A page at
  // desktop width costs a few megabytes of backing store, so an unbounded keep
  // set on a 200-page document is how a phone tab gets killed mid-read.
  const MAX_CANVASES  = 12;

  // Zoom is relative to fitting the page to the width of the viewer (1).
  const ZOOM_MIN = 0.5, ZOOM_MAX = 3, ZOOM_STEP = 0.25;
  let zoom = 1;

  // A page reserves its height with padding-top, which a browser measures
  // against the width of the list, not of the page - so a zoomed page has to
  // scale its reservation by the zoom as well, or pages overlap.
  function reserve(holder, ratio) {
    holder.style.paddingTop = 'calc(' + (ratio * 100) + '% * var(--pdf-zoom, 1))';
  }

  function setStatus(text) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.style.display = text ? '' : 'none';
  }

  // What the reader sees when the document cannot be shown. The reason goes to
  // the console as well: without it a report of "the PDF isn't working" carries
  // nothing to act on.
  function fail(reason, err) {
    if (err) console.error('[pdf-viewer] ' + reason, err);
    setStatus('This document could not be loaded. Please try again, or contact the CSPC Library.');
  }

  if (typeof pdfjsLib === 'undefined') {
    fail('pdf.js did not load', new Error('pdfjsLib is undefined'));
    return;
  }

  pdfjsLib.GlobalWorkerOptions.workerSrc = view.dataset.workerUrl;

  // Above 1.5 the memory cost on a phone outweighs the sharpness gained.
  const scaleFor = () => Math.min(window.devicePixelRatio || 1, 1.5);

  // ── The watermark, in the page rather than over it ─────────────────────────
  // The watermark belongs to the document only, so it is drawn here, into the
  // rendered PDF pages, and nowhere else on the screen. A div laid over the page
  // could be taken off in developer tools; drawing the identity into the canvas
  // puts it in the same pixels as the document. There is no property that
  // removes it, because it is not a layer: taking it off means not rendering
  // the page.
  const detail = document.getElementById('bluebook-detail');
  const viewer = detail ? (detail.dataset.viewer || '').trim() : '';

  // The mark is the CSPC crest with the reader's email under it - nothing
  // else. The crest is fetched now; the document takes far longer to arrive,
  // so it is in hand by the time the first page is drawn.
  const logo = new Image();
  logo.src = '/images/cspc-logo.png';

  // Drawn after the page, so it sits over the content rather than under it.
  function stamp(canvas) {
    const ctx = canvas.getContext('2d');

    // The tile scales with the page: a phone render is not covered edge to edge
    // and a desktop one is not left with four lonely marks in the corners.
    const tile = Math.max(260, Math.round(canvas.width / 2.2));
    const size = Math.max(11, Math.round(tile / 24));
    const crest = Math.round(tile * 0.28);
    const hasLogo = logo.complete && logo.naturalWidth > 0;

    ctx.save();
    ctx.fillStyle    = '#0f2350';
    ctx.textAlign    = 'center';
    ctx.textBaseline = 'middle';
    ctx.font         = '600 ' + size + 'px Inter, system-ui, sans-serif';

    for (let y = tile / 2; y < canvas.height + tile; y += tile) {
      for (let x = tile / 2; x < canvas.width + tile; x += tile) {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(-22 * Math.PI / 180);
        // Light enough to read the thesis through, dark enough to survive the
        // contrast knocked out of a photographed screen.
        if (hasLogo) {
          ctx.globalAlpha = 0.09;
          ctx.drawImage(logo, -crest / 2, -crest - size * 0.4, crest, crest);
        }
        if (viewer) {
          ctx.globalAlpha = 0.14;
          ctx.fillText(viewer, 0, size * 0.6);
        }
        ctx.restore();
      }
    }
    ctx.restore();
  }

  // A page reserves its height with padding-top and holds its canvas out of
  // flow, so it contributes no intrinsic width of its own. Measure the page
  // list instead, which is the element that actually has a width, and take its
  // padding off. Rendering against a zero width draws a 0x0 canvas.
  function pageWidth() {
    const style = getComputedStyle(pagesEl);
    const inner = pagesEl.clientWidth
                - parseFloat(style.paddingLeft || 0)
                - parseFloat(style.paddingRight || 0);
    return Math.floor(inner);
  }

  // A document runs to tens of megabytes, so the wait before the first page
  // appears is long enough that a motionless "Loading" reads as a viewer that
  // has hung. A signed storage link answers with a Content-Length and the
  // percentage is real; the streaming route through PHP has to go out chunked
  // with no declared length, so there count the megabytes in hand instead.
  const mb = (bytes) => (bytes / 1048576).toFixed(1) + ' MB';
  // Progress does not stop when the document opens. Over a signed link PDF.js
  // goes on pulling ranges for as long as the reader is reading, so without
  // this the status line reappears over pages that are already drawn and sits
  // there at whatever percentage it last saw.
  let opened = false;
  function onProgress(progress) {
    if (opened || !progress || !progress.loaded) return;
    if (progress.total) {
      const pct = Math.min(100, Math.round((progress.loaded / progress.total) * 100));
      setStatus('Loading document… ' + pct + '%');
    } else {
      setStatus('Loading document… ' + mb(progress.loaded));
    }
  }

  function load(url, direct) {
    const task = pdfjsLib.getDocument({
      url: url,
      // A signed storage link is cross-origin and is answered with a wildcard
      // Access-Control-Allow-Origin, which a browser refuses to pair with
      // credentials - and the link carries its own authorisation, so it does
      // not want them. The streaming route is same-origin and sits behind the
      // session, so it does.
      withCredentials: !direct,
      // The server answers with the file's length and byte ranges, so the
      // viewer asks for the parts it needs - the end of the file, where the
      // index is, then the pages being read - and draws page one after a few
      // hundred kilobytes instead of waiting for the whole document. Nothing is
      // fetched in the background beyond what is on or near the screen.
      disableRange: false,
      disableStream: true,
      disableAutoFetch: true,
      rangeChunkSize: 262144,
    });
    task.onProgress = onProgress;
    return task.promise;
  }

  const direct   = view.dataset.direct === '1';
  const fallback = view.dataset.fallbackUrl;

  load(view.dataset.pdfUrl, direct).catch(function (err) {
    // The signed link is the fast path, not the only one. A bucket with no CORS
    // rule for this origin, a link that has outlived the reading session, or
    // storage that cannot be reached all land here - and the document is still
    // readable through PHP, so take the slow path rather than tell the reader
    // it is unavailable. Logged either way: this is the path that costs 29 MB
    // of PHP throughput per read, so it should not go unnoticed.
    if (!direct || !fallback) throw err;
    console.warn('[pdf-viewer] direct fetch refused, falling back to the stream', err);
    setStatus('Loading document…');
    return load(fallback, false);
  }).then(function (pdf) {
    opened = true;
    setStatus('');

    const holders  = new Map();   // page number -> holder element
    const rendered = new Map();   // page number -> canvas
    const tasks    = new Map();   // page number -> in-flight RenderTask
    const keep     = new Set();   // pages close enough to hold in memory

    function release(num) {
      // Cancel first: a render left running draws into a canvas that is about
      // to be discarded, which on a long document is most of the work the
      // worker ever does.
      const task = tasks.get(num);
      if (task) {
        tasks.delete(num);
        try { task.cancel(); } catch (e) { /* already settled */ }
      }

      const canvas = rendered.get(num);
      if (!canvas) return;
      canvas.width = 0;             // frees the backing store, not just the node
      canvas.height = 0;
      canvas.remove();
      rendered.delete(num);
    }

    // The margins bound how far a retained page can be from the viewport, but
    // not how many pages that is: a short page or a wide screen admits more.
    // Drop the furthest ones back to the ceiling.
    function enforceCap() {
      if (rendered.size <= MAX_CANVASES) return;
      const middle = pagesEl.scrollTop + pagesEl.clientHeight / 2;
      Array.from(rendered.keys())
        .map(function (num) {
          const holder = holders.get(num);
          return { num: num, dist: holder ? Math.abs(holder.offsetTop - middle) : Infinity };
        })
        .sort(function (a, b) { return b.dist - a.dist; })
        .slice(0, rendered.size - MAX_CANVASES)
        .forEach(function (p) { keep.delete(p.num); release(p.num); });
    }

    function render(holder, num) {
      if (rendered.has(num) || tasks.has(num)) return;

      const width = Math.floor(pageWidth() * zoom);
      // Nothing to draw into yet: the viewer is still being laid out, or is
      // hidden. The width observer below re-runs this once there is a box.
      if (width <= 0) return;

      pdf.getPage(num).then(function (page) {
        // Scrolled well clear while the page was being fetched.
        if (!keep.has(num)) return;

        const base     = page.getViewport({ scale: 1 });
        const viewport = page.getViewport({ scale: (width / base.width) * scaleFor() });

        // Pages within one document are not always the same size, so correct
        // the height reserved up front now this page's own is known.
        reserve(holder, base.height / base.width);

        const canvas  = document.createElement('canvas');
        canvas.width  = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);

        const task = page.render({ canvasContext: canvas.getContext('2d'), viewport });
        tasks.set(num, task);

        return task.promise.then(function () {
          tasks.delete(num);
          holder.textContent = '';
          // Stamped before it is shown, so there is no frame in which a clean
          // page is on screen to be captured.
          stamp(canvas);
          // Scrolled far away while this was drawing: drop it rather than keep
          // a canvas for a page nowhere near the viewport.
          if (!keep.has(num)) return;
          holder.appendChild(canvas);
          rendered.set(num, canvas);
          enforceCap();
        });
      }).catch(function (err) {
        tasks.delete(num);
        // Cancelling is how a page that scrolled away is stopped. It is the
        // normal path, not a failure, and must not put a notice over the page.
        if (err && err.name === 'RenderingCancelledException') return;

        // The holder's own box is reserved padding, so an in-flow message would
        // sit below it and be clipped; the notice is placed over the page.
        holder.textContent = '';
        const note = document.createElement('div');
        note.className = 'pdf-page-error';
        note.textContent = 'This page could not be displayed.';
        holder.appendChild(note);
        console.error('[pdf-viewer] page ' + num + ' failed to render', err);
      });
    }

    // Both observers take .pdf-pages as their root. Against the default root
    // the margins are measured from the window, and a margin on the window does
    // not widen an intervening scroller's clip, so every page was drawn only
    // once it was already on screen and released the moment it left. On a
    // 200-page document that reads as a viewer showing grey.
    //
    // The two margins differ on purpose: a page is not released the moment it
    // stops being a render candidate, so scrolling a little way back and forth
    // does not redraw it.
    const near = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        const num = Number(entry.target.dataset.page);
        // Join the keep set here rather than wait for the observer below to say
        // so. The render margin sits inside the keep margin, so a page worth
        // drawing is by definition worth keeping - and the two observers report
        // separately, each callback followed by its own microtask checkpoint. A
        // page already in the worker cache resolves inside that gap, finds a
        // keep set the other observer has not filled in yet, and throws its
        // finished canvas away. Page 1 is always in that cache, because the
        // layout pass reads it for the document proportions, so page 1 is
        // exactly the page it happened to: every document opened on a blank
        // first page and only caught up when the reader scrolled.
        keep.add(num);
        render(entry.target, num);
      });
    }, { root: pagesEl, rootMargin: RENDER_MARGIN });

    const far = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        const num = Number(entry.target.dataset.page);
        if (entry.isIntersecting) {
          keep.add(num);
        } else {
          keep.delete(num);
          release(num);
        }
      });
    }, { root: pagesEl, rootMargin: KEEP_MARGIN });

    // Lay every page out first, at its real proportions, so the document has a
    // correct height immediately.
    pdf.getPage(1).then(function (page) {
      const base  = page.getViewport({ scale: 1 });
      const ratio = base.height / base.width;

      for (let n = 1; n <= pdf.numPages; n++) {
        const holder = document.createElement('div');
        holder.className = 'pdf-page';
        holder.dataset.page = String(n);
        reserve(holder, ratio);   // reserve height before render
        pagesEl.appendChild(holder);
        holders.set(n, holder);
        // keep before render: a page that finishes drawing before the keep set
        // knows about it throws its own canvas away on completion.
        far.observe(holder);
        near.observe(holder);
      }
    }).catch(function (err) {
      fail('the first page could not be read', err);
    });

    // Rotating a phone, or collapsing the sidebar, changes the width every
    // canvas was sized against. The pages still fit afterwards - CSS scales
    // them - but they stay at the old resolution until they are drawn again.
    //
    // This watches the list rather than the window because it has a second job:
    // if the viewer is laid out late, or starts hidden, the first render
    // measures zero and gives up, and the page observers do not fire again
    // because nothing about the intersection changed. The width arriving is the
    // only signal that those pages can now be drawn.
    let lastWidth = pageWidth();
    let resizeTimer = null;

    function widthChanged() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        const width = pageWidth();
        if (width <= 0 || width === lastWidth) return;
        const grewFromNothing = lastWidth <= 0;
        lastWidth = width;

        // Drawn at the old width: redraw at the new one.
        if (!grewFromNothing) Array.from(rendered.keys()).forEach(release);
        Array.from(keep).forEach(function (num) {
          const holder = holders.get(num);
          if (holder) render(holder, num);
        });
      }, 200);
    }

    if (typeof ResizeObserver !== 'undefined') {
      new ResizeObserver(widthChanged).observe(pagesEl);
    } else {
      window.addEventListener('resize', widthChanged);
      window.addEventListener('orientationchange', widthChanged);
    }

    // ── Reading controls ────────────────────────────────────────────────────
    const toolbar   = document.getElementById('pdf-toolbar');
    if (!toolbar) return;

    const pageInput = document.getElementById('pdf-page-input');
    const countEl   = document.getElementById('pdf-page-count');
    const zoomEl    = document.getElementById('pdf-zoom-level');
    const contents  = document.getElementById('pdf-contents');

    toolbar.querySelectorAll('button, input').forEach(function (el) { el.disabled = false; });
    pageInput.max = String(pdf.numPages);
    countEl.textContent = String(pdf.numPages);

    // The page whose top has passed a third of the way down the viewer.
    function currentPage() {
      const line = pagesEl.scrollTop + pagesEl.clientHeight / 3;
      let page = 1;
      for (let n = 1; n <= pdf.numPages; n++) {
        const holder = holders.get(n);
        if (!holder || holder.offsetTop > line) break;
        page = n;
      }
      return page;
    }

    function goTo(num) {
      const n = Math.min(pdf.numPages, Math.max(1, Math.round(num) || 1));
      const holder = holders.get(n);
      if (holder) pagesEl.scrollTop = holder.offsetTop - 8;
      pageInput.value = String(n);
    }

    let ticking = false;
    pagesEl.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        ticking = false;
        if (document.activeElement !== pageInput) pageInput.value = String(currentPage());
      });
    }, { passive: true });

    pageInput.addEventListener('change', function () { goTo(Number(pageInput.value)); });
    pageInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); goTo(Number(pageInput.value)); pageInput.blur(); }
    });

    // Every page changes size with the zoom, so the drawn ones are redrawn at
    // the new size and the reader is kept on the page they were reading.
    function setZoom(next) {
      const page = currentPage();
      zoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, Math.round(next * 100) / 100));
      pagesEl.style.setProperty('--pdf-zoom', String(zoom));
      zoomEl.textContent = Math.round(zoom * 100) + '%';

      Array.from(rendered.keys()).forEach(release);
      requestAnimationFrame(function () {
        goTo(page);
        Array.from(keep).forEach(function (num) {
          const holder = holders.get(num);
          if (holder) render(holder, num);
        });
      });
    }

    // A reader who has scrolled into the document keeps their place when the
    // viewer goes full screen or comes back, since the width changes both ways.
    let pageBeforeResize = 1;
    function fullscreenElement() { return document.fullscreenElement || document.webkitFullscreenElement; }
    document.addEventListener('fullscreenchange', onFullscreen);
    document.addEventListener('webkitfullscreenchange', onFullscreen);
    function onFullscreen() {
      const on = fullscreenElement() === view;
      view.classList.toggle('is-fullscreen', on);
      toolbar.querySelector('[data-pdf="fullscreen"]').textContent = on ? '✕ Exit full screen' : '⛶ Full screen';
      requestAnimationFrame(function () { goTo(pageBeforeResize); });
    }

    toolbar.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-pdf]');
      if (!btn) return;

      switch (btn.dataset.pdf) {
        case 'prev':     goTo(currentPage() - 1); break;
        case 'next':     goTo(currentPage() + 1); break;
        case 'zoom-in':  setZoom(zoom + ZOOM_STEP); break;
        case 'zoom-out': setZoom(zoom - ZOOM_STEP); break;
        case 'fit':      setZoom(1); break;
        case 'fullscreen':
          pageBeforeResize = currentPage();
          if (fullscreenElement()) {
            (document.exitFullscreen || document.webkitExitFullscreen).call(document);
          } else if (view.requestFullscreen) {
            view.requestFullscreen();
          } else if (view.webkitRequestFullscreen) {
            view.webkitRequestFullscreen();
          }
          break;
      }
    });

    // The document's own bookmarks, when it has them - most typed theses
    // exported to PDF do; a scan usually has none, and the list stays hidden.
    function pageOf(dest) {
      const lookup = typeof dest === 'string' ? pdf.getDestination(dest) : Promise.resolve(dest);
      return lookup.then(function (d) {
        if (!Array.isArray(d) || d.length === 0) return null;
        if (typeof d[0] === 'number') return d[0] + 1;
        return pdf.getPageIndex(d[0]).then(function (i) { return i + 1; });
      });
    }

    pdf.getOutline().then(function (outline) {
      if (!outline || outline.length === 0) return;

      const dests = [];
      (function add(items, depth) {
        items.forEach(function (item) {
          if (depth > 1) return;               // chapters and their sections
          const option = document.createElement('option');
          option.value = String(dests.length);
          option.textContent = (depth ? ' ' : '') + item.title;
          dests.push(item.dest);
          contents.appendChild(option);
          if (item.items && item.items.length) add(item.items, depth + 1);
        });
      })(outline, 0);

      contents.hidden = false;
      contents.addEventListener('change', function () {
        const dest = dests[Number(contents.value)];
        contents.value = '';
        if (dest === undefined) return;
        pageOf(dest).then(function (num) { if (num) goTo(num); }).catch(function () {});
      });
    }).catch(function () { /* no outline: nothing to offer */ });
  }).catch(function (err) {
    fail('the document could not be opened', err);
  });
})();

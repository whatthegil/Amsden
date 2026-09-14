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
      disableRange: false,
      disableStream: false,
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

      const width = pageWidth();
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
        holder.style.paddingTop = ((base.height / base.width) * 100) + '%';

        const canvas  = document.createElement('canvas');
        canvas.width  = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);

        const task = page.render({ canvasContext: canvas.getContext('2d'), viewport });
        tasks.set(num, task);

        return task.promise.then(function () {
          tasks.delete(num);
          holder.textContent = '';
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
        holder.style.paddingTop = (ratio * 100) + '%';   // reserve height before render
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
  }).catch(function (err) {
    fail('the document could not be opened', err);
  });
})();

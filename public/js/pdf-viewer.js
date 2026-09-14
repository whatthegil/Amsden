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

  const loadingTask = pdfjsLib.getDocument({
    url: view.dataset.pdfUrl,
    withCredentials: true,          // the file route is behind the session
    disableRange: false,
    disableStream: false,
  });

  // These documents run to tens of megabytes and travel through PHP from
  // object storage, so the wait before the first page appears is long enough
  // that a motionless "Loading" reads as a viewer that has hung.
  loadingTask.onProgress = function (progress) {
    if (!progress || !progress.total) return;
    const pct = Math.min(100, Math.round((progress.loaded / progress.total) * 100));
    setStatus('Loading document… ' + pct + '%');
  };

  loadingTask.promise.then(function (pdf) {
    setStatus('');

    const holders  = new Map();   // page number -> holder element
    const rendered = new Map();   // page number -> canvas
    const keep     = new Set();   // pages close enough to hold in memory

    function release(num) {
      const canvas = rendered.get(num);
      if (!canvas) return;
      canvas.width = 0;             // frees the backing store, not just the node
      canvas.height = 0;
      canvas.remove();
      rendered.delete(num);
    }

    function render(holder, num) {
      if (rendered.has(num) || holder.dataset.busy === '1') return;

      const width = pageWidth();
      // Nothing to draw into yet: the viewer is still being laid out, or is
      // hidden. The observers fire again on the next scroll or resize.
      if (width <= 0) return;

      holder.dataset.busy = '1';

      pdf.getPage(num).then(function (page) {
        const base     = page.getViewport({ scale: 1 });
        const viewport = page.getViewport({ scale: (width / base.width) * scaleFor() });

        // Pages within one document are not always the same size, so correct
        // the height reserved up front now this page's own is known.
        holder.style.paddingTop = ((base.height / base.width) * 100) + '%';

        const canvas  = document.createElement('canvas');
        canvas.width  = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);

        return page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise.then(function () {
          holder.dataset.busy = '0';
          holder.textContent = '';
          // Scrolled far away while this was drawing: drop it rather than keep
          // a canvas for a page nowhere near the viewport.
          if (!keep.has(num)) return;
          holder.appendChild(canvas);
          rendered.set(num, canvas);
        });
      }).catch(function (err) {
        holder.dataset.busy = '0';
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

    // A generous margin so a page is drawn before it is scrolled to, and let go
    // only once it is well out of the way. The two margins differ on purpose: a
    // page is not released the moment it stops being a render candidate, so
    // scrolling a little way back and forth does not redraw it.
    const near = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) render(entry.target, Number(entry.target.dataset.page));
      });
    }, { rootMargin: '1200px 0px' });

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
    }, { rootMargin: '3000px 0px' });

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
        near.observe(holder);
        far.observe(holder);
      }
    }).catch(function (err) {
      fail('the first page could not be read', err);
    });

    // Rotating a phone, or collapsing the sidebar, changes the width every
    // canvas was sized against. The pages still fit afterwards - CSS scales
    // them - but they stay at the old resolution until they are drawn again.
    let resizeTimer = null;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        if (pageWidth() <= 0) return;
        Array.from(keep).forEach(function (num) {
          release(num);
          const holder = holders.get(num);
          if (holder) render(holder, num);
        });
      }, 250);
    });
  }).catch(function (err) {
    fail('the document could not be opened', err);
  });
})();

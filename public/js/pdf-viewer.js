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
  if (!view || typeof pdfjsLib === 'undefined') return;

  const pagesEl  = document.getElementById('pdf-pages');
  const statusEl = document.getElementById('pdf-status');

  pdfjsLib.GlobalWorkerOptions.workerSrc = view.dataset.workerUrl;

  // Above 1.5 the memory cost on a phone outweighs the sharpness gained.
  const scaleFor = () => Math.min(window.devicePixelRatio || 1, 1.5);

  function setStatus(text) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.style.display = text ? '' : 'none';
  }

  pdfjsLib.getDocument({
    url: view.dataset.pdfUrl,
    withCredentials: true,          // the file route is behind the session
    disableRange: false,
    disableStream: false,
  }).promise.then(function (pdf) {
    setStatus('');

    const rendered = new Map();

    function release(num) {
      const canvas = rendered.get(num);
      if (!canvas) return;
      canvas.width = 0;             // frees the backing store, not just the node
      canvas.height = 0;
      canvas.removeAttribute('style');
      rendered.delete(num);
    }

    function render(holder, num) {
      if (rendered.has(num) || holder.dataset.busy === '1') return;
      holder.dataset.busy = '1';

      pdf.getPage(num).then(function (page) {
        const scale    = scaleFor();
        const viewport = page.getViewport({ scale: (holder.clientWidth / page.getViewport({ scale: 1 }).width) * scale });
        const canvas   = document.createElement('canvas');
        canvas.width   = Math.floor(viewport.width);
        canvas.height  = Math.floor(viewport.height);
        canvas.style.width = '100%';

        return page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise.then(function () {
          holder.innerHTML = '';
          holder.appendChild(canvas);
          rendered.set(num, canvas);
          holder.dataset.busy = '0';
        });
      }).catch(function () {
        holder.dataset.busy = '0';
        holder.textContent = 'This page could not be displayed.';
      });
    }

    // A generous margin so a page is drawn before it is scrolled to, and let go
    // only once it is well out of the way.
    const near = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        const num = Number(entry.target.dataset.page);
        if (entry.isIntersecting) render(entry.target, num);
      });
    }, { rootMargin: '1200px 0px' });

    const far = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) release(Number(entry.target.dataset.page));
      });
    }, { rootMargin: '3000px 0px' });

    // Lay every page out first, at its real proportions, so the document has a
    // correct height immediately.
    const first = pdf.getPage(1);
    first.then(function (page) {
      const base  = page.getViewport({ scale: 1 });
      const ratio = base.height / base.width;

      for (let n = 1; n <= pdf.numPages; n++) {
        const holder = document.createElement('div');
        holder.className = 'pdf-page';
        holder.dataset.page = String(n);
        holder.style.paddingTop = (ratio * 100) + '%';   // reserve height before render
        pagesEl.appendChild(holder);
        near.observe(holder);
        far.observe(holder);
      }
    });
  }).catch(function () {
    setStatus('This document could not be loaded. Please try again, or contact the CSPC Library.');
  });
})();

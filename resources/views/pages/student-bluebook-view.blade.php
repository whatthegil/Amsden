@php
  $title = $bluebook['title'];
  // The same page serves the admin, who reads any bluebook at any stage and all
  // of it, from the admin side of the app.
  $asAdmin = $asAdmin ?? false;
@endphp
@include('partials.head')

<div class="app">
  @include($asAdmin ? 'partials.admin-sidebar' : 'partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Bluebook Detail</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content">
      <div style="margin-bottom:1.25rem;display:flex;gap:0.5rem;">
        @if($asAdmin)
          <a href="{{ route('admin.bluebooks') }}" class="btn btn-outline btn-sm">← Back to Bluebooks</a>
          @if(\App\Models\User::allows($user, 'manage_bluebooks') || \App\Models\User::allows($user, 'review_bluebooks'))
            <a href="{{ route('admin.bluebooks.edit', $bluebook['id']) }}" class="btn btn-outline btn-sm">{{ \App\Models\User::allows($user, 'manage_bluebooks') ? 'Edit' : 'Record waiver' }}</a>
          @endif
          @if(\App\Models\User::allows($user, 'approve_bluebooks') && in_array($bluebook['status'], ['Approved', \App\Models\Bluebook::STATUS_AWAITING_WAIVER], true))
            {{-- Undo an approval made by mistake, or to correct the record. --}}
            <details class="reject-box">
              <summary class="btn btn-warning btn-sm">Recall to Pending</summary>
              <form method="POST" action="{{ route('admin.bluebooks.recall', $bluebook['id']) }}">
                @csrf
                <span style="font-size:0.8rem;">Undo the approval? It is taken out of Browse until approved again; its waiver, views and bookmarks are kept.</span>
                <button type="submit" class="btn btn-warning btn-sm">Confirm Recall</button>
              </form>
            </details>
          @endif
        @else
          <a href="{{ route('student.bluebooks') }}" class="btn btn-outline btn-sm">← Back to Browse</a>
        @endif
      </div>

      <div class="bluebook-detail" id="bluebook-detail" data-bluebook-id="{{ $bluebook['id'] }}"
           data-viewer="{{ $user['email'] ?? '' }}">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;">
          <h1>{{ $bluebook['title'] }}</h1>
          @unless($asAdmin)
          <div style="flex-shrink:0;">
            @if($isBookmarked)
              <form method="POST" action="{{ route('student.bookmarks.remove', $bluebook['id']) }}?from=view">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" fill="currentColor" fill-opacity="0.18"/><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  Remove Bookmark
                </button>
              </form>
            @else
              <form method="POST" action="{{ route('student.bookmarks.add', $bluebook['id']) }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" fill="currentColor" fill-opacity="0.18"/><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  Bookmark
                </button>
              </form>
            @endif
          </div>
          @endunless
        </div>

        <div class="bluebook-meta">
          @if($asAdmin)
            @if($bluebook['status'] === 'Approved') <span class="meta-pill badge-green">Posted</span>
            @elseif($bluebook['status'] === \App\Models\Bluebook::STATUS_AWAITING_WAIVER) <span class="meta-pill badge-blue">Awaiting Waiver</span>
            @elseif($bluebook['status'] === 'Pending') <span class="meta-pill badge-yellow">Pending</span>
            @else <span class="meta-pill badge-red">Rejected</span>
            @endif
          @endif
          <span class="meta-pill">{{ $bluebook['department'] }}</span>
          <span class="meta-pill">{{ $bluebook['year'] }}</span>
          <span class="meta-pill">{{ $bluebook['pages'] }} pages</span>
          <span class="meta-pill">{{ $bluebook['views'] }} views</span>
        </div>

        <div style="font-size:0.9rem;color:var(--gray-600);margin-bottom:1.5rem;">
          <strong>Authors:</strong> {{ implode(', ', $bluebook['authors']) }}
        </div>

        <h4 style="font-size:0.82rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.6rem;">Abstract</h4>
        <div class="bluebook-abstract">{{ $bluebook['abstract'] }}</div>

        <h4 style="font-size:0.82rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.6rem;">Keywords</h4>
        <div class="keywords">
          @foreach($bluebook['keywords'] as $kw)
            <span class="keyword">{{ $kw }}</span>
          @endforeach
        </div>

        @php
          $deptName = config('departments.' . $bluebook['department'] . '.name');
          $access   = \App\Models\Bluebook::accessName($bluebook['accessLevel']);
          $citation = \App\Services\LiteratureReviewService::citation($bluebook);
        @endphp
        <div class="info-grid">
          <div class="info-row">
            <span class="key">Department</span>
            <span class="val">{{ $bluebook['department'] }}{{ $deptName && $deptName !== $bluebook['department'] ? ' — ' . $deptName : '' }}</span>
          </div>
          <div class="info-row">
            <span class="key">Program</span>
            <span class="val">{{ $bluebook['program'] }}</span>
          </div>
          <div class="info-row">
            <span class="key">Adviser</span>
            <span class="val">{{ $bluebook['adviser'] }}</span>
          </div>
          <div class="info-row">
            <span class="key">Year &middot; Pages</span>
            <span class="val">{{ $bluebook['year'] }} &middot; {{ $bluebook['pages'] }} pages</span>
          </div>
          <div class="info-row">
            <span class="key">Access</span>
            <span class="val">{{ $access }}</span>
          </div>
        </div>

        {{-- The citation a student needs to reference this paper. --}}
        <div class="cite-box">
          <div>
            <span class="key">Cite this paper (APA 7) &middot; in text: {{ $citation['inText'] }}</span>
            <div class="val">{!! $citation['html'] !!}</div>
          </div>
          <button type="button" class="btn btn-outline btn-sm" data-open-modal="cite-modal">Cite</button>
        </div>

        @if($asAdmin && in_array($bluebook['status'], ['Pending', 'Rejected'], true))
          {{-- The Library Manual's criteria for evaluating a submission (5.2.1),
               pre-filled from the OCR text and the record. The admin confirms or
               corrects each one; a comment box opens on those marked not okay,
               and that is what the author is shown. --}}
          @php
            $evalItems   = \App\Services\BluebookEvaluation::items($bluebook);
            $canEvaluate = \App\Models\User::allows($user, 'review_bluebooks');
            $evalIssues  = count(array_filter($evalItems, fn($i) => $i['status'] === 'issue'));
          @endphp
          <details class="eval-checklist card" style="padding:0.9rem 1rem;" open>
            <summary style="cursor:pointer;font-weight:700;">Evaluation checklist
              <span style="font-weight:400;color:var(--gray-400);font-size:0.82rem;">(Library Manual 5.2.1 &middot; to be completed within 3 working days &middot;
                {{ $bluebook['evaluatedAt'] ? 'saved ' . $bluebook['evaluatedAt'] : 'checked automatically from the text, not yet saved' }})</span>
            </summary>
            <form method="POST" action="{{ route('admin.bluebooks.evaluate', $bluebook['id']) }}">
              @csrf
              <fieldset @disabled(!$canEvaluate) style="border:0;padding:0;margin:0;min-width:0;">
              <ul>
                @foreach($evalItems as $key => $item)
                  <li class="eval-item is-{{ $item['status'] }}" data-eval-item>
                    <div class="eval-label">{{ $item['label'] }}</div>
                    <div class="eval-note">
                      @if($item['auto'] === 'ok') <span class="badge badge-green">Auto: okay</span>
                      @elseif($item['auto'] === 'issue') <span class="badge badge-red">Auto: not okay</span>
                      @else <span class="badge badge-gray">Check manually</span>
                      @endif
                      {{ $item['note'] }}
                    </div>
                    <div class="eval-choice" role="radiogroup" aria-label="{{ $item['label'] }}">
                      <label><input type="radio" name="status[{{ $key }}]" value="ok" @checked($item['status'] === 'ok')> Okay</label>
                      <label><input type="radio" name="status[{{ $key }}]" value="issue" @checked($item['status'] === 'issue')> Not okay / missing</label>
                    </div>
                    <div class="eval-comment" @if($item['status'] !== 'issue') hidden @endif>
                      <label class="sr-only" for="eval-comment-{{ $key }}">What is the problem with: {{ $item['label'] }}</label>
                      <textarea id="eval-comment-{{ $key }}" name="comment[{{ $key }}]" rows="2" maxlength="{{ \App\Services\BluebookEvaluation::COMMENT_MAX }}"
                                placeholder="Tell the uploader what is wrong or missing">{{ $item['comment'] }}</textarea>
                    </div>
                  </li>
                @endforeach
              </ul>
              @if($canEvaluate)
                <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-top:0.75rem;">
                  <button type="submit" class="btn btn-primary btn-sm">Save evaluation</button>
                  <span style="font-size:0.82rem;color:var(--gray-600);">{{ $evalIssues }} not okay. The uploader sees those items and your comments in My Uploads.</span>
                </div>
              @endif
              </fieldset>
            </form>
          </details>
          <script nonce="{{ $cspNonce ?? '' }}">
          (function () {
            // The comment box belongs to "Not okay" only, and goes with it.
            document.querySelectorAll('[data-eval-item]').forEach(function (item) {
              const box = item.querySelector('.eval-comment');
              item.querySelectorAll('input[type=radio]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                  const issue = radio.value === 'issue';
                  box.hidden = !issue;
                  item.classList.toggle('is-issue', issue);
                  item.classList.toggle('is-ok', !issue);
                  item.classList.remove('is-unchecked');
                  if (issue) box.querySelector('textarea').focus();
                });
              });
            });
          })();
          </script>
        @endif

        <h4 style="font-size:0.82rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.6rem;">Document</h4>
        @if(session('success'))
          <div class="alert alert-success" style="margin-bottom:0.75rem;">{{ session('success') }}</div>
        @endif
        @if(session('error'))
          <div class="alert alert-error" style="margin-bottom:0.75rem;">{{ session('error') }}</div>
        @endif
        @if($bluebook['hasFile'] && $bluebook['accessLevel'] === 'consultation' && !$asAdmin)
          <div class="alert alert-info" style="margin-bottom:0.75rem;">
            <strong>Restricted Access.</strong> The author has not permitted this bluebook for general use. It may be viewed only with
            the author's written authorization or after consultation with them, which the library arranges on request.
          </div>
          <div style="font-size:0.78rem;color:var(--gray-400);margin-top:0.5rem;">Access logged for: {{ $user['email'] }}</div>
        @elseif($asAdmin && ($preview ?? false) && ($pageImages ?? 0) === 0)
          <div class="alert alert-info" style="margin-bottom:0.5rem;">
            Previewing as a reader: <strong>readers are sent no pages of this bluebook</strong>
            ({{ \App\Models\Bluebook::accessName($bluebook['accessLevel']) }}).
            <a href="{{ route('admin.bluebooks.view', $bluebook['id']) }}">Show the whole document</a>
          </div>
        @elseif($bluebook['hasFile'])
          @if($asAdmin && ($preview ?? false))
            <div class="alert alert-info" style="margin-bottom:0.75rem;">
              Previewing as a reader: these are the <strong>{{ $pageImages }} of {{ $bluebook['pageImagesCount'] }} pages</strong>
              a student is sent, numbered as they see them and marked with your email.
              <a href="{{ route('admin.bluebooks.view', $bluebook['id']) }}">Show the whole document</a>
            </div>
          @elseif($asAdmin)
            <div class="alert alert-info" style="margin-bottom:0.75rem;">
              You are seeing the whole document. Readers get:
              <strong>{{ \App\Models\Bluebook::accessName($bluebook['accessLevel']) }}</strong>@if($bluebook['accessLevel'] === 'partial' && $bluebook['accessParts']) &mdash;
                @foreach($bluebook['accessParts'] as $key => $range){{ \App\Models\Bluebook::ACCESS_PART_LABELS[$key] ?? $key }} (pp. {{ $range['from'] }}–{{ $range['to'] }}){{ $loop->last ? '' : ', ' }}@endforeach
              @endif.
              @if($bluebook['withheldPages']) Withheld from every reader: pp. {{ str_replace(',', ', ', $bluebook['withheldPages']) }}. @endif
              @unless($bluebook['waiverRecorded']) <em>(Not recorded yet.)</em> @endunless
              @if($canPreview ?? false)
                <a href="{{ route('admin.bluebooks.view', ['id' => $bluebook['id'], 'preview' => 'reader']) }}">Preview as a reader</a>
              @endif
            </div>
            @if(\App\Models\User::allows($user, 'manage_bluebooks'))
              @php
                $pagesReady   = \App\Services\Pdf\PageImages::ready($bluebook);
                $pagesStale   = !$pagesReady && ($bluebook['pageImagesCount'] ?? 0) > 0;
                $canRender    = \App\Services\Pdf\PageRenderer::tool() !== null && \App\Services\Pdf\PageImages::canWatermark();
              @endphp
              {{-- Readers are sent watermarked page images once these exist, and
                   the PDF until then. Drawn here a few pages per request, since
                   no worker on the server takes the render queue. --}}
              <div class="render-pages" id="render-pages"
                   data-url="{{ route('admin.bluebooks.renderPages', $bluebook['id']) }}"
                   data-estimate="{{ (int) $bluebook['pages'] }}">
                <span class="render-pages-status">
                  @if($pagesReady)
                    <strong>Watermarked pages: ready</strong> ({{ $bluebook['pageImagesCount'] }} pages) &mdash; readers get marked page images, never the PDF.
                  @elseif($pagesStale)
                    <strong>Watermarked pages: out of date</strong> &mdash; the file was replaced, so readers get the PDF until the pages are drawn again.
                  @else
                    <strong>Watermarked pages: not rendered</strong> &mdash; readers get the PDF, which can be saved unmarked, until they are.
                  @endif
                </span>
                @if($canRender)
                  <button type="button" class="btn btn-outline btn-sm" data-render-pages>{{ $pagesReady ? 'Render again' : 'Render pages' }}</button>
                @else
                  <em>This server cannot draw pages (see <code>php artisan system:check</code>).</em>
                @endif
              </div>
              <script nonce="{{ $cspNonce ?? '' }}">
              (function () {
                const box = document.getElementById('render-pages');
                const btn = box && box.querySelector('[data-render-pages]');
                if (!btn) return;
                const status   = box.querySelector('.render-pages-status');
                const estimate = Number(box.dataset.estimate) || 0;
                const token    = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                // One request per few pages, each well inside a request's time
                // limit, until the server says the document has run out.
                async function run(from) {
                  const body = new FormData();
                  body.append('from', String(from));
                  const res  = await fetch(box.dataset.url, {
                    method: 'POST', body: body, credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                  });
                  const data = await res.json().catch(() => ({ error: 'The server gave no answer (' + res.status + ').' }));
                  if (!res.ok || data.error) throw new Error(data.error || ('HTTP ' + res.status));
                  return data;
                }

                btn.addEventListener('click', async function () {
                  if (!confirm('Draw every page of this bluebook as a watermarked image? A long thesis takes a few minutes - keep this tab open until it finishes.')) return;
                  btn.disabled = true;
                  let from = 1;
                  try {
                    for (;;) {
                      status.textContent = 'Rendering… ' + (from - 1) + (estimate ? ' of about ' + estimate : '') + ' pages done. Keep this tab open.';
                      const r = await run(from);
                      if (r.done) {
                        status.innerHTML = '<strong>Watermarked pages: ready</strong> (' + r.rendered + ' pages) — readers get marked page images from now on.';
                        btn.textContent = 'Render again';
                        break;
                      }
                      from = r.next;
                    }
                  } catch (e) {
                    status.textContent = 'Stopped: ' + e.message + ' Readers still get what they got before; you can try again.';
                  } finally {
                    btn.disabled = false;
                  }
                });
              })();
              </script>
            @endif
          @elseif($bluebook['accessLevel'] === 'partial')
            <div class="alert alert-info" style="margin-bottom:0.75rem;">
              <strong>Partial Access.</strong> The author has permitted only certain parts of this bluebook to be viewed:
              @foreach($bluebook['accessParts'] as $key => $range)
                <strong>{{ \App\Models\Bluebook::ACCESS_PART_LABELS[$key] ?? $key }}</strong>
                (pp. {{ $range['from'] }}–{{ $range['to'] }}){{ $loop->last ? '.' : ',' }}
              @endforeach
            </div>
          @elseif($bluebook['accessLevel'] === 'legacy')
            <div class="alert alert-info" style="margin-bottom:0.75rem;">
              <strong>Legacy &ndash; No Access Permission on File.</strong> This work predates the Access Permission Waiver.
              It may be consulted here for academic purposes, but no copy of it may be made or shared.
            </div>
          @endif
          {{-- Rendered page by page to canvas by PDF.js rather than handed to
               the browser's own viewer in an iframe. Android's WebView ships no
               PDF renderer at all, so the iframe was simply blank there; this
               also removes the built-in viewer's download and print controls,
               and lets the watermark sit over the pages instead of beside
               them. --}}
          {{-- Where the disk can sign a link the document is fetched straight
               from storage: the object stops travelling through PHP on every
               read, and the bucket answers range requests, so the first page
               arrives after a few kilobytes instead of after all 29 MB. The
               streaming route stays as the fallback - it is what serves a disk
               that cannot sign, and what the viewer retries on if the direct
               fetch is refused (a bucket without CORS configured, or a link
               that has outlived the reading session). --}}
          <div class="pdf-view watermark-overlay"
               id="pdf-view"
               data-pdf-url="{{ $asAdmin ? route('admin.bluebooks.file', $bluebook['id']) : ($fileUrl ?? route('student.bluebook.file', $bluebook['id'])) }}"
               @if($fileUrl)
                 data-direct="1"
                 data-fallback-url="{{ route('student.bluebook.file', $bluebook['id']) }}"
               @endif
               @if(($pageImages ?? 0) > 0)
                 {{-- Watermarked page images instead of the PDF: see PageImages.
                      An admin gets them only when previewing as a reader. --}}
                 data-pages-url="{{ route($asAdmin ? 'admin.bluebooks.page' : 'student.bluebook.page', [$bluebook['id'], '__N__', 'v' => \App\Services\Pdf\PageImages::version($bluebook, $user['email'] ?? '')]) }}"
                 data-page-count="{{ $pageImages }}"
               @endif
               data-worker-url="/vendor/pdfjs/pdf.worker.min.js">
            {{-- Reading controls; pdf-viewer.js enables them once the document is open. --}}
            <div class="pdf-toolbar" id="pdf-toolbar" role="toolbar" aria-label="Document controls">
              <div class="pdf-tool-group">
                <button type="button" class="pdf-tool" data-pdf="prev" aria-label="Previous page" disabled>&lsaquo;</button>
                <label class="pdf-page-jump">
                  <span class="sr-only">Page number</span>
                  Page <input type="number" min="1" value="1" id="pdf-page-input" inputmode="numeric" disabled>
                  of <span id="pdf-page-count">–</span>
                </label>
                <button type="button" class="pdf-tool" data-pdf="next" aria-label="Next page" disabled>&rsaquo;</button>
              </div>
              <div class="pdf-tool-group">
                <button type="button" class="pdf-tool" data-pdf="zoom-out" aria-label="Zoom out" disabled>&minus;</button>
                <span class="pdf-zoom-level" id="pdf-zoom-level">100%</span>
                <button type="button" class="pdf-tool" data-pdf="zoom-in" aria-label="Zoom in" disabled>+</button>
                <button type="button" class="pdf-tool pdf-tool-text" data-pdf="fit" disabled>Fit width</button>
              </div>
              <div class="pdf-tool-group">
                <label class="sr-only" for="pdf-contents">Jump to a section</label>
                <select id="pdf-contents" class="pdf-contents" hidden><option value="">Contents</option></select>
                <button type="button" class="pdf-tool pdf-tool-text" data-pdf="fullscreen" disabled>&#x26F6; Full screen</button>
              </div>
            </div>
            <div class="pdf-status" id="pdf-status">Loading document&hellip;</div>
            <div class="pdf-pages" id="pdf-pages"></div>
          </div>
          <div style="font-size:0.78rem;color:var(--gray-400);margin-top:0.5rem;">Access logged for: {{ $user['email'] }}</div>
          @unless($asAdmin)
            {{-- Library Manual 4.3.1.2 and Responsibilities of Users. --}}
            <p class="use-notice">
              For research, instruction and academic purposes only. Cite the author/s properly. Reproducing, downloading,
              photographing, scanning or sharing this work is prohibited. Some pages may be withheld to protect personal
              information. See the <a href="{{ route('terms') }}" target="_blank" rel="noopener">Terms and Conditions</a>.
            </p>
          @endunless
        @else
          <div class="watermark-overlay" style="margin-top:0;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" style="margin:0 auto 1rem;display:block;"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" fill="var(--gray-400)" fill-opacity="0.18"/><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" fill="none" stroke="var(--gray-400)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <div style="font-size:0.9rem;color:var(--gray-600);margin-bottom:0.5rem;">No document file was attached to this record.</div>
            <div style="font-size:0.78rem;color:var(--gray-400);">Access logged for: {{ $user['email'] }}</div>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;opacity:0.08;font-size:2.5rem;font-weight:700;letter-spacing:0.2em;transform:rotate(-25deg);color:var(--primary-dark);user-select:none;">CSPC ARCHIVE</div>
          </div>
        @endif
      </div>
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; Camarines Sur Polytechnic Colleges. All Rights Reserved.
    </footer>
  </main>
</div>

@if($bluebook['hasFile'])
  @if(($pageImages ?? 0) > 0)
    {{-- Start on page one now, while the viewer script is still loading. --}}
    <link rel="preload" as="image" href="{{ route($asAdmin ? 'admin.bluebooks.page' : 'student.bluebook.page', [$bluebook['id'], 1, 'v' => \App\Services\Pdf\PageImages::version($bluebook, $user['email'] ?? '')]) }}">
  @endif
  {{-- The page-image viewer draws pictures, so it needs no PDF engine. --}}
  @if(($pageImages ?? 0) === 0)
    <script src="/vendor/pdfjs/pdf.min.js"></script>
  @endif
  <script src="/js/pdf-viewer.js?v={{ filemtime(public_path('js/pdf-viewer.js')) }}"></script>
@endif
{{-- The same paper in each style a student may be asked for. --}}
<x-cite-modal id="cite-modal" :styles="\App\Services\CitationFormats::all($bluebook)" />
@include('partials.footer')

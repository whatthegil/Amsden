{{-- A paper (or a reference list) in each citation style, one Copy per style.
     Opened by a button carrying data-open-modal="{{ $id }}"; main.js does the
     opening and closing. $styles comes from CitationFormats::all() or ::lists(). --}}
@props(['id', 'title' => 'Cite this paper', 'styles' => []])

<div class="modal-overlay" id="{{ $id }}" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
  <div class="modal-card cite-modal">
    <div class="modal-header">
      <h3 id="{{ $id }}-title">{{ $title }}</h3>
      <button type="button" class="modal-close" data-close-modal aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      @foreach($styles as $style)
        <div class="cite-format">
          <div class="cite-format-head">
            <span class="key">{{ $style['label'] }}</span>
            <button type="button" class="btn btn-outline btn-sm" data-copy="{{ $style['text'] }}">Copy</button>
          </div>
          <div class="val">{!! $style['html'] !!}</div>
        </div>
      @endforeach
    </div>
  </div>
</div>

{{-- The access permission waiver: which of the bluebook readers may see.
     $level is the chosen level (or null for none), $parts the saved page
     ranges keyed by part, and the slot is the hint shown under the legend.
     $legacy offers "no waiver on file", for records the library added itself;
     $withheld is the saved list of pages no reader is sent. --}}
@props(['level' => null, 'parts' => [], 'checked' => [], 'legacy' => false, 'withheld' => ''])

<fieldset class="access-waiver">
  <legend>Access Permission Waiver</legend>
  <p class="form-hint" style="margin:0 0 0.75rem;">{{ $slot }}</p>

  @foreach(\App\Models\Bluebook::ACCESS_LEVELS as $value => $label)
    <label class="access-option">
      <input type="radio" name="access_level" value="{{ $value }}" required
             @checked($level === $value)>
      <span><strong>{{ \App\Models\Bluebook::accessName($value) }}</strong> &mdash; {{ $label }}</span>
    </label>
  @endforeach
  @if($legacy)
    <label class="access-option">
      <input type="radio" name="access_level" value="{{ \App\Models\Bluebook::ACCESS_LEGACY }}" required
             @checked($level === \App\Models\Bluebook::ACCESS_LEGACY)>
      <span><strong>{{ \App\Models\Bluebook::accessName(\App\Models\Bluebook::ACCESS_LEGACY) }}</strong> &mdash; no signed waiver is on record. Readers may view it in the watermarked viewer only; it is never treated as an open copy.</span>
    </label>
  @endif

  <div id="access-parts" class="access-parts" @if($level !== \App\Models\Bluebook::ACCESS_PARTIAL) hidden @endif>
    <p class="form-hint" style="margin:0 0 0.5rem;">Tick each part readers may see and give the PDF pages it covers. Pages you do not list are left out of what readers receive.</p>
    <label class="access-option access-parts-all">
      <input type="checkbox" id="access-parts-all">
      <span>Select all parts</span>
    </label>
    @foreach(\App\Models\Bluebook::ACCESS_PARTS as $key => $label)
      <div class="access-part">
        <label class="access-option">
          <input type="checkbox" name="access_parts[]" value="{{ $key }}"
                 @checked(in_array($key, $checked, true))>
          <span>{{ $label }}</span>
        </label>
        <span class="access-range">
          <label class="sr-only" for="part-from-{{ $key }}">{{ $label }} first page</label>
          <input id="part-from-{{ $key }}" type="number" min="1" name="part_from[{{ $key }}]" placeholder="From" value="{{ $parts[$key]['from'] ?? '' }}">
          <span aria-hidden="true">–</span>
          <label class="sr-only" for="part-to-{{ $key }}">{{ $label }} last page</label>
          <input id="part-to-{{ $key }}" type="number" min="1" name="part_to[{{ $key }}]" placeholder="To" value="{{ $parts[$key]['to'] ?? '' }}">
        </span>
      </div>
    @endforeach
  </div>

  {{-- Library Manual 4.3.1.3: the CV, contact details, signatures and ID
       numbers are never made publicly accessible, whatever the level. --}}
  <div class="form-group" style="margin:1rem 0 0;">
    <label for="withheld-pages">Withheld pages <span style="font-weight:400;color:var(--gray-400);">(optional)</span></label>
    <input id="withheld-pages" type="text" name="withheld_pages" value="{{ $withheld }}" placeholder="e.g. 3, 148-152" maxlength="255">
    <p class="form-hint" style="margin:0.35rem 0 0;">Pages holding the curriculum vitae, contact details, signatures, ID numbers or other sensitive personal information. No reader is sent these pages, under any access level or approved request.</p>
  </div>
</fieldset>

<script nonce="{{ $cspNonce ?? '' }}">
// The part list only applies to a partial waiver, and a part's page range only
// once it is ticked - then the range is required, since it is what decides
// which pages readers are sent.
function toggleAccessParts() {
  const chosen  = document.querySelector('input[name="access_level"]:checked');
  const partial = !!chosen && chosen.value === 'partial';
  document.getElementById('access-parts').hidden = !partial;

  const boxes = document.querySelectorAll('.access-part input[type="checkbox"]');
  let ticked = 0;
  document.querySelectorAll('.access-part').forEach(function (row) {
    const box = row.querySelector('input[type="checkbox"]');
    const on  = partial && box.checked;
    if (box.checked) ticked++;
    row.querySelectorAll('.access-range input').forEach(function (input) {
      input.disabled = !on;
      input.required = on;
    });
  });

  // "Select all" follows the parts: ticked when every part is, half-ticked
  // when only some are.
  const all = document.getElementById('access-parts-all');
  if (all) {
    all.checked       = ticked === boxes.length && boxes.length > 0;
    all.indeterminate = ticked > 0 && ticked < boxes.length;
  }
}

function checkAllAccessParts(on) {
  document.querySelectorAll('.access-part input[type="checkbox"]').forEach(function (box) {
    box.checked = on;
  });
  toggleAccessParts();
}
document.querySelectorAll('input[name="access_level"], .access-part input[type="checkbox"]').forEach(function (input) {
  input.addEventListener('change', toggleAccessParts);
});
document.getElementById('access-parts-all').addEventListener('change', function () {
  checkAllAccessParts(this.checked);
});
toggleAccessParts();
</script>

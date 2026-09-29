{{-- The access permission waiver: which of the bluebook readers may see.
     $level is the chosen level (or null for none), $parts the saved page
     ranges keyed by part, and the slot is the hint shown under the legend. --}}
@props(['level' => null, 'parts' => [], 'checked' => []])

<fieldset class="access-waiver">
  <legend>Access Permission Waiver</legend>
  <p class="form-hint" style="margin:0 0 0.75rem;">{{ $slot }}</p>

  @foreach(\App\Models\Bluebook::ACCESS_LEVELS as $value => $label)
    <label class="access-option">
      <input type="radio" name="access_level" value="{{ $value }}" required
             @checked($level === $value) onchange="toggleAccessParts()">
      <span>{{ $label }}</span>
    </label>
  @endforeach

  <div id="access-parts" class="access-parts" @if($level !== \App\Models\Bluebook::ACCESS_PARTIAL) hidden @endif>
    <p class="form-hint" style="margin:0 0 0.5rem;">Tick each part readers may see and give the PDF pages it covers. Pages you do not list are left out of what readers receive.</p>
    <label class="access-option access-parts-all">
      <input type="checkbox" id="access-parts-all" onchange="checkAllAccessParts(this.checked)">
      <span>Select all parts</span>
    </label>
    @foreach(\App\Models\Bluebook::ACCESS_PARTS as $key => $label)
      <div class="access-part">
        <label class="access-option">
          <input type="checkbox" name="access_parts[]" value="{{ $key }}"
                 @checked(in_array($key, $checked, true)) onchange="toggleAccessParts()">
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
</fieldset>

<script>
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
toggleAccessParts();
</script>

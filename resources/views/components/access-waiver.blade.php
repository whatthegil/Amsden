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

  document.querySelectorAll('.access-part').forEach(function (row) {
    const on = partial && row.querySelector('input[type="checkbox"]').checked;
    row.querySelectorAll('.access-range input').forEach(function (input) {
      input.disabled = !on;
      input.required = on;
    });
  });
}
toggleAccessParts();
</script>

{{--
  The Department and Program pickers, shared by the upload form and the admin
  bluebook form.

  Program lists only the programs of the chosen department, and waits until a
  department is picked. Without JavaScript it falls back to every program,
  grouped by department; the server checks the pair either way
  (App\Rules\ProgramInDepartment).

  A bluebook filed under a program the list no longer carries keeps it as an
  option, marked, so opening the edit form does not silently blank it.

  Usage:

    <x-department-program prefix="upload" :department="$old['department'] ?? ''" :program="$old['program'] ?? ''" />
--}}
@props(['prefix', 'department' => '', 'program' => ''])

@php
  $departments = config('departments');
  $label  = fn ($code, $dept) => $code === $dept['name'] ? $code : "$code — {$dept['name']}";
  $listed = collect($departments)->pluck('programs')->flatten()->all();
  $legacy = $program !== '' && $program !== null && !in_array($program, $listed, true) ? $program : null;
  $legacyDept = $department !== '' && $department !== null && !array_key_exists($department, $departments) ? $department : null;
@endphp

<div class="form-group">
  <label for="{{ $prefix }}-department">College / Department</label>
  <select id="{{ $prefix }}-department" name="department" required data-department-picker="{{ $prefix }}-program">
    <option value="">Select a department</option>
    @if($legacyDept)
      <option value="{{ $legacyDept }}" selected>{{ $legacyDept }} (no longer listed)</option>
    @endif
    @foreach($departments as $code => $dept)
      <option value="{{ $code }}" @selected($department === $code)>{{ $label($code, $dept) }}</option>
    @endforeach
  </select>
</div>

<div class="form-group">
  <label for="{{ $prefix }}-program">Program</label>
  <select id="{{ $prefix }}-program" name="program" required>
    <option value="">Select a program</option>
    @if($legacy)
      <optgroup label="Current (no longer listed)" data-department="*">
        <option value="{{ $legacy }}" selected>{{ $legacy }}</option>
      </optgroup>
    @endif
    @foreach($departments as $code => $dept)
      <optgroup label="{{ $label($code, $dept) }}" data-department="{{ $code }}">
        @foreach($dept['programs'] as $p)
          <option value="{{ $p }}" @selected(!$legacy && $program === $p && $department === $code)>{{ $p }}</option>
        @endforeach
      </optgroup>
    @endforeach
  </select>
  <p class="form-hint" id="{{ $prefix }}-program-hint">Pick the department first to see its programs.</p>
</div>

@once
<script nonce="{{ $cspNonce ?? '' }}">
// Narrow each Program list to the chosen department. The full, grouped list is
// kept aside and the matching options copied back in, since hiding an
// <optgroup> is not honoured by every browser (Safari on iOS among them).
// Run once the page is parsed, so a picker further down is found too.
function initDepartmentPickers() {
document.querySelectorAll('[data-department-picker]').forEach(function (dept) {
  const program = document.getElementById(dept.dataset.departmentPicker);
  const hint    = document.getElementById(program.id + '-hint');
  const groups  = Array.from(program.querySelectorAll('optgroup')).map(function (g) { return g.cloneNode(true); });

  function refresh() {
    const chosen = program.value;
    // Everything but the "Select…" placeholder goes, then the matches come back.
    while (program.children.length > 1) program.lastElementChild.remove();

    groups.forEach(function (g) {
      if (g.dataset.department !== '*' && g.dataset.department !== dept.value) return;
      const copy = g.cloneNode(true);
      // Only one department's programs remain, so its heading adds nothing -
      // except for the kept, no-longer-listed program, which needs its note.
      if (g.dataset.department === '*') {
        program.appendChild(copy);
      } else {
        Array.from(copy.children).forEach(function (o) { program.appendChild(o); });
      }
    });

    const match = Array.from(program.options).find(function (o) { return chosen !== '' && o.value === chosen; });
    if (match) match.selected = true; else program.value = '';

    program.disabled = dept.value === '';
    program.options[0].textContent = dept.value === '' ? 'Select a department first' : 'Select a program';
    if (hint) hint.hidden = dept.value !== '';
  }

  dept.addEventListener('change', refresh);
  refresh();
});
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDepartmentPickers);
} else {
  initDepartmentPickers();
}
</script>
@endonce

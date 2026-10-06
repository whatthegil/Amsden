{{-- Narrows a similarity check or literature search to one college's bluebooks.
     Expects $id (unique per form) and $department (the selected code, or null). --}}
<div class="form-group">
  <label for="{{ $id }}">Department</label>
  <select id="{{ $id }}" name="department" aria-describedby="{{ $id }}-hint">
    <option value="" @selected(!($department ?? null))>All departments</option>
    @foreach(config('departments') as $code => $dept)
      <option value="{{ $code }}" @selected(($department ?? null) === $code)>{{ $code === $dept['name'] ? $code : "$code — {$dept['name']}" }}</option>
    @endforeach
  </select>
  <small style="color:var(--gray-400);font-size:0.8rem;" id="{{ $id }}-hint">Pick your college to compare only against its bluebooks — faster, and more to the point.</small>
</div>

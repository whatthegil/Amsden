{{-- A reader's request for the full text of a Restricted or Partial Access
     bluebook (Library Manual 4.3.1.4), or where their request stands.
     Expects $bluebook, $accessRequest (their live request or null) and
     $lastRequest (their latest request for any bluebook, to pre-fill). --}}
@if($accessRequest && $accessRequest->status === \App\Models\AccessRequest::STATUS_PENDING)
  <div class="alert alert-info" style="margin-bottom:0.75rem;">
    You requested full-text access on {{ $accessRequest->created_at->timezone('Asia/Manila')->format('M j, Y') }}.
    The library is evaluating it. You can follow it under <a href="{{ route('student.access-requests') }}">Access Requests</a>.
  </div>
@elseif(!$accessRequest)
  <details class="access-request" @if($errors->hasAny(['research_title', 'program', 'adviser', 'purpose', 'agree'])) open @endif>
    <summary class="btn btn-outline btn-sm">Request full-text access</summary>
    <form method="POST" action="{{ route('student.bluebook.request-access', $bluebook['id']) }}" class="access-request-form">
      @csrf
      <p class="form-hint" style="margin:0 0 0.75rem;">
        The library evaluates each title on its own and grants access only with the authorization the author/s
        require. Approval lets you <strong>view</strong> the full text here. It does not give you a copy.
      </p>
      @if ($errors->hasAny(['research_title', 'program', 'adviser', 'purpose', 'agree']))
        <div class="alert alert-error" role="alert" style="margin-bottom:0.75rem;">
          @foreach ($errors->only(['research_title', 'program', 'adviser', 'purpose', 'agree']) as $message)
            <div>{{ $message }}</div>
          @endforeach
        </div>
      @endif
      <div class="form-group">
        <label for="ar-title">Your research / capstone title</label>
        <input id="ar-title" type="text" name="research_title" maxlength="500" required
               value="{{ old('research_title', $lastRequest->research_title ?? '') }}">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="ar-program">Program</label>
          <input id="ar-program" type="text" name="program" maxlength="255" required
                 value="{{ old('program', $lastRequest->program ?? '') }}">
        </div>
        <div class="form-group">
          <label for="ar-adviser">Research adviser</label>
          <input id="ar-adviser" type="text" name="adviser" maxlength="255" required
                 value="{{ old('adviser', $lastRequest->adviser ?? '') }}">
        </div>
      </div>
      <div class="form-group">
        <label for="ar-purpose">Purpose of use</label>
        <textarea id="ar-purpose" name="purpose" rows="3" maxlength="2000" required
                  placeholder="How this work relates to your research, and which parts you need.">{{ old('purpose') }}</textarea>
      </div>
      <label class="access-option" style="margin-bottom:0.75rem;">
        <input type="checkbox" name="agree" value="1" required @checked(old('agree'))>
        <span>I will use this material only for research, instruction or academic purposes, cite the author/s,
          and not reproduce, photograph, download or share it.</span>
      </label>
      <button type="submit" class="btn btn-primary btn-sm">Send request</button>
    </form>
  </details>
@endif

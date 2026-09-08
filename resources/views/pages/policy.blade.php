@php $title = 'Acceptable Use Policy'; @endphp
@include('partials.head')

<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;z-index:1;padding:2rem;">
  <div style="background:rgba(255,255,255,0.97);border-radius:16px;padding:3rem;max-width:680px;width:100%;box-shadow:0 24px 80px rgba(0,0,0,0.4);">
    <div style="text-align:center;margin-bottom:2rem;">
      <img src="/images/cspc-logo.png" alt="CSPC" width="60" height="60" style="border-radius:10px;margin:0 auto 1rem;">
      <h1 style="font-weight:700;letter-spacing:-0.02em;color:var(--gray-800);font-size:1.6rem;margin-bottom:0.4rem;">Before you continue</h1>
      <p style="color:var(--gray-600);font-size:0.9rem;">C-BAMS &mdash; Bluebook Archive Management System</p>
    </div>

    @if ($errors->any())
      <div class="alert alert-error" role="alert" style="margin-bottom:1.25rem;">{{ $errors->first() }}</div>
    @endif

    <div style="background:var(--primary-light);border-left:3px solid var(--primary);padding:1.25rem 1.5rem;border-radius:0 8px 8px 0;margin-bottom:1.5rem;font-size:0.9rem;line-height:1.8;color:var(--gray-800);">
      <p style="margin-bottom:0.75rem;">By using the C-BAMS system, you agree to the following terms:</p>
      <ol style="padding-left:1.5rem;">
        <li style="margin-bottom:0.5rem;">All research materials are for academic and educational purposes only.</li>
        <li style="margin-bottom:0.5rem;">You must not reproduce, distribute, or use any content without proper attribution.</li>
        <li style="margin-bottom:0.5rem;">Uploaded bluebooks must be original works you authored or have explicit permission to submit.</li>
        <li style="margin-bottom:0.5rem;">Plagiarism, misrepresentation, or any fraudulent activity will result in immediate account suspension.</li>
        <li style="margin-bottom:0.5rem;">Your access activity is monitored and logged for institutional compliance purposes.</li>
        <li>CSPC reserves the right to modify or revoke access at any time without prior notice.</li>
      </ol>
    </div>

    {{-- The full documents open in a new tab so this gate is not lost on the way. --}}
    <p style="font-size:0.9rem;line-height:1.7;color:var(--gray-800);margin-bottom:1.5rem;">
      The summary above is not the whole agreement. Please read the
      <a href="{{ route('terms') }}" target="_blank" rel="noopener">Terms and Conditions</a>
      and the
      <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>,
      which explain what you may do with archived work, what you confirm when you submit your own,
      and what the system records about your activity.
    </p>

    <form method="POST" action="{{ route('student.policy.accept') }}">
      @csrf

      <label style="display:flex;align-items:flex-start;gap:0.65rem;font-size:0.9rem;line-height:1.6;color:var(--gray-800);margin-bottom:1.5rem;cursor:pointer;">
        <input type="checkbox" name="agree" value="1" required style="margin-top:0.25rem;flex-shrink:0;width:1rem;height:1rem;">
        <span>
          I have read and agree to the
          <a href="{{ route('terms') }}" target="_blank" rel="noopener">Terms and Conditions</a>
          and the
          <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>,
          and to the Acceptable Use Policy above.
        </span>
      </label>

      <div style="text-align:center;">
        <button type="submit" class="btn btn-primary" style="padding:0.85rem 2.5rem;font-size:1rem;">
          I Agree &mdash; Continue to Dashboard
        </button>
      </div>
    </form>
  </div>
</div>

<script src="/js/main.js"></script>
</body>
</html>

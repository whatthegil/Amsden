@php $title = 'Acceptable Use Policy'; @endphp
@include('partials.head')

<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;z-index:1;padding:2rem;">
  <div style="background:rgba(255,255,255,0.97);border-radius:16px;padding:3rem;max-width:680px;width:100%;box-shadow:0 24px 80px rgba(0,0,0,0.4);">
    <div style="text-align:center;margin-bottom:2rem;">
      <img src="/images/cspc-logo.png" alt="CSPC" width="60" height="60" style="border-radius:10px;margin:0 auto 1rem;">
      <h1 style="font-weight:700;letter-spacing:-0.02em;color:var(--gray-800);font-size:1.6rem;margin-bottom:0.4rem;">Acceptable Use Policy</h1>
      <p style="color:var(--gray-600);font-size:0.9rem;">C-BAMS — Bluebook Archive Management System</p>
    </div>

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

    <form method="POST" action="{{ route('student.policy.accept') }}" style="text-align:center;">
      @csrf
      <button type="submit" class="btn btn-primary" style="padding:0.85rem 2.5rem;font-size:1rem;">
        I Agree — Continue to Dashboard
      </button>
    </form>
  </div>
</div>

<script src="/js/main.js"></script>
</body>
</html>

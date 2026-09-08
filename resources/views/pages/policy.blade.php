@php $title = 'Terms and Privacy'; @endphp
@include('partials.head')

{{--
  First-login consent gate.

  Renders the published Terms and Conditions and Privacy Policy themselves,
  from the same partials the public /terms and /privacy pages use, so the
  agreement a user accepts here is always the current document rather than a
  paraphrase that can drift out of step with it.

  Each document is agreed to separately: they cover different things — what
  you may do with archived work, and what the system records about you — and
  a single tick would not show which of them a user was actually shown.
--}}
<div class="consent-page">
  <div class="consent-card">
    <header class="consent-head">
      <img src="/images/cspc-logo.png" alt="CSPC" width="56" height="56">
      <h1>Before you continue</h1>
      <p>Please read and accept both documents to use the C-BAMS archive.</p>
    </header>

    @if ($errors->any())
      <div class="alert alert-error" role="alert">
        @foreach ($errors->all() as $message)
          <div>{{ $message }}</div>
        @endforeach
      </div>
    @endif

    <form method="POST" action="{{ route('student.policy.accept') }}">
      @csrf

      <section class="consent-doc" aria-label="Terms and Conditions">
        <h2>Terms and Conditions</h2>
        <div class="consent-scroll legal-doc" tabindex="0">
          @include('partials.legal.terms-body')
        </div>
        <label class="consent-agree">
          <input type="checkbox" name="agree_terms" value="1" @checked(old('agree_terms'))>
          <span>I have read and agree to the <strong>Terms and Conditions</strong>.</span>
        </label>
      </section>

      <section class="consent-doc" aria-label="Privacy Policy">
        <h2>Privacy Policy</h2>
        <div class="consent-scroll legal-doc" tabindex="0">
          @include('partials.legal.privacy-body')
        </div>
        <label class="consent-agree">
          <input type="checkbox" name="agree_privacy" value="1" @checked(old('agree_privacy'))>
          <span>I have read and agree to the <strong>Privacy Policy</strong>.</span>
        </label>
      </section>

      <div class="consent-actions">
        <a href="{{ route('logout') }}" class="btn btn-outline">Sign out</a>
        <button type="submit" class="btn btn-primary">I Agree &mdash; Continue</button>
      </div>
    </form>

    <p class="consent-note">
      You can read these again at any time:
      <a href="{{ route('terms') }}" target="_blank" rel="noopener">Terms and Conditions</a> &middot;
      <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>
    </p>
  </div>
</div>

<script src="/js/main.js"></script>
</body>
</html>

@php $title = 'My Profile'; @endphp
@include('partials.head')

<div class="app">
  @include($user['role'] === 'Admin' ? 'partials.admin-sidebar' : 'partials.student-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">My Profile</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content form-page">
      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if($errors->any())
        <div class="alert alert-error">
          <div>
            @foreach($errors->all() as $err)
              <div>{{ $err }}</div>
            @endforeach
          </div>
        </div>
      @endif

      {{-- Profile Hero --}}
      <div class="card profile-hero-card">
        <div class="profile-hero">
          @if($profile['avatar'])
            <img src="{{ $profile['avatar'] }}" alt="{{ $profile['name'] }}" class="profile-avatar" referrerpolicy="no-referrer">
          @else
            <div class="profile-avatar profile-avatar-initial">{{ strtoupper(substr($profile['name'], 0, 1)) }}</div>
          @endif
          <div class="profile-hero-info">
            <h2>{{ $profile['name'] }}</h2>
            <div class="profile-hero-email">{{ $profile['email'] }}</div>
            <div class="profile-hero-badges">
              <span class="badge badge-blue">{{ $profile['role'] }}</span>
              @if($profile['role'] !== 'Admin')
                <span class="badge {{ $profile['canUpload'] ? 'badge-green' : 'badge-gray' }}">
                  {{ $profile['canUpload'] ? 'Upload Enabled' : 'Upload Disabled' }}
                </span>
              @endif
              @if($profile['googleLinked'])
                <span class="badge badge-gray">Google Linked</span>
              @endif
            </div>
          </div>
        </div>
        <div class="info-grid" style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--gray-200);">
          <div class="info-row">
            <span class="key">Member Since</span>
            <span class="val">{{ $profile['createdAt'] ?? '—' }}</span>
          </div>
          <div class="info-row">
            <span class="key">Sign-In Method</span>
            <span class="val">{{ $profile['googleLinked'] ? 'Google / Email & Password' : 'Email & Password' }}</span>
          </div>
        </div>
      </div>

      {{-- Edit Profile --}}
      <div class="form-card" style="margin-top:1.5rem;">
        <div class="form-card-header">
          <span class="icon">👤</span>
          <h2>Edit Profile</h2>
        </div>
        <div class="form-card-body">
          <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            <div class="form-group">
              <label>Full Name</label>
              <input type="text" name="name" required minlength="2" maxlength="100" value="{{ old('name', $profile['name']) }}">
            </div>
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" value="{{ $profile['email'] }}" disabled>
              <div class="form-hint">Your institutional email identifies your account and cannot be changed.</div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>

      {{-- Change Password --}}
      <div class="form-card" style="margin-top:1.5rem;">
        <div class="form-card-header">
          <span class="icon">🔒</span>
          <h2>Change Password</h2>
        </div>
        <div class="form-card-body">
          @if(session('password_error'))
            <div class="alert alert-error">{{ session('password_error') }}</div>
          @endif
          <form method="POST" action="{{ route('profile.password') }}">
            @csrf
            <div class="form-group">
              <label>Current Password</label>
              <input type="password" name="current_password" required autocomplete="current-password">
              @if($profile['googleLinked'])
                <div class="form-hint">Registered through Google? Your account may not have a password you know — keep signing in with Google instead.</div>
              @endif
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
                <div class="form-hint">At least 8 characters.</div>
              </div>
              <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="new_password_confirmation" required minlength="8" autocomplete="new-password">
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary">Change Password</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <footer style="padding:1rem 2rem;font-size:0.8rem;color:var(--gray-400);border-top:1px solid var(--gray-200);background:rgba(255,255,255,0.98);">
      AMSDEN &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </div>
</div>

@include('partials.footer')

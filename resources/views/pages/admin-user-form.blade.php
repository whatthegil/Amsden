@php $title = $editUser ? 'Edit User' : 'Add User'; $isEdit = !!$editUser; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">{{ $isEdit ? 'Edit' : 'Add' }} User</h1>
      <div class="topbar-right">
        <span class="topbar-badge">Administrator</span>
      </div>
    </header>

    <div class="content form-page">
      <div class="page-header">
        <div>
          <h1>{{ $isEdit ? 'Edit User' : 'Add New User' }}</h1>
          <p>{{ $isEdit ? 'Update this user\'s account details.' : 'Create a new user account.' }}</p>
        </div>
        <a href="{{ route('admin.users') }}" class="btn btn-outline">← Back</a>
      </div>

      <div class="form-card">
        <div class="form-card-header">
          <span class="icon">👤</span>
          <h2>{{ $isEdit ? 'Edit Account' : 'User Details' }}</h2>
        </div>
        <div class="form-card-body">
          <form method="POST" action="{{ $isEdit ? route('admin.users.update', $editUser['id']) : route('admin.users.store') }}">
            @csrf

            <div class="form-group">
              <label>Full Name</label>
              <input type="text" name="name" value="{{ $editUser['name'] ?? '' }}" required>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="{{ $editUser['email'] ?? '' }}" required>
              </div>
              <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                  <option value="Student" {{ ($editUser['role'] ?? 'Student') === 'Student' ? 'selected' : '' }}>Student</option>
                  <option value="Faculty" {{ ($editUser['role'] ?? '') === 'Faculty' ? 'selected' : '' }}>Faculty</option>
                  <option value="Admin" {{ ($editUser['role'] ?? '') === 'Admin' ? 'selected' : '' }}>Admin</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>{{ $isEdit ? 'New Password (leave blank to keep current)' : 'Password' }}</label>
              <input type="password" name="password" {{ $isEdit ? '' : 'required' }} placeholder="{{ $isEdit ? 'Leave blank to keep current password' : 'Create a password' }}">
            </div>

            <div class="form-actions">
              <a href="{{ route('admin.users') }}" class="btn btn-outline">Cancel</a>
              <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update User' : 'Add User' }}</button>
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

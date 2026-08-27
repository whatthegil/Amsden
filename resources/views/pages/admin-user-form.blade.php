@php $title = $editUser ? 'Edit User' : 'Add User'; $isEdit = !!$editUser; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <main class="main" id="main-content">
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
          <span class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" fill="currentColor" fill-opacity="0.18"/><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <h2>{{ $isEdit ? 'Edit Account' : 'User Details' }}</h2>
        </div>
        <div class="form-card-body">
          <form method="POST" action="{{ $isEdit ? route('admin.users.update', $editUser['id']) : route('admin.users.store') }}">
            @csrf

            <div class="form-group">
              <label for="user-form-name">Full Name</label>
              <input id="user-form-name" type="text" name="name" value="{{ $editUser['name'] ?? '' }}" required>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="user-form-email">Email Address</label>
                <input id="user-form-email" type="email" name="email" value="{{ $editUser['email'] ?? '' }}" required>
              </div>
              <div class="form-group">
                <label for="user-form-role">Role</label>
                <select id="user-form-role" name="role" required>
                  <option value="Student" {{ ($editUser['role'] ?? 'Student') === 'Student' ? 'selected' : '' }}>Student</option>
                  <option value="Faculty" {{ ($editUser['role'] ?? '') === 'Faculty' ? 'selected' : '' }}>Faculty</option>
                  <option value="Admin" {{ ($editUser['role'] ?? '') === 'Admin' ? 'selected' : '' }}>Admin</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label for="user-form-password">{{ $isEdit ? 'New Password (leave blank to keep current)' : 'Password' }}</label>
              <input id="user-form-password" type="password" name="password" {{ $isEdit ? '' : 'required' }} placeholder="{{ $isEdit ? 'Leave blank to keep current password' : 'Create a password' }}">
            </div>

            <div class="form-actions">
              <a href="{{ route('admin.users') }}" class="btn btn-outline">Cancel</a>
              <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update User' : 'Add User' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </main>
</div>

@include('partials.footer')

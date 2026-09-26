@php $title = $editUser ? 'Edit User' : 'Add User'; $isEdit = !!$editUser; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">{{ $isEdit ? 'Edit' : 'Add' }} User</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ ($user['role'] ?? '') === 'Sub-Admin' ? 'Sub-Admin' : 'Administrator' }}</span>
      </div>
    </header>

    <div class="content form-page">
      <x-page-hero heading="{{ $isEdit ? 'Edit User' : 'Add New User' }}"
                   sub="{{ $isEdit ? 'Update this account and what it is allowed to do.' : 'Create an account for a student, faculty member or administrator.' }}">
        <x-slot name="action">
          <a href="{{ route('admin.users') }}" class="btn btn-sm btn-outline">&larr; Back</a>
        </x-slot>
      </x-page-hero>

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
                <select id="user-form-role" name="role" required onchange="togglePermissions()">
                  @foreach($roles as $role)
                    <option value="{{ $role }}" @selected(($editUser['role'] ?? 'Student') === $role)>{{ $role }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            @if(in_array(\App\Models\User::ROLE_SUB_ADMIN, $roles, true))
              {{-- What this Sub-Admin may do. Only an Admin sees this, and it applies only to a Sub-Admin. --}}
              <fieldset class="access-waiver" id="sub-admin-permissions" @if(($editUser['role'] ?? '') !== \App\Models\User::ROLE_SUB_ADMIN) hidden @endif>
                <legend>Sub-Admin privileges</legend>
                <p class="form-hint" style="margin:0 0 0.75rem;">Every Sub-Admin can open the admin dashboard and read the bluebooks. Tick what else this account may do.</p>
                @foreach(\App\Models\User::PERMISSIONS as $key => $label)
                  <label class="access-option">
                    <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $editUser['permissions'] ?? [], true))>
                    <span>{{ $label }}</span>
                  </label>
                @endforeach
              </fieldset>
            @endif

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
      C-BAMS &copy; {{ date('Y') }} &mdash; Camarines Sur Polytechnic Colleges. All Rights Reserved.
    </footer>
  </main>
</div>

<script>
// The privileges apply only to a Sub-Admin, so they show only when that role is picked.
function togglePermissions() {
  const box = document.getElementById('sub-admin-permissions');
  if (box) box.hidden = document.getElementById('user-form-role').value !== 'Sub-Admin';
}
</script>

@include('partials.footer')

@php $title = 'Manage Users'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">User Management</h1>
      <div class="topbar-right">
        <span class="topbar-badge">Administrator</span>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div>
          <h1>Users</h1>
          <p>{{ count($users) }} user(s) found</p>
        </div>
        <a href="{{ route('admin.users.new') }}" class="btn btn-primary">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Add User
        </a>
      </div>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      <form method="GET" action="{{ route('admin.users') }}" class="filter-bar">
        <div class="filter-group grow">
          <label>Search</label>
          <input type="text" name="search" value="{{ $query['search'] ?? '' }}" placeholder="Name or email…">
        </div>
        <div class="filter-group">
          <label>Role</label>
          <select name="role">
            <option value="">All Roles</option>
            <option value="Admin" {{ ($query['role'] ?? '') === 'Admin' ? 'selected' : '' }}>Admin</option>
            <option value="Student" {{ ($query['role'] ?? '') === 'Student' ? 'selected' : '' }}>Student</option>
            <option value="Faculty" {{ ($query['role'] ?? '') === 'Faculty' ? 'selected' : '' }}>Faculty</option>
          </select>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:flex-end;">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="{{ route('admin.users') }}" class="btn btn-outline btn-sm">Clear</a>
        </div>
      </form>

      @if(count($users) > 0)
        <div class="card">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Upload</th>
                  <th>Joined</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @foreach($users as $i => $u)
                  <tr>
                    <td class="mono">{{ $i + 1 }}</td>
                    <td style="font-weight:600;">{{ $u['name'] }}</td>
                    <td class="mono" style="font-size:0.83rem;">{{ $u['email'] }}</td>
                    <td>
                      @if($u['role'] === 'Admin')
                        <span class="badge badge-blue">Admin</span>
                      @elseif($u['role'] === 'Faculty')
                        <span class="badge badge-green">Faculty</span>
                      @else
                        <span class="badge badge-gray">Student</span>
                      @endif
                    </td>
                    <td>
                      @if($u['canUpload'] ?? false)
                        <span class="badge badge-green">Enabled</span>
                      @else
                        <span class="badge badge-red">Disabled</span>
                      @endif
                    </td>
                    <td style="font-size:0.83rem;">{{ $u['createdAt'] }}</td>
                    <td>
                      <div class="td-actions">
                        <a href="{{ route('admin.users.edit', $u['id']) }}" class="btn btn-outline btn-sm">Edit</a>
                        @if(in_array($u['role'], ['Student', 'Faculty']))
                          @if($u['canUpload'] ?? false)
                            <form method="POST" action="{{ route('admin.users.disableUpload', $u['id']) }}" style="display:inline;">
                              @csrf <button type="submit" class="btn btn-warning btn-sm">Disable Upload</button>
                            </form>
                          @else
                            <form method="POST" action="{{ route('admin.users.enableUpload', $u['id']) }}" style="display:inline;">
                              @csrf <button type="submit" class="btn btn-success btn-sm">Enable Upload</button>
                            </form>
                          @endif
                        @endif
                        @if($u['id'] !== $user['id'])
                          <form method="POST" action="{{ route('admin.users.delete', $u['id']) }}" style="display:inline;">
                            @csrf <button type="submit" class="btn btn-danger btn-sm confirm-delete">Delete</button>
                          </form>
                        @endif
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @else
        <div class="card">
          <div class="empty-state">
            <div class="icon">👥</div>
            <p>No users found.</p>
          </div>
        </div>
      @endif
    </div>

    <footer style="padding:1rem 2rem;font-size:0.8rem;color:var(--gray-400);border-top:1px solid var(--gray-200);background:rgba(255,255,255,0.98);">
      AMSDEN &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </div>
</div>

@include('partials.footer')

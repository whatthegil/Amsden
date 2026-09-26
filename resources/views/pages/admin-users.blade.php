@php $title = 'Manage Users'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">User Management</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ ($user['role'] ?? '') === 'Sub-Admin' ? 'Sub-Admin' : 'Administrator' }}</span>
      </div>
    </header>

    <div class="content">
      <x-page-hero heading="Users"
                   sub="{{ count($users) }} {{ Str::plural('account', count($users)) }} registered.">
        <x-slot name="action">
          <a href="{{ route('admin.users.new') }}" class="btn btn-sm btn-primary">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add User
          </a>
        </x-slot>
      </x-page-hero>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      <form method="GET" action="{{ route('admin.users') }}" class="filter-bar">
        <div class="filter-group grow">
          <label for="users-filter-search">Search</label>
          <input id="users-filter-search" type="text" name="search" value="{{ $query['search'] ?? '' }}" placeholder="Name or email…">
        </div>
        <div class="filter-group">
          <label for="users-filter-role">Role</label>
          <select id="users-filter-role" name="role">
            <option value="">All Roles</option>
            <option value="Admin" {{ ($query['role'] ?? '') === 'Admin' ? 'selected' : '' }}>Admin</option>
            <option value="Sub-Admin" {{ ($query['role'] ?? '') === 'Sub-Admin' ? 'selected' : '' }}>Sub-Admin</option>
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
                      @elseif($u['role'] === 'Sub-Admin')
                        <span class="badge badge-blue" title="{{ count($u['permissions'] ?? []) }} of {{ count(\App\Models\User::PERMISSIONS) }} privileges">Sub-Admin &middot; {{ count($u['permissions'] ?? []) }}/{{ count(\App\Models\User::PERMISSIONS) }}</span>
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
                        {{-- A Sub-Admin looks after student and faculty accounts only. --}}
                        @if(($user['role'] ?? '') === 'Admin' || !\App\Models\User::isStaff($u['role']))
                          <a href="{{ route('admin.users.edit', $u['id']) }}" class="btn btn-outline btn-sm">Edit</a>
                        @endif
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
            <div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" fill="currentColor" fill-opacity="0.18"/><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
            @php $isFiltered = collect($query ?? [])->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty(); @endphp
            @if($isFiltered)
              <p>No accounts match these filters.</p>
              <a href="{{ route('admin.users') }}" class="btn btn-outline btn-sm" style="margin-top:0.85rem;">Clear all filters</a>
            @else
              <p>There are no accounts yet.</p>
              <a href="{{ route('admin.users.new') }}" class="btn btn-primary btn-sm" style="margin-top:0.85rem;">Add the first user</a>
            @endif
          </div>
        </div>
      @endif
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; Camarines Sur Polytechnic Colleges. All Rights Reserved.
    </footer>
  </main>
</div>

@include('partials.footer')

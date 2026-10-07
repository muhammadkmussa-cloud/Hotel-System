@extends('layouts.staff', ['title' => 'Staff'])
@php
    $roleLabels = ['owner' => 'Owner', 'manager' => 'Manager', 'cashier' => 'Cashier', 'waiter' => 'Waiter', 'kitchen_lead' => 'Kitchen lead', 'kitchen_staff' => 'Kitchen staff', 'menu_editor' => 'Menu editor', 'auditor' => 'Auditor'];
@endphp
@section('content')
<div class="page-head"><h1>Staff</h1></div>

<section class="card">
    <h2>Team</h2>
    @if (count($staff) === 0)
        <p class="muted">No staff members yet.</p>
    @else
    <div class="table-wrap"><table class="data">
        <thead><tr><th>Name</th><th>Email</th><th>Roles</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($staff as $member)
            <tr>
                <td>{{ $member['name'] }}</td>
                <td>{{ $member['email'] }}</td>
                <td>@forelse ($member['roles'] as $role)<span class="pill">{{ $roleLabels[$role] ?? $role }}</span> @empty <span class="muted">none</span> @endforelse</td>
                <td>@if ($member['active'])<span class="pill pill-ok">Active</span>@else<span class="pill pill-muted">Inactive</span>@endif</td>
                <td>
                    <form method="post" action="/admin/staff/{{ $member['active'] ? 'deactivate' : 'activate' }}" class="inline-form" @if($member['active']) data-confirm="Deactivate {{ $member['name'] }}? Their sessions end immediately." @endif>@csrf
                        <input type="hidden" name="staff_user_id" value="{{ $member['id'] }}">
                        <button class="btn-small btn-secondary">{{ $member['active'] ? 'Deactivate' : 'Reactivate' }}</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>

<section class="card">
    <h2>Add a staff member</h2>
    <form method="post" action="/admin/staff" data-single-submit>@csrf
        <div class="form-grid">
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="off" required maxlength="190" value="{{ old('email') }}"></div>
            <div class="field"><label for="name">Name</label><input id="name" name="name" maxlength="150" required value="{{ old('name') }}"></div>
            <div class="field"><label for="password">Temporary password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
                <p class="hint">At least 12 characters. Ask them to keep it private.</p></div>
        </div>
        <fieldset class="field"><legend>Roles</legend>
            <div class="check-grid">
            @foreach ($roles as $role)
                <label class="check"><input name="roles[]" type="checkbox" value="{{ $role }}" @checked(is_array(old('roles')) && in_array($role, old('roles'), true))> {{ $roleLabels[$role] ?? $role }}</label>
            @endforeach
            </div>
        </fieldset>
        <button data-pending-label="Creating…">Create staff member</button>
    </form>
</section>

@if (count($staff))
<div class="grid-2">
<section class="card">
    <h2>Change roles</h2>
    <form method="post" action="/admin/staff/roles">@csrf
        <div class="field"><label for="role_staff">Staff member</label>
            <select id="role_staff" name="staff_user_id" required>@foreach ($staff as $member)<option value="{{ $member['id'] }}">{{ $member['name'] }} — {{ $member['email'] }}</option>@endforeach</select></div>
        <fieldset class="field"><legend>Roles</legend>
            <div class="check-grid">@foreach ($roles as $role)<label class="check"><input name="roles[]" type="checkbox" value="{{ $role }}"> {{ $roleLabels[$role] ?? $role }}</label>@endforeach</div>
        </fieldset>
        <div class="btn-row"><button name="action" value="grant">Grant selected</button><button name="action" value="revoke" class="btn-secondary">Remove selected</button></div>
    </form>
</section>
<section class="card">
    <h2>Edit name or email</h2>
    <form method="post" action="/admin/staff/update">@csrf
        <div class="field"><label for="edit_staff">Staff member</label>
            <select id="edit_staff" name="staff_user_id" required>@foreach ($staff as $member)<option value="{{ $member['id'] }}">{{ $member['name'] }} — {{ $member['email'] }}</option>@endforeach</select></div>
        <div class="field"><label for="edit_name">Name</label><input id="edit_name" name="name" maxlength="150" required></div>
        <div class="field"><label for="edit_email">Email</label><input id="edit_email" name="email" type="email" autocomplete="off" required maxlength="190"></div>
        <button>Save changes</button>
    </form>
</section>
</div>
@endif
@endsection

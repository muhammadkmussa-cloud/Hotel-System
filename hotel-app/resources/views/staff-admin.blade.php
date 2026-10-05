<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <title>{{ config('app.name') }} — Staff administration</title>
</head>
<body>
    <main id="main">
        <h1>Staff administration</h1>
        <p>Only owners and managers can list and create staff.</p>

        @if (session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="state" tabindex="-1" data-error-focus>
                <h2>Check these details</h2>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section aria-labelledby="staff-list-heading">
            <h2 id="staff-list-heading">Staff</h2>
            @if (count($staff) === 0)
                <p>No staff members yet.</p>
            @else
                <table>
                    <caption>Current staff members</caption>
                    <thead><tr><th scope="col">Email</th><th scope="col">Name</th><th scope="col">Roles</th><th scope="col">Active</th></tr></thead>
                    <tbody>
                        @foreach ($staff as $member)
                            <tr>
                                <th scope="row">{{ $member['email'] }}</th>
                                <td>{{ $member['name'] }}</td>
                                <td>{{ implode(', ', $member['roles']) }}</td>
                                <td>
                                    {{ $member['active'] ? 'Yes' : 'No' }}
                                    @if ($member['active'])
                                        <form method="post" action="/admin/staff/deactivate">
                                            @csrf
                                            <input type="hidden" name="staff_user_id" value="{{ $member['id'] }}">
                                            <button type="submit">Deactivate</button>
                                        </form>
                                    @else
                                        <form method="post" action="/admin/staff/activate">
                                            @csrf
                                            <input type="hidden" name="staff_user_id" value="{{ $member['id'] }}">
                                            <button type="submit">Activate</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section aria-labelledby="staff-create-heading">
            <h2 id="staff-create-heading">Create staff member</h2>
            <form method="post" action="/admin/staff" data-single-submit>
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" autocomplete="off" required value="{{ old('email') }}">
                </div>
                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" name="name" type="text" maxlength="150" required value="{{ old('name') }}">
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required>
                </div>
                <fieldset>
                    <legend>Roles</legend>
                    @foreach ($roles as $role)
                        <div class="field">
                            <input id="role-{{ $role }}" name="roles[]" type="checkbox" value="{{ $role }}" @checked(is_array(old('roles')) && in_array($role, old('roles'), true))>
                            <label for="role-{{ $role }}">{{ $role }}</label>
                        </div>
                    @endforeach
                </fieldset>
                <button type="submit" data-pending-label="Creating…">Create staff member</button>
                <p data-submit-status class="visually-hidden" role="status" aria-live="polite"></p>
            </form>
        </section>
    <section aria-labelledby="staff-edit-heading">
        <h2 id="staff-edit-heading">Edit staff member</h2>
        <form method="post" action="/admin/staff/update" data-single-submit>
            @csrf
            <div class="field">
                <label for="edit_staff_user_id">Staff member</label>
                <select id="edit_staff_user_id" name="staff_user_id" required>
                    @foreach ($staff as $member)
                        <option value="{{ $member['id'] }}">{{ $member['email'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="edit_name">Name</label>
                <input id="edit_name" name="name" type="text" maxlength="150" required value="{{ old('name') }}">
            </div>
            <div class="field">
                <label for="edit_email">Email</label>
                <input id="edit_email" name="email" type="email" autocomplete="off" required value="{{ old('email') }}">
            </div>
            <button type="submit" data-pending-label="Saving…">Save changes</button>
            <p data-submit-status class="visually-hidden" role="status" aria-live="polite"></p>
        </form>
        @endif
    </section>

    <section aria-labelledby="staff-roles-heading">
        <h2 id="staff-roles-heading">Manage roles</h2>
        <form method="post" action="/admin/staff/roles" data-single-submit>
            @csrf
            <div class="field">
                <label for="staff_user_id">Staff member</label>
                <select id="staff_user_id" name="staff_user_id" required>
                    @foreach ($staff as $member)
                        <option value="{{ $member['id'] }}">{{ $member['email'] }}</option>
                    @endforeach
                </select>
            </div>
            <fieldset>
                <legend>Roles</legend>
                @foreach ($roles as $role)
                    <div class="field">
                        <input id="manage-role-{{ $role }}" name="roles[]" type="checkbox" value="{{ $role }}">
                        <label for="manage-role-{{ $role }}">{{ $role }}</label>
                    </div>
                @endforeach
            </fieldset>
            <div class="field">
                <label for="action">Action</label>
                <select id="action" name="action" required>
                    <option value="grant">Grant roles</option>
                    <option value="revoke">Revoke roles</option>
                </select>
            </div>
            <button type="submit" data-pending-label="Updating…">Update roles</button>
            <p data-submit-status class="visually-hidden" role="status" aria-live="polite"></p>
        </form>
    </section>
    </main>
    <script type="module">
        import { initSingleSubmit } from '/assets/js/lib/single-submit.js';
        initSingleSubmit(document.querySelector('form[data-single-submit]'));
        document.querySelector('[data-error-focus]')?.focus();
    </script>
</body>
</html>

<x-app-layout>

<div class="py-6">
<div class="max-w-4xl mx-auto">

<div class="bg-white shadow rounded-lg p-6">

<h2 class="text-2xl font-bold mb-6">
Create User
</h2>

<form method="POST" action="{{ route('users.store') }}">

@csrf

<div class="mb-4">
<label>First Name</label>
<input
type="text"
name="first_name"
class="w-full border rounded p-2"
required>
</div>

<div class="mb-4">
<label>Last Name</label>
<input
type="text"
name="last_name"
class="w-full border rounded p-2"
required>
</div>

<div class="mb-4">
<label>Email</label>
<input
type="email"
name="email"
class="w-full border rounded p-2"
required>
</div>

<div class="mb-4">
<label>Department</label>

<select
name="department_id"
class="w-full border rounded p-2">

<option value="">
Select Department
</option>

@foreach($departments as $department)

<option value="{{ $department->id }}">
{{ $department->department_name }}
</option>

@endforeach

</select>

</div>

<div class="mb-4">

<label>Role</label>

<select
name="role"
class="w-full border rounded p-2">

@foreach($roles as $role)

<option value="{{ $role->name }}">
{{ $role->name }}
</option>

@endforeach

</select>

</div>

<div class="mb-4">
<label>Password</label>
<input
type="password"
name="password"
class="w-full border rounded p-2"
required>
</div>

<div class="mb-4">
<label>Confirm Password</label>
<input
type="password"
name="password_confirmation"
class="w-full border rounded p-2"
required>
</div>

<button
type="submit"
style="
background:#2563eb;
color:white;
padding:10px 16px;
border-radius:6px;
">

Create User

</button>

</form>

</div>

</div>
</div>

</x-app-layout>
<x-app-layout>

<div class="py-6">
<div class="max-w-4xl mx-auto">

<div class="bg-white shadow rounded-lg p-6">

<h2 class="text-2xl font-bold mb-6">
Edit User
</h2>

<form method="POST"
      action="{{ route('users.update', $user->id) }}">

@csrf
@method('PUT')

<div class="mb-4">
<label>First Name</label>

<input
type="text"
name="first_name"
value="{{ $user->first_name }}"
class="w-full border rounded p-2">
</div>

<div class="mb-4">
<label>Last Name</label>

<input
type="text"
name="last_name"
value="{{ $user->last_name }}"
class="w-full border rounded p-2">
</div>

<div class="mb-4">
<label>Email</label>

<input
type="email"
name="email"
value="{{ $user->email }}"
class="w-full border rounded p-2">
</div>

<div class="mb-4">
<label>Department</label>

<select
name="department_id"
class="w-full border rounded p-2">

@foreach($departments as $department)

<option
value="{{ $department->id }}"
{{ $user->department_id == $department->id ? 'selected' : '' }}>

{{ $department->department_name }}

</option>

@endforeach

</select>

</div>

<button
type="submit"
style="
background:#2563eb;
color:white;
padding:10px 16px;
border-radius:6px;
">

Update User

</button>

</form>

</div>
</div>
</div>

</x-app-layout>
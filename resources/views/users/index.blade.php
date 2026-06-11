<x-app-layout>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="flex justify-between items-center mb-6">


            @if($errors->any())
<div style="
    background:#fee2e2;
    color:#991b1b;
    padding:10px;
    border-radius:6px;
    margin-bottom:20px;
">

    {{ $errors->first() }}

</div>
@endif

        @if(session('success'))
    <div
        style="
            background:#dcfce7;
            color:#166534;
            padding:10px;
            border-radius:6px;
            margin-bottom:20px;
        ">

        {{ session('success') }}

    </div>
@endif

            <h2 class="text-2xl font-bold">
                User Management
            </h2>

            <a href="{{ route('users.create') }}"
               style="
                    background-color:#2563eb;
                    color:white;
                    padding:10px 16px;
                    border-radius:6px;
                    text-decoration:none;
                    font-weight:bold;
               ">
                Create User
            </a>

        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">

            <table class="min-w-full">

                <thead style="background:#f3f4f6;">

                    <tr>
                        <th class="px-6 py-3 text-left">ID</th>
                        <th class="px-6 py-3 text-left">Name</th>
                        <th class="px-6 py-3 text-left">Email</th>
                        <th class="px-6 py-3 text-left">Department</th>
                        <th class="px-6 py-3 text-left">Role</th>
                        <th class="px-6 py-3 text-left">Status</th>
                        <th class="px-6 py-3 text-left">Actions</th>
                    </tr>

                </thead>

                <tbody>

                @forelse($users as $user)

                    <tr class="border-b">

                        <td class="px-6 py-4">{{ $user->id }}</td>

                        <td class="px-6 py-4">
                            {{ $user->first_name }} {{ $user->last_name }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $user->email }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $user->department?->department_name ?? 'Not Assigned' }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $user->getRoleNames()->first() }}
                        </td>

                        <td class="px-6 py-4">

                            @if($user->status)
                                Active
                            @else
                                Inactive
                            @endif

                        </td>

                        <td class="px-6 py-4">

                            <div style="display:flex; gap:10px;">

                                <a href="{{ route('users.show', $user->id) }}"
                                   style="
                                        background:#2563eb;
                                        color:white;
                                        width:80px;
                                        text-align:center;
                                        padding:8px;
                                        border-radius:4px;
                                        text-decoration:none;
                                   ">
                                    View
                                </a>

                                <a href="{{ route('users.edit', $user->id) }}"
                                   style="
                                        background:#f59e0b;
                                        color:white;
                                        width:80px;
                                        text-align:center;
                                        padding:8px;
                                        border-radius:4px;
                                        text-decoration:none;
                                   ">
                                    Edit
                                </a>

                                <form action="{{ route('users.destroy', $user->id) }}"
      method="POST">

    @csrf
    @method('DELETE')

    <button
        type="submit"
        onclick="return confirm('Delete this user?')"
        style="
            background:#dc2626;
            color:white;
            width:80px;
            padding:8px;
            border-radius:4px;
            border:none;
            cursor:pointer;
        ">

        Delete

    </button>

    

</form>
@if($user->status)

<form action="{{ route('users.deactivate', $user->id) }}"
      method="POST">

    @csrf

    <button
        type="submit"
        style="
            background:#f59e0b;
            color:white;
            width:100px;
            padding:8px;
            border-radius:4px;
            border:none;
            cursor:pointer;
        ">
        Deactivate
    </button>

</form>

@else

<form action="{{ route('users.activate', $user->id) }}"
      method="POST">

    @csrf

    <button
        type="submit"
        style="
            background:#16a34a;
            color:white;
            width:100px;
            padding:8px;
            border-radius:4px;
            border:none;
            cursor:pointer;
        ">
        Activate
    </button>

</form>

@endif

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="7" class="text-center py-6">
                            No Users Found
                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>
</div>

</x-app-layout>
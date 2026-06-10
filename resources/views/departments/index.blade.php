<x-app-layout>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <div class="flex justify-between items-center mb-6">

                <h2 class="text-2xl font-bold">
                    Departments
                </h2>

                <a href="{{ route('departments.create') }}"
   style="
        background-color:#2563eb;
        color:white;
        padding:10px 16px;
        border-radius:6px;
        text-decoration:none;
        font-weight:bold;
   ">

    Create Department

</a>

            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden">

                <table class="min-w-full">

                    <thead class="bg-gray-100">

                        <tr>
                            <th class="px-6 py-3 text-left">ID</th>
                            <th class="px-6 py-3 text-left">Department</th>
                            <th class="px-6 py-3 text-left">Description</th>
                            <th class="px-6 py-3 text-left">Status</th>
                            <th class="px-6 py-3 text-left">Created</th>
                            <th class="px-6 py-3 text-left">Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                    @forelse($departments as $department)

                        <tr class="border-b">

                            <td class="px-6 py-4">
                                {{ $department->id }}
                            </td>

                            <td class="px-6 py-4">
                                {{ $department->department_name }}
                            </td>

                            <td class="px-6 py-4">
                                {{ $department->description }}
                            </td>

                            <td class="px-6 py-4">

                                @if($department->status)
                                    <span class="text-green-600">
                                        Active
                                    </span>
                                @else
                                    <span class="text-red-600">
                                        Inactive
                                    </span>
                                @endif

                            </td>

                            <td class="px-6 py-4">
                                {{ $department->created_at->format('M d, Y') }}
                            </td>
<td class="px-6 py-4">

    <div style="display:flex; gap:10px;">

        <a href="{{ route('departments.show', $department->id) }}"
           style="
                background-color:#2563eb;
                color:white;
                width:90px;
                text-align:center;
                padding:8px;
                border-radius:4px;
                text-decoration:none;
                display:inline-block;
           ">
            View
        </a>

        <a href="{{ route('departments.edit', $department->id) }}"
           style="
                background-color:#f59e0b;
                color:white;
                width:90px;
                text-align:center;
                padding:8px;
                border-radius:4px;
                text-decoration:none;
                display:inline-block;
           ">
            Edit
        </a>

        <form action="{{ route('departments.destroy', $department->id) }}"
              method="POST">

            @csrf
            @method('DELETE')

            <button
                type="submit"
                onclick="return confirm('Delete this department?')"
                style="
                    background-color:#dc2626;
                    color:white;
                    width:90px;
                    padding:8px;
                    border-radius:4px;
                    border:none;
                    cursor:pointer;
                ">
                Delete
            </button>

        </form>

    </div>

</td>

        </form>

    </div>

</td>
                            

                        </tr>

                    @empty

                        <tr>
                            <td colspan="5" class="text-center py-6">

                                No Departments Found

                            </td>
                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </div>
    </div>

</x-app-layout>
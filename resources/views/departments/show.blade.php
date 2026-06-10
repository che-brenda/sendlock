<x-app-layout>

<div class="py-6">
    <div class="max-w-4xl mx-auto">

        <div class="bg-white shadow rounded-lg p-6">

            <h2 class="text-2xl font-bold mb-6">
                Department Details
            </h2>

            <div class="mb-4">
                <strong>Department Name:</strong>
                {{ $department->department_name }}
            </div>

            <div class="mb-4">
                <strong>Description:</strong>
                {{ $department->description }}
            </div>

            <div class="mb-4">
                <strong>Status:</strong>

                @if($department->status)
                    Active
                @else
                    Inactive
                @endif
            </div>

            <div class="mb-4">
                <strong>Created:</strong>
                {{ $department->created_at->format('M d, Y H:i') }}
            </div>

            <a href="{{ route('departments.index') }}"
               style="
                    background-color:#2563eb;
                    color:white;
                    padding:10px 16px;
                    border-radius:6px;
                    text-decoration:none;
               ">
                Back to Departments
            </a>

        </div>

    </div>
</div>

</x-app-layout>
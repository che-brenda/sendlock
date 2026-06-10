<x-app-layout>

<div class="py-6">
    <div class="max-w-4xl mx-auto">

        <div class="bg-white shadow rounded-lg p-6">

            <h2 class="text-2xl font-bold mb-6">
                Edit Department
            </h2>

            <form method="POST"
                  action="{{ route('departments.update', $department) }}">

                @csrf
                @method('PUT')

                <div class="mb-4">

                    <label class="block font-medium">
                        Department Name
                    </label>

                    <input
                        type="text"
                        name="department_name"
                        value="{{ $department->department_name }}"
                        class="w-full border rounded p-2"
                        required>

                </div>

                <div class="mb-4">

                    <label class="block font-medium">
                        Description
                    </label>

                    <textarea
                        name="description"
                        rows="4"
                        class="w-full border rounded p-2">{{ $department->description }}</textarea>

                </div>

                <button
                    type="submit"
                    style="background-color:#2563eb;color:white;padding:10px 20px;border-radius:6px;">

                    Update Department

                </button>

            </form>

        </div>

    </div>
</div>

</x-app-layout>
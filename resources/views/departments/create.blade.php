<x-app-layout>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow rounded-lg p-6">

                <h2 class="text-2xl font-bold mb-6">
                    Create Department
                </h2>

                <form method="POST" action="{{ route('departments.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block font-medium">
                            Department Name
                        </label>

                        <input
                            type="text"
                            name="department_name"
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
                            class="w-full border rounded p-2"></textarea>
                    </div>

                   <button
    type="submit"
    style="background-color:#2563eb;color:white;padding:10px 20px;border-radius:6px;">

    Save Department

</button>

                </form>

            </div>

        </div>
    </div>

</x-app-layout>
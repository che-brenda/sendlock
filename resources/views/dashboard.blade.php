<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            SendLock Dashboard
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                <div class="bg-white p-6 rounded shadow">
                    <h3 class="text-lg font-bold">Organizations</h3>
                    <p class="text-3xl font-bold">1</p>
                </div>

                <div class="bg-white p-6 rounded shadow">
                    <h3 class="text-lg font-bold">Users</h3>
                    <p class="text-3xl font-bold">1</p>
                </div>

                <div class="bg-white p-6 rounded shadow">
                    <h3 class="text-lg font-bold">Pending Approvals</h3>
                    <p class="text-3xl font-bold">0</p>
                </div>

                <div class="bg-white p-6 rounded shadow">
                    <h3 class="text-lg font-bold">Trusted Domains</h3>
                    <p class="text-3xl font-bold">0</p>
                </div>

                <div class="bg-white p-6 rounded shadow">
                    <h3 class="text-lg font-bold">Vendors</h3>
                    <p class="text-3xl font-bold">0</p>
                </div>

                <div class="bg-white p-6 rounded shadow">
                    <h3 class="text-lg font-bold">Risk Alerts</h3>
                    <p class="text-3xl font-bold">0</p>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
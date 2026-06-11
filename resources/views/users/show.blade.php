<x-app-layout>

<div class="py-6">
    <div class="max-w-4xl mx-auto">

        <div class="bg-white shadow rounded-lg p-6">

            <h2 class="text-2xl font-bold mb-6">
                User Details
            </h2>

            <div class="mb-4">
                <strong>Name:</strong>
                {{ $user->first_name }}
                {{ $user->last_name }}
            </div>

            <div class="mb-4">
                <strong>Email:</strong>
                {{ $user->email }}
            </div>

            <div class="mb-4">
                <strong>Department:</strong>

                {{ $user->department?->department_name
                    ?? 'Not Assigned' }}
            </div>

            <div class="mb-4">
                <strong>Role:</strong>

                {{ $user->getRoleNames()->first() }}
            </div>

            <div class="mb-4">
                <strong>Status:</strong>

                @if($user->status)
                    Active
                @else
                    Inactive
                @endif
            </div>

            <div class="mb-4">
                <strong>Organization:</strong>

                {{ $user->organization?->organization_name }}
            </div>

            <div class="mb-4">
                <strong>Created:</strong>

                {{ $user->created_at->format('M d, Y H:i') }}
            </div>

            <a href="{{ route('users.index') }}"
               style="
                    background:#2563eb;
                    color:white;
                    padding:10px 16px;
                    border-radius:6px;
                    text-decoration:none;
               ">
                Back to Users
            </a>

        </div>

    </div>
</div>

</x-app-layout>
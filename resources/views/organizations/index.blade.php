<x-app-layout>

<div class="p-6">

    <h1 class="text-2xl font-bold mb-4">
        Organization Management
    </h1>

    @foreach($organizations as $organization)

        <div class="border p-4 mb-2">

            <strong>
                {{ $organization->organization_name }}
            </strong>

            <br>

            Plan:
            {{ $organization->subscription_plan }}

            <br>

            Status:

            @if($organization->status)
                Active
            @else
                Inactive
            @endif

        </div>

    @endforeach

</div>

</x-app-layout>
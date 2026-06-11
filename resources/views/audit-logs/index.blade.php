<x-app-layout>

<div class="py-6">

<div class="max-w-7xl mx-auto">

<h2 class="text-2xl font-bold mb-6">
Audit Logs
</h2>

<div class="bg-white shadow rounded-lg overflow-hidden">

<table class="min-w-full">

<thead class="bg-gray-100">

<tr>

<th class="px-4 py-3">Date</th>
<th class="px-4 py-3">User</th>
<th class="px-4 py-3">Organization</th>
<th class="px-4 py-3">Action</th>
<th class="px-4 py-3">Entity</th>
<th class="px-4 py-3">Description</th>
<th class="px-4 py-3">IP Address</th>

</tr>

</thead>

<tbody>

@foreach($logs as $log)

<tr class="border-b">

<td class="px-4 py-3">
{{ $log->created_at->format('M d, Y H:i') }}
</td>

<td class="px-4 py-3">
{{ $log->user?->name }}
</td>

<td class="px-4 py-3">
{{ $log->organization?->organization_name }}
</td>

<td class="px-4 py-3">
{{ $log->action }}
</td>

<td class="px-4 py-3">
{{ $log->entity_type }}
</td>

<td class="px-4 py-3">
{{ $log->description }}
</td>

<td class="px-4 py-3">
{{ $log->ip_address }}
</td>

</tr>

@endforeach

</tbody>

</table>

</div>

<div class="mt-4">
{{ $logs->links() }}
</div>

</div>

</div>

</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Edit Department</h2>
            <p class="text-sm text-slate-400">{{ $department->department_name }}</p>
        </div>
    </x-slot>

    @php
        $field = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500';
        $label = 'block text-sm font-medium text-slate-700';
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">

                @if($errors->any())
                <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
                @endif

                <form method="POST" action="{{ route('departments.update', $department->id) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="{{ $label }}">Department name</label>
                        <input type="text" name="department_name" value="{{ old('department_name', $department->department_name) }}" required class="{{ $field }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">Description</label>
                        <textarea name="description" rows="4" class="{{ $field }}">{{ old('description', $department->description) }}</textarea>
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="status" value="1" @checked(old('status', $department->status))
                               class="rounded border-slate-300 text-teal-600 shadow-sm focus:ring-teal-500">
                        <span class="text-sm text-slate-600">Active</span>
                    </label>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                        <a href="{{ route('departments.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancel</a>
                        <button type="submit" class="rounded-lg bg-teal-600 px-5 py-2 text-sm font-medium text-white hover:bg-teal-700">Update Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

{{--
    Reusable Trust Center list table.
    Expects: $rows (Collection), $columns (['Header' => 'attribute']),
             $route (named destroy route), $empty (empty-state text).
--}}
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    @foreach($columns as $header => $attribute)
                        <th class="px-6 py-3">{{ $header }}</th>
                    @endforeach
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $row)
                    <tr class="hover:bg-slate-50">
                        @foreach($columns as $header => $attribute)
                            <td class="px-6 py-3 text-slate-700">{{ $row->{$attribute} ?? '—' }}</td>
                        @endforeach
                        <td class="px-6 py-3 text-right">
                            <form method="POST" action="{{ route($route, $row) }}"
                                  onsubmit="return confirm('Remove this entry?');">
                                @csrf
                                @method('DELETE')
                                <button class="text-sm font-medium text-rose-600 hover:text-rose-700">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + 1 }}" class="px-6 py-10 text-center text-slate-400">{{ $empty }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

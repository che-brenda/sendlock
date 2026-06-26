<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLogger;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::where('organization_id', auth()->user()->organization_id)
            ->withCount('users')
            ->latest()
            ->get();

        return view('departments.index', compact('departments'));
    }

    public function create()
    {
        return view('departments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $department = Department::create([
            'organization_id' => auth()->user()->organization_id,
            'department_name' => $validated['department_name'],
            'description' => $validated['description'] ?? null,
            'status' => true,
        ]);

        AuditLogger::log('CREATE', 'DEPARTMENT', $department->id, 'Created department '.$department->department_name);

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department created successfully.');
    }

    public function show(Department $department)
    {
        $department = $this->scoped($department);
        $department->loadCount('users');
        $department->load('users');

        return view('departments.show', compact('department'));
    }

    public function edit(Department $department)
    {
        $department = $this->scoped($department);

        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $department = $this->scoped($department);

        $validated = $request->validate([
            'department_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|boolean',
        ]);

        $department->update([
            'department_name' => $validated['department_name'],
            'description' => $validated['description'] ?? null,
            'status' => $request->boolean('status'),
        ]);

        AuditLogger::log('UPDATE', 'DEPARTMENT', $department->id, 'Updated department '.$department->department_name);

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        $department = $this->scoped($department);

        AuditLogger::log('DELETE', 'DEPARTMENT', $department->id, 'Deleted department '.$department->department_name);

        $department->delete();

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department deleted successfully.');
    }

    /**
     * Resolve the department within the current tenant, 404/403-ing otherwise.
     */
    private function scoped(Department $department): Department
    {
        return Department::where('organization_id', auth()->user()->organization_id)
            ->findOrFail($department->id);
    }
}

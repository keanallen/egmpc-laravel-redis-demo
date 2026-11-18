<?php
namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class EmployeeController
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $branches = Branch::orderBy('id')->get();
        $branchId = (int) ($filters['branch_id'] ?? $branches->first()?->id);
        $employees = Employee::where('branch_id', $branchId)->orderBy('employee_number')
            ->paginate(25)->appends(['branch_id' => $branchId]);
        return view('employees.index', compact('branches', 'branchId', 'employees'));
    }

    public function show(Request $request, Employee $employee)
    {
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $filters['month'] ?? config('demo.month');
        $start = CarbonImmutable::parse($month.'-01');
        $records = $employee->attendanceRecords()
            ->where('attendance_date', '>=', $start->toDateString())
            ->where('attendance_date', '<', $start->addMonth()->toDateString())
            ->orderBy('attendance_date')->get();
        return view('employees.show', compact('employee', 'month', 'records'));
    }

    public function update(Request $request, Employee $employee, AttendanceRecord $attendanceRecord)
    {
        // Never allow an employee URL to update another employee's record.
        abort_unless($attendanceRecord->employee_id === $employee->id, 404);
        $validated = $request->validate([
            'status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $attendanceRecord->update(['status' => $validated['status']]);

        return to_route('employees.show', ['employee' => $employee->id, 'month' => $validated['month']])
            ->with('success', 'Attendance saved.');
    }
}

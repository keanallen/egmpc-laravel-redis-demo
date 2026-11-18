<?php
namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class MonthlyAttendanceReport
{
    public function load(int $branchId, string $month, int $page, int $perPage): array
    {
        $start = CarbonImmutable::parse($month.'-01');
        $end = $start->addMonth();

        // One query definition. paginate runs a total-count query and a page query.
        $report = DB::table('employees')
            ->leftJoin('attendance_records', function (JoinClause $join) use ($start, $end) {
                $join->on('employees.id', '=', 'attendance_records.employee_id')
                    ->where('attendance_records.attendance_date', '>=', $start->toDateString())
                    ->where('attendance_records.attendance_date', '<', $end->toDateString());
            })
            ->where('employees.branch_id', $branchId)
            ->select('employees.id', 'employees.employee_number', 'employees.name')
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) AS present_days")
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'late' THEN 1 ELSE 0 END) AS late_days")
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) AS absent_days")
            ->groupBy('employees.id', 'employees.employee_number', 'employees.name')
            ->orderBy('employees.employee_number')
            ->paginate($perPage, ['*'], 'page', $page);


        return [
            'rows' => $report->getCollection()->map(fn ($row) => [
                'id' => (int) $row->id, 'employee_number' => $row->employee_number, 'name' => $row->name,
                'present_days' => (int) $row->present_days,
                'late_days' => (int) $row->late_days,
                'absent_days' => (int) $row->absent_days,
            ])->all(),
            'total' => $report->total(),
        ];
    }
}

<?php
namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // First 20 weekdays of September 2025: September 1–26.
        $dates = [];
        $date = CarbonImmutable::parse(config('demo.month').'-01');
        while (count($dates) < 20) {
            if ($date->isWeekday()) { $dates[] = $date->toDateString(); }
            $date = $date->addDay();
        }
        $now = now();
        DB::transaction(function () use ($dates, $now) {
            for ($branch = 1; $branch <= 6; $branch++) {
                DB::table('branches')->upsert([
                    ['id' => $branch, 'name' => "Branch {$branch}", 'created_at' => $now, 'updated_at' => $now],
                ], ['id'], ['name', 'updated_at']);
                $employees = [];
                for ($number = 1; $number <= 500; $number++) {
                    $employees[] = [
                        'branch_id' => $branch,
                        'employee_number' => sprintf('B%02d-%04d', $branch, $number),
                        'name' => sprintf('Employee %02d-%04d', $branch, $number),
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                DB::table('employees')->upsert($employees, ['employee_number'], ['branch_id', 'name', 'updated_at']);
                $employees = DB::table('employees')->where('branch_id', $branch)->whereIn('employee_number', array_column($employees, 'employee_number'))->orderBy('employee_number')->get();
                $records = [];
                foreach ($employees as $employee) {
                    $number = (int) substr($employee->employee_number, -4);
                    foreach ($dates as $day => $date) {
                        // Every employee has 16 present, 3 late and 1 absent days.
                        $slot = ($number + $day) % 20;
                        $records[] = [
                            'employee_id' => $employee->id, 'attendance_date' => $date,
                            'status' => $slot === 0 ? 'absent' : ($slot <= 3 ? 'late' : 'present'),
                            'created_at' => $now, 'updated_at' => $now,
                        ];
                        if (count($records) === 1000) {
                            DB::table('attendance_records')->upsert($records, ['employee_id', 'attendance_date'], ['status', 'updated_at']);
                            $records = [];
                        }
                    }
                }
                if ($records) {
                    DB::table('attendance_records')->upsert($records, ['employee_id', 'attendance_date'], ['status', 'updated_at']);
                }
            }
        });
    }
}

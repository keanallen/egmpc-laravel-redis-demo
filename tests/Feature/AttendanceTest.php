<?php
namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Reports\MonthlyAttendanceReport;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function employee(Branch $branch, string $number): Employee
    {
        return Employee::create(['branch_id' => $branch->id, 'employee_number' => $number, 'name' => 'Demo '.$number]);
    }

    private function record(Employee $employee, string $date, string $status): AttendanceRecord
    {
        return $employee->attendanceRecords()->create(['attendance_date' => $date, 'status' => $status]);
    }

    public function test_seeder_has_exact_counts_and_is_repeatable(): void
    {
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('branches', 6);
        $this->assertDatabaseCount('employees', 3000);
        $this->assertDatabaseCount('attendance_records', 60000);
        foreach (Branch::all() as $branch) {
            $this->assertSame(500, $branch->employees()->count());
        }
        $this->assertSame(20, DB::table('attendance_records')->distinct()->count('attendance_date'));
        foreach (DB::table('attendance_records')->distinct()->pluck('attendance_date') as $date) {
            $this->assertSame('2025-09', substr($date, 0, 7));
            $this->assertLessThanOrEqual(5, (int) date('N', strtotime($date)));
        }
        $data = app(MonthlyAttendanceReport::class)->load(1, '2025-09', 1, 25);
        $this->assertSame(500, $data['total']);
        $this->assertCount(25, $data['rows']);
        $this->assertSame([16, 3, 1], array_values(array_intersect_key($data['rows'][0], array_flip(['present_days', 'late_days', 'absent_days']))));
        $this->get('/')->assertOk()->assertViewHas('branchId', 1)->assertViewHas('month', '2025-09');
    }

    public function test_report_counts_statuses_exclusively_and_includes_missing_records(): void
    {
        $branch = Branch::create(['name' => 'Branch 1']);
        $first = $this->employee($branch, 'E001');
        $second = $this->employee($branch, 'E002');
        $this->employee($branch, 'E003');
        $other = $this->employee(Branch::create(['name' => 'Other']), 'OTHER');
        foreach (['present', 'late', 'absent'] as $day => $status) {
            $this->record($first, '2025-09-0'.($day + 1), $status);
        }
        $this->record($first, '2025-08-31', 'present');
        $this->record($first, '2025-10-01', 'present');
        $this->record($second, '2025-08-01', 'absent');
        $this->record($other, '2025-09-01', 'present');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $data = app(MonthlyAttendanceReport::class)->load($branch->id, '2025-09', 1, 25);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(2, $queries); // Total plus page; no per-employee attendance queries.
        $this->assertSame(3, $data['total']);
        $this->assertSame([1, 1, 1], array_slice(array_values($data['rows'][0]), -3));
        foreach (array_slice($data['rows'], 1) as $row) {
            $this->assertSame([0, 0, 0], array_slice(array_values($row), -3));
        }
    }

    public function test_report_and_employee_pagination_preserve_filters_and_order(): void
    {
        $branch = Branch::create(['name' => 'Branch 1']);
        $other = Branch::create(['name' => 'Branch 2']);
        for ($i = 30; $i >= 1; $i--) { $this->employee($branch, sprintf('E%03d', $i)); }
        $this->employee($other, 'OTHER');
        $url = '/?branch_id='.$branch->id.'&month=2025-09';
        $first = $this->get($url)->assertOk()->assertViewHas('source', 'MySQL')->assertViewHas('reportQueriesRan', true);
        $page = $first->viewData('employees');
        $this->assertCount(25, $page->items());
        $this->assertSame(30, $page->total());
        $this->assertSame('E001', $page->items()[0]['employee_number']);
        $this->assertStringContainsString('branch_id='.$branch->id, $page->nextPageUrl());
        $this->assertStringContainsString('month=2025-09', $page->nextPageUrl());
        $second = $this->get($url.'&page=2')->assertOk()->viewData('employees');
        $this->assertCount(5, $second->items());
        $this->assertSame('E026', $second->items()[0]['employee_number']);
        $list = $this->get('/employees?branch_id='.$branch->id)->assertOk()->viewData('employees');
        $this->assertCount(25, $list->items());
        $this->assertStringContainsString('branch_id='.$branch->id, $list->nextPageUrl());
        $this->get('/employees?branch_id='.$branch->id.'&page=2')->assertOk()
            ->assertSee('E026')->assertDontSee('E001')->assertDontSee('OTHER');
        $this->get('/?branch_id='.$branch->id.'&month=2025-10')->assertOk()
            ->assertViewHas('month', '2025-10');
    }

    public function test_update_changes_totals_and_preserves_employee_and_month(): void
    {
        $employee = $this->employee(Branch::create(['name' => 'Branch 1']), 'E001');
        $record = $this->record($employee, '2025-09-01', 'present');
        $this->get('/employees/'.$employee->id.'?month=2025-09')->assertOk()->assertSee('Present');
        $before = app(MonthlyAttendanceReport::class)->load($employee->branch_id, '2025-09', 1, 25);
        $this->assertSame(1, $before['rows'][0]['present_days']);
        $this->patch('/employees/'.$employee->id.'/attendance/'.$record->id, ['status' => 'late', 'month' => '2025-09'])
            ->assertRedirect(route('employees.show', ['employee' => $employee->id, 'month' => '2025-09']))
            ->assertSessionHas('success');
        $after = app(MonthlyAttendanceReport::class)->load($employee->branch_id, '2025-09', 1, 25);
        $this->assertSame([0, 1, 0], array_slice(array_values($after['rows'][0]), -3));
        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'status' => 'late']);
    }

    public function test_invalid_status_and_cross_employee_update_are_rejected(): void
    {
        $branch = Branch::create(['name' => 'Branch 1']);
        $employee = $this->employee($branch, 'E001');
        $other = $this->employee($branch, 'E002');
        $record = $this->record($employee, '2025-09-01', 'present');
        $url = '/employees/'.$employee->id.'/attendance/'.$record->id;
        foreach (['excused', 'present,late', '', ['present']] as $invalid) {
            $this->patch($url, ['status' => $invalid, 'month' => '2025-09'])->assertSessionHasErrors('status');
        }
        $this->patch('/employees/'.$other->id.'/attendance/'.$record->id, ['status' => 'absent', 'month' => '2025-09'])->assertNotFound();
        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'status' => 'present']);
        $this->get('/?month=not-a-month')->assertSessionHasErrors('month');
        $this->get('/?branch_id=999999')->assertSessionHasErrors('branch_id');
        $this->get('/?page=0')->assertSessionHasErrors('page');
    }

    public function test_employee_date_unique_constraint_is_enforced(): void
    {
        $employee = $this->employee(Branch::create(['name' => 'Branch 1']), 'E001');
        $this->record($employee, '2025-09-01', 'present');
        $this->expectException(QueryException::class);
        $this->record($employee, '2025-09-01', 'absent');
    }

    public function test_employee_number_unique_constraint_is_enforced(): void
    {
        $branch = Branch::create(['name' => 'Branch 1']);
        $this->employee($branch, 'E001');
        $this->expectException(QueryException::class);
        $this->employee($branch, 'E001');
    }
}

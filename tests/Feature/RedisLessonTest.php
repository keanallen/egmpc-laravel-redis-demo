<?php
namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RedisLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_hits_stale_data_and_targeted_invalidation(): void
    {
        if (!getenv('RUN_REDIS_LESSON_TESTS')) { $this->markTestSkipped('Run python3 scripts/verify_redis_lesson.py to enable the actual commented blocks temporarily.'); }
        // Separate namespace: never flush the trainer's or another application's cache.
        config(['cache.prefix' => 'attendance_test_'.bin2hex(random_bytes(8)).'_']);
        $branch = Branch::create(['name' => 'Branch 1']);
        $other = Branch::create(['name' => 'Branch 2']);
        for ($i = 1; $i <= 26; $i++) {
            $employee = Employee::create(['branch_id' => $branch->id, 'employee_number' => sprintf('E%03d', $i), 'name' => 'Employee '.$i]);
            $employee->attendanceRecords()->create(['attendance_date' => '2025-09-01', 'status' => 'present']);
        }
        $employee = Employee::orderBy('employee_number')->first();
        $record = $employee->attendanceRecords()->first();
        $tag = 'attendance:branch:'.$branch->id.':month:2025-09';
        $otherTag = 'attendance:branch:'.$other->id.':month:2025-09';
        $otherMonthTag = 'attendance:branch:'.$branch->id.':month:2025-10';
        try {
            $url = '/?branch_id='.$branch->id.'&month=2025-09';
            $this->get($url)->assertOk()->assertViewHas('source', 'MySQL')->assertViewHas('reportQueriesRan', true);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $hit = $this->get($url)->assertOk()->assertViewHas('source', 'Redis')->assertViewHas('reportQueriesRan', false);
            $attendanceQueries = array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'attendance_records'));
            DB::disableQueryLog();
            $this->assertCount(0, $attendanceQueries);
            $this->assertCount(25, $hit->viewData('employees')->items());
            $this->assertStringContainsString('month=2025-09', $hit->viewData('employees')->nextPageUrl());
            $this->get($url.'&page=2')->assertOk()->assertViewHas('source', 'MySQL');
            $this->get($url)->assertOk()->assertViewHas('source', 'Redis');
            $this->get('/?branch_id='.$other->id.'&month=2025-09')->assertViewHas('source', 'MySQL');
            $this->get('/?branch_id='.$branch->id.'&month=2025-10')->assertViewHas('source', 'MySQL');
            $key = 'attendance:v1:branch:'.$branch->id.':month:2025-09:page:1:size:25';
            $data = Cache::store('redis')->tags([$tag])->get($key);
            $this->assertIsArray($data['rows'][0]);
            $this->assertArrayNotHasKey('links', $data);
            // Deliberately submit another context month: invalidation must use the record date.
            $this->patch('/employees/'.$employee->id.'/attendance/'.$record->id, ['status' => 'late', 'month' => '2025-10'])->assertRedirect();
            $invalidates = getenv('CACHE_LESSON_INVALIDATION') === '1';
            $response = $this->get($url)->assertViewHas('source', $invalidates ? 'MySQL' : 'Redis');
            $row = $response->viewData('employees')->items()[0];
            $this->assertSame($invalidates ? 0 : 1, $row['present_days']);
            $this->assertSame($invalidates ? 1 : 0, $row['late_days']);
            $this->get($url)->assertViewHas('source', 'Redis');
            $this->get($url.'&page=2')->assertViewHas('source', $invalidates ? 'MySQL' : 'Redis');
            $this->get('/?branch_id='.$other->id.'&month=2025-09')->assertViewHas('source', 'Redis');
            $this->get('/?branch_id='.$branch->id.'&month=2025-10')->assertViewHas('source', 'Redis');
        } finally {
            foreach ([$tag, $otherTag, $otherMonthTag] as $cleanupTag) {
                Cache::store('redis')->tags([$cleanupTag])->flush();
            }
        }
    }
}

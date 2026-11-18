<?php
namespace App\Http\Controllers;

use App\Models\Branch;
use App\Reports\MonthlyAttendanceReport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class DashboardController
{
    public function __invoke(Request $request, MonthlyAttendanceReport $report)
    {
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'month' => ['nullable', 'date_format:Y-m'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        // Dropdown work is outside the report measurement.
        $branches = Branch::orderBy('id')->get();
        $branchId = (int) ($filters['branch_id'] ?? $branches->first()?->id);
        $month = $filters['month'] ?? config('demo.month');
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) config('demo.page_size');
        $started = hrtime(true);


        $data = $report->load($branchId, $month, $page, $perPage);
        $source = 'MySQL';
        $reportQueriesRan = true;


        $loadMs = (hrtime(true) - $started) / 1_000_000;
        // Build links from THIS request, even when rows came from an older cache entry.
        $employees = new LengthAwarePaginator($data['rows'], $data['total'], $perPage, $page, [
            'path' => $request->url(), 'query' => ['branch_id' => $branchId, 'month' => $month],
        ]);
        return view('dashboard', compact('branches', 'branchId', 'month', 'employees', 'source', 'reportQueriesRan', 'loadMs'));
    }
}

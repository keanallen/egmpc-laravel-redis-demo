@extends('layout')
@section('title', 'Monthly attendance report')
@section('content')
<h1>Monthly attendance report</h1>
<p class="intro">Review attendance by branch. Each day counts once: present, late, or absent.</p>
<form class="filters" method="GET" action="{{ route('dashboard') }}">
    @include('partials.branch-filter')
    <label for="month">Month <input type="month" id="month" name="month" value="{{ $month }}" required></label>
    <button type="submit">Load report</button>
</form>
<div class="report-metadata" aria-label="Report diagnostics">
    <span>Data source: <strong>{{ $source }}</strong></span>
    <span>Data load time: <strong>{{ number_format($loadMs, 2) }} ms</strong></span>
    <span>Attendance report queries ran: <strong>{{ $reportQueriesRan ? 'Yes' : 'No' }}</strong></span>
</div>
<p class="hint">Data load time measures report loading before Blade renders. Branch dropdown queries are excluded.</p>
<div class="table-scroll" role="region" aria-label="Monthly employee attendance" tabindex="0">
<table>
    <caption>{{ $branches->firstWhere('id', $branchId)?->name ?? 'No branch selected' }} · {{ $month }} · {{ number_format($employees->total()) }} employees</caption>
    <thead><tr><th scope="col">Employee number</th><th scope="col">Employee name</th><th scope="col" class="number">Present days</th><th scope="col" class="number">Late days</th><th scope="col" class="number">Absent days</th></tr></thead>
    <tbody>
    @forelse($employees as $employee)
        <tr>
            <td><a href="{{ route('employees.show', ['employee' => $employee['id'], 'month' => $month]) }}">{{ $employee['employee_number'] }}</a></td>
            <td>{{ $employee['name'] }}</td>
            <td class="number">{{ $employee['present_days'] }}</td>
            <td class="number">{{ $employee['late_days'] }}</td>
            <td class="number">{{ $employee['absent_days'] }}</td>
        </tr>
    @empty
        <tr><td colspan="5">No employees on this page. Choose a branch or return to page 1.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@include('partials.pagination')
<p class="hint">Absences are stored explicitly. A missing daily record contributes zero to every count.</p>
@endsection

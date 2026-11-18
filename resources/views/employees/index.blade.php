@extends('layout')
@section('title', 'Employees')
@section('content')
<h1>Employees</h1>
<p class="intro">Choose an employee to review daily records and change an attendance status.</p>
<form class="filters" method="GET" action="{{ route('employees.index') }}">
    @include('partials.branch-filter')
    <button type="submit">Show employees</button>
</form>
<div class="table-scroll" role="region" aria-label="Employees" tabindex="0">
<table>
    <caption>{{ $branches->firstWhere('id', $branchId)?->name ?? 'No branch selected' }}</caption>
    <thead><tr><th scope="col">Employee number</th><th scope="col">Employee name</th><th scope="col">Attendance</th></tr></thead>
    <tbody>
    @forelse($employees as $employee)
        <tr><td>{{ $employee->employee_number }}</td><td>{{ $employee->name }}</td><td><a href="{{ route('employees.show', $employee) }}">View attendance<span class="sr-only"> for {{ $employee->employee_number }}</span></a></td></tr>
    @empty
        <tr><td colspan="3">No employees on this page.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@include('partials.pagination')
@endsection

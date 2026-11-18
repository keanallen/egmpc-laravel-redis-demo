@extends('layout')
@section('title', $employee->employee_number.' attendance')
@section('content')
<a href="{{ route('employees.index', ['branch_id' => $employee->branch_id]) }}">Back to branch employees</a>
<h1>{{ $employee->name }}</h1>
<p class="intro">{{ $employee->employee_number }} · Daily attendance</p>
<form class="filters" method="GET" action="{{ route('employees.show', $employee) }}">
    <label for="month">Month <input type="month" id="month" name="month" value="{{ $month }}" required></label>
    <button type="submit">Show records</button>
    <a href="{{ route('dashboard', ['branch_id' => $employee->branch_id, 'month' => $month]) }}">Open branch report</a>
</form>
<div class="table-scroll" role="region" aria-label="Daily attendance" tabindex="0">
<table>
    <caption>Existing records · {{ $month }}</caption>
    <thead><tr><th scope="col">Date</th><th scope="col">Current status</th><th scope="col">Change status</th></tr></thead>
    <tbody>
    @forelse($records as $record)
        <tr>
            <td>{{ $record->attendance_date->format('D, M j, Y') }}</td>
            <td><span class="status {{ $record->status }}">{{ ucfirst($record->status) }}</span></td>
            <td>
                <form class="status-form" method="POST" action="{{ route('attendance.update', ['employee' => $employee, 'attendanceRecord' => $record]) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="month" value="{{ $month }}">
                    <label class="sr-only" for="status-{{ $record->id }}">Status for {{ $record->attendance_date->format('Y-m-d') }}</label>
                    <select name="status" id="status-{{ $record->id }}">
                        @foreach(\App\Models\AttendanceRecord::STATUSES as $status)
                            <option value="{{ $status }}" @selected($record->status === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <button type="submit">Save<span class="sr-only"> status for {{ $record->attendance_date->format('Y-m-d') }}</span></button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="3">No daily records for this month. Missing days are not counted as absences.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection

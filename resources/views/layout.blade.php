<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Attendance Lab</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
</head>
<body>
<header class="site-header">
    <a class="brand" href="{{ route('dashboard') }}">Attendance Lab</a>
    <nav aria-label="Main navigation">
        <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Monthly report</a>
        <a href="{{ route('employees.index') }}" @if(request()->routeIs('employees.*')) aria-current="page" @endif>Employees</a>
    </nav>
</header>
<main>
    @if(session('success')) <p class="notice" role="status">{{ session('success') }}</p> @endif
    @if($errors->any())
        <div class="errors" role="alert">
            <strong>Please check your input.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>

</body>
</html>

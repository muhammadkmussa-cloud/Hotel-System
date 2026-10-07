@extends('layouts.staff', ['title' => 'Not available'])
@section('content')
    <h1>{{ $status === 404 ? 'Not found' : 'Not available' }}</h1>
    <p>{{ $message }}</p>
    <p><a class="btn" href="javascript:history.back()">Go back</a></p>
@endsection

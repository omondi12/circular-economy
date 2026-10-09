@extends('errors.minimal')

@section('title')
    Too many requests
@endsection
@section('code')
    429
@endsection
@section('heading')
    Please slow down
@endsection
@section('message')
    Too many attempts in a short time. Wait a minute, then try again.
@endsection

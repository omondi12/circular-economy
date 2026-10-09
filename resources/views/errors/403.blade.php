@extends('errors.minimal')

@section('title')
    Access denied
@endsection
@section('code')
    403
@endsection
@section('heading')
    You don't have access to this page
@endsection
@section('message')
    Your account's role doesn't include this page or action. If you think it should, ask an administrator.
@endsection

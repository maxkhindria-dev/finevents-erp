@extends('layouts.app')

@section('title', 'Your recovery codes')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <h1 class="h4 mb-3">Two-step verification is on</h1>
            <div class="alert alert-warning">
                <strong>Save these recovery codes now.</strong> Each one works once, if you lose your phone.
                They will not be shown again. Store them in Dashlane, not in an email or a document.
            </div>
            <ul class="list-group mb-4 font-monospace">
                @foreach ($codes as $code)
                    <li class="list-group-item">{{ $code }}</li>
                @endforeach
            </ul>
            <a href="{{ route('home') }}" class="btn btn-primary">I have saved them, continue</a>
        </div>
    </div>
@endsection

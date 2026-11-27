@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-sm mt-4">
                <div class="card-body p-4 text-center">
                    <h1 class="h4 mb-3">Sign in to FinEvents ERP</h1>
                    <p class="text-muted mb-4">Use your FinEvents Google Workspace account.</p>
                    <a href="{{ route('auth.google') }}" class="btn btn-secondary btn-lg w-100">Continue with Google</a>
                    <p class="small text-muted mt-4 mb-0">
                        You will also be asked for a code from your authenticator app.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

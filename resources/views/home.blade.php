@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-3">Welcome to FinEvents ERP</h1>
            <p class="lead">
                This is the foundation release. Sign-in with Google and multi-factor
                authentication arrive in the next steps of Phase 1.
            </p>

            <div class="card mt-4">
                <div class="card-body">
                    <h2 class="h5">Coming next</h2>
                    <ul class="mb-0">
                        @foreach (config('finevents.modules') as $module)
                            <li>
                                <strong>{{ $module['label'] }}</strong>
                                — {{ $module['phase'] ? 'Phase '.$module['phase'] : 'after v1' }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <a href="#" class="btn btn-secondary mt-4 disabled" aria-disabled="true">Sign in with Google (coming soon)</a>
        </div>
    </div>
@endsection

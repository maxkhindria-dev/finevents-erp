@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-1">Welcome, {{ auth()->user()->full_name }}</h1>
            <p class="text-muted">{{ auth()->user()->role->label() }}</p>

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
        </div>
    </div>
@endsection

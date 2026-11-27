@extends('layouts.app')

@section('title', 'Enter your code')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <form method="POST" action="{{ route('mfa.challenge') }}" class="card card-body mt-4">
                @csrf
                <h1 class="h4 mb-3">Enter your code</h1>
                <label for="code" class="form-label">Code from your authenticator app, or one of your recovery codes</label>
                <input id="code" name="code" type="text" autocomplete="one-time-code" required
                       class="form-control form-control-lg @error('code') is-invalid @enderror" autofocus>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-primary mt-3">Continue</button>
            </form>
        </div>
    </div>
@endsection

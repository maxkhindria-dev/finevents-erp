@extends('layouts.app')

@section('title', 'Set up two-step verification')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <h1 class="h4 mb-3">Set up two-step verification</h1>
            <p>FinEvents ERP asks for a code from an authenticator app every time you sign in.</p>
            <ol>
                <li>Open an authenticator app on your phone (Google Authenticator, Microsoft Authenticator, 1Password or similar).</li>
                <li>Scan this QR code.</li>
                <li>Enter the 6-digit code the app shows.</li>
            </ol>

            <div class="text-center my-4" aria-label="QR code for your authenticator app">
                {!! preg_replace('/^<\?xml[^>]*>\s*/', '', $qrCode) !!}
            </div>

            <details class="mb-4">
                <summary>Can't scan? Enter this key instead</summary>
                <code class="d-block mt-2 user-select-all">{{ trim(chunk_split($secret, 4, ' ')) }}</code>
            </details>

            <form method="POST" action="{{ route('mfa.setup') }}" class="card card-body">
                @csrf
                <label for="code" class="form-label fw-bold">6-digit code</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required
                       class="form-control form-control-lg @error('code') is-invalid @enderror" autofocus>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-primary mt-3">Confirm</button>
            </form>
        </div>
    </div>
@endsection

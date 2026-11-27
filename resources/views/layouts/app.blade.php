<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Home') · FinEvents ERP</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark fe-navbar" aria-label="Main navigation">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="{{ url('/') }}">
                Fin<span class="fe-brand-mark">Events</span> ERP
            </a>
            @unless (app()->isProduction())
                <span class="badge fe-env-badge me-2">{{ strtoupper(app()->environment()) }}</span>
            @endunless
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                @auth
                    @if (session('mfa_verified_at'))
                        <ul class="navbar-nav me-auto">
                            @foreach (config('finevents.modules') as $module)
                                <li class="nav-item">
                                    <span class="nav-link fe-nav-later" aria-disabled="true"
                                          title="{{ $module['phase'] ? 'Arrives in Phase '.$module['phase'] : 'Planned after v1' }}">
                                        {{ $module['label'] }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="d-flex align-items-center ms-auto gap-3">
                        <span class="navbar-text text-white small">{{ auth()->user()->full_name }}</span>
                        <form method="POST" action="{{ route('sign-out') }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-light">Sign out</button>
                        </form>
                    </div>
                @endauth
            </div>
        </div>
    </nav>

    <main class="container py-4">
        @if (session('status'))
            <div class="alert alert-info" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>

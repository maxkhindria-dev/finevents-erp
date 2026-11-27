<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security headers to every response (OWASP / ASVS L2).
 *
 * The Content Security Policy only allows scripts and styles from our own
 * domain plus a per-request nonce, so injected scripts cannot run.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->remove('X-Powered-By');

        // HSTS only over HTTPS (browsers ignore it on http, and it would break local dev).
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $self = "'self'";
        $scripts = [$self, "'nonce-{$nonce}'"];
        $styles = [$self, "'nonce-{$nonce}'"];
        $connect = [$self];

        // Local development only: allow the Vite dev server for hot reload.
        if (app()->isLocal() && Vite::isRunningHot()) {
            $hot = rtrim((string) file_get_contents(public_path('hot')));
            $scripts[] = $hot;
            $styles[] = $hot;
            $connect[] = $hot;
            $connect[] = preg_replace('#^http#', 'ws', $hot);
        }

        return implode('; ', [
            "default-src {$self}",
            'script-src '.implode(' ', $scripts),
            'style-src '.implode(' ', $styles),
            "img-src {$self} data:",
            "font-src {$self}",
            'connect-src '.implode(' ', $connect),
            "object-src 'none'",
            "base-uri 'none'",
            "form-action {$self}",
            "frame-ancestors 'none'",
        ]);
    }
}

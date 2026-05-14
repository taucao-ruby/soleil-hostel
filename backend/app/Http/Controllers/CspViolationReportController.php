<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CspViolationReportController extends Controller
{
    /**
     * Handle CSP violation reports from the browser
     *
     * The browser sends CSP violation reports to this endpoint
     * when a resource is blocked by the Content Security Policy.
     */
    public function report(Request $request): Response
    {
        $payload = $request->json()->all();
        $report = $payload['csp-report'] ?? null;

        if (! is_array($report)) {
            return response('', 204);
        }

        Log::warning('CSP Violation Report', [
            'ip' => $this->cleanForLog($request->ip(), 64),
            'user_agent' => $this->cleanForLog($request->userAgent(), 256),
            'document_uri' => $this->cleanForLog($report['document-uri'] ?? null, 512),
            'blocked_uri' => $this->cleanForLog($report['blocked-uri'] ?? null, 512),
            'effective_directive' => $this->cleanForLog($report['effective-directive'] ?? null, 128),
            'violated_directive' => $this->cleanForLog($report['violated-directive'] ?? null, 128),
            'disposition' => $this->cleanForLog($report['disposition'] ?? null, 32),
            'status_code' => $this->statusCodeForLog($report['status-code'] ?? null),
        ]);

        return response('', 204);
    }

    private function cleanForLog(mixed $value, int $maxLength): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $clean = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $value);
        $clean = preg_replace('/\s+/', ' ', $clean ?? '');
        $clean = trim($clean ?? '');

        if ($clean === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($clean, 0, $maxLength);
        }

        return substr($clean, 0, $maxLength);
    }

    private function statusCodeForLog(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}

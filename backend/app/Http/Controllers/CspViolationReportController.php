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
        // Get the CSP violation report from the request body
        // CSP reports come as application/csp-report content type
        $violation = $request->json()->all() ?? $request->all();

        // Log the CSP violation for monitoring
        Log::warning('CSP Violation Report', [
            'document_uri' => $violation['document-uri'] ?? 'unknown',
            'blocked_uri' => $violation['blocked-uri'] ?? 'unknown',
            'directive' => $violation['violated-directive'] ?? 'unknown',
            'status_code' => $violation['status-code'] ?? 0,
            'original_policy' => $violation['original-policy'] ?? 'unknown',
        ]);

        // Return 204 No Content (standard response for CSP reports)
        return response('', 204);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuthSecurityService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityController extends Controller
{
    public function __construct(private readonly AuthSecurityService $securityService) {}

    public function auditList(Request $request)
    {
        $limit = $request->query('limit') ? (int) $request->query('limit') : 100;
        $logs = $this->securityService->listAuthAuditLogs($limit);

        return response()->json([
            'logs' => $logs,
        ]);
    }

    public function auditExport(Request $request)
    {
        $limit = $request->query('limit') ? (int) $request->query('limit') : 1000;
        $logs = $this->securityService->listAuthAuditLogs($limit);

        return new StreamedResponse(function () use ($logs) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['ID', 'User ID', 'Email', 'Event', 'IP Address', 'User Agent', 'Created At']);

            foreach ($logs as $log) {
                fputcsv($out, [
                    $log['id'] ?? '',
                    $log['user_id'] ?? '',
                    $log['email'] ?? '',
                    $log['event'] ?? '',
                    $log['ip_address'] ?? '',
                    $log['user_agent'] ?? '',
                    $log['created_at'] ?? '',
                ]);
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="security_audit.csv"',
        ]);
    }
}

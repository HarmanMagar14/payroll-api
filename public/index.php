<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\PayrollService;
use App\PayslipPdf;
use App\S3Uploader;

function respond(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function fail(int $status, string $message): never
{
    respond($status, ['success' => false, 'message' => $message]);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$service = new PayrollService();

try {
    // GET / — simple index so the base URL shows something useful
    if ($path === '/') {
        respond(200, [
            'success' => true,
            'message' => 'Payroll API',
            'endpoints' => [
                'GET /api/employees',
                'GET /api/reports/payroll',
                'GET /api/payslips/{payroll_id}',
                'POST /api/payslips/{payroll_id}/generate',
            ],
        ]);
    }

    // GET /api/employees
    if ($path === '/api/employees') {
        if ($method !== 'GET') fail(405, 'Method not allowed');
        respond(200, ['success' => true, 'data' => $service->employees()]);
    }

    // GET /api/reports/payroll
    if ($path === '/api/reports/payroll') {
        if ($method !== 'GET') fail(405, 'Method not allowed');
        respond(200, ['success' => true, 'data' => $service->payrollReport()]);
    }

    // POST /api/payslips/{payroll_id}/generate
    if (preg_match('#^/api/payslips/(\d+)/generate$#', $path, $m)) {
        if ($method !== 'POST') fail(405, 'Method not allowed, use POST');
        $payslip = $service->payslip((int) $m[1]);
        if ($payslip === null) fail(404, "Payroll record {$m[1]} not found");

        $pdf = PayslipPdf::render($payslip);
        $upload = (new S3Uploader())->putPdf("payslips/{$payslip['payroll_id']}.pdf", $pdf);

        respond(201, [
            'success' => true,
            'message' => 'Payslip generated and uploaded.',
            'data' => [
                'payroll_id'   => $payslip['payroll_id'],
                'payslip_file' => $upload['url'],
                's3_key'       => $upload['key'],
            ],
        ]);
    }

    // GET /api/payslips/{payroll_id}
    if (preg_match('#^/api/payslips/(\d+)$#', $path, $m)) {
        if ($method !== 'GET') fail(405, 'Method not allowed');
        $payslip = $service->payslip((int) $m[1]);
        if ($payslip === null) fail(404, "Payroll record {$m[1]} not found");
        respond(200, ['success' => true, 'data' => $payslip]);
    }

    fail(404, "Route not found: {$method} {$path}");
} catch (Throwable $e) {
    error_log((string) $e); // visible in CloudWatch Logs
    fail(500, $e->getMessage());
}

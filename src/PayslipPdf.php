<?php

declare(strict_types=1);

namespace App;

use Dompdf\Dompdf;
use Dompdf\Options;

/** Renders a payslip array (from PayrollService::payslip) into PDF bytes. */
final class PayslipPdf
{
    public static function render(array $p): string
    {
        $options = new Options();
        // Lambda's filesystem is read-only except /tmp
        $options->setTempDir('/tmp');
        $options->setFontCache('/tmp');
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::html($p));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private static function html(array $p): string
    {
        $e = fn ($v) => htmlspecialchars((string) ($v ?? '—'), ENT_QUOTES, 'UTF-8');
        $m = fn (float $v) => number_format($v, 2);
        $generated = gmdate('Y-m-d H:i') . ' UTC';

        return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>
  body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #222; margin: 30px; }
  h1 { font-size: 22px; margin: 0; }
  .sub { color: #666; margin-top: 4px; }
  .box { border: 1px solid #ccc; padding: 12px; margin-top: 20px; }
  .box h2 { font-size: 13px; text-transform: uppercase; color: #555; margin: 0 0 8px; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 5px 0; }
  td.label { color: #666; width: 40%; }
  td.amt { text-align: right; }
  tr.total td { border-top: 2px solid #222; font-weight: bold; font-size: 14px; padding-top: 8px; }
  .foot { margin-top: 30px; color: #999; font-size: 10px; }
</style></head><body>
  <h1>Payslip</h1>
  <div class="sub">Payroll ID #{$e($p['payroll_id'])}</div>

  <div class="box">
    <h2>Employee details</h2>
    <table>
      <tr><td class="label">Employee ID</td><td>{$e($p['employee_id'])}</td></tr>
      <tr><td class="label">Name</td><td>{$e($p['employee_name'])}</td></tr>
      <tr><td class="label">Email</td><td>{$e($p['email'])}</td></tr>
      <tr><td class="label">Department</td><td>{$e($p['department'])}</td></tr>
      <tr><td class="label">Position</td><td>{$e($p['position'])}</td></tr>
    </table>
  </div>

  <div class="box">
    <h2>Pay period</h2>
    <table>
      <tr><td class="label">Period start</td><td>{$e($p['pay_period_start'])}</td></tr>
      <tr><td class="label">Period end</td><td>{$e($p['pay_period_end'])}</td></tr>
    </table>
  </div>

  <div class="box">
    <h2>Earnings and deductions</h2>
    <table>
      <tr><td class="label">Gross pay</td><td class="amt">{$m($p['gross_pay'])}</td></tr>
      <tr><td class="label">Deductions</td><td class="amt">- {$m($p['deductions'])}</td></tr>
      <tr class="total"><td>Net pay</td><td class="amt">{$m($p['net_pay'])}</td></tr>
    </table>
  </div>

  <div class="foot">Generated {$generated}. Deductions are computed as gross pay minus net pay.</div>
</body></html>
HTML;
    }
}

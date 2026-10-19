<?php

declare(strict_types=1);

namespace App;

/**
 * Business logic for employees, the payroll report and payslips.
 * Money is summed in integer cents to avoid floating-point drift.
 */
final class PayrollService
{
    /** @return list<array<string, mixed>> */
    public function employees(): array
    {
        $departments = Csv::indexById('departments', true);
        $positions = Csv::indexById('positions', true);

        return array_map(function (array $e) use ($departments, $positions) {
            $deptId = (int) $e['department_id'];
            $posId = (int) $e['position_id'];

            return [
                'employee_id'     => (int) $e['id'],
                'full_name'       => $e['full_name'],
                'email'           => $e['email'],
                'position_id'     => $posId,
                'position_title'  => $positions[$posId]['title'] ?? null,
                'department_id'   => $deptId,
                'department_name' => $departments[$deptId]['name'] ?? null,
                'manager_id'      => $e['manager_id'] !== null ? (int) $e['manager_id'] : null,
            ];
        }, Csv::read('employees'));
    }

    /** @return array<string, mixed> */
    public function payrollReport(): array
    {
        $gross = 0;
        $net = 0;
        $count = 0;
        $netExceedsGross = 0;

        foreach (Csv::read('payroll') as $p) {
            $g = self::toCents($p['gross_pay']);
            $n = self::toCents($p['net_pay']);
            $gross += $g;
            $net += $n;
            $count++;
            if ($n > $g) {
                $netExceedsGross++;
            }
        }

        return [
            'total_payroll_records' => $count,
            'total_gross_pay'       => self::fromCents($gross),
            'total_deductions'      => self::fromCents($gross - $net),
            'total_net_pay'         => self::fromCents($net),
            // Data-quality note: the synthetic dataset has some rows where
            // net pay is higher than gross pay (negative deductions).
            'records_with_net_greater_than_gross' => $netExceedsGross,
        ];
    }

    /** @return array<string, mixed>|null null when the payroll id does not exist */
    public function payslip(int $payrollId): ?array
    {
        $payroll = Csv::indexById('payroll')[$payrollId] ?? null;
        if ($payroll === null) {
            return null;
        }

        $employeeId = (int) $payroll['employee_id'];
        $employee = Csv::indexById('employees')[$employeeId] ?? null;
        $department = $employee ? (Csv::indexById('departments', true)[(int) $employee['department_id']] ?? null) : null;
        $position = $employee ? (Csv::indexById('positions', true)[(int) $employee['position_id']] ?? null) : null;

        $gross = self::toCents($payroll['gross_pay']);
        $net = self::toCents($payroll['net_pay']);

        return [
            'payroll_id'       => $payrollId,
            'employee_id'      => $employeeId,
            'employee_name'    => $employee['full_name'] ?? 'Unknown employee',
            'email'            => $employee['email'] ?? null,
            'department'       => $department['name'] ?? null,
            'position'         => $position['title'] ?? null,
            'pay_period_start' => $payroll['pay_period_start'],
            'pay_period_end'   => $payroll['pay_period_end'],
            'gross_pay'        => self::fromCents($gross),
            'deductions'       => self::fromCents($gross - $net), // CSV has no deductions column
            'net_pay'          => self::fromCents($net),
        ];
    }

    private static function toCents(?string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private static function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }
}

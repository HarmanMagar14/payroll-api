# Payroll API — PHP on AWS Lambda with Bref

Reads employee and payroll CSVs (no database) and exposes a JSON API, plus a
PDF payslip generator that uploads to S3.

| Method | Endpoint | Description |
|---|---|---|
| GET  | `/api/employees` | Employee records (with department and position names) |
| GET  | `/api/reports/payroll` | Totals: records, gross pay, deductions, net pay |
| GET  | `/api/payslips/{payroll_id}` | One payslip as JSON |
| POST | `/api/payslips/{payroll_id}/generate` | Builds a PDF, uploads to `s3://<bucket>/payslips/{id}.pdf` |

Dataset: https://databasesample.com/datasets/employees-and-payroll (CC0).
The payroll CSV has no deductions column, so **deductions = gross_pay − net_pay**.

## Project layout

```
public/index.php        router + JSON responses (Lambda entrypoint)
src/Csv.php             CSV reader (cached per warm container)
src/PayrollService.php  employees, report, payslip logic
src/PayslipPdf.php      Dompdf rendering
src/S3Uploader.php      AWS SDK S3 upload
data/*.csv              employees, payroll, departments, positions
serverless.yml          Bref / Lambda / API Gateway / IAM config
```

## Setup

Requirements: PHP 8.2+, Composer, Node.js, and the Serverless CLI from the
Bref setup guide (https://bref.sh/docs/setup).

```bash
composer install
./scripts/download-data.sh          # or download the 4 CSVs into data/ manually
```

Edit `serverless.yml`: set `custom.payslipBucket` to your bucket name and
`provider.region` to the bucket's region.

## Deploy

```bash
serverless deploy
```

The command prints the API base URL, e.g. `https://abc123.execute-api.us-east-1.amazonaws.com`.

## Test

```bash
BASE=https://YOUR-API-ID.execute-api.us-east-1.amazonaws.com
curl $BASE/api/employees
curl $BASE/api/reports/payroll
curl $BASE/api/payslips/1
curl -X POST $BASE/api/payslips/1/generate
```

Then open the bucket in the S3 console → `payslips/1.pdf`.

## IAM

The Lambda execution role only gets `s3:PutObject` on
`arn:aws:s3:::<bucket>/payslips/*` (see `provider.iam` in `serverless.yml`).

## Logs

```bash
serverless logs -f api --tail
```

#!/usr/bin/env bash
# Downloads the CSV files from databasesample.com into ./data
set -e
BASE="https://database-sample-website.s3.eu-north-1.amazonaws.com/datasets/employees-and-payroll/v3/csv"
DIR="$(cd "$(dirname "$0")/.." && pwd)/data"
mkdir -p "$DIR"
cd "$DIR"
for f in employees payroll; do
  curl -fsSLO "$BASE/$f.csv"
  echo "Downloaded $f.csv"
done

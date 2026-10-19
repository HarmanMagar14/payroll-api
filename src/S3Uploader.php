<?php

declare(strict_types=1);

namespace App;

use Aws\S3\S3Client;
use RuntimeException;

/** Uploads files to the payslip bucket. Credentials come from the Lambda execution role. */
final class S3Uploader
{
    private S3Client $client;
    private string $bucket;
    private string $region;

    public function __construct()
    {
        $bucket = getenv('PAYSLIP_BUCKET');
        if (!$bucket) {
            throw new RuntimeException('PAYSLIP_BUCKET environment variable is not set');
        }
        $this->bucket = $bucket;
        $this->region = getenv('AWS_REGION') ?: 'us-east-1';
        $this->client = new S3Client(['region' => $this->region, 'version' => 'latest']);
    }

    /** @return array{key: string, url: string} */
    public function putPdf(string $key, string $pdfBytes): array
    {
        $this->client->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'Body'        => $pdfBytes,
            'ContentType' => 'application/pdf',
        ]);

        return [
            'key' => $key,
            'url' => "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$key}",
        ];
    }
}

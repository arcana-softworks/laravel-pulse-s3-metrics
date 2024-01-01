<?php

namespace Arcana\PulseS3Metrics\Recorders;

use Arcana\PulseS3Metrics\Events\S3MetricsRequested;
use Illuminate\Contracts\Config\Repository;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Pulse;

class S3Metrics
{
    /**
     * The events to listen for.
     *
     * @var list<class-string>
     */
    public array $listen = [
        S3MetricsRequested::class,
        SharedBeat::class,
    ];

    public function __construct(
        protected Pulse $pulse,
        protected Repository $config
    ) {
        //
    }

    public function record(SharedBeat|S3MetricsRequested $event): void
    {
        // Run every hour when running in worker mode.
        // The S3 metrics are updated on CloudWatch daily, so we can relax.
        if ($event instanceof SharedBeat &&
            $event->time->minute !== 0 &&
            $event->time->second !== 0
        ) {
            return;
        }

        // Configure and instantiate the CloudWatch client.
        $cloudWatch = new \Aws\CloudWatch\CloudWatchClient([
            'version' => 'latest',
            'region' => config('pulse-s3-metrics.region'),
            'credentials' => [
                'key' => config('pulse-s3-metrics.key'),
                'secret' => config('pulse-s3-metrics.secret'),
            ],
        ]);

        // Create a slugged named for the bucket.
        $slug = sprintf('%s.%s', config('pulse-s3-metrics.bucket'), config('pulse-s3-metrics.class'));

        // Get the bucket size in bytes from CloudWatch.
        // By default, the last 14 days are stored.
        $result = $cloudWatch->getMetricStatistics([
            'Namespace' => 'AWS/S3',
            'MetricName' => 'BucketSizeBytes',
            'Dimensions' => [
                [
                    'Name' => 'BucketName',
                    'Value' => config('pulse-s3-metrics.bucket'),
                ],
                [
                    'Name' => 'StorageType',
                    'Value' => config('pulse-s3-metrics.class'),
                ],
            ],
            'StartTime' => strtotime('-14 days midnight UTC'),
            'EndTime' => strtotime('tomorrow midnight UTC'),
            'Period' => 86400,
            'Statistics' => ['Average'],
        ]);

        // Convert the CloudWatch data into a normalized collection of metrics.
        $bytes = collect($result['Datapoints'])
            ->mapWithKeys(fn ($datapoint) => [$datapoint['Timestamp']->format('U') => $datapoint['Average']])
            ->sortKeys()
            ->each(fn ($value, $timestamp) => $this->recordBytesUsedForBucket($slug, $timestamp, $value));

        // Get the number of objects in the bucket from CloudWatch.
        // By default, the last 14 days are stored.
        $result = $cloudWatch->getMetricStatistics([
            'Namespace' => 'AWS/S3',
            'MetricName' => 'NumberOfObjects',
            'Dimensions' => [
                [
                    'Name' => 'BucketName',
                    'Value' => config('pulse-s3-metrics.bucket'),
                ],
                [
                    'Name' => 'StorageType',
                    'Value' => 'AllStorageTypes',
                ],
            ],
            'StartTime' => strtotime('-14 days midnight UTC'),
            'EndTime' => strtotime('tomorrow midnight UTC'),
            'Period' => 86400,
            'Statistics' => ['Average'],
        ]);

        // Convert the CloudWatch data into a normalized collection of metrics.
        $objects = collect($result['Datapoints'])
            ->mapWithKeys(fn ($datapoint) => [$datapoint['Timestamp']->format('U') => $datapoint['Average']])
            ->sortKeys()
            ->each(fn ($value, $timestamp) => $this->recordObjectsForBucket($slug, $timestamp, $value));

        $this->pulse->set('s3_bucket', $slug, $values = json_encode([
            'name' => config('pulse-s3-metrics.bucket'),
            'storage_class' => config('pulse-s3-metrics.class'),
            'size_current' => (int) ($bytes->filter()->last() ?? 0),
            'size_peak' => (int) ($bytes->max() ?? 0),
            'objects_current' => (int) ($objects->filter()->last() ?? 0),
            'objects_peak' => (int) ($objects->max() ?? 0),
        ], flags: JSON_THROW_ON_ERROR));
    }

    private function recordBytesUsedForBucket($slug, $timestamp, $value): void
    {
        $this->pulse->record(
            type: 's3_bytes',
            key: $slug,
            value: $value,
            timestamp: $timestamp,
        )->max()->onlyBuckets();
    }

    private function recordObjectsForBucket($slug, $timestamp, $value): void
    {
        $this->pulse->record(
            type: 's3_objects',
            key: $slug,
            value: $value,
            timestamp: $timestamp,
        )->max()->onlyBuckets();
    }
}

<?php

namespace Arcana\PulseS3Metrics\Livewire;

use Arcana\PulseS3Metrics\Events\S3MetricsRequested;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;
use Livewire\Livewire;

#[Lazy]
class PulseS3Metrics extends Card
{
    public function render(): Renderable
    {
        [$buckets, $time, $runAt] = $this->remember(ttl: 300, query: function () {
            // Request a refresh of the metrics.
            S3MetricsRequested::dispatch();

            // Fetch the metrics from the database, organized in buckets.
            $graphs = $this->graph(['s3_bytes', 's3_objects'], 'max');

            return $this->values('s3_bucket')
                ->map(function ($bucket, $slug) use ($graphs) {
                    $values = json_decode($bucket->value, flags: JSON_THROW_ON_ERROR);

                    return (object) [
                        'bucket' => (string) str($slug)->beforeLast('.'),
                        'size_current' => (int) ($values->size_current ?? 0),
                        'size_peak' => (int) ($values->size_peak ?? 0),
                        'size' => $graphs->get($slug)?->get('s3_bytes')->filter() ?? collect(),
                        'objects_current' => (int) ($values->objects_current ?? 0),
                        'objects_peak' => (int) ($values->objects_peak ?? 0),
                        'objects' => $graphs->get($slug)?->get('s3_objects')->filter() ?? collect(),
                        'updated_at' => $updatedAt = CarbonImmutable::createFromTimestamp($bucket->timestamp),
                        'recently_reported' => $updatedAt->isAfter(now()->startOfDay()->subDay()),
                    ];
            });
        });

        if (Livewire::isLivewireRequest()) {
            $this->dispatch('s3-metrics-chart-update', buckets: $buckets);
        }

        return View::make('pulse-s3-metrics::livewire.pulse-s3-metrics', [
            'buckets' => $buckets,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}

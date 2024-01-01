<?php

use Arcana\PulseS3Metrics\Events\S3MetricsRequested;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Facades\Pulse;

it('records cloudwatch metrics via SharedBeat event', function () {
    Date::setTestNow(now()->startOfDay());
    event(app(SharedBeat::class));
    Pulse::ingest();

    $entries = Pulse::ignore(fn () => DB::table('pulse_values')->get());

    expect($entries)->toHaveCount(1);

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereIn('type', ['s3_bytes', 's3_objects'])->orderBy('period')->get());

    expect($aggregates)->not()->toBeEmpty();
});

it('records cloudwatch metrics via S3MetricsRequested event', function () {
    event(app(S3MetricsRequested::class));
    Pulse::ingest();

    $entries = Pulse::ignore(fn () => DB::table('pulse_values')->get());

    expect($entries)->toHaveCount(1);

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereIn('type', ['s3_bytes', 's3_objects'])->orderBy('period')->get());

    expect($aggregates)->not()->toBeEmpty();
});

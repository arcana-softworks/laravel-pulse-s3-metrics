<?php

use Arcana\PulseS3Metrics\Livewire\PulseS3Metrics;
use function Pest\Livewire\livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(PulseS3Metrics::class);
});

it('exists', function () {
    livewire(PulseS3Metrics::class, ['lazy' => false])
        ->assertSee('S3');
});

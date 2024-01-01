<?php

use Arcana\PulseS3Metrics\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;

/*
 * Some (most) of these snippets are from the Laravel Pulse Pest helper.
 * @see https://github.com/laravel/pulse/blob/32fb030ebf9679a30c373cada2905568d3cf818e/tests/Pest.php
 */

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)
    ->beforeEach(function () {
        Model::unguard();
        Http::preventStrayRequests();
        Pulse::flush();
        Pulse::handleExceptionsUsing(fn (Throwable $e) => throw $e);
        Gate::define('viewPulse', fn ($user = null) => true);
        Config::set('pulse.ingest.trim.lottery', [1, 1]);
    })
    ->afterEach(function () {
        Str::createUuidsNormally();

        if (Pulse::wantsIngesting()) {
            throw new RuntimeException('There are pending entries.');
        }
    })
    ->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toContainAggregateForAllPeriods', function (string|array $type, string $aggregate, string $key, int $value, ?int $count = null, ?int $timestamp = null) {
    $this->toBeInstanceOf(Collection::class);

    $values = $this->value->each(function (stdClass $value) {
        unset($value->id);
    });

    $types = (array) $type;
    $timestamp ??= now()->timestamp;

    $periods = collect([60, 360, 1440, 10080]);

    foreach ($types as $type) {
        foreach ($periods as $period) {
            $record = (object) [
                'bucket' => (int) (floor($timestamp / $period) * $period),
                'period' => $period,
                'type' => $type,
                'aggregate' => $aggregate,
                'key' => $key,
                'key_hash' => keyHash($key),
                'value' => $value,
                'count' => $count,
            ];

            Assert::assertContainsEquals($record, $this->value);
        }
    }

    return $this;
});

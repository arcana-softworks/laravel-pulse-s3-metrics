@php
    $friendlySize = function(int $mb, int $precision = 0) {
        if ($mb >= 1000 * 1000 * 1000) {
            return round($mb / 1000 / 1000 / 1000, $precision) . 'GB';
        }
        if ($mb >= 1000 * 1000) {
            return round($mb / 1000 / 1000, $precision) . 'MB';
        }
        if ($mb >= 1000) {
            return round($mb / 1000, $precision) . 'KB';
        }
        return round($mb, $precision) . 'B';
    };

    $cols = ! empty($cols) ? $cols : 'full';
    $rows = ! empty($rows) ? $rows : 1;
@endphp

<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="S3 Usage">
        <x-slot:icon>
            <x-pulse::icons.circle-stack />
        </x-slot:icon>
    </x-pulse::card-header>


    @if ($buckets->isEmpty())
        <x-pulse::no-results />
    @else
        <div class="grid grid-cols-[max-content,minmax(max-content,1fr),max-content,minmax(min-content,2fr),max-content,minmax(min-content,2fr),minmax(max-content,1fr)]">
            <div></div>
            <div></div>
            <div class="text-xs uppercase text-left text-gray-500 dark:text-gray-400 font-bold">Size</div>
            <div></div>
            <div class="text-xs uppercase text-left text-gray-500 dark:text-gray-400 font-bold">Objects</div>
            <div></div>
            <div></div>
            @foreach ($buckets as $slug => $bucket)
                <div wire:key="{{ $slug }}-indicator" class="flex items-center {{ $buckets->count() > 1 ? 'py-2' : '' }}" title="{{ $bucket->updated_at->fromNow() }}">
                    @if ($bucket->recently_reported)
                        <div class="w-5 flex justify-center mr-1">
                            <div class="h-1 w-1 bg-green-500 rounded-full animate-pulse"></div>
                        </div>
                    @else
                        <x-pulse::icons.signal-slash class="w-5 h-5 stroke-red-500 mr-1" />
                    @endif
                </div>
                <div wire:key="{{ $slug }}-name" class="flex items-center {{ $buckets->count() > 1 ? 'py-2' : '' }} {{ ! $bucket->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                    <x-pulse::icons.circle-stack class="w-6 h-6 mr-2 stroke-gray-500 dark:stroke-gray-400" />
                    <span class="text-base font-bold text-gray-600 dark:text-gray-300" title="Time: {{ number_format($time) }}ms; Run at: {{ $runAt }};">{{ $bucket->bucket }}</span>
                </div>
                <div wire:key="{{ $slug }}-size" class="flex items-center {{ $buckets->count() > 1 ? 'py-2' : '' }} {{ ! $bucket->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                    <div class="w-36 flex-shrink-0 whitespace-nowrap tabular-nums">
                        <span class="text-xl font-bold text-gray-700 dark:text-gray-200">
                            {{ $friendlySize($bucket->size_current, 1) }}
                        </span>
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400" title="Peak bucket size recorded">
                            / {{ $friendlySize($bucket->size_peak, 1) }}
                        </span>
                    </div>
                </div>
                <div wire:key="{{ $slug }}-size-graph" class="w-full flex items-center pr-8 xl:pr-12 {{ $buckets->count() > 1 ? 'py-2' : '' }} {{ ! $bucket->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                    <div
                        wire:ignore
                        class="w-full min-w-[5rem] max-w-xs h-9 relative"
                        x-data="s3BytesChart({
                            slug: '{{ $slug }}',
                            labels: @js($bucket->size->keys()),
                            data: @js($bucket->size->values()),
                            total: @js($bucket->size_peak),
                        })"
                    >
                        <canvas x-ref="canvas" class="w-full ring-1 ring-gray-900/5 bg-white dark:bg-gray-900 rounded-md shadow-sm"></canvas>
                    </div>
                </div>
                <div wire:key="{{ $slug }}-objects" class="flex items-center {{ $buckets->count() > 1 ? 'py-2' : '' }} {{ ! $bucket->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                    <div class="w-36 flex-shrink-0 whitespace-nowrap tabular-nums">
                        <span class="text-xl font-bold text-gray-700 dark:text-gray-200">
                            {{ $bucket->objects_current }}
                        </span>
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400" title="Peak number of objects recorded">
                            / {{ $bucket->objects_peak }}
                        </span>
                    </div>
                </div>
                <div wire:key="{{ $slug }}-objects-graph" class="w-full flex items-center {{ $buckets->count() > 1 ? 'py-2' : '' }} {{ ! $bucket->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                    <div
                        wire:ignore
                        class="w-full min-w-[5rem] max-w-xs h-9 relative"
                        x-data="s3ObjectsChart({
                            slug: '{{ $slug }}',
                            labels: @js($bucket->objects->keys()),
                            data: @js($bucket->objects->values()),
                            total: @js($bucket->objects_peak),
                        })"
                    >
                        <canvas x-ref="canvas" class="w-full ring-1 ring-gray-900/5 bg-white dark:bg-gray-900 rounded-md shadow-sm"></canvas>
                    </div>
                </div>
                <div></div>
            @endforeach
        </div>
    @endif
</x-pulse::card>


@script
<script>
    Alpine.data('s3BytesChart', (config) => ({
        init() {
            function friendlySize(mb, precision = 0) {
                if (mb >= 1000 * 1000 * 1000) {
                    return (mb / 1000 / 1000 / 1000).toFixed(precision) + 'GB';
                }
                if (mb >= 1000 * 1000) {
                    return (mb / 1000 / 1000).toFixed(precision) + 'MB';
                }
                if (mb >= 1000) {
                    return (mb / 1000).toFixed(precision) + 'KB';
                }
                return mb.toFixed(precision) + 'B';
            }

            let chart = new Chart(
                this.$refs.canvas,
                {
                    type: 'line',
                    data: {
                        labels: config.labels,
                        datasets: [
                            {
                                label: 'Bucket Size',
                                borderColor: '#9333ea',
                                borderWidth: 2,
                                borderCapStyle: 'round',
                                data: config.data,
                                pointHitRadius: 10,
                                pointStyle: false,
                                tension: 0.2,
                                spanGaps: false,
                            },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        layout: {
                            autoPadding: false,
                        },
                        scales: {
                            x: {
                                display: false,
                                grid: {
                                    display: false,
                                },
                            },
                            y: {
                                display: false,
                                min: 0,
                                max: config.total,
                                grid: {
                                    display: false,
                                },
                            },
                        },
                        plugins: {
                            legend: {
                                display: false,
                            },
                            tooltip: {
                                mode: 'index',
                                position: 'nearest',
                                intersect: false,
                                callbacks: {
                                    title: () => '',
                                    label: (context) => `${context.label.substring(0, context.label.indexOf(' '))} - ${friendlySize(context.parsed.y, 2)}`
                                },
                                displayColors: false,
                            },
                        },
                    },
                }
            )

            Livewire.on('s3-metrics-chart-update', ({ buckets }) => {
                if (chart === undefined) {
                    return
                }

                if (buckets[config.slug] === undefined && chart) {
                    chart.destroy()
                    chart = undefined
                    return
                }

                chart.data.labels = Object.keys(buckets[config.slug].size)
                chart.data.datasets[0].data = Object.values(buckets[config.slug].size)
                chart.update()
            })
        }
    }))

    Alpine.data('s3ObjectsChart', (config) => ({
        init() {
            let chart = new Chart(
                this.$refs.canvas,
                {
                    type: 'line',
                    data: {
                        labels: config.labels,
                        datasets: [
                            {
                                label: 'Objects in Bucket',
                                borderColor: '#9333ea',
                                borderWidth: 2,
                                borderCapStyle: 'round',
                                data: config.data,
                                pointHitRadius: 10,
                                pointStyle: false,
                                tension: 0.2,
                                spanGaps: false,
                            },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        layout: {
                            autoPadding: false,
                        },
                        scales: {
                            x: {
                                display: false,
                                grid: {
                                    display: false,
                                },
                            },
                            y: {
                                display: false,
                                min: 0,
                                max: config.total,
                                grid: {
                                    display: false,
                                },
                            },
                        },
                        plugins: {
                            legend: {
                                display: false,
                            },
                            tooltip: {
                                mode: 'index',
                                position: 'nearest',
                                intersect: false,
                                callbacks: {
                                    title: () => '',
                                    label: (context) => `${context.label.substring(0, context.label.indexOf(' '))} - ${context.formattedValue}`
                                },
                                displayColors: false,
                            },
                        },
                    },
                }
            )

            Livewire.on('s3-metrics-chart-update', ({ buckets }) => {
                if (chart === undefined) {
                    return
                }

                if (buckets[config.slug] === undefined && chart) {
                    chart.destroy()
                    chart = undefined
                    return
                }

                chart.data.labels = Object.keys(buckets[config.slug].objects)
                chart.data.datasets[0].data = Object.values(buckets[config.slug].objects)
                chart.update()
            })
        }
    }))
</script>
@endscript

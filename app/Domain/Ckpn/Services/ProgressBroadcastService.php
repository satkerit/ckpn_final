<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use Livewire\Livewire;

class ProgressBroadcastService
{
    /**
     * Start progress bar dengan title & initial details.
     * Panggil ini sebelum mulai proses lama.
     */
    public static function start(string $title, array $initialDetails = []): void
    {
        Livewire::dispatch('progress:start', title: $title, initialDetails: $initialDetails);
    }

    /**
     * Update progress bar: percentage, status text, info + detail metrics.
     * Panggil ini di dalam loop batch processing.
     */
    public static function update(int $percentage, string $statusText = '', string $infoText = '', array $details = []): void
    {
        Livewire::dispatch('progress:update', percentage: $percentage, statusText: $statusText, infoText: $infoText, details: $details);
    }

    /**
     * Mark proses selesai sukses.
     */
    public static function complete(string $message = 'Selesai'): void
    {
        Livewire::dispatch('progress:complete', message: $message);
    }

    /**
     * Mark proses gagal dengan error message.
     */
    public static function error(string $message): void
    {
        Livewire::dispatch('progress:error', message: $message);
    }

    /**
     * Close progress bar.
     */
    public static function close(): void
    {
        Livewire::dispatch('progress:close');
    }

    /**
     * Helper: update percentage dengan format "X / Y baris" + custom details.
     * Gunakan di dalam loop batch insert.
     */
    public static function updateWithCounter(int $current, int $total, string $statusText = '', array $details = []): void
    {
        $percentage = $total > 0 ? (int) (($current / $total) * 100) : 0;
        $infoText = $current . ' / ' . $total . ' baris';
        $mergedDetails = array_merge(['items_processed' => $current, 'total_items' => $total], $details);
        self::update($percentage, $statusText, $infoText, $mergedDetails);
    }

    /**
     * Shorthand: update dengan detail metrics (processed, failed, skipped, etc).
     */
    public static function updateWithMetrics(int $percentage, array $metrics = [], string $statusText = ''): void
    {
        self::update($percentage, $statusText, '', $metrics);
    }
}

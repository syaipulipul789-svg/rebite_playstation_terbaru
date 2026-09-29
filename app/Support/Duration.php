<?php

namespace App\Support;

final class Duration
{
    /**
     * 125 -> "02:05"
     */
    public static function toClock(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * 3725 -> "01:02:05"
     */
    public static function toHms(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60,
        );
    }

    /**
     * 90 -> "1 jam 30 menit"
     */
    public static function humanize(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours === 0) {
            return $remaining.' menit';
        }

        if ($remaining === 0) {
            return $hours.' jam';
        }

        return "{$hours} jam {$remaining} menit";
    }
}

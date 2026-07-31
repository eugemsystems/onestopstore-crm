<?php

namespace App\Helpers;

class OrderStatusColors
{
    /**
     * Canonical map of order/item status (lowercased) to HEX color.
     */
    public static function map(): array
    {
        return [
            'pending'               => '#ffc107', // amber
            'processing'            => '#0d6efd', // blue
            'shipped'               => '#6610f2', // purple
            'in transit to zim'     => '#6f42c1', // indigo
            'out for delivery'      => '#fd7e14', // orange
            'delivered'             => '#198754', // green
            'ready for collection'  => '#20c997', // teal
            'cancelled'             => '#dc3545', // red
            'canceled'              => '#dc3545', // red alias
            'stuck'                 => '#d63384', // pink
            'warehouse packing'     => '#6c757d', // gray
            'dropped at the deport' => '#343a40', // dark
            'from supplier'         => '#0dcaf0', // cyan
            'out of stock'          => '#dc3545', // red for OOS
        ];
    }

    public static function normalize(?string $name): string
    {
        return strtolower(trim((string) $name));
    }

    /**
     * Get HEX color for a given status string. Falls back to neutral gray.
     */
    public static function hex(?string $status): string
    {
        $key = self::normalize($status);
        return self::map()[$key] ?? '#adb5bd';
    }

    /**
     * Compute a readable text color (#000 or #fff) for a background HEX color.
     */
    public static function textColor(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) {
            return '#fff';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        return $yiq >= 128 ? '#000' : '#fff';
    }
}

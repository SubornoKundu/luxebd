<?php

namespace App\Services;

/**
 * Display name for the courier saved on an order (shown to the customer and
 * the admin). No booking happens here: delivery is arranged by the shop.
 */
class CourierService
{
    public static function label(?string $courier): ?string
    {
        return $courier !== null && trim($courier) !== '' ? trim($courier) : null;
    }
}

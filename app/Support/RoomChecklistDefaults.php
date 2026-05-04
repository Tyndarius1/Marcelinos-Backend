<?php

namespace App\Support;

final class RoomChecklistDefaults
{
    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return [
            'Door lock / key',
            'Windows / curtains',
            'Lights / switches',
            'Aircon / remote',
            'TV / remote',
            'Wi‑Fi info card (if applicable)',
            'Bed / mattress condition',
            'Bedsheets / pillowcases',
            'Pillows',
            'Blanket / comforter',
            'Towels (bath / hand)',
            'Toiletries (soap, shampoo, tissue)',
            'Bathroom cleanliness',
            'Shower / faucet working',
            'Toilet flush working',
            'Mirror',
            'Trash bin',
            'Table / chairs',
            'Cabinet / hangers',
            'Overall room cleanliness',
        ];
    }

    /**
     * @return array<string, float>
     */
    public static function prices(): array
    {
        return [
            'Door lock / key' => 1000.00,
            'Lights / switches' => 2000.00,
            'Aircon / remote' => 500.00,
            'TV / remote' => 25000.00,
            'Bedsheets / pillowcases' => 500.00,
            'Blanket / comforter' => 500.00,
            'Towels (bath / hand)' => 500.00,
            'Smoking violation' => 5000.00,
            'Cups and Glass' => 100.00,
            'Slippers' => 100.00,
        ];
    }
}


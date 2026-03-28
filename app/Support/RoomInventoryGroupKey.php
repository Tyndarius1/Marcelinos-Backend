<?php

namespace App\Support;

use App\Models\Room;

/**
 * Mirrors frontend {@see roomInventoryGroupKey} / {@see bedSpecificationLine} in fe.
 */
final class RoomInventoryGroupKey
{
    public static function forRoom(Room $room): string
    {
        $room->loadMissing(['bedSpecifications', 'bedModifiers']);

        $bedLine = self::bedSpecificationLine($room);
        if ($bedLine !== null) {
            return 'spec:'.$bedLine;
        }

        $desc = trim((string) $room->description);

        return 'desc:'.($desc !== '' ? $desc : '__default__');
    }

    public static function bedSpecificationLine(Room $room): ?string
    {
        $specs = $room->bedSpecifications->pluck('specification')->filter()->values()->all();
        if ($specs === []) {
            return null;
        }
        $base = implode(', ', $specs);
        if ($base === '') {
            return null;
        }
        $mods = $room->bedModifiers->pluck('modifier')->filter()->values()->all();
        if ($mods !== []) {
            return $base.' ('.implode(', ', $mods).')';
        }

        return $base;
    }
}

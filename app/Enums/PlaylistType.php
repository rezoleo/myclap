<?php

namespace App\Enums;

enum PlaylistType: int
{
    case CLASSIC = 0;
    case BROADCAST = 1;

    public function label(): string
    {
        return match ($this) {
            self::CLASSIC => 'Classique',
            self::BROADCAST => 'Diffusion',
        };
    }

    public static function options(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}

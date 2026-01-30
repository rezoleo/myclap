<?php

namespace App\Enums;

enum ContentAccess: int
{
    case PRIVATE = 0;
    case UNLINKED = 1;
    case CENTRALIENS = 2;
    case PUBLIC = 3;

    public function label(): string
    {
        return match ($this) {
            self::PRIVATE => 'Privée',
            self::UNLINKED => 'Non répertoriée',
            self::CENTRALIENS => 'Centraliens',
            self::PUBLIC => 'Public',
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

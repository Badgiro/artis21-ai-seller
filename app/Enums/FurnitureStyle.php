<?php

namespace App\Enums;

enum FurnitureStyle: string
{
    case Modern = 'modern';
    case Classic = 'classic';
    case Loft = 'loft';
    case Minimalism = 'minimalism';

    public function label(): string
    {
        return match ($this) {
            self::Modern => 'Современный',
            self::Classic => 'Классика',
            self::Loft => 'Лофт',
            self::Minimalism => 'Минимализм',
        };
    }
}

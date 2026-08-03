<?php

namespace App\Enums;

enum FurnitureStyle: string
{
    case Modern = 'modern';
    case Classic = 'classic';
    case Loft = 'loft';
    case Minimalism = 'minimalism';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $style) {
            $options[$style->value] = $style->label();
        }

        return $options;
    }

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

<?php

namespace App\Enums;

enum FurnitureType: string
{
    case Kitchen = 'kitchen';
    case Wardrobe = 'wardrobe';
    case Sofa = 'sofa';
    case Bed = 'bed';

    public function label(): string
    {
        return match ($this) {
            self::Kitchen => 'Кухня',
            self::Wardrobe => 'Шкаф',
            self::Sofa => 'Диван',
            self::Bed => 'Кровать',
        };
    }
}

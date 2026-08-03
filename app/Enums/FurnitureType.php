<?php

namespace App\Enums;

enum FurnitureType: string
{
    case Kitchen = 'kitchen';
    case Wardrobe = 'wardrobe';
    case Sofa = 'sofa';
    case Bed = 'bed';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }

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

<?php

namespace Database\Seeders;

use App\Enums\FurnitureStyle;
use App\Enums\FurnitureType;
use App\Models\Furniture;
use Illuminate\Database\Seeder;

class FurnitureSeeder extends Seeder
{
    /**
     * Seed the furniture catalog.
     */
    public function run(): void
    {
        $furnitureItems = [
            [
                'name' => 'Кухня «Лира»',
                'type' => FurnitureType::Kitchen,
                'price' => '89900.00',
                'room_size' => 'кухня, гарнитур 240 см',
                'style' => FurnitureStyle::Modern,
            ],
            [
                'name' => 'Кухня «Гранада»',
                'type' => FurnitureType::Kitchen,
                'price' => '149900.00',
                'room_size' => 'кухня, гарнитур 300 см',
                'style' => FurnitureStyle::Classic,
            ],
            [
                'name' => 'Кухня «Бруклин»',
                'type' => FurnitureType::Kitchen,
                'price' => '219900.00',
                'room_size' => 'кухня-гостиная, угловая 320×180 см',
                'style' => FurnitureStyle::Loft,
            ],
            [
                'name' => 'Кухня «Норд»',
                'type' => FurnitureType::Kitchen,
                'price' => '119900.00',
                'room_size' => 'кухня, гарнитур 260 см',
                'style' => FurnitureStyle::Minimalism,
            ],
            [
                'name' => 'Шкаф «Лозанна 2-25»',
                'type' => FurnitureType::Wardrobe,
                'price' => '54900.00',
                'room_size' => 'спальня, 180×60×220 см',
                'style' => FurnitureStyle::Modern,
            ],
            [
                'name' => 'Шкаф «Гранада 1-28»',
                'type' => FurnitureType::Wardrobe,
                'price' => '79900.00',
                'room_size' => 'спальня, 220×60×240 см',
                'style' => FurnitureStyle::Classic,
            ],
            [
                'name' => 'Шкаф «Форест»',
                'type' => FurnitureType::Wardrobe,
                'price' => '109900.00',
                'room_size' => 'прихожая, 240×65×250 см',
                'style' => FurnitureStyle::Loft,
            ],
            [
                'name' => 'Шкаф «Лайн»',
                'type' => FurnitureType::Wardrobe,
                'price' => '64900.00',
                'room_size' => 'детская, 160×55×210 см',
                'style' => FurnitureStyle::Minimalism,
            ],
            [
                'name' => 'Диван «Милан»',
                'type' => FurnitureType::Sofa,
                'price' => '59900.00',
                'room_size' => 'гостиная, 220×95 см',
                'style' => FurnitureStyle::Modern,
            ],
            [
                'name' => 'Диван «Честер»',
                'type' => FurnitureType::Sofa,
                'price' => '129900.00',
                'room_size' => 'гостиная, 250×100 см',
                'style' => FurnitureStyle::Classic,
            ],
            [
                'name' => 'Диван «Манхэттен»',
                'type' => FurnitureType::Sofa,
                'price' => '94900.00',
                'room_size' => 'кухня-гостиная, угловой 270×170 см',
                'style' => FurnitureStyle::Loft,
            ],
            [
                'name' => 'Диван «Софт»',
                'type' => FurnitureType::Sofa,
                'price' => '74900.00',
                'room_size' => 'гостиная, 230×90 см',
                'style' => FurnitureStyle::Minimalism,
            ],
            [
                'name' => 'Кровать «Верона»',
                'type' => FurnitureType::Bed,
                'price' => '49900.00',
                'room_size' => 'спальня, матрас 160×200 см',
                'style' => FurnitureStyle::Classic,
            ],
            [
                'name' => 'Кровать «Осло»',
                'type' => FurnitureType::Bed,
                'price' => '69900.00',
                'room_size' => 'спальня, матрас 180×200 см',
                'style' => FurnitureStyle::Modern,
            ],
            [
                'name' => 'Кровать «Нова»',
                'type' => FurnitureType::Bed,
                'price' => '89900.00',
                'room_size' => 'спальня, матрас 180×200 см',
                'style' => FurnitureStyle::Minimalism,
            ],
        ];

        foreach ($furnitureItems as $item) {
            Furniture::query()->updateOrCreate(
                ['name' => $item['name']],
                $item,
            );
        }
    }
}

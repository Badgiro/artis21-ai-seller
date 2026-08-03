<?php

namespace App\Models;

use App\Enums\FurnitureStyle;
use App\Enums\FurnitureType;
use Illuminate\Database\Eloquent\Model;

class Furniture extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'furnitures';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'price',
        'room_size',
        'style',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FurnitureType::class,
            'style' => FurnitureStyle::class,
            'price' => 'decimal:2',
        ];
    }
}

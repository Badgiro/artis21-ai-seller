<?php

namespace App\Enums;

enum ConversationStage: string
{
    case New = 'new';
    case Qualification = 'qualification';
    case Checkout = 'checkout';
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новый диалог',
            self::Qualification => 'Квалификация',
            self::Checkout => 'Оформление',
            self::Submitted => 'Заявка отправлена',
        };
    }
}

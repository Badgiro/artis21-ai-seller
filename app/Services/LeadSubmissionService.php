<?php

namespace App\Services;

use App\Enums\FurnitureStyle;
use App\Enums\FurnitureType;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadSubmissionService
{
    public function __construct(
        private readonly PhoneValidator $phoneValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $leadDraft
     *
     * @throws ValidationException
     */
    public function submit(array $leadDraft): Lead
    {
        $validated = Validator::make($leadDraft, [
            'furniture_type' => ['required', Rule::enum(FurnitureType::class)],
            'budget' => ['nullable', 'regex:/^\d+(?:\.\d+)?$/'],
            'room_size' => ['nullable', 'string', 'max:255'],
            'style' => ['nullable', Rule::enum(FurnitureStyle::class)],
            'timeline' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
        ])->validate();

        $phone = $this->phoneValidator->normalize($validated['phone']);
        $name = trim($validated['name']);

        if ($phone === null || $name === '') {
            throw ValidationException::withMessages([
                $phone === null ? 'phone' : 'name' => $phone === null
                    ? 'Укажите корректный номер телефона.'
                    : 'Укажите имя.',
            ]);
        }

        return Lead::query()->create([
            'furniture_type' => $validated['furniture_type'],
            'budget' => $validated['budget'] ?? null,
            'room_size' => $validated['room_size'] ?? null,
            'style' => $validated['style'] ?? null,
            'timeline' => $validated['timeline'] ?? null,
            'city' => $validated['city'] ?? null,
            'name' => $name,
            'phone' => $phone,
            'status' => LeadStatus::New,
            'assigned_user_id' => null,
        ]);
    }
}

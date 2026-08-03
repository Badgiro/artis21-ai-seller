<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Services\LeadSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeadSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeadSubmissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LeadSubmissionService::class);
    }

    public function test_service_creates_lead_with_collected_fields(): void
    {
        $lead = $this->service->submit($this->validDraft());

        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertDatabaseHas(Lead::class, [
            'id' => $lead->getKey(),
            'furniture_type' => 'wardrobe',
            'budget' => '70000.00',
            'room_size' => 'спальня',
            'style' => 'modern',
            'timeline' => 'через месяц',
            'city' => 'Нальчик',
            'name' => 'Иван',
            'phone' => '+79991234567',
        ]);
    }

    public function test_new_lead_has_new_status_and_no_assigned_manager(): void
    {
        $lead = $this->service->submit($this->validDraft());

        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertNull($lead->assigned_user_id);
    }

    public function test_invalid_phone_prevents_submission(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->service->submit($this->validDraft(['phone' => '123']));
        } finally {
            $this->assertDatabaseCount(Lead::class, 0);
        }
    }

    public function test_missing_furniture_type_prevents_submission(): void
    {
        $draft = $this->validDraft();
        unset($draft['furniture_type']);

        $this->expectException(ValidationException::class);

        try {
            $this->service->submit($draft);
        } finally {
            $this->assertDatabaseCount(Lead::class, 0);
        }
    }

    public function test_missing_name_prevents_submission(): void
    {
        $draft = $this->validDraft();
        unset($draft['name']);

        $this->expectException(ValidationException::class);

        try {
            $this->service->submit($draft);
        } finally {
            $this->assertDatabaseCount(Lead::class, 0);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validDraft(array $overrides = []): array
    {
        return [
            'furniture_type' => 'wardrobe',
            'budget' => '70000',
            'room_size' => 'спальня',
            'style' => 'modern',
            'timeline' => 'через месяц',
            'city' => 'Нальчик',
            'name' => 'Иван',
            'phone' => '+7 999 123-45-67',
            ...$overrides,
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Lead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_authenticated_user_can_open_leads_resource(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/leads')->assertOk();
    }

    public function test_lead_can_be_created(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateLead::class)
            ->fillForm([
                'furniture_type' => 'wardrobe',
                'budget' => '70000',
                'room_size' => 'спальня, 200×60×240 см',
                'style' => 'modern',
                'timeline' => 'в течение месяца',
                'city' => 'Москва',
                'name' => 'Иван',
                'phone' => '+7 999 123-45-67',
                'status' => LeadStatus::New->value,
                'assigned_user_id' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Lead::class, [
            'name' => 'Иван',
            'phone' => '+7 999 123-45-67',
            'status' => LeadStatus::New->value,
        ]);
    }

    public function test_lead_belongs_to_assigned_manager(): void
    {
        $manager = User::factory()->create();
        $lead = $this->createLead([
            'assigned_user_id' => $manager->getKey(),
        ]);

        $this->assertTrue($lead->assignedManager->is($manager));
        $this->assertTrue($manager->assignedLeads->contains($lead));
    }

    public function test_assigned_user_id_becomes_null_if_manager_is_deleted(): void
    {
        $manager = User::factory()->create();
        $lead = $this->createLead([
            'assigned_user_id' => $manager->getKey(),
        ]);

        $manager->delete();

        $this->assertNull($lead->refresh()->assigned_user_id);
        $this->assertNull($lead->assignedManager);
    }

    public function test_status_cast_works(): void
    {
        $lead = $this->createLead([
            'status' => LeadStatus::InProgress,
        ]);

        $this->assertInstanceOf(LeadStatus::class, $lead->status);
        $this->assertSame(LeadStatus::InProgress, $lead->status);
    }

    public function test_default_status_is_new(): void
    {
        $lead = $this->createLead();

        $this->assertSame(LeadStatus::New, $lead->refresh()->status);
        $this->assertSame(LeadStatus::New->value, $lead->getRawOriginal('status'));
    }

    public function test_newest_leads_are_ordered_first(): void
    {
        $this->actingAs(User::factory()->create());

        $oldestLead = $this->createLead([
            'name' => 'Старый лид',
            'created_at' => now()->subDays(2),
        ]);
        $newestLead = $this->createLead([
            'name' => 'Новый лид',
            'created_at' => now(),
        ]);
        $middleLead = $this->createLead([
            'name' => 'Средний лид',
            'created_at' => now()->subDay(),
        ]);

        Livewire::test(ListLeads::class)
            ->assertCanSeeTableRecords([
                $newestLead,
                $middleLead,
                $oldestLead,
            ], inOrder: true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createLead(array $attributes = []): Lead
    {
        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $lead = Lead::query()->create([
            'name' => 'Тестовый клиент',
            'phone' => '+7 999 000-00-00',
            ...$attributes,
        ]);

        if ($createdAt !== null) {
            $lead->forceFill(['created_at' => $createdAt])->save();
        }

        return $lead;
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_authenticated_user_can_open_users_resource(): void
    {
        $currentUser = User::factory()->create();

        $this->actingAs($currentUser)
            ->get('/admin/users')
            ->assertOk();
    }

    public function test_can_create_another_user(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Manager',
                'email' => 'manager@example.com',
                'password' => 'manager-password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(User::class, [
            'name' => 'New Manager',
            'email' => 'manager@example.com',
        ]);
    }

    public function test_password_is_hashed(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Hashed Manager',
                'email' => 'hashed@example.com',
                'password' => 'secure-password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()
            ->where('email', 'hashed@example.com')
            ->firstOrFail();

        $this->assertNotSame('secure-password', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('secure-password', $user->getRawOriginal('password')));
    }

    public function test_editing_user_with_empty_password_keeps_old_password(): void
    {
        $this->actingAs(User::factory()->create());

        $user = User::factory()->create([
            'password' => 'old-password',
        ]);
        $oldPasswordHash = $user->getRawOriginal('password');

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Manager',
                'email' => $user->email,
                'password' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($oldPasswordHash, $user->refresh()->getRawOriginal('password'));
    }

    public function test_editing_user_with_new_password_changes_hash(): void
    {
        $this->actingAs(User::factory()->create());

        $user = User::factory()->create([
            'password' => 'old-password',
        ]);
        $oldPasswordHash = $user->getRawOriginal('password');

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'new-password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $newPasswordHash = $user->refresh()->getRawOriginal('password');

        $this->assertNotSame($oldPasswordHash, $newPasswordHash);
        $this->assertTrue(Hash::check('new-password', $newPasswordHash));
    }

    public function test_current_authenticated_user_cannot_delete_themselves(): void
    {
        $currentUser = User::factory()->create();
        $this->actingAs($currentUser);

        $this->assertTrue(Gate::forUser($currentUser)->denies('delete', $currentUser));
        $this->assertFalse(UserResource::canDelete($currentUser));

        Livewire::test(EditUser::class, ['record' => $currentUser->getRouteKey()])
            ->assertActionHidden('delete');

        $this->assertDatabaseHas(User::class, [
            'id' => $currentUser->getKey(),
        ]);
    }

    public function test_bulk_delete_cannot_delete_current_authenticated_user(): void
    {
        $currentUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($currentUser);

        Livewire::test(ListUsers::class)
            ->selectTableRecords([
                $currentUser->getKey(),
                $otherUser->getKey(),
            ])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertDatabaseHas(User::class, [
            'id' => $currentUser->getKey(),
        ]);
        $this->assertDatabaseMissing(User::class, [
            'id' => $otherUser->getKey(),
        ]);
    }
}

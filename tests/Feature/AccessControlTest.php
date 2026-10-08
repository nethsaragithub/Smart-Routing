<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Depot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public static function staffForbiddenPages(): array
    {
        return [
            'create route' => ['/routes/create'],
            'create schedule' => ['/schedules/create'],
            'add bus' => ['/buses/create'],
            'reports' => ['/reports'],
            'users' => ['/users'],
            'depots' => ['/depots'],
        ];
    }

    #[DataProvider('staffForbiddenPages')]
    public function test_operations_staff_cannot_open_management_pages(string $url): void
    {
        $this->actingAs($this->staff())->get($url)->assertForbidden();
    }

    public function test_operations_staff_can_use_daily_operation_pages(): void
    {
        $staff = $this->staff();

        foreach (['/dashboard', '/trips', '/routes', '/fuel/create', '/maintenance/create'] as $url) {
            $this->actingAs($staff)->get($url)->assertOk();
        }
    }

    public function test_supervisor_can_manage_routes_but_not_users(): void
    {
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)->get('/routes/create')->assertOk();
        $this->actingAs($supervisor)->get('/reports')->assertOk();
        $this->actingAs($supervisor)->get('/users')->assertForbidden();
    }

    public function test_users_only_see_records_from_their_own_depot(): void
    {
        $ownBus = $this->bus(['registration_no' => 'NB-1111']);
        $otherBus = Bus::factory()->for(Depot::factory())->create(['registration_no' => 'NB-2222']);

        $staff = $this->staff();

        $this->actingAs($staff)->get('/buses')->assertSee('NB-1111')->assertDontSee('NB-2222');
        $this->actingAs($staff)->get("/buses/{$ownBus->id}")->assertOk();
        $this->actingAs($staff)->get("/buses/{$otherBus->id}")->assertNotFound();
    }

    public function test_administrator_can_switch_depot(): void
    {
        $admin = $this->admin();
        $other = Depot::factory()->create(['name' => 'Galle Depot']);
        Bus::factory()->for($other)->create(['registration_no' => 'NG-9999']);

        $this->actingAs($admin)->post('/depot/switch', ['depot_id' => $other->id])->assertRedirect('/dashboard');
        $this->actingAs($admin)->get('/buses')->assertSee('NG-9999');
    }

    public function test_supervisor_cannot_switch_depot(): void
    {
        $other = Depot::factory()->create();

        $this->actingAs($this->supervisor())->post('/depot/switch', ['depot_id' => $other->id])->assertForbidden();
    }

    public function test_deactivated_user_is_signed_out_on_next_request(): void
    {
        $user = User::factory()->inactive()->for($this->depot())->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}

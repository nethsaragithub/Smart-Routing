<?php

namespace Tests\Unit;

use App\Enums\LicenseStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Support\GeoPoint;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DomainRulesTest extends TestCase
{
    public function test_licence_status_is_derived_from_the_expiry_date(): void
    {
        $today = CarbonImmutable::parse('2026-10-08');

        $this->assertSame(LicenseStatus::Expired, LicenseStatus::fromExpiry($today->subDay(), $today));
        $this->assertSame(LicenseStatus::ExpiringSoon, LicenseStatus::fromExpiry($today->addDays(10), $today));
        $this->assertSame(LicenseStatus::Valid, LicenseStatus::fromExpiry($today->addDays(90), $today));
        $this->assertSame(LicenseStatus::Expired, LicenseStatus::fromExpiry(null, $today));
    }

    public function test_roles_grant_the_expected_permissions(): void
    {
        $this->assertTrue(UserRole::Admin->can(Permission::ManageUsers));
        $this->assertFalse(UserRole::Supervisor->can(Permission::ManageUsers));
        $this->assertFalse(UserRole::Supervisor->can(Permission::ManageSchedules));
        $this->assertFalse(UserRole::Supervisor->can(Permission::ManageFleet));
        $this->assertTrue(UserRole::Supervisor->can(Permission::AssignTrips));
        $this->assertTrue(UserRole::Supervisor->can(Permission::RecordDelays));
        $this->assertFalse(UserRole::Supervisor->can(Permission::OperateTrips));
        $this->assertFalse(UserRole::Supervisor->can(Permission::LogFuelAndMaintenance));
        $this->assertFalse(UserRole::Supervisor->can(Permission::ViewManagementReports));
        $this->assertTrue(UserRole::Staff->can(Permission::OperateTrips));
        $this->assertTrue(UserRole::Staff->can(Permission::LogFuelAndMaintenance));
        $this->assertFalse(UserRole::Staff->can(Permission::AssignTrips));
        $this->assertFalse(UserRole::Staff->can(Permission::ManageFuelAndMaintenance));
        $this->assertFalse(UserRole::Staff->can(Permission::ManageRoutes));
        $this->assertFalse(UserRole::Staff->can(Permission::ViewReports));
        $this->assertTrue(UserRole::Admin->can(Permission::CorrectTrips));
    }

    public function test_distance_between_colombo_and_kandy_is_about_95_km_in_a_straight_line(): void
    {
        $colombo = new GeoPoint(6.9344, 79.8428);
        $kandy = new GeoPoint(7.2906, 80.6337);

        $this->assertEqualsWithDelta(95, $colombo->distanceTo($kandy), 3);
    }

    public function test_report_periods_cover_whole_weeks_and_months(): void
    {
        $day = CarbonImmutable::parse('2026-10-08'); // Thursday

        $week = ReportPeriod::week($day);
        $this->assertSame(['2026-10-05', '2026-10-11'], $week->dateRange());
        $this->assertSame(7, $week->days());

        $month = ReportPeriod::month($day);
        $this->assertSame(['2026-10-01', '2026-10-31'], $month->dateRange());
        $this->assertSame(['2026-09-01', '2026-09-30'], $month->previous()->dateRange());
    }
}

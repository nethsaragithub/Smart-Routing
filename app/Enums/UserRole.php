<?php

namespace App\Enums;

use App\Contracts\HasBadge;
use App\Enums\Concerns\HasOptions;

enum UserRole: string implements HasBadge
{
    use HasOptions;

    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Supervisor => 'Depot supervisor',
            self::Staff => 'Operations staff',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Admin => 'violet',
            self::Supervisor => 'blue',
            self::Staff => 'slate',
        };
    }

    /**
     * Permissions granted to the role.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Supervisor => [
                Permission::ManageRoutes,
                Permission::ManageSchedules,
                Permission::ManageFleet,
                Permission::OperateTrips,
                Permission::LogFuelAndMaintenance,
                Permission::ViewReports,
            ],
            self::Staff => [
                Permission::OperateTrips,
                Permission::LogFuelAndMaintenance,
            ],
        };
    }

    public function can(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}

<?php

namespace VentureDrake\LaravelCrm\Console\Commands;

use Illuminate\Console\Command;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Support\CurrentTenant;

class TenantAssign extends Command
{
    protected $signature = 'laravelcrm:tenant-assign
        {email : Email user}
        {tenant? : ID tenant (kosongkan + --super untuk super admin)}
        {--super : Jadikan super admin (tenant_id NULL, melihat semua tenant)}';

    protected $description = 'Pindahkan user ke tenant tertentu, atau jadikan super admin';

    public function handle(): int
    {
        $userModel = config('auth.providers.users.model');

        $user = CurrentTenant::withoutTenancy(fn () => $userModel::where('email', $this->argument('email'))->first());

        if (! $user) {
            $this->error('User tidak ditemukan.');

            return self::FAILURE;
        }

        if ($this->option('super')) {
            $user->forceFill(['tenant_id' => null])->save();
            $this->info("{$user->email} sekarang SUPER ADMIN (lihat semua tenant).");

            return self::SUCCESS;
        }

        $tenant = Tenant::find($this->argument('tenant'));

        if (! $tenant) {
            $this->error('Tenant tidak ditemukan.');

            return self::FAILURE;
        }

        $user->forceFill(['tenant_id' => $tenant->id])->save();
        $this->info("{$user->email} dipindahkan ke tenant #{$tenant->id} ({$tenant->name}).");

        return self::SUCCESS;
    }
}

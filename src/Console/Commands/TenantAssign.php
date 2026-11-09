<?php

namespace VentureDrake\LaravelCrm\Console\Commands;

use Illuminate\Console\Command;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Support\CurrentTenant;

class TenantAssign extends Command
{
    protected $signature = 'laravelcrm:tenant-assign
        {email : Email user}
        {tenant? : ID tenant (default: tenant user saat ini, atau 1)}
        {--super : Jadikan super admin (tetap punya tenant_id; bisa masuk ke tenant lain)}
        {--revoke-super : Cabut hak super admin}';

    protected $description = 'Pindahkan user ke tenant tertentu, atau atur hak super admin';

    public function handle(): int
    {
        $userModel = config('auth.providers.users.model');

        $user = CurrentTenant::withoutTenancy(fn () => $userModel::where('email', $this->argument('email'))->first());

        if (! $user) {
            $this->error('User tidak ditemukan.');

            return self::FAILURE;
        }

        $tenantId = $this->argument('tenant') ?: ($user->tenant_id ?: 1);
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error('Tenant tidak ditemukan.');

            return self::FAILURE;
        }

        $user->forceFill(['tenant_id' => $tenant->id]);

        if ($this->option('super')) {
            $user->forceFill(['is_super_admin' => true]);
        }

        if ($this->option('revoke-super')) {
            $user->forceFill(['is_super_admin' => false]);
        }

        $user->save();

        $this->info("{$user->email}: tenant #{$tenant->id} ({$tenant->name})".($user->is_super_admin ? ', SUPER ADMIN' : '').'.');

        return self::SUCCESS;
    }
}

<?php

namespace VentureDrake\LaravelCrm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Support\CurrentTenant;
use VentureDrake\LaravelCrm\Support\TenantProvisioner;

class TenantCreate extends Command
{
    protected $signature = 'laravelcrm:tenant-create
        {name : Nama perusahaan}
        {--code= : Kode unik (default dari nama)}
        {--admin-email= : Email admin awal}
        {--admin-name= : Nama admin awal}
        {--admin-password= : Password admin awal (default acak)}
        {--template=1 : ID tenant sumber salinan settings/pipeline/lookup}
        {--expires= : Tanggal kedaluwarsa (YYYY-MM-DD)}
        {--max-users= : Batas jumlah user}';

    protected $description = 'Buat tenant baru + salin konfigurasi default + admin awal';

    public function handle(TenantProvisioner $provisioner): int
    {
        $tenant = Tenant::create([
            'external_id' => (string) Str::uuid(),
            'name' => $this->argument('name'),
            'code' => strtoupper($this->option('code') ?: Str::slug($this->argument('name'), '')),
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'expires_at' => $this->option('expires') ?: null,
            'max_users' => $this->option('max-users') ?: null,
        ]);

        $report = CurrentTenant::withoutTenancy(
            fn () => $provisioner->cloneDefaults((int) $this->option('template'), $tenant->id)
        );

        $this->info("Tenant #{$tenant->id} ({$tenant->code}) dibuat. Data default disalin: ".json_encode($report));

        if ($email = $this->option('admin-email')) {
            $userModel = config('auth.providers.users.model');
            $password = $this->option('admin-password') ?: Str::random(10);

            $user = CurrentTenant::withoutTenancy(function () use ($userModel, $email, $password, $tenant) {
                $user = $userModel::create([
                    'name' => $this->option('admin-name') ?: 'Admin '.$tenant->name,
                    'email' => $email,
                    'password' => $password,
                ]);
                $user->forceFill(['tenant_id' => $tenant->id, 'crm_access' => 1])->save();

                if (method_exists($user, 'assignRole')) {
                    $user->assignRole('Admin');
                }

                return $user;
            });

            $this->info("Admin dibuat: {$user->email} / password: {$password}");
        }

        return self::SUCCESS;
    }
}

<?php

namespace VentureDrake\LaravelCrm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Support\TenantProvisioner;

class TenantCreate extends Command
{
    protected $signature = 'laravelcrm:tenant-create
        {name : Nama perusahaan}
        {--code= : Kode unik (default dari nama)}
        {--admin-email= : Email admin awal}
        {--admin-name= : Nama admin awal}
        {--admin-password= : Password admin awal (default acak)}
        {--template=1 : ID tenant sumber salinan settings/pipeline/lookup/role}
        {--expires= : Tanggal kedaluwarsa (YYYY-MM-DD)}
        {--max-users= : Batas jumlah user}';

    protected $description = 'Buat tenant baru + salin konfigurasi default & role + admin awal';

    public function handle(TenantProvisioner $provisioner): int
    {
        $password = $this->option('admin-password') ?: Str::random(10);

        [$tenant, $user] = $provisioner->createTenant(
            array_filter([
                'name' => $this->argument('name'),
                'code' => $this->option('code'),
                'starts_at' => now()->toDateString(),
                'expires_at' => $this->option('expires'),
                'max_users' => $this->option('max-users'),
            ]),
            $this->option('admin-email') ? [
                'name' => $this->option('admin-name'),
                'email' => $this->option('admin-email'),
                'password' => $password,
            ] : null,
            (int) $this->option('template')
        );

        $this->info("Tenant #{$tenant->id} ({$tenant->code}) dibuat.");

        if ($user) {
            $this->info("Admin: {$user->email} / password: {$password}");
        }

        return self::SUCCESS;
    }
}

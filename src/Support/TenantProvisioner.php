<?php

namespace VentureDrake\LaravelCrm\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Menyalin data konfigurasi/lookup (settings, pipeline, stage, tipe alamat, dst.)
 * dari tenant template ke tenant baru, supaya tenant baru langsung bisa dipakai.
 */
class TenantProvisioner
{
    /** Urutan penting: pipelines sebelum stages, dst. [tabel => [kolom FK => tabel induk]] */
    protected array $tables = [
        'settings' => [],
        'address_types' => [],
        'contact_types' => [],
        'organization_types' => [],
        'industries' => [],
        'lead_statuses' => [],
        'lead_sources' => [],
        'tax_rates' => [],
        'pipeline_stage_probabilities' => [],
        'pipelines' => [],
        'pipeline_stages' => [
            'pipeline_id' => 'pipelines',
            'pipeline_stage_probability_id' => 'pipeline_stage_probabilities',
        ],
    ];

    /**
     * Buat tenant + salin default + (opsional) admin awal. Dipakai halaman web & command.
     *
     * @param  array  $data   name, code?, email?, phone?, address?, status?, starts_at?, expires_at?, max_users?, attendance_enforce_radius?
     * @param  array|null  $admin  name, email, password
     * @return array [Tenant, ?User]
     */
    public function createTenant(array $data, ?array $admin = null, int $templateId = 1): array
    {
        $data['external_id'] = (string) Str::uuid();
        $data['code'] = strtoupper($data['code'] ?? '') ?: strtoupper(Str::slug($data['name'], ''));
        $data['status'] = $data['status'] ?? 'active';

        $tenant = \VentureDrake\LaravelCrm\Models\Tenant::create($data);

        CurrentTenant::withoutTenancy(fn () => $this->cloneDefaults($templateId, $tenant->id));

        $user = null;

        if ($admin && ! empty($admin['email'])) {
            $userModel = config('auth.providers.users.model');

            $user = CurrentTenant::withoutTenancy(function () use ($userModel, $admin, $tenant) {
                $user = $userModel::create([
                    'name' => $admin['name'] ?: 'Admin '.$tenant->name,
                    'email' => $admin['email'],
                    'password' => $admin['password'],
                ]);
                $user->forceFill(['tenant_id' => $tenant->id, 'crm_access' => 1])->save();

                if (method_exists($user, 'assignRole')) {
                    try {
                        $user->assignRole('Admin');
                    } catch (\Throwable $e) {
                        // role "Admin" belum ada -> atur manual lewat Roles
                    }
                }

                return $user;
            });
        }

        return [$tenant, $user];
    }

    public function cloneDefaults(int $fromTenantId, int $toTenantId): array
    {
        $prefix = config('laravel-crm.db_table_prefix', 'crm_');
        $report = [];
        $idMap = []; // [tabel][idLama] = idBaru

        foreach ($this->tables as $name => $fks) {
            $table = $prefix.$name;

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $rows = DB::table($table)->where('tenant_id', $fromTenantId)->get();
            $count = 0;

            foreach ($rows as $row) {
                $data = (array) $row;
                $oldId = $data['id'];
                unset($data['id']);

                $data['tenant_id'] = $toTenantId;

                if (array_key_exists('external_id', $data)) {
                    $data['external_id'] = (string) Str::uuid();
                }

                foreach ($fks as $col => $parent) {
                    if (! empty($data[$col]) && isset($idMap[$parent][$data[$col]])) {
                        $data[$col] = $idMap[$parent][$data[$col]];
                    }
                }

                $idMap[$name][$oldId] = DB::table($table)->insertGetId($data);
                $count++;
            }

            $report[$name] = $count;
        }

        return $report;
    }
}

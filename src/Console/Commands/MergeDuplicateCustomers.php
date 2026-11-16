<?php

namespace VentureDrake\LaravelCrm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Support\CurrentTenant;
use VentureDrake\LaravelCrm\Support\CustomerMatcher;

/**
 * Gabungkan pelanggan ganda yang sudah terlanjur ada (Person & Organization, termasuk "Pelanggan Umum").
 *
 *   php artisan crm:merge-duplicate-customers            # simulasi saja (tidak mengubah data)
 *   php artisan crm:merge-duplicate-customers --apply    # benar-benar menggabungkan
 *   php artisan crm:merge-duplicate-customers --apply --only=people|organizations
 *
 * Aturan pencocokan sama dengan CustomerMatcher: HP sama = satu orang; tanpa HP, nama sama persis.
 * Data tertua dipertahankan. Semua relasi (quote, order, invoice, lead, deal, HP, email, alamat, aktivitas, dst)
 * dipindah ke data yang dipertahankan, lalu duplikatnya di-soft-delete (bisa dipulihkan).
 */
class MergeDuplicateCustomers extends Command
{
    protected $signature = 'crm:merge-duplicate-customers {--apply : Jalankan penggabungan (tanpa ini hanya simulasi)} {--only= : people atau organizations}';

    protected $description = 'Gabungkan data pelanggan (Person/Organization) yang ganda';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $only = $this->option('only');

        // Tenant dipilih lewat data masing-masing record (command ini jalan di luar sesi login).
        CurrentTenant::withoutTenancy(function () use ($apply, $only) {
            if (! $only || $only === 'people') {
                $this->mergePeople($apply);
            }

            if (! $only || $only === 'organizations') {
                $this->mergeOrganizations($apply);
            }
        });

        $this->newLine();
        $this->info($apply ? 'Selesai.' : 'Simulasi selesai. Tambahkan --apply untuk menggabungkan.');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ people

    protected function mergePeople(bool $apply): void
    {
        $this->line('<options=bold>Person</>');

        $people = Person::query()->orderBy('id')->get();
        $groups = [];

        foreach ($people as $person) {
            $tenant = $person->tenant_id ?? 0;
            $phones = $person->phones()->get()->map(fn ($p) => CustomerMatcher::normalizePhone($p->number))->filter();

            // kunci utama: nomor HP; kalau tidak punya HP: nama
            $keys = $phones->map(fn ($n) => "t{$tenant}:phone:{$n}")->all();

            if (empty($keys)) {
                $name = CustomerMatcher::normalizeName($person->first_name.' '.$person->last_name);
                if ($name !== '') {
                    $keys = ["t{$tenant}:name:{$name}"];
                }
            }

            foreach ($keys as $key) {
                $groups[$key][] = $person;
            }
        }

        $done = [];
        $merged = 0;

        foreach ($groups as $key => $members) {
            $members = collect($members)->unique('id')->sortBy('id')->values();

            if ($members->count() < 2) {
                continue;
            }

            $keep = $members->first();

            if (isset($done[$keep->id])) {
                continue; // sudah digabung ke data lain lewat kunci berbeda
            }

            foreach ($members->slice(1) as $dup) {
                if (isset($done[$dup->id]) || $dup->id === $keep->id) {
                    continue;
                }

                $this->line(sprintf('  %s  #%d "%s"  <-  #%d "%s"', $key, $keep->id, trim($keep->first_name.' '.$keep->last_name), $dup->id, trim($dup->first_name.' '.$dup->last_name)));

                if ($apply) {
                    $this->mergeInto(Person::class, 'person_id', $keep->id, $dup->id);
                    $dup->delete();
                }

                $done[$dup->id] = true;
                $merged++;
            }
        }

        $this->info("  {$merged} Person ganda ".($apply ? 'digabung.' : 'ditemukan.'));
    }

    // ------------------------------------------------------------ organizations

    protected function mergeOrganizations(bool $apply): void
    {
        $this->line('<options=bold>Organization</>');

        $groups = [];

        foreach (Organization::query()->orderBy('id')->get() as $org) {
            $name = CustomerMatcher::normalizeName($org->name);

            if ($name !== '') {
                $groups['t'.($org->tenant_id ?? 0).':'.$name][] = $org;
            }
        }

        $merged = 0;

        foreach ($groups as $key => $members) {
            if (count($members) < 2) {
                continue;
            }

            $keep = $members[0];

            foreach (array_slice($members, 1) as $dup) {
                $this->line(sprintf('  #%d "%s"  <-  #%d', $keep->id, $keep->name, $dup->id));

                if ($apply) {
                    $this->mergeInto(Organization::class, 'organization_id', $keep->id, $dup->id);
                    $dup->delete();
                }

                $merged++;
            }
        }

        $this->info("  {$merged} Organization ganda ".($apply ? 'digabung.' : 'ditemukan.'));
    }

    // ------------------------------------------------------------------ inti

    /**
     * Pindahkan semua rujukan dari $dupId ke $keepId:
     *  - kolom FK biasa (person_id / organization_id) di semua tabel CRM
     *  - relasi morph (phones, emails, addresses, activities, label, custom field, dst) yang menunjuk kelas model ini
     */
    protected function mergeInto(string $modelClass, string $fkColumn, int $keepId, int $dupId): void
    {
        $prefix = config('laravel-crm.db_table_prefix', 'crm_');
        $morph = (new $modelClass)->getMorphClass();

        DB::transaction(function () use ($prefix, $fkColumn, $morph, $keepId, $dupId) {
            foreach (Schema::getTableListing() as $table) {
                $table = preg_replace('/^.*\./', '', $table); // buang nama schema kalau ada

                if (! str_starts_with($table, $prefix)) {
                    continue;
                }

                $columns = Schema::getColumnListing($table);

                if (in_array($fkColumn, $columns, true)) {
                    DB::table($table)->where($fkColumn, $dupId)->update([$fkColumn => $keepId]);
                }

                foreach ($columns as $column) {
                    if (! str_ends_with($column, '_type')) {
                        continue;
                    }

                    $idColumn = substr($column, 0, -5).'_id';

                    if (! in_array($idColumn, $columns, true)) {
                        continue;
                    }

                    DB::table($table)
                        ->where($column, $morph)
                        ->where($idColumn, $dupId)
                        ->update([$idColumn => $keepId]);
                }
            }
        });
    }
}

<?php

namespace VentureDrake\LaravelCrm\Support;

use Carbon\Carbon;
use VentureDrake\LaravelCrm\Models\Attendance;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Models\Team;
use VentureDrake\LaravelCrm\Models\Visit;

/**
 * Daftar modul yang bisa diexport (Excel / CSV / cetak PDF). Semua query memakai model ber-BelongsToTenant
 * sehingga otomatis hanya data tenant aktif. Uang disimpan x100 -> dibagi 100.
 *
 * Kolom: [label, type (text|money|int|date), fn(model, tz)]
 */
class ExportRegistry
{
    /** Kunci = segmen route `laravel-crm.{kunci}.index`. */
    public static function keys(): array
    {
        return array_keys(static::all());
    }

    public static function has(string $key): bool
    {
        return isset(static::all()[$key]);
    }

    protected static function d($v, string $tz, string $fmt = 'Y-m-d H:i'): ?string
    {
        return $v ? Carbon::parse($v)->setTimezone($tz)->format($fmt) : null;
    }

    protected static function m($v): ?float
    {
        return $v === null ? null : round(((int) $v) / 100, 2);
    }

    protected static function who($model): ?string
    {
        return data_get($model, 'organization.name') ?: data_get($model, 'person.name');
    }

    public static function all(): array
    {
        return [
            'leads' => [
                'title' => 'Lead', 'model' => Lead::class, 'permission' => 'view crm leads',
                'with' => ['leadStatus', 'leadSource', 'pipelineStage', 'organization', 'person', 'ownerUser', 'assignedToUser'],
                'columns' => [
                    ['Nomor', 'text', fn ($m) => $m->lead_id ?? $m->number],
                    ['Judul', 'text', fn ($m) => $m->title],
                    ['Nilai', 'money', fn ($m) => static::m($m->amount)],
                    ['Status', 'text', fn ($m) => data_get($m, 'leadStatus.name')],
                    ['Sumber', 'text', fn ($m) => data_get($m, 'leadSource.name')],
                    ['Tahap pipeline', 'text', fn ($m) => data_get($m, 'pipelineStage.name')],
                    ['Organisasi', 'text', fn ($m) => data_get($m, 'organization.name')],
                    ['Kontak', 'text', fn ($m) => data_get($m, 'person.name')],
                    ['Pemilik', 'text', fn ($m) => data_get($m, 'ownerUser.name')],
                    ['Ditugaskan ke', 'text', fn ($m) => data_get($m, 'assignedToUser.name')],
                    ['Perkiraan closing', 'text', fn ($m, $tz) => static::d($m->expected_close, $tz, 'Y-m-d')],
                    ['Dibuat', 'text', fn ($m, $tz) => static::d($m->created_at, $tz)],
                ],
            ],
            'deals' => [
                'title' => 'Deal', 'model' => Deal::class, 'permission' => 'view crm deals',
                'with' => ['pipelineStage', 'organization', 'person', 'ownerUser', 'assignedToUser'],
                'columns' => [
                    ['Nomor', 'text', fn ($m) => $m->deal_id ?? $m->number],
                    ['Judul', 'text', fn ($m) => $m->title],
                    ['Nilai', 'money', fn ($m) => static::m($m->amount)],
                    ['Status', 'text', fn ($m) => match ($m->closed_status) { 'won' => 'Menang', 'lost' => 'Kalah', default => 'Terbuka' }],
                    ['Tahap pipeline', 'text', fn ($m) => data_get($m, 'pipelineStage.name')],
                    ['Organisasi', 'text', fn ($m) => data_get($m, 'organization.name')],
                    ['Kontak', 'text', fn ($m) => data_get($m, 'person.name')],
                    ['Pemilik', 'text', fn ($m) => data_get($m, 'ownerUser.name')],
                    ['Ditugaskan ke', 'text', fn ($m) => data_get($m, 'assignedToUser.name')],
                    ['Perkiraan closing', 'text', fn ($m, $tz) => static::d($m->expected_close, $tz, 'Y-m-d')],
                    ['Ditutup', 'text', fn ($m, $tz) => static::d($m->closed_at, $tz)],
                    ['Dibuat', 'text', fn ($m, $tz) => static::d($m->created_at, $tz)],
                ],
            ],
            'people' => [
                'title' => 'Kontak', 'model' => Person::class, 'permission' => 'view crm people',
                'with' => ['organization', 'emails', 'phones', 'ownerUser'],
                'columns' => [
                    ['Nama', 'text', fn ($m) => $m->name],
                    ['Jabatan', 'text', fn ($m) => $m->job_title ?? null],
                    ['Organisasi', 'text', fn ($m) => data_get($m, 'organization.name')],
                    ['Email', 'text', fn ($m) => data_get($m, 'emails.0.address')],
                    ['Telepon', 'text', fn ($m) => data_get($m, 'phones.0.number')],
                    ['Pemilik', 'text', fn ($m) => data_get($m, 'ownerUser.name')],
                    ['Dibuat', 'text', fn ($m, $tz) => static::d($m->created_at, $tz)],
                ],
            ],
            'organizations' => [
                'title' => 'Organisasi', 'model' => Organization::class, 'permission' => 'view crm organizations',
                'with' => ['organizationType', 'industry', 'emails', 'phones', 'ownerUser'],
                'columns' => [
                    ['Nama', 'text', fn ($m) => $m->name],
                    ['Jenis', 'text', fn ($m) => data_get($m, 'organizationType.name')],
                    ['Industri', 'text', fn ($m) => data_get($m, 'industry.name')],
                    ['Email', 'text', fn ($m) => data_get($m, 'emails.0.address')],
                    ['Telepon', 'text', fn ($m) => data_get($m, 'phones.0.number')],
                    ['Pemilik', 'text', fn ($m) => data_get($m, 'ownerUser.name')],
                    ['Dibuat', 'text', fn ($m, $tz) => static::d($m->created_at, $tz)],
                ],
            ],
            'quotes' => [
                'title' => 'Quote', 'model' => Quote::class, 'permission' => 'view crm quotes',
                'with' => ['organization', 'person', 'ownerUser'],
                'columns' => [
                    ['Nomor', 'text', fn ($m) => $m->quote_id ?? $m->number],
                    ['Referensi', 'text', fn ($m) => $m->reference ?? null],
                    ['Pelanggan', 'text', fn ($m) => static::who($m)],
                    ['Total', 'money', fn ($m) => static::m($m->total)],
                    ['Terbit', 'text', fn ($m, $tz) => static::d($m->issue_at ?? null, $tz, 'Y-m-d')],
                    ['Berlaku sampai', 'text', fn ($m, $tz) => static::d($m->expire_at ?? null, $tz, 'Y-m-d')],
                    ['Status', 'text', fn ($m) => $m->accepted_at ? 'Diterima' : ($m->rejected_at ? 'Ditolak' : 'Belum diputuskan')],
                    ['Pemilik', 'text', fn ($m) => data_get($m, 'ownerUser.name')],
                    ['Dibuat', 'text', fn ($m, $tz) => static::d($m->created_at, $tz)],
                ],
            ],
            'orders' => [
                'title' => 'Order', 'model' => Order::class, 'permission' => 'view crm orders',
                'with' => ['organization', 'person', 'ownerUser'],
                'columns' => [
                    ['Nomor', 'text', fn ($m) => $m->order_id ?? $m->number],
                    ['Referensi', 'text', fn ($m) => $m->reference ?? null],
                    ['Pelanggan', 'text', fn ($m) => static::who($m)],
                    ['Total', 'money', fn ($m) => static::m($m->total)],
                    ['Pemilik', 'text', fn ($m) => data_get($m, 'ownerUser.name')],
                    ['Dibuat', 'text', fn ($m, $tz) => static::d($m->created_at, $tz)],
                ],
            ],
            'invoices' => [
                'title' => 'Invoice', 'model' => Invoice::class, 'permission' => 'view crm invoices',
                'with' => ['organization', 'person', 'ownerUser'],
                'columns' => [
                    ['Nomor', 'text', fn ($m) => $m->invoice_id ?? $m->number],
                    ['Pelanggan', 'text', fn ($m) => static::who($m)],
                    ['Total', 'money', fn ($m) => static::m($m->total)],
                    ['Terutang', 'money', fn ($m) => static::m($m->amount_due)],
                    ['Terbayar', 'money', fn ($m) => static::m($m->amount_paid)],
                    ['Jatuh tempo', 'text', fn ($m, $tz) => static::d($m->due_date, $tz, 'Y-m-d')],
                    ['Lunas pada', 'text', fn ($m, $tz) => static::d($m->fully_paid_at, $tz)],
                    ['Pemilik', 'text', fn ($m) => data_get($m, 'ownerUser.name')],
                    ['Dibuat', 'text', fn ($m, $tz) => static::d($m->created_at, $tz)],
                ],
            ],
            'products' => [
                'title' => 'Produk', 'model' => Product::class, 'permission' => 'view crm products',
                'with' => ['productCategory', 'productPrices'],
                'columns' => [
                    ['Kode', 'text', fn ($m) => $m->code],
                    ['Nama', 'text', fn ($m) => $m->name],
                    ['Kategori', 'text', fn ($m) => data_get($m, 'productCategory.name')],
                    ['Harga', 'money', fn ($m) => static::m(data_get($m, 'productPrices.0.unit_price'))],
                    ['Aktif', 'text', fn ($m) => ($m->active ?? true) ? 'Ya' : 'Tidak'],
                    ['Dibuat', 'text', fn ($m, $tz) => static::d($m->created_at, $tz)],
                ],
            ],
            'tasks' => [
                'title' => 'Task', 'model' => Task::class, 'permission' => 'view crm tasks',
                'with' => ['assignedToUser', 'ownerUser'],
                'columns' => [
                    ['Task', 'text', fn ($m) => $m->name],
                    ['Deskripsi', 'text', fn ($m) => $m->description],
                    ['Jatuh tempo', 'text', fn ($m, $tz) => static::d($m->due_at, $tz)],
                    ['Selesai', 'text', fn ($m, $tz) => static::d($m->completed_at, $tz)],
                    ['Status', 'text', fn ($m) => $m->completed_at ? 'Selesai' : (($m->due_at && $m->due_at->isPast()) ? 'Terlambat' : 'Terbuka')],
                    ['Ditugaskan ke', 'text', fn ($m) => data_get($m, 'assignedToUser.name')],
                    ['Pemilik', 'text', fn ($m) => data_get($m, 'ownerUser.name')],
                    ['Dibuat', 'text', fn ($m, $tz) => static::d($m->created_at, $tz)],
                ],
            ],
            'visits' => [
                'title' => 'Visit', 'model' => Visit::class, 'permission' => 'view crm visits',
                'with' => ['user'],
                'columns' => [
                    ['Jadwal', 'text', fn ($m, $tz) => static::d($m->scheduled_at, $tz)],
                    ['Petugas', 'text', fn ($m) => data_get($m, 'user.name')],
                    ['Judul', 'text', fn ($m) => $m->title],
                    ['Alamat tujuan', 'text', fn ($m) => $m->target_address],
                    ['Check-in', 'text', fn ($m, $tz) => static::d($m->checked_in_at, $tz)],
                    ['Jarak check-in (m)', 'int', fn ($m) => $m->distance_meters !== null ? round($m->distance_meters) : null],
                    ['Status', 'text', fn ($m) => $m->checked_in_at ? 'Selesai' : ($m->status ?? 'Belum')],
                ],
            ],
            'attendances' => [
                'title' => 'Absensi', 'model' => Attendance::class, 'permission' => 'view crm attendances',
                'with' => ['user'],
                'order' => 'recorded_at',
                'columns' => [
                    ['Waktu', 'text', fn ($m, $tz) => static::d($m->recorded_at, $tz)],
                    ['User', 'text', fn ($m) => data_get($m, 'user.name')],
                    ['Tipe', 'text', fn ($m) => $m->type === 'in' ? 'Masuk' : 'Pulang'],
                    ['Status', 'text', fn ($m) => ['on_time' => 'Tepat waktu', 'late' => 'Terlambat', 'early_leave' => 'Pulang cepat', 'overtime' => 'Lembur'][$m->status] ?? $m->status],
                    ['Telat (mnt)', 'int', fn ($m) => $m->late_minutes],
                    ['Pulang cepat (mnt)', 'int', fn ($m) => $m->early_leave_minutes],
                    ['Lembur (mnt)', 'int', fn ($m) => $m->overtime_minutes],
                    ['Jarak ke lokasi kerja (m)', 'int', fn ($m) => $m->distance_meters],
                    ['Dalam radius', 'text', fn ($m) => $m->within_radius === null ? '' : ($m->within_radius ? 'Ya' : 'Tidak')],
                    ['Alamat', 'text', fn ($m) => $m->address],
                ],
            ],
            'users' => [
                'title' => 'User', 'model' => config('auth.providers.users.model'), 'permission' => 'view crm users',
                'with' => [],
                'order' => 'name', 'direction' => 'asc',
                'columns' => [
                    ['Nama', 'text', fn ($m) => $m->name],
                    ['Email', 'text', fn ($m) => $m->email],
                    ['Role', 'text', fn ($m) => method_exists($m, 'getRoleNames') ? $m->getRoleNames()->implode(', ') : null],
                    ['Akses CRM', 'text', fn ($m) => ($m->crm_access ?? false) ? 'Ya' : 'Tidak'],
                    ['Dibuat', 'text', fn ($m, $tz) => static::d($m->created_at, $tz)],
                ],
            ],
            'teams' => [
                'title' => 'Team', 'model' => Team::class, 'permission' => 'view crm teams',
                'with' => ['users'],
                'order' => 'name', 'direction' => 'asc',
                'columns' => [
                    ['Nama team', 'text', fn ($m) => $m->name],
                    ['Jumlah anggota', 'int', fn ($m) => $m->users->count()],
                    ['Anggota', 'text', fn ($m) => $m->users->pluck('name')->implode(', ')],
                    ['Dibuat', 'text', fn ($m, $tz) => static::d($m->created_at, $tz)],
                ],
            ],
        ];
    }

    /**
     * @return array{title: string, columns: array, rows: \Generator}
     */
    public static function dataset(string $key, ?string $from = null, ?string $to = null, int $limit = 50000): array
    {
        $def = static::all()[$key];
        $tz = AttendanceRecapService::timezone();
        $model = $def['model'];
        $instance = new $model;

        $with = array_values(array_filter($def['with'] ?? [], fn ($r) => method_exists($instance, $r)));
        $q = $model::query()->with($with);

        $dateCol = $def['order'] ?? 'created_at';

        if ($from) {
            $q->where($dateCol, '>=', Carbon::parse($from, $tz)->startOfDay()->utc());
        }
        if ($to) {
            $q->where($dateCol, '<=', Carbon::parse($to, $tz)->endOfDay()->utc());
        }

        $q->orderBy($dateCol, $def['direction'] ?? 'desc')->orderBy($instance->getKeyName(), 'desc');

        $columns = array_map(fn ($c) => ['label' => $c[0], 'type' => $c[1]], $def['columns']);

        $rows = (function () use ($q, $def, $tz, $limit) {
            $n = 0;
            $page = 1;

            // Paging 500 baris agar eager load tetap berlaku (cursor() akan N+1 per baris).
            while (true) {
                $chunk = (clone $q)->forPage($page++, 500)->get();

                if ($chunk->isEmpty()) {
                    return;
                }

                foreach ($chunk as $m) {
                    yield array_map(fn ($c) => rescue(fn () => $c[2]($m, $tz), null, false), $def['columns']);

                    if (++$n >= $limit) {
                        return;
                    }
                }
            }
        })();

        return ['title' => $def['title'], 'columns' => $columns, 'rows' => $rows];
    }
}

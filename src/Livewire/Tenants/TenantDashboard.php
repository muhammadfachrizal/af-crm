<?php

namespace VentureDrake\LaravelCrm\Livewire\Tenants;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Support\CurrentTenant;

/** Dashboard Super Admin: ringkasan semua tenant. Query langsung ke DB agar tidak terkena filter tenant. */
class TenantDashboard extends Component
{
    public function mount(): void
    {
        abort_unless(CurrentTenant::isSuperAdmin(), 403);
    }

    protected function countBy(string $table, array $where = [], bool $softDeletes = true)
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            return collect();
        }

        $q = DB::table($table)->selectRaw('tenant_id, count(*) as c')->whereNotNull('tenant_id');

        if ($softDeletes && Schema::hasColumn($table, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        foreach ($where as $col => $val) {
            $q->where($col, $val);
        }

        return $q->groupBy('tenant_id')->pluck('c', 'tenant_id');
    }

    public function render()
    {
        $tenants = Tenant::orderBy('name')->get();

        $users = DB::table('users')->selectRaw('tenant_id, count(*) as c')->whereNotNull('tenant_id')
            ->groupBy('tenant_id')->pluck('c', 'tenant_id');
        $leads = $this->countBy('crm_leads');
        $deals = $this->countBy('crm_deals');
        $people = $this->countBy('crm_people');
        $visits = $this->countBy('crm_visits');

        $attToday = collect();
        if (Schema::hasTable('crm_attendances')) {
            $attToday = DB::table('crm_attendances')->selectRaw('tenant_id, count(distinct user_id) as c')
                ->whereNotNull('tenant_id')->whereNull('deleted_at')
                ->whereDate('recorded_at', now()->toDateString())
                ->groupBy('tenant_id')->pluck('c', 'tenant_id');
        }

        $rows = $tenants->map(fn ($t) => [
            'tenant' => $t,
            'usable' => $t->isUsable(),
            'users' => $users[$t->id] ?? 0,
            'leads' => $leads[$t->id] ?? 0,
            'deals' => $deals[$t->id] ?? 0,
            'people' => $people[$t->id] ?? 0,
            'visits' => $visits[$t->id] ?? 0,
            'attendance_today' => $attToday[$t->id] ?? 0,
            'days_left' => $t->expires_at ? now()->startOfDay()->diffInDays($t->expires_at->startOfDay(), false) : null,
        ]);

        $audits = Schema::hasTable('crm_tenant_audits')
            ? DB::table('crm_tenant_audits')
                ->leftJoin('users', 'users.id', '=', 'crm_tenant_audits.user_id')
                ->leftJoin('crm_tenants', 'crm_tenants.id', '=', 'crm_tenant_audits.tenant_id')
                ->orderByDesc('crm_tenant_audits.id')->limit(10)
                ->get(['crm_tenant_audits.*', 'users.name as user_name', 'crm_tenants.name as tenant_name'])
            : collect();

        return view('laravel-crm::livewire.tenants.tenant-dashboard', [
            'audits' => $audits,
            'rows' => $rows,
            'total' => $tenants->count(),
            'active' => $rows->where('usable', true)->count(),
            'suspended' => $tenants->where('status', 'suspended')->count(),
            'expiringSoon' => $rows->filter(fn ($r) => $r['usable'] && $r['days_left'] !== null && $r['days_left'] <= 30)->values(),
            'expired' => $rows->filter(fn ($r) => $r['tenant']->status === 'active' && ! $r['usable'])->count(),
            'totalUsers' => $users->sum(),
            'totalLeads' => $leads->sum(),
            'totalDeals' => $deals->sum(),
            'attendanceToday' => $attToday->sum(),
        ]);
    }
}

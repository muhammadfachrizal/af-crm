<?php

namespace VentureDrake\LaravelCrm\Livewire\Tenants;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Support\CurrentTenant;
use VentureDrake\LaravelCrm\Support\TenantAuditLog;
use VentureDrake\LaravelCrm\Support\TenantProvisioner;

class TenantIndex extends Component
{
    use Toast, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $address = null;

    public string $status = 'active';

    public ?string $starts_at = null;

    public ?string $expires_at = null;

    public $max_users = null;

    public bool $attendance_enforce_radius = false;

    public string $timezone = 'Asia/Jakarta';

    public bool $block_mock_location = true;

    public bool $bind_device = false;

    // Admin awal (hanya saat membuat tenant)
    public bool $create_admin = true;

    public string $admin_name = '';

    public string $admin_email = '';

    public string $admin_password = '';

    public int $template_id = 1;

    public ?string $createdInfo = null;

    public function mount(): void
    {
        abort_unless(CurrentTenant::isSuperAdmin(), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => 'Tenant'],
            ['key' => 'code', 'label' => 'Kode'],
            ['key' => 'status_label', 'label' => 'Status', 'sortable' => false],
            ['key' => 'expires_at', 'label' => 'Kedaluwarsa', 'sortable' => false],
            ['key' => 'users_count', 'label' => 'User', 'sortable' => false],
            ['key' => 'actions', 'label' => '', 'sortable' => false],
        ];
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'code', 'email', 'phone', 'address', 'starts_at', 'expires_at', 'max_users',
            'admin_name', 'admin_email', 'admin_password', 'createdInfo',
        ]);
        $this->status = 'active';
        $this->attendance_enforce_radius = false;
        $this->timezone = 'Asia/Jakarta';
        $this->block_mock_location = true;
        $this->bind_device = false;
        $this->create_admin = true;
        $this->template_id = (int) (Tenant::orderBy('id')->value('id') ?? 1);
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->admin_password = Str::random(10);
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->resetForm();
        $t = Tenant::findOrFail($id);

        $this->editingId = $t->id;
        $this->name = $t->name;
        $this->code = $t->code;
        $this->email = $t->email;
        $this->phone = $t->phone;
        $this->address = $t->address;
        $this->status = $t->status;
        $this->starts_at = $t->starts_at?->format('Y-m-d');
        $this->expires_at = $t->expires_at?->format('Y-m-d');
        $this->max_users = $t->max_users;
        $this->attendance_enforce_radius = (bool) $t->attendance_enforce_radius;
        $this->timezone = $t->timezone ?: 'Asia/Jakarta';
        $this->block_mock_location = (bool) $t->block_mock_location;
        $this->bind_device = (bool) $t->bind_device;
        $this->showForm = true;
    }

    public function save(TenantProvisioner $provisioner): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:crm_tenants,code'.($this->editingId ? ','.$this->editingId : '')],
            'email' => ['nullable', 'email'],
            'status' => ['required', 'in:active,suspended'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'timezone' => ['required', 'timezone'],
        ];

        if (! $this->editingId && $this->create_admin) {
            $rules['admin_email'] = ['required', 'email', 'unique:users,email'];
            $rules['admin_password'] = ['required', 'string', 'min:8'];
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'address' => $this->address ?: null,
            'status' => $this->status,
            'starts_at' => $this->starts_at ?: null,
            'expires_at' => $this->expires_at ?: null,
            'max_users' => $this->max_users ?: null,
            'attendance_enforce_radius' => $this->attendance_enforce_radius,
            'timezone' => $this->timezone,
            'block_mock_location' => $this->block_mock_location,
            'bind_device' => $this->bind_device,
        ];

        if ($this->editingId) {
            $t = Tenant::findOrFail($this->editingId);
            $data['code'] = strtoupper($data['code'] ?? $t->code);
            $t->update($data);
            TenantAuditLog::record('tenant.updated', $t->id, ['name' => $t->name]);
            $this->success('Tenant diperbarui.');
            $this->showForm = false;

            return;
        }

        [$tenant, $user] = $provisioner->createTenant(
            $data,
            $this->create_admin ? [
                'name' => $this->admin_name,
                'email' => $this->admin_email,
                'password' => $this->admin_password,
            ] : null,
            $this->template_id
        );

        TenantAuditLog::record('tenant.created', $tenant->id, ['name' => $tenant->name]);

        $this->createdInfo = $user
            ? "Tenant {$tenant->name} dibuat. Login admin: {$user->email} / {$this->admin_password}"
            : "Tenant {$tenant->name} dibuat.";

        $this->success('Tenant dibuat.');
        $this->showForm = false;
    }

    /** Super admin bekerja di tenant ini: semua data & data baru memakai tenant tsb. */
    public function enter(int $id)
    {
        $t = Tenant::findOrFail($id);
        session(['acting_tenant_id' => $t->id]);
        TenantAuditLog::record('tenant.enter', $t->id);

        return redirect()->route('laravel-crm.dashboard');
    }

    public function toggleStatus(int $id): void
    {
        $t = Tenant::findOrFail($id);
        $t->update(['status' => $t->status === 'active' ? 'suspended' : 'active']);
        TenantAuditLog::record('tenant.'.$t->status, $t->id);
        $this->success($t->status === 'active' ? 'Tenant diaktifkan.' : 'Tenant dinonaktifkan.');
    }

    public function render()
    {
        $tenants = Tenant::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->paginate(15);

        $counts = DB::table('users')
            ->whereIn('tenant_id', $tenants->pluck('id'))
            ->selectRaw('tenant_id, count(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');

        $tenants->getCollection()->transform(function ($t) use ($counts) {
            $t->users_count = ($counts[$t->id] ?? 0).($t->max_users ? ' / '.$t->max_users : '');
            $t->status_label = $t->isUsable() ? 'Aktif' : ($t->status === 'active' ? 'Kedaluwarsa' : 'Nonaktif');

            return $t;
        });

        return view('laravel-crm::livewire.tenants.tenant-index', [
            'tenants' => $tenants,
            'headers' => $this->headers(),
            'templates' => Tenant::orderBy('name')->get(['id', 'name']),
        ]);
    }
}

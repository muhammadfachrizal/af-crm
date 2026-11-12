<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Services\InvoiceService;
use VentureDrake\LaravelCrm\Services\OrderService;
use VentureDrake\LaravelCrm\Services\QuoteService;
use VentureDrake\LaravelCrm\Support\SalesDocuments;

/** Penjualan cepat dari mobile: keranjang -> Quote / Order / Invoice / Invoice lunas, plus PDF & berbagi. */
class SalesController extends ApiController
{
    /** Cari produk (nama/kode/barcode) atau cocokkan barcode persis. */
    public function lookup(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $q = Product::query()->with(['productPrices', 'taxRate'])
            ->where(fn ($w) => $w->where('active', true)->orWhereNull('active'));

        if ($barcode = trim((string) $request->query('barcode'))) {
            $q->where('barcode', $barcode);
        } elseif ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")->orWhere('barcode', 'like', "%{$term}%"));
        }

        $items = $q->orderBy('name')->limit(30)->get()->map(fn (Product $p) => [
            'id' => $p->external_id,
            'name' => $p->name,
            'code' => $p->code,
            'barcode' => $p->barcode,
            'unit' => $p->unit,
            'unit_price' => SalesDocuments::unitPrice($p),
            'tax_rate' => SalesDocuments::taxRate($p),
        ]);

        return response()->json(['data' => $items->values()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:quote,order,invoice,invoice_paid'],
            'customer.person_id' => ['nullable', 'string'],
            'customer.organization_id' => ['nullable', 'string'],
            'customer.name' => ['nullable', 'string', 'max:150'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['nullable', 'string', 'max:30'],
        ]);

        $type = $data['type'] === 'invoice_paid' ? 'invoice' : $data['type'];
        $modelClass = ['quote' => Quote::class, 'order' => Order::class, 'invoice' => Invoice::class][$type];
        $this->authorize('create', $modelClass);

        $user = $request->user();
        $calc = SalesDocuments::lines($data['items']);
        abort_if(empty($calc['lines']), 422, 'Tidak ada item produk yang valid.');

        // ---- Pelanggan: pilih yang ada, buat cepat (nama + HP), atau pelanggan umum
        $person = null;
        $organization = null;
        $c = $data['customer'] ?? [];

        if (! empty($c['person_id'])) {
            $person = Person::where('external_id', $c['person_id'])->firstOrFail();
        } elseif (! empty($c['organization_id'])) {
            $organization = Organization::where('external_id', $c['organization_id'])->firstOrFail();
        } elseif (! empty($c['name'])) {
            $person = Person::create(['first_name' => $c['name'], 'external_id' => (string) Str::uuid(), 'user_owner_id' => $user->id]);

            if (! empty($c['phone'])) {
                $person->phones()->create(['external_id' => (string) Str::uuid(), 'number' => $c['phone'], 'type' => 'mobile', 'primary' => 1]);
            }
        } else {
            $person = SalesDocuments::walkInCustomer($user);
        }

        $who = $person?->name ?: $organization?->name;
        $currency = SalesDocuments::currency();
        $paid = $data['type'] === 'invoice_paid';

        $payload = (object) [
            'title' => 'Penjualan - '.$who,
            'description' => $data['notes'] ?? null,
            'reference' => null,
            'currency' => $currency,
            'issue_at' => now()->toIso8601String(),
            'expire_at' => now()->addDays(14)->toIso8601String(),
            'issue_date' => now()->toIso8601String(),
            'due_date' => now()->addDays($paid ? 0 : 14)->toIso8601String(),
            'terms' => $paid ? 'Pembayaran: '.($data['payment_method'] ?? 'Tunai') : null,
            'sub_total' => $calc['subtotal'],
            'discount' => null,
            'tax' => $calc['tax'],
            'adjustment' => null,
            'total' => $calc['total'],
            'lead_id' => null,
            'deal_id' => null,
            'quote_id' => null,
            'order_id' => null,
            'pipeline_stage_id' => null,
            'user_owner_id' => $user->id,
            'labels' => [],
            'addresses' => [], // OrderService membaca $request->addresses
            'products' => $calc['lines'],
        ];

        $doc = match ($type) {
            'quote' => app(QuoteService::class)->create($payload, $person, $organization),
            'order' => app(OrderService::class)->create($payload, $person, $organization),
            'invoice' => app(InvoiceService::class)->create($payload, $person, $organization),
        };

        $doc->forceFill(['source' => 'quick_sale'])->save();

        if ($type === 'invoice') {
            // amount_due harus terisi agar piutang/pembayaran benar
            $doc->forceFill($paid
                ? ['amount_due' => 0, 'amount_paid' => $calc['total'], 'fully_paid_at' => now(), 'payment_method' => $data['payment_method'] ?? 'Tunai']
                : ['amount_due' => $calc['total'], 'amount_paid' => 0])->save();
        }

        $doc->refresh();

        return response()->json(['data' => SalesDocuments::share($type, $doc)], 201);
    }

    /** PDF dokumen (untuk dibagikan lewat WhatsApp / aplikasi lain dari mobile). */
    public function pdf(Request $request, string $type, string $id)
    {
        $doc = SalesDocuments::resolveModel($type, $id);
        $this->authorize('view', $doc);

        [$pdf, $name] = SalesDocuments::pdf($type, $doc);

        return $pdf->download($name);
    }

    /** Info berbagi untuk dokumen yang sudah ada (link online, teks, wa.me). */
    public function share(Request $request, string $type, string $id)
    {
        $doc = SalesDocuments::resolveModel($type, $id);
        $this->authorize('view', $doc);

        return response()->json(['data' => SalesDocuments::share($type, $doc)]);
    }

    /**
     * Ringkasan penjualan untuk mobile: ?range=today|week|month&all=0|1
     * all=1 (semua user) hanya untuk yang berhak mengubah invoice; selain itu hanya penjualan milik sendiri.
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        $range = $request->query('range', 'today');
        $tz = \VentureDrake\LaravelCrm\Support\AttendanceRecapService::timezone();
        $now = \Carbon\Carbon::now($tz);

        [$from, $to] = match ($range) {
            'week' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };

        $all = $request->boolean('all') && $user->can('edit crm invoices');

        $q = Invoice::query()->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->when(! $all, fn ($w) => $w->where('user_owner_id', $user->id));

        $invoices = (clone $q)->with(['person', 'organization'])->orderByDesc('id')->get();

        $paid = $invoices->whereNotNull('fully_paid_at');
        $methods = $paid->groupBy(fn ($i) => $i->payment_method ?: 'Lainnya')->map(fn ($g, $k) => [
            'method' => $k, 'count' => $g->count(), 'total' => round($g->sum('total') / 100, 2),
        ])->values();

        $lines = \Illuminate\Support\Facades\DB::table(config('laravel-crm.db_table_prefix', 'crm_').'invoice_lines as l')
            ->join(config('laravel-crm.db_table_prefix', 'crm_').'invoices as i', 'i.id', '=', 'l.invoice_id')
            ->leftJoin(config('laravel-crm.db_table_prefix', 'crm_').'products as p', 'p.id', '=', 'l.product_id')
            ->whereIn('i.id', $invoices->pluck('id'))
            ->groupBy('l.product_id', 'p.name')
            ->selectRaw('p.name as name, sum(l.quantity) as qty, sum(l.amount) as amount')
            ->orderByDesc('qty')->limit(5)->get()
            ->map(fn ($r) => ['name' => $r->name ?: '-', 'qty' => (float) $r->qty, 'amount' => round($r->amount / 100, 2)]);

        return response()->json(['data' => [
            'range' => $range,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'all' => $all,
            'can_view_all' => $user->can('edit crm invoices'),
            'count' => $invoices->count(),
            'total' => round($invoices->sum('total') / 100, 2),
            'paid_count' => $paid->count(),
            'paid_total' => round($paid->sum('total') / 100, 2),
            'unpaid_total' => round($invoices->whereNull('fully_paid_at')->sum('amount_due') / 100, 2),
            'methods' => $methods,
            'top_products' => $lines,
            'recent' => $invoices->take(15)->map(fn ($i) => [
                'id' => $i->external_id,
                'number' => (string) ($i->invoice_id ?? $i->id),
                'customer' => $i->person?->name ?: $i->organization?->name ?: '-',
                'total' => round($i->total / 100, 2),
                'paid' => (bool) $i->fully_paid_at,
                'method' => $i->payment_method,
                'at' => $i->created_at?->toIso8601String(),
            ])->values(),
        ]]);
    }
}

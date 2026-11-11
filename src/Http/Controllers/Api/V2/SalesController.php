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
            'products' => $calc['lines'],
        ];

        $doc = match ($type) {
            'quote' => app(QuoteService::class)->create($payload, $person, $organization),
            'order' => app(OrderService::class)->create($payload, $person, $organization),
            'invoice' => app(InvoiceService::class)->create($payload, $person, $organization),
        };

        if ($type === 'invoice') {
            // amount_due harus terisi agar piutang/pembayaran benar
            $doc->update($paid
                ? ['amount_due' => 0, 'amount_paid' => $calc['total'], 'fully_paid_at' => now()]
                : ['amount_due' => $calc['total'], 'amount_paid' => 0]);
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
}

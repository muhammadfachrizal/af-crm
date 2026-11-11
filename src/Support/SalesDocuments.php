<?php

namespace VentureDrake\LaravelCrm\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Models\TaxRate;
use VentureDrake\LaravelCrm\Models\Tenant;

/** Pembantu penjualan cepat: hitung baris, buat PDF, dan susun pesan berbagi (WhatsApp). */
class SalesDocuments
{
    public static function settings(string $key, $default = null)
    {
        return app('laravel-crm.settings')->get($key, $default);
    }

    public static function currency(): string
    {
        return static::settings('currency', 'IDR') ?: 'IDR';
    }

    /** Tarif pajak produk, dengan urutan fallback yang sama dengan InvoiceService/OrderService. */
    public static function taxRate(Product $product): float
    {
        if ($product->taxRate) {
            return (float) $product->taxRate->rate;
        }

        if ($product->tax_rate) {
            return (float) $product->tax_rate;
        }

        if ($default = TaxRate::where('default', 1)->first()) {
            return (float) $default->rate;
        }

        return (float) (optional(Setting::where('name', 'tax_rate')->first())->value ?? 0);
    }

    public static function unitPrice(Product $product): float
    {
        $price = $product->productPrices->firstWhere('default', true) ?? $product->productPrices->first();

        return $price && $price->unit_price !== null ? round($price->unit_price / 100, 2) : 0.0;
    }

    /**
     * @param  array  $items  [{product_id (external_id), quantity, unit_price?, discount_percent?, comments?}]
     * @return array{lines: array, subtotal: float, tax: float, total: float}
     */
    public static function lines(array $items): array
    {
        $lines = [];
        $subtotal = 0.0;
        $tax = 0.0;

        foreach ($items as $item) {
            $product = Product::with(['taxRate', 'productPrices'])->where('external_id', $item['product_id'] ?? null)->first();
            $qty = (float) ($item['quantity'] ?? 0);

            if (! $product || $qty <= 0) {
                continue;
            }

            $price = isset($item['unit_price']) && $item['unit_price'] !== '' ? (float) $item['unit_price'] : static::unitPrice($product);
            $discount = max(0, min(100, (float) ($item['discount_percent'] ?? 0)));
            $amount = round($qty * $price * (1 - $discount / 100), 2);
            $rate = static::taxRate($product);

            $subtotal += $amount;
            $tax += $amount * $rate / 100;

            $lines[] = [
                'id' => $product->id,
                'product_id' => $product->id,
                'quote_product_id' => null,
                'order_product_id' => null,
                'quantity' => $qty,
                // harga per unit setelah diskon, supaya quantity x unit_price = amount
                'unit_price' => $qty > 0 ? round($amount / $qty, 2) : $price,
                'amount' => $amount,
                'comments' => $item['comments'] ?? ($discount > 0 ? "Diskon {$discount}%" : null),
            ];
        }

        return [
            'lines' => $lines,
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($subtotal + $tax, 2),
        ];
    }

    /** Pelanggan umum (walk-in) per tenant untuk penjualan tanpa data pelanggan. */
    public static function walkInCustomer($user): Person
    {
        return Person::firstOrCreate(
            ['first_name' => 'Pelanggan Umum'],
            ['external_id' => (string) Str::uuid(), 'user_owner_id' => $user->id]
        );
    }

    public static function resolveModel(string $type, string $externalId)
    {
        $class = ['quote' => Quote::class, 'order' => Order::class, 'invoice' => Invoice::class][$type] ?? abort(404);

        return $class::where('external_id', $externalId)->firstOrFail();
    }

    public static function pdf(string $type, $doc): array
    {
        $person = $doc->person;
        $common = [
            'dateFormat' => static::settings('date_format', config('laravel-crm.date_format')),
            'email' => $person?->getPrimaryEmail(),
            'phone' => $person?->getPrimaryPhone(),
            'address' => $person?->getPrimaryAddress(),
            'organization_address' => $doc->organization?->getPrimaryAddress(),
            'fromName' => static::settings('organization_name', null),
            'logo' => static::settings('logo_file', null),
        ];

        $view = ['quote' => 'quotes', 'order' => 'orders', 'invoice' => 'invoices'][$type];
        $number = strtolower((string) ($doc->{$type.'_id'} ?? $doc->id));

        if ($type === 'invoice') {
            $common += [
                'taxName' => static::settings('tax_name', 'Tax'),
                'contactDetails' => static::settings('invoice_contact_details', null),
                'paymentInstructions' => static::settings('invoice_payment_instructions', null),
            ];
        }

        $pdf = Pdf::setOption(['fontDir' => public_path('vendor/laravel-crm/fonts')])
            ->loadView("laravel-crm::{$view}.pdf", [$type => $doc] + $common);

        return [$pdf, "{$type}-{$number}.pdf"];
    }

    protected static function phoneDigits(?string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $raw);

        if ($d === '') {
            return null;
        }

        if (str_starts_with($d, '0')) {
            return '62'.substr($d, 1); // 0812... -> 62812...
        }

        return $d;
    }

    /** Info berbagi: link online (quote/invoice), teks pesan, dan tautan wa.me ke nomor pelanggan. */
    public static function share(string $type, $doc): array
    {
        $labels = ['quote' => 'Penawaran', 'order' => 'Order', 'invoice' => 'Invoice'];
        $number = (string) ($doc->{$type.'_id'} ?? $doc->id);
        $name = $doc->person?->name ?: $doc->organization?->name ?: 'Bapak/Ibu';
        $company = static::settings('organization_name', null) ?: optional(Tenant::find(CurrentTenant::id()))->name ?: config('app.name');
        $currency = $doc->currency ?: 'IDR';
        $total = ($currency === 'IDR' ? 'Rp ' : $currency.' ').number_format(((int) $doc->total) / 100, 0, ',', '.');

        $url = null;
        if ($type === 'quote') {
            $url = URL::temporarySignedRoute('laravel-crm.portal.quotes.show', now()->addDays(14), ['quote' => $doc]);
        } elseif ($type === 'invoice') {
            $url = URL::temporarySignedRoute('laravel-crm.portal.invoices.show', now()->addDays(14), ['invoice' => $doc]);
        }

        $text = "Halo {$name}, berikut {$labels[$type]} {$number} dari {$company} sebesar {$total}.".($url ? " Lihat online: {$url}" : '')." Terima kasih.";

        $phone = static::phoneDigits(optional($doc->person?->getPrimaryPhone())->number ?? null);

        return [
            'type' => $type,
            'id' => $doc->external_id,
            'number' => $number,
            'total' => round(((int) $doc->total) / 100, 2),
            'currency' => $currency,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'online_url' => $url,
            'text' => $text,
            'whatsapp_url' => 'https://wa.me/'.($phone ?: '').'?text='.rawurlencode($text),
        ];
    }
}

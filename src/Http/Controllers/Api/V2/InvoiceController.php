<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\StoreInvoiceRequest;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\UpdateInvoiceRequest;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\InvoiceResource;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\InvoiceLine;
use VentureDrake\LaravelCrm\Models\Label;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Services\InvoiceService;

class InvoiceController extends ApiController
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $query = Invoice::query()->with(['ownerUser', 'person', 'organization', 'labels', 'invoiceLines.product', 'order']);

        $query = $this->applySearch(
            $query,
            $request,
            [
                'reference',
                'invoice_id',
                'person.first_name',
                'person.middle_name',
                'person.last_name',
                'person.maiden_name',
                'organization.name',
            ]
        );

        if ($request->filled('user_owner_id')) {
            $query->where('user_owner_id', $request->input('user_owner_id'));
        }

        $query = $this->applySort(
            $query,
            $request,
            ['created_at', 'updated_at', 'total'],
            '-created_at'
        );

        $invoices = $query->paginate($this->perPage($request))->withQueryString();

        return InvoiceResource::collection($invoices);
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load(['ownerUser', 'person', 'organization', 'labels', 'invoiceLines.product', 'order']);

        return new InvoiceResource($invoice);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $this->authorize('create', Invoice::class);

        [$payload, $person, $organization] = $this->buildPayload($request);

        $invoice = $this->invoiceService->create($payload, $person, $organization);

        $invoice->refresh()->load(['ownerUser', 'person', 'organization', 'labels', 'invoiceLines.product', 'order']);

        return (new InvoiceResource($invoice))->response()->setStatusCode(201);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        [$payload, $person, $organization] = $this->buildPayload($request, $invoice);

        $this->invoiceService->update($payload, $invoice, $person, $organization);

        $invoice->refresh()->load(['ownerUser', 'person', 'organization', 'labels', 'invoiceLines.product', 'order']);

        return new InvoiceResource($invoice);
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);

        $invoice->delete();

        return response()->noContent();
    }

    private function buildPayload(FormRequest $request, ?Invoice $existing = null): array
    {
        $person = $request->filled('person_id')
            ? Person::where('external_id', $request->input('person_id'))->first()
            : ($existing?->person);

        $organization = $request->filled('organization_id')
            ? Organization::where('external_id', $request->input('organization_id'))->first()
            : ($existing?->organization);

        $order = $request->filled('order_id')
            ? Order::where('external_id', $request->input('order_id'))->first()
            : null;

        $labelIds = collect($request->input('labels', []))
            ->map(fn ($uuid) => Label::where('external_id', $uuid)->value('id'))
            ->filter()
            ->values()
            ->all();

        $products = $this->buildProducts($request->input('line_items', []), $existing);

        $payload = (object) [
            'reference' => $request->input('reference', $existing?->reference),
            'issue_date' => $request->input('issue_date', $existing?->issue_date?->toIso8601String()),
            'due_date' => $request->input('due_date', $existing?->due_date?->toIso8601String()),
            'currency' => $request->input('currency', $existing?->currency) ?: 'USD',
            'terms' => $request->input('terms', $existing?->terms),
            'sub_total' => $request->input('subtotal', $existing && $existing->subtotal !== null ? $existing->subtotal / 100 : null),
            'tax' => $request->input('tax', $existing && $existing->tax !== null ? $existing->tax / 100 : null),
            'total' => $request->input('total', $existing && $existing->total !== null ? $existing->total / 100 : null),
            'order_id' => $order?->id ?? $existing?->order_id,
            'user_owner_id' => $request->input('user_owner_id', $existing?->user_owner_id ?? $request->user()?->id),
            'labels' => $labelIds,
            'products' => $products,
        ];

        return [$payload, $person, $organization];
    }

    private function buildProducts(array $lineItems, ?Invoice $existing): ?array
    {
        if (empty($lineItems)) {
            return null;
        }

        return collect($lineItems)->map(function (array $item) use ($existing) {
            $productId = Product::where('external_id', $item['product_id'])->value('id');

            $invoiceLineId = null;
            if ($existing && ! empty($item['id'])) {
                $invoiceLineId = InvoiceLine::where('external_id', $item['id'])
                    ->where('invoice_id', $existing->id)
                    ->value('id');
            }

            return [
                'id' => $productId,
                'product_id' => $productId,
                'invoice_line_id' => $invoiceLineId,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'amount' => $item['amount'],
                'comments' => $item['comments'] ?? null,
            ];
        })->all();
    }

    private function freshInvoice(Invoice $invoice): InvoiceResource
    {
        $invoice->refresh()->load(['ownerUser', 'person', 'organization', 'labels', 'invoiceLines.product', 'order']);

        return new InvoiceResource($invoice);
    }

    /**
     * Catat pembayaran invoice — sama persis dengan InvoicePay::pay() di
     * web. Nominalnya diterima sebagai angka bersih dari mobile (bukan
     * string berformat), jadi tidak kena bug "amount field must be a
     * number" yang ada di web (lihat catatan di InvoicePay.php: itu bug
     * dari mask uang Alpine.js yang ngirim string berformat ke Livewire).
     */
    public function pay(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $amountDue = $invoice->amount_due !== null ? $invoice->amount_due / 100 : 0;

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max($amountDue, 0.01)],
        ]);

        $amountPaidBefore = $invoice->amount_paid !== null ? $invoice->amount_paid / 100 : 0;
        $newAmountDue = round($amountDue - $data['amount'], 2);

        $invoice->update([
            'amount_paid' => $data['amount'] + $amountPaidBefore,
            'amount_due' => $newAmountDue,
            'fully_paid_at' => ($newAmountDue <= 0) ? now() : null,
        ]);

        return $this->freshInvoice($invoice);
    }

    /**
     * Kirim invoice via email — generate PDF + signed portal link, sama
     * persis dengan InvoiceSend.php di web.
     */
    public function send(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $data = $request->validate([
            'to' => ['required', 'string'],
            'subject' => ['required', 'string'],
            'message' => ['required', 'string'],
            'cc' => ['nullable', 'string'],
        ]);

        $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'laravel-crm.portal.invoices.show',
            now()->addDays(14),
            ['invoice' => $invoice]
        );

        $email = $invoice->person?->getPrimaryEmail();
        $phone = $invoice->person?->getPrimaryPhone();
        $address = $invoice->person?->getPrimaryAddress();
        $organizationAddress = $invoice->organization?->getPrimaryAddress();

        $pdfLocation = 'laravel-crm/'.strtolower(class_basename($invoice)).'/'.$invoice->id.'/';
        \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('app/'.$pdfLocation));
        $pdfPath = 'app/'.$pdfLocation.'invoice-'.strtolower($invoice->invoice_id).'.pdf';

        \Barryvdh\DomPDF\Facade\Pdf::setOption([
            'fontDir' => public_path('vendor/laravel-crm/fonts'),
        ])
            ->loadView('laravel-crm::invoices.pdf', [
                'invoice' => $invoice,
                'dateFormat' => app('laravel-crm.settings')->get('date_format', config('laravel-crm.date_format')),
                'taxName' => app('laravel-crm.settings')->get('tax_name', 'Tax'),
                'contactDetails' => app('laravel-crm.settings')->get('invoice_contact_details', null),
                'paymentInstructions' => app('laravel-crm.settings')->get('invoice_payment_instructions', null),
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'organization_address' => $organizationAddress,
            ])->save(storage_path($pdfPath));

        \Illuminate\Support\Facades\Mail::send(new \VentureDrake\LaravelCrm\Mail\SendInvoice([
            'to' => $data['to'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'cc' => $data['cc'] ?? null,
            'onlineInvoiceLink' => $signedUrl,
            'pdf' => $pdfPath,
        ]));

        return response()->json(['message' => 'Invoice berhasil dikirim.']);
    }
}

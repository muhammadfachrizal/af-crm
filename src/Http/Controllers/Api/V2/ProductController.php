<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\StoreProductRequest;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\UpdateProductRequest;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\ProductResource;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Models\ProductCategory;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Models\TaxRate;
use VentureDrake\LaravelCrm\Services\ProductService;

class ProductController extends ApiController
{
    public function __construct(private ProductService $productService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::query()->with(['ownerUser', 'productCategory', 'productPrices', 'taxRate']);

        $query = $this->applySearch(
            $query,
            $request,
            ['name', 'code', 'barcode']
        );

        if ($request->filled('product_category_id')) {
            $categoryId = ProductCategory::where('external_id', $request->input('product_category_id'))->value('id');
            $query->where('product_category_id', $categoryId ?: 0);
        }

        if ($request->filled('user_owner_id')) {
            $query->where('user_owner_id', $request->input('user_owner_id'));
        }

        if ($request->filled('active')) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $query = $this->applySort(
            $query,
            $request,
            ['created_at', 'updated_at', 'name', 'code', 'unit'],
            '-created_at'
        );

        $products = $query->paginate($this->perPage($request))->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);

        $product->load(['ownerUser', 'productCategory', 'productPrices', 'taxRate']);

        return new ProductResource($product);
    }

    public function store(StoreProductRequest $request)
    {
        $this->authorize('create', Product::class);

        $payload = $this->buildPayload($request);

        $product = $this->productService->create($payload);

        $this->applyActive($product, $request);

        $product->refresh()->load(['ownerUser', 'productCategory', 'productPrices', 'taxRate']);

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $payload = $this->buildPayload($request, $product);

        $this->productService->update($product, $payload);

        $this->applyActive($product, $request);

        $product->refresh()->load(['ownerUser', 'productCategory', 'productPrices', 'taxRate']);

        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->noContent();
    }

    /** Status aktif tidak ditangani ProductService (web tidak punya field ini), jadi diset langsung di sini. */
    private function applyActive(Product $product, FormRequest $request): void
    {
        if ($request->has('active')) {
            $product->forceFill(['active' => $request->boolean('active')])->saveQuietly();
        }
    }

    /**
     * Mata uang harga utama HARUS sama dengan yang dicari Product::getDefaultPrice() (pengaturan mata uang CRM).
     * Kalau beda (dulu default 'USD'), harga tersimpan tapi tidak ketemu: di web harga kosong, dan setiap
     * edit membuat baris harga baru. Karena itu mata uang dari klien tidak dipakai untuk harga utama.
     */
    private function defaultCurrency(): string
    {
        $setting = Setting::currency();

        return ($setting instanceof Setting ? $setting->value : null) ?: 'USD';
    }

    private function buildPayload(FormRequest $request, ?Product $existing = null): object
    {
        $currency = $this->defaultCurrency();

        // Kategori: kalau field dikirim (termasuk null) ikuti permintaan, kalau tidak dikirim pertahankan yang lama.
        $categoryId = $existing?->product_category_id;
        if ($request->has('product_category_id')) {
            $categoryId = $request->filled('product_category_id')
                ? ProductCategory::where('external_id', $request->input('product_category_id'))->value('id')
                : null;
        }

        // Tax: tax_rate (angka) harus ikut tax_rate_id, seperti form web (updatedTaxRateId).
        $taxRateId = $request->has('tax_rate_id') ? ($request->input('tax_rate_id') ?: null) : $existing?->tax_rate_id;
        $taxRate = $request->has('tax_rate') ? $request->input('tax_rate') : $existing?->tax_rate;
        if ($request->has('tax_rate_id') && ! $request->has('tax_rate')) {
            $taxRate = $taxRateId ? TaxRate::find($taxRateId)?->rate : null;
        }

        $existingPrice = $existing?->getDefaultPrice();

        return (object) [
            'name' => $request->input('name', $existing?->name),
            'code' => $request->input('code', $existing?->code),
            'barcode' => $request->input('barcode', $existing?->barcode),
            'description' => $request->input('description', $existing?->description),
            'unit' => $request->input('unit', $existing?->unit),
            'unit_price' => $request->has('unit_price')
                ? $request->input('unit_price')
                : ($existingPrice && $existingPrice->unit_price !== null ? round($existingPrice->unit_price / 100, 2) : null),
            'currency' => $currency,
            'tax_rate' => $taxRate,
            'tax_rate_id' => $taxRateId,
            'product_category' => $categoryId,
            'purchase_account' => $request->input('purchase_account', $existing?->purchase_account),
            'sales_account' => $request->input('sales_account', $existing?->sales_account),
            'user_owner_id' => $request->input('user_owner_id', $existing?->user_owner_id ?? $request->user()?->id),
        ];
    }
}

<?php

namespace App\Services\Suppliers;

use App\Jobs\CropProductImage;
use App\Models\Product;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GreenlineSupplier extends AbstractSupplier
{
    private string $baseUrl = 'https://integration.getgreenline.co/api/v1';

    /**
     * Stage configuration only for testing
     */
    private string $stagingBaseUrl = 'https://staging-api.getgreenline.co/api/v1';
    private int $stagingCompanyId = 476;
    private int $stagingLocationId = 477;

    public function syncProducts(): void
    {
        if (!$this->dispensary->greenline) {
            throw new \Exception('Greenline integration is not configured');
        }

        $this->loadCategories();
        $this->loadBrands();
        $this->loadTypes();

        $products = $this->fetchProducts();
        $this->total = count($products);

        Product::disableSearchSyncing();

        foreach ($products as $productData) {
            foreach ($productData['variants'] ?? [$productData] as $productVariant) {
                if (intval($productVariant['price']) == 0) {
                    continue;
                }

                $product = $this->saveProduct($productVariant);
                if (!$product->exists) {
                    continue;
                }

                $this->saveCategory($product, $productData);

                if (!$product->preview_image) {
                    CropProductImage::dispatch($product->id);
                }

                $this->processed++;
            }
        }

        Product::enableSearchSyncing();

        $this->indexProducts();
    }

    public function syncInventory(): void
    {
        $items = $this->fetchInventory();
        $this->total = count($items);

        foreach ($items as $item) {
            if ($this->updateStock($item['productId'], (int) $item['quantity'])) {
                $this->processed++;
            }
        }
    }

    private function fetchProducts(bool $stub = false): array
    {
        if ($stub && $data = Storage::get('stubs/greenline-products.json')) {
            return json_decode($data, true)['products'] ?? [];
        }

        $response = $this->getHttp()->get($this->buildUrl('products'));

        if ($stub) {
            Storage::put('stubs/greenline-products.json', $response->body());
        }

        return $response->json('products', []);
    }

    private function getHttp(): PendingRequest
    {
        return Http::withHeaders([
            $this->isStaging() ? 'api-key' : 'x-api-key' => $this->dispensary->greenline->api_key
        ]);
    }

    private function isStaging(): bool
    {
        return $this->dispensary->greenline->company_id == $this->stagingCompanyId
            && $this->dispensary->greenline->location_id == $this->stagingLocationId;
    }

    private function buildUrl(string $uri): string
    {
        return $this->getBaseUrl()
            . '/external/company/' . $this->dispensary->greenline->company_id
            . '/location/' . $this->dispensary->greenline->location_id
            . '/' . $uri;
    }

    private function getBaseUrl(): string
    {
        return $this->isStaging() ? $this->stagingBaseUrl : $this->baseUrl;
    }

    private function saveProduct(array $productData): Product
    {
        $product = $this->fetchProduct($productData['id']);
        $product->guid = $productData['id'];
        $product->disp_id = $this->dispensary->id;
        $product->name = $productData['name'];
        $product->description = $productData['description'];
        $product->price = floatval($productData['price']) / 100;
        $product->measure = $productData['weight'];
        $product->cannabis_weight = floatval($productData['cannabisWeight']);
        $product->cannabis_volume = floatval($productData['cannabisVolume']);
        $product->min_cbd = floatval($productData['metaData']['minCBD'] ?? 0);
        $product->max_cbd = floatval($productData['metaData']['maxCBD'] ?? 0);
        $product->min_thc = floatval($productData['metaData']['minTHC'] ?? 0);
        $product->max_thc = floatval($productData['metaData']['maxTHC'] ?? 0);
        $product->sale_price = floatval($productData['salePrice']) / 100;
        $product->deposit_fee = floatval($productData['depositFee']);
        $product->barcode = $productData['barcode'];
        $product->sku = $productData['sku'];
        $product->supplier = SupplierType::GREENLINE->value;
        $product->extra = [
            'cbd' => $productData['cbd'] ?? null,
            'thc' => $productData['thc'] ?? null,
            'unit' => $productData['metaData']['unit'] ?? null,
            'taxes' => $productData['taxes'] ?? [],
            'origin_img_url' => $productData['imageUrl'],
            'category_id' => $productData['categoryId'],
            'parent_category_id' => $productData['parentCategoryId'],
            'supplier_name' => $productData['supplierName'],
            'supplier_id' => $productData['supplierId'],
        ];

        // it is important that min will be greater than zero when max is not zero for sorting in catalog
        if (!$product->min_cbd) {
            $product->min_cbd = $product->max_cbd;
        }

        if (!$product->min_thc) {
            $product->min_thc = $product->max_thc;
        }

        if (!$product->extra['unit'] || $product->extra['unit'] == '%') {
            foreach (['min_cbd', 'max_cbd', 'min_thc', 'max_thc'] as $attribute) {
                $product->setAttribute($attribute, $this->normalizeThcCbdValue($product->getAttribute($attribute)));
            }
        }

        if (!$product->brand_id) {
            $product->brand_id = $this->getBrandId($productData['supplierName']);
        }

        if (!$product->product_type) {
            $product->product_type = $this->findOutTypeId($productData['parentCategoryName'], $productData['categoryName']);
        }

        if (!$product->id) {
            $product->is_active = true;
        }

        $product->save();

        return $product;
    }

    private function normalizeThcCbdValue(float $value): float
    {
        return $value > 100 ? $value / 10 : $value;
    }

    private function fetchInventory(bool $stub = false): array
    {
        if ($stub && $data = Storage::get('stubs/greenline-inventory.json')) {
            return json_decode($data, true)['data'] ?? [];
        }

        $response = $this->getHttp()->get($this->buildUrl('posListings/inventory'));

        if ($stub) {
            Storage::put('stubs/greenline-inventory.json', $response->body());
        }

        return $response->json('data', []);
    }
}
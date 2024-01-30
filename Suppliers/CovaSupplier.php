<?php

namespace App\Services\Suppliers;

use App\Helpers\Math;
use App\Jobs\CropProductImage;
use App\Models\Product;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CovaSupplier extends AbstractSupplier
{
    private const CATALOG_GROUP = 'catalogs';
    private const PRICING_GROUP = 'pricing';
    private const AVAILABILITY_GROUP = 'availability';
    private const PRODUCT_BULK_SIZE = 500;
    private const PER_PAGE = 1000;
    private string $baseUrl = 'https://api.covasoft.net';
    protected bool $useStubs = false;
    protected array $availabilities = [];

    public function syncProducts(): void
    {
        if (!$this->dispensary->cova) {
            throw new \Exception('Cova integration is not configured');
        }

        $this->loadCategories();
        $this->loadBrands();
        $this->loadTypes();

        $prices = Arr::pluck($this->fetchPrices(), null, 'CatalogItemId');
        $this->availabilities = Arr::pluck($this->fetchAvailability(), 'Quantity', 'Id');
        $products = $this->fetchProducts();

        Product::disableSearchSyncing();

        foreach (array_chunk(array_column($products, 'CatalogItemId'), self::PRODUCT_BULK_SIZE) as $chunkIds) {
            $productDetails = $this->fetchProductDetailBulk($chunkIds);

            foreach ($productDetails['CatalogItems'] as $catalogItemId => $productDetail) {
                if (!isset($prices[$catalogItemId]) || !$prices[$catalogItemId]['RegularPrice']) {
                    continue;
                }

                $product = $this->saveProduct($catalogItemId, $productDetail, $prices[$catalogItemId]);

                if (!$product->exists) {
                    continue;
                }

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
        $items = $this->fetchAvailability();
        $this->total = count($items);

        if (isset($items['Message'])) {
            throw new \Exception($items['Message']);
        }

        foreach ($items as $item) {
            if ($this->updateStock($item['Id'], (int) $item['Quantity'])) {
                $this->processed++;
            }
        }
    }

    private function saveProduct(string $catalogItemId, array $productData, array $priceData): Product
    {
        $product = $this->fetchProduct($catalogItemId);
        $product->in_stock = max($this->availabilities[$catalogItemId] ?? 0, 0);

        if (!$product->exists && !$product->in_stock) {
            return $product;
        }

        $product->guid = $catalogItemId;
        $product->disp_id = $this->dispensary->id;
        $product->name = $productData['Name'];
        $product->description = $productData['LongDescription'] ?: $productData['ShortDescription'];
        $product->price = floatval($priceData['RegularPrice']);

        $metaData = $this->parseMetaData([
            ...$productData['Specifications'][0]['Fields'] ?? [],
            ...$productData['Specifications'][1]['Fields'] ?? [],
        ]);

        $product->measure = $metaData['weight'] . $metaData['weight_unit'];
        $product->cannabis_weight = $metaData['cannabis_weight'];
        $product->cannabis_volume = 0;
        $product->min_cbd = floatval($metaData['cbd_min']);
        $product->max_cbd = floatval($metaData['cbd_max']);
        $product->min_thc = floatval($metaData['thc_min']);
        $product->max_thc = floatval($metaData['thc_max']);
        $product->sale_price = 0;
        $product->deposit_fee = 0;

        $product->barcode = null;
        $product->sku = $productData['Id'];
        $product->supplier = SupplierType::COVA->value;

        if (!$metaData['brand']) {
            $metaData['brand'] = $productData['VendorSkus'][0]['Entity']['Name'] ?? null;
            $metaData['brand_id'] = $productData['VendorSkus'][0]['Entity']['Id'] ?? null;
        }

        $productData['parentCategoryId'] = $productData['CanonicalClassification']['Id'];
        $productData['parentCategoryName'] = $productData['CanonicalClassification']['Name'];
        $productData['categoryId'] = '';
        $productData['categoryName'] = '';

        $product->extra = [
            'unit' => $metaData['unit'],
            'weight' => $metaData['weight'],
            'weight_unit' => $metaData['weight_unit'],
            'origin_img_url' => $this->getImage($productData['Assets']),
            'category_id' => $productData['categoryId'],
            'parent_category_id' => (string) $productData['parentCategoryId'],
            'supplier_name' => $metaData['brand'],
            'supplier_id' => $metaData['brand_id'],
        ];

        // it is important that min will be greater than zero when max is not zero for sorting in catalog
        if (!$product->min_cbd) {
            $product->min_cbd = $product->max_cbd;
        }

        if (!$product->min_thc) {
            $product->min_thc = $product->max_thc;
        }

        if (!$product->brand_id && $metaData['brand']) {
            $product->brand_id = $this->getBrandId($metaData['brand']);
        }

        if (!$product->product_type) {
            $product->product_type = $this->findOutTypeId($productData['parentCategoryName'], $metaData['type']);
        }

        if (!$product->id) {
            $product->is_active = true;
        }

        if ($product->save()) {
            $this->saveCategory($product, $productData);
        }

        return $product;
    }

    private function parseMetaData(array $specificationFields): array
    {
        $result = array_merge(
            array_fill_keys(['cbd_min', 'cbd_max', 'thc_min', 'thc_max', 'cannabis_weight', 'weight', 'brand_id'], 0),
            array_fill_keys(['unit', 'brand', 'type', 'weight_unit'], null)
        );

        if (!$specificationFields) {
            return $result;
        }

        foreach ($specificationFields as $field) {
            switch ($field['DisplayName']) {
               case 'THC Min':
               case 'THC Max':
               case 'CBD Max':
               case 'CBD Min':
                   $result[Str::snake(strtolower($field['DisplayName']))] = $field['Value'];
                   if ($field['Unit']) {
                       $result['unit'] = $field['Unit'];
                   }
                   break;
               case 'Brand':
                   $result['brand'] = $field['Value'];
                   $result['brand_id'] = $field['Id'];
                   break;
               case 'Equivalent To':
                   $result['cannabis_weight'] = floatval($field['Value']);
                   break;
               case 'Net Weight':
                   $result['weight'] = floatval($field['Value']);
                   $result['weight_unit'] = $field['Unit'];
                   break;
               case 'Strain':
                   $result['type'] = $field['Value'];
                   break;
            }
        }

        $thc = max($result['thc_min'], $result['thc_max']);

        if ($result['cannabis_weight'] == 0 && $result['weight'] > 0 && $thc > 0) {
            $result['cannabis_weight'] = Math::percent($result['weight'], $thc);
        }

        return $result;
    }

    private function getImage(array $assets): ?string
    {
        foreach($assets as $asset) {
            if ($asset['Type'] == 'Image') {
                return $asset['Uri'];
            }
        }

        return null;
    }

    /**
     * @link https://api.covasoft.net/Documentation/Api/PUT-v1-Companies(CompanyId)-Catalog-Items
     */
    private function fetchProducts(): array
    {
        return $this->stubOrRequest(function() {
            return $this
                ->getHttp()
                ->get($this->createUrl("Companies({$this->dispensary->cova->company_id})/Catalog/Items/"))
                ->json();
        }, 'cova-products-' . $this->dispensary->cova->company_id);
    }

    /**
     * @link https://api.covasoft.net/Documentation/Api/GET-v1-Companies(CompanyId)-Entities(LocationId)-CatalogItems-SellingRoomOnly
     */
    private function fetchPrices(): array
    {
        return $this->stubOrRequest(function() {
            return $this
                ->getHttp()
                ->get($this->createUrl("Companies({$this->dispensary->cova->company_id})/ProductPrices?\$filter=EntityId eq " . $this->dispensary->cova->location_id, self::PRICING_GROUP))
                ->json();
        }, 'cova-prices-' . $this->dispensary->cova->company_id . '-' . $this->dispensary->cova->location_id);
    }

    private function fetchAllPages(string $url, array $params = []): array
    {
        $allItems = [];
        $params = [...$params, '$skip' => 0, '$top' => self::PER_PAGE];
        while (true) {
            $chunkItems = $this
                ->getHttp()
                ->get($url, $params)
                ->json();

            if ($chunkItems) {
                $allItems = array_merge($allItems, $chunkItems);
            }

            if (!$chunkItems || count($chunkItems) < self::PER_PAGE) {
                break;
            }

            $params['$skip'] += self::PER_PAGE;
        }

        return $allItems;
    }

    /**
     * @link https://api.covasoft.net/Documentation/Api/GET-v1-Companies(CompanyId)-Entities(LocationId)-CatalogItems-SellingRoomOnly
     */
    private function fetchAvailability(): array
    {
        return $this->stubOrRequest(function() {
            return $this
                ->getHttp()
                ->get($this->createUrl("Companies({$this->dispensary->cova->company_id})/Entities({$this->dispensary->cova->location_id})/CatalogItems/SellingRoomOnly", self::AVAILABILITY_GROUP))
                ->json();
        }, 'cova-availability-' . $this->dispensary->cova->company_id . '-' . $this->dispensary->cova->location_id);
    }

    private function createUrl(string $uri, string $group = self::CATALOG_GROUP): string
    {
        return $this->baseUrl . "/$group/v1/" . $uri;
    }

    /**
     * @link https://api.covasoft.net/Documentation/Api/POST-v1-Companies(CompanyId)-Catalog-Items-ProductDetails-Bulk
     */
    private function fetchProductDetailBulk(array $productCatalogIds): array
    {
        return $this->stubOrRequest(function() use ($productCatalogIds) {
            return $this
                ->getHttp()
                ->withBody('{"CatalogItemIds" : ["' . join('","', $productCatalogIds) . '"]}')
                ->post($this->createUrl("Companies({$this->dispensary->cova->company_id})/Catalog/Items/ProductDetails/Bulk"))
                ->json();
        }, 'cova-detail-bulk-' . $this->dispensary->cova->company_id);
    }

    private function getHttp(): PendingRequest
    {
        return Http::acceptJson()->withToken($this->getAccessToken());
    }

    private function getAccessToken(): ?string
    {
        $cacheKey = 'cova_access_token_' . $this->dispensary->id;

        try {
            $accessToken = cache()->get($cacheKey);
        } catch (\Throwable) {
            $accessToken = null;
        }

        if (!$accessToken) {
            try {
                $response = Http::asForm()->post('https://accounts.iqmetrix.net/v1/oauth2/token/', [
                    'grant_type' => 'password',
                    'client_id' => $this->dispensary->cova->client_id,
                    'client_secret' => $this->dispensary->cova->client_secret,
                    'username' => $this->dispensary->cova->username,
                    'password' => $this->dispensary->cova->password,
                ]);

                $data = $response->json();
                if (isset($data['Message'])) {
                    throw new \Exception($data['Description']);
                }

                if ($response->json('access_token')) {
                    cache()->put($cacheKey, $response->json('access_token'), $response->json('expires_in'));
                    $accessToken = $response['access_token'];
                }
            } catch (\Throwable $e) {
                logger($e);
                throw new \Exception($e->getMessage());
            }
        }

        return $accessToken;
    }
}
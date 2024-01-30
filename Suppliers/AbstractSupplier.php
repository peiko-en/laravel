<?php

namespace App\Services\Suppliers;

use App\Models\Brand;
use App\Models\Dispensary;
use App\Models\Product;
use App\Models\Product_types;
use App\Models\SupplierCategoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class AbstractSupplier implements SupplierInterface
{
    protected Dispensary $dispensary;
    /**
     * @var SupplierCategoryInterface|Model
     */
    protected SupplierCategoryInterface $supplierCategory;
    protected SupplierType $supplier;
    protected array $availableBrands = [];
    protected array $supplierCategories = [];
    protected int $processed = 0;
    protected int $total = 0;
    protected bool $useStubs = false;
    /**
     * @var Product_types[]
     */
    protected ?Collection $types = null;

    public function __construct(
        Dispensary $dispensary,
        SupplierCategoryInterface $supplierCategory,
        SupplierType $supplier
    )
    {
        set_time_limit(60 * 30);

        $this->supplierCategory = $supplierCategory;
        $this->supplier = $supplier;
        $this->setDispensary($dispensary);
    }

    public function setDispensary(Dispensary $dispensary): self
    {
        $this->dispensary = $dispensary;

        return $this;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    protected function fetchProduct(string $catalogItemId): Product|Model
    {
        return Product::query()
            ->where('guid', $catalogItemId)
            ->where('disp_id', $this->dispensary->id)
            ->where('supplier', $this->supplier->value)
            ->firstOrNew();
    }

    protected function indexProducts(): void
    {
        Product::query()
            ->where('disp_id', $this->dispensary->id)
            ->where('supplier', $this->supplier->value)
            ->searchable();
    }

    protected function loadBrands(): void
    {
        if ($this->availableBrands) {
            return;
        }

        $this->availableBrands = Brand::query()
            ->get()
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function loadTypes(): void
    {
        if (!is_null($this->types)) {
            return;
        }

        $this->types = Product_types::query()->get();
    }

    protected function getBrandId(string $brandName): int
    {
        $res = Arr::where($this->availableBrands, function($value) use ($brandName) {
            return strcasecmp($value, $brandName) === 0;
        });

        if ($res) {
            return key($res);
        } else {
            /** @var Brand $brand */
            $brand = Brand::query()
                ->firstOrCreate(
                    ['alias' => Str::slug($brandName)],
                    ['name' => $brandName]
                );

            $this->availableBrands[$brand->id] = $brand->name;

            return $brand->id;
        }
    }

    protected function findOutTypeId(?string $parentCategoryName = null, ?string $categoryName = null): int
    {
        /** @var Product_types $type */
        $type = $this->types->firstWhere(function(Product_types $type) use($parentCategoryName, $categoryName) {
            return Str::contains($type->product_type, [$categoryName, $parentCategoryName], true);
        });

        return intval($type?->id);
    }

    protected function stubOrRequest(callable $callback, string $stub)
    {
        if ($this->useStubs && $data = Storage::get("stubs/$stub.json")) {
            return json_decode($data, true) ?? [];
        }

        $data = $callback();

        if ($this->useStubs && $data) {
            Storage::put("stubs/$stub.json", json_encode($data));
        }

        return $data;
    }

    protected function loadCategories(): void
    {
        if ($this->supplierCategories) {
            return;
        }

        $categories = $this->supplierCategory::query()
            ->where('dispensary_id', $this->dispensary->id)
            ->orderBy('parent_id')
            ->get();

        $catIdMap = $categories->pluck($this->supplierCategory->getSupplierCatAttribute(), 'id');

        $categories->each(function(Model|SupplierCategoryInterface $cat) use ($catIdMap) {
            if ($cat->isParent()) {
                $this->supplierCategories[$cat->getSupplierCatAttribute()] = $cat->toArray();
            } else {
                $parentSupplierCatId = $catIdMap->get($cat->getParentId());
                if (isset($this->supplierCategories[$parentSupplierCatId])) {
                    $this->supplierCategories[$parentSupplierCatId]['sub'][$cat->getSupplierCatAttribute()] = $cat->toArray();
                }
            }
        })->toArray();
    }

    protected function saveCategory(Product $product, array $data): void
    {
        $category = $this->supplierCategories[$data['parentCategoryId']] ?? [];

        if (!empty($category['category_id'])) {
            if (!$product->categories->contains('id', $category['category_id'])) {
                $product->categories()->detach();
                $product->categories()->attach($category['category_id']);
            }
        } elseif (!$category) {
            /** @var SupplierCategoryInterface $category */
            $category = $this->supplierCategory::query()->create([
                'parent_id' => 0,
                'dispensary_id' => $product->disp_id,
                'name' => $data['parentCategoryName'],
                $this->supplierCategory->getSupplierCatAttribute() => $data['parentCategoryId'],
            ]);

            $this->supplierCategories[$category->getSupplierCategory()] = $category->toArray();
        }
    }

    protected function updateStock(string $guid, int $quantity): int
    {
        return Product::query()
            ->where('guid', $guid)
            ->where('disp_id', $this->dispensary->id)
            ->where('supplier', $this->supplier->value)
            ->update(['in_stock' => max($quantity, 0)]);
    }
}
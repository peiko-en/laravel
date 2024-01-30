<?php

namespace App\Services\Producers\fpay;


use App\Helpers\{Arr, Debugger, Tools};
use App\Models\Entity\{Country, Service, ServiceCountry};
use App\Services\Producers\ImportAbstract;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;


/**
 * Class Import
 * @package App\Services\Producers\fpay
 * @property Api $api
 */
class Import extends ImportAbstract
{
    /**
     * @var Country
     */
    private $country;

    private $categories = [
        'Communication' => ['category_id' => 1, 'active_phone' => 1],
        'PINs' => ['category_id' => 23, 'active_phone' => 0],
        'Shops' => ['category_id' => 4, 'active_phone' => 0],
        'Utility bills' => ['category_id' => 25, 'active_phone' => 0],
        'Mobile bundles' => ['category_id' => 24, 'active_phone' => 1],
    ];

    public function execute()
    {
        try {
            $this->country = Country::query()->where('iso', 'MYS')->first();

            if (!$this->country) {
                throw new \Exception('IND country not found');
            }

            $this->process();
        } catch (\Exception $e) {
            Debugger::command($e);
        }
    }

    private function process()
    {
        $products = $this->fetchData();

        $progressBar = $this->createProgressBar(count($products));

        foreach ($products as &$product) {
            $progressBar->advance();

            $product['service_code'] = $this->getServiceCode($product['keyword']. ' -' . $product['name']);

            /** @var Service $service */
            $service = Service::query()
                ->where('external_service', $this->producer)
                ->where('external_id', $product['external_id'])
                ->first();

            if (!$service) {
                $service = $this->createService($product);
                $service->extra = Arr::only($product, ['keyword', 'length']);
                $service->save();

                if (!$service->activePhone() && $service->category_id == 25) {
                    $range = Tools::parseMinMax($product['length']);
                    $this->customFieldSort = 0;
                    $this->createField($service, 'service_number', (int) $range['min'], (int) $range['max']);
                }
            }

            $this->assignCountries($service, $product);
        }

        $progressBar->finish();
    }

    private function assignCountries(Service $service, $props)
    {
        $serviceCountry = ServiceCountry::findByServiceIdAndCountry($service->id, $this->country->id);

        if (!$serviceCountry) {
            $amountVisibility = empty($props['range']) ? 0 : 1;

            $serviceCountry = $service->countries()->create([
                'country_id' => $this->country->id,
                'min_amount' => $props['range']['min'] ?? 0,
                'max_amount' => $props['range']['max'] ?? 0,
                'step' => $amountVisibility ? 1 : 0,
                'fee_amount' => 0,
                'fee_percent' => 0,
                'amount_visible' => $amountVisibility,
                'currency' => $this->country->currency
            ]);
        }

        if ($props['vouchers']) {
            foreach ($props['vouchers'] as $voucher) {
                $this->syncVoucher($serviceCountry, [
                    'code' => $props['external_id'] . '_' . $voucher,
                    'amount' => $voucher,
                ]);
            }
        }
    }

    private function fetchData()
    {
        try {
            $handle = fopen(resource_path('data/fpay/products_new.csv'), "r");
            $result = [];

            while (($data = fgetcsv($handle, 0, ";")) !== false) {
                $productId = $data[0];
                $categoryProps = $this->categories[trim($data[5])] ?? [];
                $amounts = $data[3];

                if ($productId == 'ProductID' || !$productId || !$categoryProps || !$amounts) {
                    continue;
                }

                $result[] = array_merge([
                    'external_id' => (int) $productId,
                    'name' => trim($data[1]),
                    'keyword' => trim($data[2]),
                    'category_id' => $categoryProps['category_id'],
                    'active_phone' => $categoryProps['active_phone'],
                    'length' => $data[4],
                ], $this->collectVoucherAndRangeAmount($amounts));
            }

            return $result;
        } catch (\Exception $e) {
            Debugger::command($e);
            return [];
        }
    }

    private function collectVoucherAndRangeAmount($amounts)
    {
        $vouchers = [];
        $range = [];

        foreach (explode(',', $amounts) as $item) {
            if (strpos($item, '-') === false) {
                $vouchers[] = $item;
            } else {
                $range = Tools::parseMinMax($item);
            }
        }

        return [
            'vouchers' => $vouchers,
            'range' => $range,
        ];
    }
}

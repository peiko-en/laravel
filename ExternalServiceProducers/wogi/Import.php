<?php

namespace App\Services\Producers\wogi;

use App\Helpers\Arr;
use App\Models\Entity\Country;
use App\Models\Entity\Service;
use App\Models\Entity\ServiceCountry;
use App\Services\Producers\ImportAbstract;
use Illuminate\Support\Str;

/**
 * @property Api $api
 */
class Import extends ImportAbstract
{
    private $countries = ['SGP'];
    private $serviceProductCodes = [];

    public function execute()
    {
        ini_set("memory_limit", "-1");
        set_time_limit(0);

        foreach ($this->countries as $countryIso) {
            /** @var Country $country */
            $country = Country::query()->where('iso', $countryIso)->first();
            if (!$country) {
                continue;
            }

            $this->api->initCredentials($country->iso);
            $nextPage = 1;

            do {
                $res = $this->api->products(500, $nextPage);
                $products = $res['data'] ?? [];

                if ($products) {
                    $this->process($country, $products);
                } else {
                    break;
                }

                $this->process($country, $res['data'] ?? []);

                $nextPage = $res['meta']['pagination']['nextPage'] ?? null;
            } while($nextPage);
        }
    }

    private function process(Country $country, $products)
    {
        $combineProducts = $this->combineProducts($products);
        unset($products);

        $progressBar = $this->createProgressBar(count($combineProducts));

        foreach ($combineProducts as $product) {
            $this->serviceProductCodes[] = $product['code'];

            if (!$product['vouchers']) {
                continue;
            }

            /** @var Service $service */
            $service = Service::query()
                ->where('external_service', $this->producer)
                ->where('code', $product['code'])
                ->first();

            if (!$service) {
                $photo = null;
                if (!empty($product['brand']['logo'])) {
                    $photo = $this->createPhotoUrl(
                        $product['brand']['logo'],
                        $this->producer . '-' . $product['brand']['gatewayId']
                    );
                }

                $service = $this->createService([
                    'name' => $product['name'],
                    'service_code' => $product['code'],
                    'photo' => $photo,
                    'status' => Service::STATUS_DEACTIVATE,
                ]);

                $service->extra = [
                    'objectClass' => $product['objectClass'],
                    'validity' => $product['validity'],
                    'productDiscountRate' => $product['productDiscountRate'],
                    'brand' => Arr::except($product['brand'], [
                        'name',
                        'shortDescription',
                        'longDescription',
                        'cardTerms',
                        'keywords',
                        'redemptionOnlineInstructions',
                        'redemptionInstoreInstructions',
                        'website',
                    ])
                ];

                $service->syncTexts([
                    'en' => ['description' => "<p>" . $product['description'] . "</p>"]
                ]);
            }

            if ($service->isRemoveCandidate()) {
                $service->status = Service::STATUS_DEACTIVATE;
            }

            $service->save();
            $this->assignCountries($service, $product, $country->id);

            $progressBar->advance();
        }

        $progressBar->finish();

        $this->deleteMissingProducts($this->serviceProductCodes);
    }

    /**
     * @param array $products
     * @return array
     */
    private function combineProducts(array $products)
    {
        $combinedProducts = [];

        foreach ($products as $product) {
            $code = $this->producer . '-' . Str::slug($product['name']);

            if (!isset($combinedProducts[$code])) {
                $combinedProducts[$code] = array_merge($product, [
                    'code' => $code,
                    'vouchers' => []
                ]);
            }

            $voucherAmount = floatval($product['priceMin']['amount']);
            $combinedProducts[$code]['vouchers'][$voucherAmount] = [
                'gatewayId' => $product['gatewayId'],
                'price' => $voucherAmount,
            ];
        }

        return $combinedProducts;
    }

    private function assignCountries(Service $service, array $product, $countryId)
    {
        $serviceCountry = ServiceCountry::findByServiceIdAndCountry($service->id, $countryId);

        $minAmount = floatval($product['priceMin']['amount']);
        $maxAmount = floatval($product['priceMax']['amount']);

        if ($maxAmount < $minAmount) {
            $maxAmount = $minAmount;
        }

        $isRange = $maxAmount > $minAmount;

        if (!$serviceCountry) {
            $data = [
                'country_id' => $countryId,
                'min_amount' => 0,
                'max_amount' => 0,
                'step' => 0,
                'fee_amount' => 0,
                'fee_percent' => 0,
                'currency' => $product['priceMin']['currency']['isoCode'],
                'amount_visible' => 0,
            ];

            if ($isRange) {
                $data['min_amount'] = $minAmount;
                $data['max_amount'] = $maxAmount;
                $data['ext_face_value_id'] = $product['gatewayId'];
                $data['amount_visible'] = 1;
            }

            $serviceCountry = $service->countries()->create($data);
        } else {
            if ($isRange) {
                $serviceCountry->min_amount = $minAmount;
                $serviceCountry->max_amount = $maxAmount;

                if ($serviceCountry->isDirty()) {
                    $serviceCountry->save();
                }

                if ($serviceCountry->vouchers) {
                    $serviceCountry->vouchers()->delete();
                }
            }
        }

        if ($isRange) {
            return;
        }

        foreach ($product['vouchers'] as $voucher) {
            $this->syncVoucher($serviceCountry, [
                'code' => $voucher['gatewayId'],
                'amount' => $voucher['price'],
                'cost' => $voucher['price'],
                'cost_currency' => $product['priceMin']['currency']['isoCode'],
            ]);
        }

        if (!$serviceCountry->wasRecentlyCreated) {
            $this->removeIrrelevantVouchers($serviceCountry, array_column($product['vouchers'], 'price'));
        }
    }
}
<?php

namespace App\Services\Producers\esimgo;

use App\Helpers\{Arr, Debugger};
use App\Models\Entity\{Country, Service, ServiceCountry};
use App\Services\Producers\ImportAbstract;
use Illuminate\Support\Str;

/**
 * @package App\Services\Producers\esimgo
 * @property Api $api
 */
class Import extends ImportAbstract
{
    private $perPage = 500;
    private $photo = '/images/services/esimway.svg';
    private $currency = 'USD';
    private $countries = [];

    public function execute()
    {
        ini_set("memory_limit", "-1");
        set_time_limit(0);

        $this->countries = Country::query()
            ->get()
            ->pluck('id', 'iso2')
            ->toArray();

        try {
            $this->process();
        } catch (\Throwable $e) {
            Debugger::command($e);
        }
    }

    private function process()
    {
        $products = $this->fetchCatalogue();
        $progressBar = $this->createProgressBar(count($products));

        //$profiler = new PerformanceProfiler();
        //$profiler->trace('begin');

        $codes = [];

        foreach ($products as $product) {
            if (!Str::startsWith($product['name'], 'esims_')) {
                $progressBar->advance();
                continue;
            }

            $code = $this->producer . '-' . Str::slug($product['name']);
            $codes[] = $code;

            $countryIds = $this->getCountryIds($product);
            if (!$countryIds) {
                $progressBar->advance();
                continue;
            }

            /** @var Service $service */
            $service = Service::query()
                ->where('external_service', $this->producer)
                ->where('code', $code)
                ->first();

            if (!$service) {
                $name = join(',', array_slice(explode(',', $product['description']), 0, -2));

                $service = $this->createService([
                    'name' => $name,
                    'service_code' => $code,
                    'photo' => $this->photo,
                    'status' => Service::STATUS_DEACTIVATE,
                ]);

                $service->extra = [
                    'name' => $product['name'],
                    'dataAmount' => $product['dataAmount'],
                    'price' => $product['price'],
                    'duration' => $product['duration'],
                    'autostart' => $product['autostart'],
                    'currency' => $this->currency,
                ];

                $this->addDescription($service, $product);
                if ($service->save()) {
                    $this->saveContent($service);
                }
            } else {
                $checkFields = ['dataAmount', 'price', 'duration'];
                if (array_diff(Arr::only($product, $checkFields), Arr::only($service->extra, $checkFields))) {
                    $service->status = Service::STATUS_REMOVE_CANDIDATE;
                    $service->mergeExtra(Arr::only($product, $checkFields));
                    $service->save();
                } elseif ($service->isRemoveCandidate()) {
                    $service->status = Service::STATUS_ACTIVE;
                    $service->save();
                }
            }

            foreach ($countryIds as $countryId) {
                $this->assignCountries($service, $product, $countryId);
            }

            $progressBar->advance();

            //$profiler->trace('product-' . $i);
            //Debugger::command($profiler->getTraceList());
        }

        $progressBar->finish();

        $this->deleteMissingProducts($codes);
    }

    private function getCountryIds(array $product)
    {
        $ids = [];

        if (!empty($product['countries'])) {
            foreach ($product['countries'] as $country) {
                $countryId = $this->countries[$country['iso']] ?? 0;
                if ($countryId) {
                    $ids[] = $countryId;
                }
            }
        }

        return $ids;
    }

    private function fetchCatalogue()
    {
        $products = [];

        try {
            $page = 0;
            do {
                $result = $this->api->catalogue(++$page, $this->perPage);
                if (!isset($result['bundles'])) {
                    break;
                }

                if ($result['bundles']) {
                    $products = array_merge($products, $result['bundles']);
                }
            } while(count($result['bundles']) >= $this->perPage);
        } catch (\Exception $e) {
            Debugger::command($e);
            return [];
        }

        return $products;
    }

    private function assignCountries(Service $service, $product, $countryId)
    {
        $serviceCountry = ServiceCountry::findByServiceIdAndCountry($service->id, $countryId);

        if (!$serviceCountry) {
            $serviceCountry = $service->countries()->create([
                'country_id' => $countryId,
                'min_amount' => 0,
                'max_amount' => 0,
                'step' => 0,
                'fee_amount' => 0,
                'fee_percent' => 0,
                'currency' => $this->currency,
                'amount_visible' => 0,
            ]);
        }

        $this->syncVoucher($serviceCountry, [
            'code' => $product['name'],
            'amount' => $product['price'],
            'cost' => $product['price'],
            'cost_currency' => $this->currency,
            'caption' => ($product['dataAmount'] / 1000) . "GB, {$product['duration']} Days",
        ]);
    }

    private function addDescription(Service $service, array $product)
    {
        if (!empty($product['roamingEnabled'])) {
            $roaming = join(', ', array_column($product['roamingEnabled'], 'name'));

            $service->syncTexts([
                'en' => [
                    'description' => "<p>With eSimWay staying connected abroad is easy and safe.</p><p>After all we do not have any hidden fees roaming fees and bills! Just visit our website select your preferred data plan scan the QR code you received by email and you're ready for your trip! Stay connected with friends and family using your favorite apps like: TikTok, Facebook, Instagram, WhatsApp, Telegram and more.</p><p>One eSIM installation on a compatible device for all your future travel. No need to switch sim cards choose a new eSIM with one click.</p><p>This eSIM will also work in the following countries: $roaming</p>"
                ],
                'ru' => [
                    'description' => "<p>С eSimWay оставаться на связи за границей легко и безопасно.</p><p>У нас нет никаких скрытых комиссий платы за роуминг и счетов! Просто посетите наш веб-сайт выберите предпочтительный тарифный план отсканируйте QR-код полученный по электронной почте и вы готовы к поездке! Оставайтесь на связи с друзьями и семьей используя ваши любимые приложения такие как: TikTok, Facebook, Instagram, WhatsApp, Telegram и другие. </p><p>Одна установка eSIM на совместимом устройстве на все ваши будущие путешествия. Не нужно менять сим-карты выберите новую eSIM одним щелчком.</p><p>Эта eSIM будет так же работать в странах: $roaming.</p>"
                ]
            ]);
        }
    }

    private function saveContent(Service $service)
    {
        $this->assignFags($service, [
            [
                'en' => [
                    'question' => 'What is an eSIM card?',
                    'answer' => "An eSIM serves as a conventional digital SIM card, enabling activation of your mobile service provider's plan without the need for a physical SIM card. Users activate this eSIM by scanning a QR code or inputting manual details provided via email.",
                ],
                'ru' => [
                    'question' => 'Что такое eSIM?',
                    'answer' => "eSIM представляет собой стандартную цифровую SIM-карту, которая позволяет активировать сотовый тариф от оператора без необходимости использования физической SIM-карты. Активация такой eSIM на стороне пользователя осуществляется путем сканирования QR-кода или ввода соответствующей информации вручную. Эту информацию пользователь получает по электронной почте.",
                ],
            ],
            [
                'en' => [
                    'question' => 'In which countries will my eSIM work?',
                    'answer' => "The list of countries in which this eSIM will work is in the description section on the same page.",
                ],
                'ru' => [
                    'question' => 'В каких странах моя eSIM будет работать?',
                    'answer' => "Список стран в которых будет работать эта eSIM находится в разделе примечание на этой же странице.",
                ]
            ],
            [
                'en' => [
                    'question' => 'How to activate eSIM?',
                    'answer' => "You must be connected to wifi, then manually enter your eSIM information or point your phone's camera at the QR code. Most data plans can only be activated in the country selected by the eSIM.",
                ],
                'ru' => [
                    'question' => 'Как активировать eSIM?',
                    'answer' => "Вы должны быть подключены к wifi, затем введите вручную информацию о eSIM или наведите камеру телефона на QR код. Большинство тарифных планов могут быть активированы только в стране выбранной eSIM.",
                ]
            ],
            [
                'en' => [
                    'question' => 'Does my phone support eSIM?',
                    'answer' => "Devices starting in 2019 supporting eSIM: Apple iPhone Xr, Xs or newer; Samsung Fold or newer; Huawei P40 or newer; Pixel 4 or newer.",
                ],
                'ru' => [
                    'question' => 'Мой телефон поддерживает eSIM?',
                    'answer' => "Устройства, начиная с 2019 года, поддерживающие eSIM: Apple iPhone Xr, Xs или новее; Samsung Fold или новее; Huawei P40 или новее; Пиксель 4 или новее.",
                ]
            ],
            [
                'en' => [
                    'question' => 'Can I install a hotspot WIFI via eSIM?',
                    'answer' => "Yes, you can share internet from any device that supports eSIM.",
                ],
                'ru' => [
                    'question' => 'Могу ли я создать точку доступа через eSIM?',
                    'answer' => "Да, вы можете раздавать интернет с любого устройства, которое поддерживает eSIM.",
                ],
            ],
            [
                'en' => [
                    'question' => 'What speed will eSIM have?',
                    'answer' => "The speed depends on the coverage of the mobile operator and can be 2G, 3G, 4G, LTE, 5G.",
                ],
                'ru' => [
                    'question' => 'Какая скорость будет у eSIM?',
                    'answer' => "Скорость зависит от покрытия мобильного оператора и может быть 2G, 3G, 4G, LTE, 5G.",
                ]
            ],
            [
                'en' => [
                    'question' => 'Can I make calls from my eSIM?',
                    'answer' => "This eSIM is for internet only.",
                ],
                'ru' => [
                    'question' => 'Могу ли я совершать звонки c моей eSIM?',
                    'answer' => "Эта eSIM, предназначена только для интернета.",
                ]
            ],
            [
                'en' => [
                    'question' => 'What is the amount of traffic for eSIM?',
                    'answer' => "The number of gigabytes is indicated in the eSIM name.",
                ],
                'ru' => [
                    'question' => 'Какой размер трафика у eSIM?',
                    'answer' => "Количество гигабайт указано в названии eSIM.",
                ]
            ]
        ]);

        $this->assignVariables($service, [
            'category' => [
                [
                    'en' => 'mobile',
                    'ru' => 'mobilnyy'
                ]
            ],
            'types' => [
                [
                    'en' => 'esim',
                    'ru' => 'esim'
                ]
            ],
            'verbs' => [
                [
                    'en' => 'buy',
                    'ru' => 'kupit'
                ]
            ],
            'payments' => [
                [
                    'en' => 'coinbase',
                    'ru' => 'coinbase'
                ],
                [
                    'en' => 'coinpayments',
                    'ru' => 'coinpayments'
                ],
                [
                    'en' => 'perfect-money',
                    'ru' => 'perfect-money'
                ],
                [
                    'en' => 'advcash',
                    'ru' => 'advcash'
                ],
                [
                    'en' => 'payeer',
                    'ru' => 'payeer'
                ],
                [
                    'en' => 'visa',
                    'ru' => 'visa'
                ],
                [
                    'en' => 'mastercard',
                    'ru' => 'mastercard'
                ]
            ]
        ]);
    }
}

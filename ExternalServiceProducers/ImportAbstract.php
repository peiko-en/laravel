<?php

namespace App\Services\Producers;

use App\Helpers\Debugger;
use App\Models\Entity\MaterialFaq;
use App\Models\Entity\Service;
use App\Models\Entity\ServiceCountry;
use App\Models\Entity\ServiceField;
use App\Models\Entity\ServiceVoucher;
use App\Models\Repository\VariablesRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

abstract class ImportAbstract
{
    protected $producer;
    protected $api;
    protected $customFieldSort = 0;
    private $removeCandidateIds = [];

    public function __construct($producer)
    {
        $this->producer = $producer;
        $this->api = Producer::instance($this->producer)->apiInstance();
    }

    public abstract function execute();

    protected function getServiceCode($keywords)
    {
        return Str::slug($this->producer . '_' . $keywords);
    }

    protected function createService($props)
    {
        $service = new Service();
        $service->category_id = $props['category_id'] ?? 0;
        $service->original_name = $props['name'];
        $service->status = $props['status'] ?? Service::STATUS_ACTIVE;
        $service->active_phone = $props['active_phone'] ?? 0;
        $service->is_external = 1;
        $service->is_auto_pay = 1;
        $service->external_service = $this->producer;
        $service->external_id = $props['external_id'] ?? 0;
        $service->code = $props['service_code'];
        $service->photo = $props['photo'] ?? null;

        return $service;
    }

    protected function createField(Service $service, $param, $min = 0, $max = 0, $hint = null)
    {
        /** @var ServiceField $field */
        $field = $service->fields()->create([
            'kind' => ServiceField::KIND_TEXT,
            'data_type' => ServiceField::TYPE_STR,
            'min' => (int) $min,
            'max' => (int) $max,
            'is_required' => 1,
            'sort' => ++$this->customFieldSort,
            'param_name' => $param,
            'is_editable' => 0,
        ]);

        if ($hint) {
            $field->hint_message->setTranslates(['en' => $hint], 'service_field');
            $field->hint_message->save();
        }

        $field->save();
    }

    protected function syncVoucher(ServiceCountry $serviceCountry, $props = [])
    {
        /** @var ServiceVoucher $voucher */
        $voucher = $serviceCountry->vouchers->firstWhere('ext_face_value_id', $props['code']);
        if (!$voucher) {
            $voucher = new ServiceVoucher();
            $voucher->service_id = $serviceCountry->service_id;
            $voucher->service_country_id = $serviceCountry->id;
            $voucher->ext_face_value_id = $props['code'];
        }

        $voucher->amount = $props['amount'];
        $voucher->cost = $props['cost'] ?? 0;
        $voucher->cost_currency = $props['cost_currency'] ?? null;
        $voucher->caption = $props['caption'] ?? null;
        $voucher->save();
    }

    protected function removeIrrelevantVouchers(ServiceCountry $serviceCountry, array $amounts = [])
    {
        $removeIds = [];

        foreach ($serviceCountry->vouchers as $voucher) {
           if (!in_array($voucher->getAmount(), $amounts)) {
               $removeIds[] = $voucher->id;
           }
        }

        if ($removeIds) {
            ServiceVoucher::query()->whereIn('id', $removeIds)->delete();
        }
    }

    protected function deleteMissingProducts(array $codes)
    {
        Service::query()
            ->where('external_service', $this->producer)
            ->whereIn('status', [Service::STATUS_ACTIVE, Service::STATUS_DEACTIVATE])
            ->chunkById(500, function (Collection $services) use ($codes) {
                /** @var Service $service */
                foreach ($services as $service) {
                    if (!in_array($service->code, $codes)) {
                        $this->removeCandidateIds[] = $service->id;
                    }
                }
            });

        if ($this->removeCandidateIds) {
            Service::query()->whereIn('id', $this->removeCandidateIds)->update([
                'status' => Service::STATUS_REMOVE_CANDIDATE
            ]);
        }

        $this->removeCandidateIds = [];
    }

    protected function createProgressBar($max = 0)
    {
        return new ProgressBar(new ConsoleOutput(), $max);
    }

    protected function createPhotoUrl($url, $filenamePrefix = null)
    {
        if (!$url) {
            return null;
        }

        $basePath = '/images/services/';

        $pathParts = explode('/', $url);
        $filename = array_pop($pathParts);

        if ($filenamePrefix) {
            $filename = $filenamePrefix . '-' . $filename;
        }
        $filenamePath = public_path($basePath . $filename);

        if (is_file($filenamePath)) {
            return $basePath . $filename;
        }

        try {
            $content = file_get_contents($url);
            if ($content) {
                file_put_contents($filenamePath, $content);
                return $basePath . $filename;
            }
        } catch (\Exception $e) {
            Debugger::command($e);
        }

        return null;
    }

    /**
     * @param Service $service
     * @param array $faqs
     * Example: [
     *   [
     *    'en' => [
     *        'question' => 'Question en',
     *        'answer' => 'Answer en',
     *     ],
     *    'ru' => [
     *        'question' => 'Question ru',
     *        'answer' => 'Answer ru',
     *    ],
     *    ...
     *   ],
     *   ...
     * ]
     * @return void
     */
    protected function assignFags(Service $service, array $faqs)
    {
        foreach ($faqs as $key => $faq) {
            foreach ($faq as $locale => &$columns) {
                $columns['question'] = "⏩ " . $columns['question'];
            }

            /** @var MaterialFaq $serviceFaq */
            $serviceFaq = $service->faqs()->newModelInstance(['is_active' => 1]);
            $serviceFaq->model_id = $service->id;
            $serviceFaq->model_type = $service->getMorphClass();
            $serviceFaq->syncTexts($faq);
            $serviceFaq->save();
        }
    }

    /**
     * @param Service $service
     * @param array $variables
     * example:
     * Array (
     *       [category] => Array
     *           (
     *               [0] => Array
     *                   (
     *                       [en] => mobile
     *                       [ru] => mobilnyy
     *                   )
     *           )
     *
     *       [payments] => Array
     *           (
     *               [0] => Array
     *                   (
     *                       [en] => coinbase
     *                       [ru] => coinbase
     *                   )
     *
     *               [1] => Array
     *                   (
     *                       [en] => mastercard
     *                       [ru] => mastercard
     *                   )
     *
     *           )
     *   )
     *
     */
    protected function assignVariables(Service $service, array $variables)
    {
        foreach ($variables as $variableSlug => $keywords) {
            foreach ($keywords as $keyword) {
                foreach ($keyword as $locale => $keywordSlug) {
                    $keywordId = app(VariablesRepository::class)->getVariableKeywordId($variableSlug, $keywordSlug, $locale);

                    if ($keywordId) {
                        $service->keywords()->create([
                            'var_keyword_id' => app(VariablesRepository::class)->getVariableKeywordId($variableSlug, $keywordSlug, $locale)
                        ]);
                    }
                }
            }
        }
    }

    protected function saveMockup($key, $data)
    {
        $dir = base_path('resources/data/' . $this->producer);
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        file_put_contents($dir . '/' . $key . '.json', json_encode($data));
    }

    protected function getMockData($key)
    {
        $dir = base_path('resources/data/' . $this->producer);
        return json_decode(file_get_contents($dir . '/' . $key . '.json'), true);
    }
}

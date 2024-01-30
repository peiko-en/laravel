<?php

namespace App\Services\Producers\esimgo;

use App\Helpers\Tools;
use App\Models\Entity\TopupTransaction;
use App\Services\MailService;
use App\Services\Producers\TopupAbstract;
use Exception;
use Illuminate\Support\Str;

class Topup extends TopupAbstract
{
    /**
     * @var Api
     */
    private $api;

    public function __construct(Api $api)
    {
        $this->api = $api;
    }

    public function topup(TopupTransaction $transaction)
    {
        $response = $this->api->sendOrder($transaction);

        if (isset($response['status']) && $response['status'] == 'completed') {
            $transaction->external_service_key = $response['orderReference'];
            $params = $this->parseEsimParams($transaction->external_service_key);

            $response['iccid'] = $params['iccid'] ?? '';

            $this->complete($transaction, $response);

            (new MailService())->common(
                $transaction->order->email,
                Tools::createNameFromEmail($transaction->order->email),
                view('mail.messages.esimgo', [
                    'serviceName' => $transaction->order->service->getName(),
                    'iccid' => $params['iccid'],
                    'matchingId' => $params['matching_id'],
                    'rspUrl' => $params['rsp_url'],
                ])->render(),
                $transaction->order->service->getName(),
                [
                    'attachData' => $params['attachData']
                ]
            );
        }

        return $response;
    }

    /**
     * @throws Exception
     */
    public function parseEsimParams($reference)
    {
        $contentBytes = $this->api->esimsAssignments($reference);

        $fileTemp = tmpfile();

        if (fwrite($fileTemp, $contentBytes) === false) {
            throw new Exception('Cannot save into a temp file');
        }

        $fileTempLocation = stream_get_meta_data($fileTemp)['uri'] ?? null;
        if (!$fileTempLocation) {
            throw new Exception('Cannot define temp file location');
        }

        $zip = new \ZipArchive();

        if ($zip->open($fileTempLocation) === false) {
            throw new \Exception('Cannot open a zip file');
        }

        $params = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $ext = trim(strrchr($stat['name'], '.'), '.');
            $content = $zip->getFromIndex($i);

            if ($ext == 'png') {
                $params['attachData'] = [
                    'data' => $content,
                    'name' => $stat['name']
                ];
            } elseif ($ext == 'csv') {
                $data = collect(explode("\n", $content))
                    ->mapWithKeys(function($line, $index) {
                        $values = str_getcsv($line);

                        if ($index == 0) {
                            $values = array_map(function($title) {
                                return Str::snake(strtolower($title));
                            }, $values);
                        }

                        return [$index => $values];
                    });

                $params = array_merge($params,
                    array_combine($data->get(0, []), $data->get(1, []))
                );
            }
        }

        return $params;
    }
}

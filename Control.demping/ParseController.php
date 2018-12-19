<?php

namespace App\Http\Controllers\Admin;

use App\Model\ParseLog;
use Carbon\Carbon;
use Html;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Classes\LaravelExcelWorksheet;
use Yajra\Datatables\Facades\Datatables;

class ParseController extends Controller
{
    public function index()
    {
        return view('parse.index');
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function dataTable(Request $request)
    {
        $search = $request->get('columns');

        $dateRange = '';
        if (isset($search[3])) {
            $dateRange = $search[3]['search']['value'];
        }

        $userName = '';
        if (isset($search[2])) {
            $userName = $search[2]['search']['value'];
        }

        /** @var Datatables $dataTable */
        $dataTable = Datatables::of(ParseLog::prepareDataTable())
            ->editColumn('user_name', function(ParseLog $model) {
                return ($model->user_id > 0) ? $model->user_name : ParseLog::$botName;
            })
            ->addColumn('url', function(ParseLog $model) {
                return Html::link('#', $model->url, ['onclick' => 'showUrlDetail('.(int)$model->shop_url_id.', this, event)']);
            });


        if ($dateRange) {
            if ($periods = parse_period_date($dateRange)) {
                $dataTable->filterColumn('parse_logs.created_at', function($query) use ($periods) {
                    $query->where('parse_logs.created_at', '>=', $periods['from']);
                    $query->where('parse_logs.created_at', '<=', $periods['to']);
                });
            }
        }

        if ($userName == ParseLog::$botName) {
            $dataTable->filterColumn('users.name', function($query) {
                $query->where('parse_logs.user_id', 0);
            });
        }

        return $dataTable->make(true);
    }

    public function export(Request $request, \Maatwebsite\Excel\Excel $excel)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $logs = ParseLog::exportDataTable($request);

        $excel->create('parse_journal_'.date('Y_m_d_H_i_s'), function($excel) use ($request, $logs) {
            $excel->sheet('Parse journal', function(LaravelExcelWorksheet $sheet) use ($logs) {
                $sheet->appendRow([
                    'id', 'shop_url_id', 'user_name', 'account_name', 'created_at', 'url'
                ]);

                foreach ($logs->cursor() as $row) {
                    $sheet->appendRow([
                        $row->id, $row->shop_url_id, $row->user_name, $row->account_name, $row->created_at, $row->url
                    ]);
                }
            });
        })->export('xls');
    }
}

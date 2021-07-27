<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Khill\Lavacharts\Lavacharts;
use App\Models\ChartModel;
use App\Models\MaterialRequest;
use App\Charts\MaterialRequestChart;
use App\Models\MaterialPurchased;
use App\Charts\PurchaseOrderChart;
use App\Models\Supplier;
use App\Models\SalesOrder;
use App\Models\ManufacturingMaterials;
use Carbon\Carbon;
use DB;
use DataTables;
use App\Http\Controllers\ReportExcelExport\excel_export;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\Storage;


class ChartController extends Controller
{
    public function generate_sample_chart(Request $request){

        $date_from      = date('Y-m-d', strtotime($request->date_from));
        $date_to        = date('Y-m-d', strtotime($request->date_to));
        $filter_type    = $request->filter_type;

        $pie_chart      = \Lava::DataTable();

        if ($filter_type == 'yearly') {
            $date_from = !empty($date_from) ? date('Y',strtotime($date_from)) : date('Y');
        }
        else if ($filter_type == 'monthly') {
            $date_from = !empty($date_from) ? date('Y-m',strtotime($date_from)) : date('Y-m');
        }

        $chart_data = ChartModel::get_chart_data($date_from,$filter_type);
        $table_data = ChartModel::get_table_data($date_from,$filter_type);
        // $chart_data = !empty($chart_data) ? $chart_data[0] : [];
        $complete   = !empty($chart_data['Completed'])  ? array_column($chart_data['Completed'],'count','date') : [];
        $pending    = !empty($chart_data['Pending'])    ? array_column($chart_data['Pending'],  'count','date') : [];

        // dd($complete,$pending);

        $pie_chart->addStringColumn('Stocks')
        ->addNumberColumn('Completed')
        ->addNumberColumn('Pending');

        if ($filter_type == 'yearly') {
            for ($i=1; $i <= 12; $i++) { 
                $i = ($i < 10) ? '0' . $i : $i;
                $complete_count = !empty($complete[$date_from . '-' . $i])   ? $complete[$date_from . '-' . $i]   : 0;
                $pending_count  = !empty($pending[$date_from . '-' . $i])    ? $pending[$date_from . '-' . $i]    : 0;
                $pie_chart->addRow([date('F Y',strtotime($date_from . '-' . $i)), $complete_count, $pending_count]);
            }
        }
        else if ($filter_type == 'monthly') {
            $end_day    = date('d',strtotime($date_to));
            for ($i = 1; $i <= $end_day; $i++) { 
                $i = ($i < 10) ? '0' . $i : $i;
                $complete_count = !empty($complete[$date_from   . '-' . $i]) ? $complete[$date_from  . '-' . $i] : 0;
                $pending_count  = !empty($pending[$date_from    . '-' . $i]) ? $pending[$date_from   . '-' . $i] : 0;
                $pie_chart->addRow([date('F d, Y',strtotime($date_from . '-' . $i)), $complete_count, $pending_count]);
            }
        }

        // ->addRow(['Completed',      (!empty($chart_data['completed'])   ? $chart_data['completed']  : 0),(!empty($chart_data['pending'])     ? $chart_data['pending']    : 0)]);
        // ->addRow(['Pending',        (!empty($chart_data['pending'])     ? $chart_data['pending']    : 0)]);

        \Lava::BarChart('pie-chart', $pie_chart, [
            'title'  => 'Work Order Report ',
            'is3D'   => true,
            'isStacked' => true,
            'event' => [
                'ready' => 'getImageCallBack'],
            'PNG' => true,
            'animation' => [
                'easing' => 'inAndout',
                'startup' => true,
                'duration' => 600,
            ],
            'legend' => [
                'position' => 'bottom',
                'textStyle' => [
                    'fontSize' => 16,
                ]
            ],
            'colors' => ['#ffa600', '#003f5c'],
            'backgroundColor' => '#f7f7f7',
            'width' => 1550,
            'height' => 900,
            'pieSliceTextStyle' => [
                'fontSize' => 15, 
            ]
        ]);

    
        return View('chart.chart',['table_data' => $table_data,'is_excel' => false]);
    }

    public function generate_reports_sales (Request $request){

        $date_from      = date('Y-m-d', strtotime($request->date_from));
        $date_to        = date('Y-m-d', strtotime($request->date_to));
        $filter_type    = $request->filter_type;

        $pie_chart1 = \Lava::DataTable();

        $pie_chart1->addStringColumn('Percent')
        ->addNumberColumn('Fully Paid')
        ->addNumberColumn('With Outstanding Balance');

        if ($filter_type == 'yearly') {
            $date_from = !empty($date_from) ? date('Y',strtotime($date_from)) : date('Y');
        }
        else if ($filter_type == 'monthly') {
            $date_from = !empty($date_from) ? date('Y-m',strtotime($date_from)) : date('Y-m');
        }

        $sales_data     = ChartModel::get_sales_data($date_from,$filter_type);
        $sales_data     = !empty($sales_data) ? array_column($sales_data,'total_count','sales_status') : [];
        $table_data     = ChartModel::get_sales_table_data($date_from,$filter_type);
        // dd($table_data);
        // $table_data1    = ChartModel::get_sales_table_data($date_from,$date_to);
        $fully          = !empty($chart_data['Fully Paid'])                  ? array_column($chart_data['Fully Paid'],'count','date') : [];
        $outstanding    = !empty($chart_data['With Outstanding Balance'])    ? array_column($chart_data['With Outstanding Balance'],  'count','date') : [];

        if ($filter_type == 'yearly') {
            for ($i=1; $i <= 12; $i++) { 
                $i = ($i < 10) ? '0' . $i : $i;
                $fully_count           = !empty($fully[$date_from . '-' . $i])   ? $fully[$date_from . '-' . $i]   : 0;
                $outstanding_count     = !empty($outstanding[$date_from . '-' . $i])    ? $outstanding[$date_from . '-' . $i]    : 0;
                $pie_chart1->addRow([date('F Y',strtotime($date_from . '-' . $i)), $fully_count, $outstanding_count]);
            }
        }
        else if ($filter_type == 'monthly') {
            $end_day    = date('d',strtotime($date_to));
            for ($i = 1; $i <= $end_day; $i++) { 
                $i = ($i < 10) ? '0' . $i : $i;
                $fully_count = !empty($fully[$date_from   . '-' . $i]) ? $fully[$date_from  . '-' . $i] : 0;
                $outstanding_count  = !empty($outstanding[$date_from    . '-' . $i]) ? $outstanding[$date_from   . '-' . $i] : 0;
                $pie_chart1->addRow([date('F d, Y',strtotime($date_from . '-' . $i)), $fully_count, $outstanding_count]);
            }
        }


        $pie_chart1->addStringColumn('Stocks')
        ->addNumberColumn('Percent')
        ->addRow(['Fully Paid',                 (!empty($sales_data['Fully Paid'])                      ? $sales_data['Fully Paid']                 : 0)])
        ->addRow(['With Outstanding Balance',   (!empty($sales_data['With Outstanding Balance'])        ? $sales_data['With Outstanding Balance']   : 0)]);

        \Lava::PieChart('pie-chart1', $pie_chart1, [
            'title'  => ' ',
            'is3D'   => true,
            'legend' => [
                'position' => 'bottom',
                'textStyle' => [
                    'fontSize' => 16,
                ]
            ],
            'colors' => ['#ffa600', '#003f5c'],
            'backgroundColor' => '#f7f7f7',
            'width' => 600,
            'height' => 600,
            'pieSliceTextStyle' => [
                'fontSize' => 15, 
            ]
            
        ]);

        return View('modules.reports.reports_sales',['table_data' => $table_data]);
    }

    public function generate_report_trends (Request $request){
        $date_from      = date('Y-m-d', strtotime($request->date_from));
        $date_to        = date('Y-m-d', strtotime($request->date_to));
        $filter_type    = $request->filter_type;

        $line_chart = \Lava::DataTable();

        $line_data      = ChartModel::get_sales_order_data($filter_type,$date_from,$date_to);

        // dd($line_data)

        $line_chart->addDateColumn('Date')
             ->addNumberColumn('Sales');

        if (!empty($line_data)) {
            if ($filter_type == 'yearly') {
                foreach ($line_data as $key => $value) {
                    $line_chart->addRow([$key,  $value]); //With Outstandiong Balance ,$value[1]
                }
            }
            else{
                $start_day  = date('d',strtotime($date_from));
                $end_day    = date('d',strtotime($date_to));
                $temp_date  = explode('-',$date_from);
                $date       = '';
                for ($i = $start_day; $i < $end_day; $i++) {
                    $date = $temp_date[0] . '-' . $temp_date[1] . '-' . $i;
                    $date = date('Y-m-d',strtotime($date));
                    $line_chart->addRow([$date,  (!empty($line_data[$date]) ? $line_data[$date] : 0)]); //With Outstandiong Balance ,$value[1]
                }
            }
        }

        if ($filter_type == 'yearly') {
            $date_from = !empty($date_from) ? date('Y',strtotime($date_from)) : date('Y');
        }
        else if ($filter_type == 'monthly') {
            $date_from = !empty($date_from) ? date('Y-m',strtotime($date_from)) : date('Y-m');
        }
        
        $sales_data      = ChartModel::get_sales_data($date_from,$filter_type);
        $sales_data      = !empty($sales_data) ? array_column($sales_data,'total_count','sales_status') : [];
        $table_data1     = ChartModel::get_sales_trends_table($date_from,$filter_type);
        
        \Lava::LineChart('line-chart', $line_chart, [
            'title' => 'Sales Order Trends',
            'legend' => [
                'position' => 'bottom',
                'textStyle' => [
                    'fontSize' => 16,
                ]
            ],
            'width' => 1200,
            'height' => 600,
            'backgroundColor' => '#f7f7f7',
            'hAxis' => [
                'format' => 'MMM',
            ],
            
            'chartArea' => [
                'width' => 960,
                'height' => 400,
            ],
            'pointSize' => 7,
            'series' => [
                0 => [
                    'color' => '#003f5c',
                ],
            ],
            
        ]);
        return view('chart.report_sales_line',['line_data' => $line_data , 'table_data1' => $table_data1]);
    }

    //MATERIAL PURCHASE
    public function generate_reports_materials_purchased(Request $request){

        $date_from = date('Y-m-d', strtotime($request->date_from));
        $date_to   = date('Y-m-d', strtotime($request->date_to));
        $filter_type = $request->filter_type;
        // $lava = new Lavacharts;

        // $datefrom = request()->get('date_from');
        // $filter_type = request()->get('filter_type');
        // $date_to = request()->get('date_to');


        $yearly = date('Y', strtotime($date_from ));
        $month =  date('m', strtotime($date_from ));
        // return  $datefrom ;


        // $jas = 'monthly';
        if ( $filter_type == 'yearly'){
        $materials_purchasedPie     = \Lava::DataTable();
        $materials_purchasedDataTable = MaterialPurchased::whereYear('purchase_date', '=', $yearly)->get();
        $mp_status=  $materials_purchasedDataTable->pluck('mp_status')->unique();
        

        $purchase_order_Completed = MaterialPurchased::where('mp_status','=','Completed')
        ->whereYear('purchase_date', '=',  $yearly)->pluck('mp_status');
        $purchase_order_ToReceive = MaterialPurchased::where('mp_status','=','To Receive')
        ->whereYear('purchase_date', '=', $yearly)->pluck('mp_status'); 
        $purchase_order_ToReceiveBill = MaterialPurchased::where('mp_status','=','To Receive and bill')
        ->whereYear('purchase_date', '=',  $yearly)->pluck('mp_status');
        $purchase_order_ToBill = MaterialPurchased::where('mp_status','=','To Bill')
        ->whereYear('purchase_date', '=',  $yearly)->pluck('mp_status');  
        }

        else if( $filter_type== 'monthly'){
            $materials_purchasedPie     = \Lava::DataTable();
            $materials_purchasedDataTable = MaterialPurchased::whereYear('purchase_date', '=',  $yearly)
            ->whereMonth('purchase_date', '=', $month)
            ->get();
            $mp_status=  $materials_purchasedDataTable->pluck('mp_status')->unique();
            

            $purchase_order_Completed = MaterialPurchased::where('mp_status','=','Completed')
            ->whereYear('purchase_date', '=', $yearly)
            ->whereMonth('purchase_date', '=', $month)
            ->pluck('mp_status');
            $purchase_order_ToReceive = MaterialPurchased::where('mp_status','=','To Receive')
            ->whereYear('purchase_date', '=', $yearly)
            ->whereMonth('purchase_date', '=',  $month)
            ->pluck('mp_status'); 
            $purchase_order_ToReceiveBill = MaterialPurchased::where('mp_status','=','To Receive and bill')
            ->whereYear('purchase_date', '=',  $yearly)
            ->whereMonth('purchase_date', '=',  $month)
            ->pluck('mp_status');
            $purchase_order_ToBill = MaterialPurchased::where('mp_status','=','To Bill')
            ->whereYear('purchase_date', '=',  $yearly)
            ->whereMonth('purchase_date', '=',  $month)
            ->pluck('mp_status');  
    
        }


        $Count_purchase_order_Completed [] = $purchase_order_Completed->count();
        $Count_purchase_order_Receive [] = $purchase_order_ToReceive->count();
        $Count_purchase_order_ToReceiveBill [] = $purchase_order_ToReceiveBill->count();
        $Count_purchase_order_ToBill [] = $purchase_order_ToBill->count();

    

        $materials_purchasedPie->addStringColumn('Status')
                                ->addNumberColumn('Percent')
                                ->addRow(['Completed', $Count_purchase_order_Completed])
                                ->addRow(['Receive', $Count_purchase_order_Receive])
                                ->addRow(['To Receive and Bill', $Count_purchase_order_ToReceiveBill])
                                ->addRow(['To Bill', $Count_purchase_order_ToBill]);

        \Lava::PieChart('pie-chart', $materials_purchasedPie, [
            'title'  => ' ',
            'is3D'   => true,
            'legend' => [
                'position' => 'bottom',
                'textStyle' => [
                    'fontSize' => 16,
                ]
            ],
            'colors' => ['#003f5c','#ffa600','#93bcd4','#0f81c4'],
            'backgroundColor' => '#f7f7f7',
            'width' => 800,
            'height' => 500,
            'pieSliceTextStyle' => [
                'fontSize' => 15, 
            ]
        ]);

        return view('modules.reports.reports_materials_purchased',
            ['mp_status' => $mp_status],
            ['materials_purchasedDataTable' => $materials_purchasedDataTable],
            ['materials_purchasedPie' => $materials_purchasedPie]
        );
    }
 
    //PURCHASE AND SALES
    public function generate_reports_purchase_and_sales(Request $request){
        
        
        $date_from = date('Y-m-d', strtotime($request->date_from));
        $date_to   = date('Y-m-d', strtotime($request->date_to));
        $filter_type = $request->filter_type;
        // $datefrom = request()->get('date_from');
        // $filter_type = request()->get('filter_type');
        // $date_to = request()->get('date_to');

        //$day = date('d', strtotime($datefrom));
        //return $datefrom;

        $yearly = date('Y', strtotime($date_from));
        $month=  date('m', strtotime($date_from));   

        // $jas =  date('Y', $date_to);
        //  return  $datefrom  ;

        
            // $lava = new Lavacharts;
            $current_year = Carbon::now()->format('Y');

            //$materials_purchased_data = MaterialPurchased::where('mp_status','=','Completed')->pluck('mp_status')->get();

            //SCOREBOARDS DATA
            //ANNUAL PURCHASE
            $annual_purchase_sum = MaterialPurchased::select(
                MaterialPurchased::raw('SUM(total_cost) as sums'), 
                MaterialPurchased::raw("DATE_FORMAT(purchase_date,'%y') as year"))
                ->whereYear('purchase_date','=', $yearly)
                ->groupBy('year')
                ->orderBy('purchase_date','asc')
                ->get();

            
            

            $purchase_order_Completed = MaterialPurchased::where('mp_status','=','Completed')->pluck('mp_status');
            $purchase_order_ToReceive = MaterialPurchased::where('mp_status','=','To Receive')->pluck('mp_status'); 
            $purchase_order_ToReceiveBill = MaterialPurchased::where('mp_status','=','To Receive and bill')->pluck('mp_status');
            $purchase_order_ToBill = MaterialPurchased::where('mp_status','=','To Bill')->pluck('mp_status'); 

            //PURCHASE ORDER TO RECEIVE
            $po_to_receive = $purchase_order_ToReceive->count() + $purchase_order_ToReceiveBill->count();
            //PURCHASE ORDER TO BILL
            $po_to_bill = $purchase_order_ToReceiveBill->count()  + $purchase_order_ToBill->count();
            //SUPPLIER
            $supplier =  MaterialPurchased::select('supp_quotation_id', DB::raw('count(supp_quotation_id) quantity'))->groupBy('supp_quotation_id')->get();
            $active_supplier = $supplier->count();
        
        
            if(count($annual_purchase_sum)){
                $annual_purchase = $annual_purchase_sum->pluck('sums')[0];
                
            }    
            else
            {
                    $annual_purchase =  0;
                    $po_to_receive =0;
                    $po_to_bill =0;
                    $active_supplier = 0;
            }      

            //COLUMN CHART

            if ( $filter_type == 'yearly'){
                $materials_purchasedDataTable = MaterialPurchased::where('mp_status','=','Completed')->whereYear('purchase_date', '=', $yearly)
                ->get();


            $purchase_order_trends = MaterialPurchased::select(
                MaterialPurchased::raw('SUM(total_cost) as sums'), 
                MaterialPurchased::raw("DATE_FORMAT(purchase_date,'%m') as months"))
                    ->whereYear('purchase_date','=', $yearly)
                    ->groupBy('months')
                    ->orderBy('purchase_date','asc')
                    ->get();
                    $data = [0,0,0,0,0,0,0,0,0,0,0,0];
                        foreach($purchase_order_trends as $order){
                        $data[$order->months-1] = $order->sums;
                        }

            $sales_order_trends = SalesOrder::select(
                MaterialPurchased::raw('SUM(cost_price) as sums'), 
                MaterialPurchased::raw("DATE_FORMAT(transaction_date,'%m') as months"))
                    ->whereYear('transaction_date','=', $yearly)
                    ->groupBy('months')
                    ->orderBy('transaction_date','asc')
                    ->get();
                    $data2 = [0,0,0,0,0,0,0,0,0,0,0,0];
                    foreach($sales_order_trends as $order2){
                    $data2[$order2->months-1] = $order2->sums;
                    }
            }

            else{
            
                $materials_purchasedDataTable = MaterialPurchased::where('mp_status','=','Completed')->whereYear('purchase_date', '=', $yearly)
                ->whereMonth('purchase_date', '=', $month)->get();
            
            $purchase_order_trends = MaterialPurchased::select(
                MaterialPurchased::raw('SUM(total_cost) as sums'), 
                MaterialPurchased::raw("DATE_FORMAT(purchase_date,'%m') as months"))
                    ->whereYear('purchase_date','=', $yearly)
                    ->groupBy('months')
                    ->orderBy('purchase_date','asc')
                    ->get();
                    $data = [0,0,0,0,0,0,0,0,0,0,0,0];
                        foreach($purchase_order_trends as $order){
                        $data[$order->months-1] = $order->sums;
                        }

            $sales_order_trends = SalesOrder::select(
                MaterialPurchased::raw('SUM(cost_price) as sums'), 
                MaterialPurchased::raw("DATE_FORMAT(transaction_date,'%m') as months"))
                    ->whereYear('transaction_date','=', $yearly)
                    ->groupBy('months')
                    ->orderBy('transaction_date','asc')
                    ->get();
                    $data2 = [0,0,0,0,0,0,0,0,0,0,0,0];
                    foreach($sales_order_trends as $order2){
                    $data2[$order2->months-1] = $order2->sums;
                    }

            }
    

        $purchase_sales     = \Lava::DataTable();

            $purchase_sales->addStringColumn('Status')
                    ->addNumberColumn('Expenses')
                    ->addNumberColumn('Sales')
                    ->addRow(['Jan', $data[0], $data2[0]])
                    ->addRow(['Feb', $data[1], $data2[1]])
                    ->addRow(['Mar', $data[2], $data2[2]])
                    ->addRow(['Apr', $data[3], $data2[3]])
                    ->addRow(['May', $data[4], $data2[4]])
                    ->addRow(['Jun', $data[5], $data2[5]])
                    ->addRow(['Jul', $data[6], $data2[6]])
                    ->addRow(['Aug', $data[7], $data2[7]])
                    ->addRow(['Sept', $data[8], $data2[8]])
                    ->addRow(['Oct', $data[9], $data2[9]])
                    ->addRow(['Nov', $data[10], $data2[10]])
                    ->addRow(['Dec', $data[11], $data2[11]]);
                    

                    \Lava::ColumnChart('column-chart', $purchase_sales, [
                        'title' => 'Purchase Order & Sales Order Trends',
                        'width' => 1250,
                        'height' => 600,
                        'legend' => [
                            'position' => 'bottom',
                            'textStyle' => [
                                'fontSize' => 16,
                            ]
                        ],
                        'chartArea' => [
                            'width' => 960,
                            'height' => 400,
                        ],
                        'groupWidth' => '33%',
                        'colors' => ['#003f5c','#ffa600'],
                        'vAxis' => [
                            'format' => '₱#,###,###.##'
                        ],
                        'backgroundColor' => 'transparent'
                    ]);

                        
                    //$product = MaterialPurchased::create($request->all());
                    //return $product;

                // $jas = json_encode(MaterialPurchased::table('materials_purchased')->get()->toArray());
                // return $jas;


                return view('modules.reports.reports_purchase_and_sales',['purchase_sales' => $purchase_sales],
                compact('annual_purchase','po_to_receive','po_to_bill','active_supplier'));
    }


    public function generate_reports_delivery (Request $request){
        $date_from      = date('Y-m-d', strtotime($request->date_from));
        $date_to        = date('Y-m-d', strtotime($request->date_to));
        $filter_type    = $request->filter_type;

        $pie_chart2 = \Lava::DataTable();

        $pie_chart2->addStringColumn('Percent')
        ->addNumberColumn('To Ship')
        // ->addNumberColumn('Shipped')
        ->addNumberColumn('Received');

        if ($filter_type == 'yearly') {
            $date_from = !empty($date_from) ? date('Y',strtotime($date_from)) : date('Y');
        }
        else if ($filter_type == 'monthly') {
            $date_from = !empty($date_from) ? date('Y-m',strtotime($date_from)) : date('Y-m');
        }
        
        $delivery_data      = ChartModel::get_delivery_data($date_from,$filter_type);
        $delivery_data      = !empty($delivery_data) ? array_column($delivery_data,'total_count','delivery_status') : [];
        $table_data         = ChartModel::get_delivery_table_data($date_from,$filter_type);
        // dd($delivery_data);
        $to_ship            = !empty($delivery_data['To Ship'])        ?         :0;
        // $shipped            = !empty($delivery_data['Shipped'])         ?        :0;
        $received           = !empty($delivery_data['Received'])        ?        :0;
        if ($filter_type == 'yearly') {
            for ($i=1; $i <= 12; $i++) { 
                $i = ($i < 10) ? '0' . $i : $i;
                $toship_count           = !empty($to_ship[$date_from . '-' . $i])   ? $to_ship[$date_from . '-' . $i]   : 0;
                // $shipped_count          = !empty($shipped[$date_from . '-' . $i])   ? $shipped[$date_from . '-' . $i]   : 0;
                $received_count         = !empty($received[$date_from . '-' . $i])  ? $received[$date_from . '-' . $i]  : 0;
                $pie_chart2->addRow([date('F Y',strtotime($date_from . '-' . $i)), $toship_count, $received_count]);
            }
        }
        else if ($filter_type == 'monthly') {
            $end_day    = date('d',strtotime($date_to));
            for ($i = 1; $i <= $end_day; $i++) { 
                $i = ($i < 10) ? '0' . $i : $i;
                $toship_count   =     !empty($to_ship[$date_from   . '-' . $i])     ? $to_ship[$date_from  . '-' . $i]      : 0;
                // $shipped_count  =     !empty($shipped[$date_from    . '-' . $i])    ? $shipped[$date_from   . '-' . $i]     : 0;
                $received_count  =    !empty($received[$date_from    . '-' . $i])   ? $received[$date_from   . '-' . $i]    : 0;
                $pie_chart2->addRow([date('F d, Y',strtotime($date_from . '-' . $i)), $toship_count, $received_count]);
            }
        }

        $pie_chart2->addStringColumn('Stocks')
        ->addNumberColumn('Percent')
        ->addRow(['To Ship      ',              (!empty($delivery_data['To Ship'])                          ? $delivery_data['To Ship']                 : 0)])
        // ->addRow(['Shipped      ',              (!empty($delivery_data['Shipped'])                          ? $delivery_data['Shipped']                 : 0)])
        ->addRow(['Received     ',              (!empty($delivery_data['Received'])                         ? $delivery_data['Received']                : 0)]);
        
        \Lava::PieChart('pie-chart2', $pie_chart2, [
            'title'  => 'Delivery Status ',
            'is3D'   => true,
            'legend' => [
                'position' => 'bottom',
                'textStyle' => [
                    'fontSize' => 16,
                ]
            ],
            'colors' => ['#ffa600', '#003f5c'],
            'backgroundColor' => '#f7f7f7',
            'width' => 600,
            'height' => 600,
            'pieSliceTextStyle' => [
                'fontSize' => 15, 
            ]

        ]);
        
        return view('modules.reports.reports_delivery', ['table_data' => $table_data]);

    }
 
    public function export (Request $request) {

        $date_filter_type   = $request->input('date-filter-option');
        $date_from          = $request->input('date-from');
        $report_type        = $request->input('report-name');
        $export_type        = $request->input('button-export');

        
        if ($date_filter_type == 'monthly') {
            $date_from = !empty($date_from) ? explode('/',$date_from)               : [];
            $date_from = !empty($date_from) ? $date_from[1] . '-' . $date_from[0]   : date('Y-m');
        }
        else if($date_filter_type == 'yearly'){
            $date_from = !empty($date_from) ? $date_from : date('Y');
        }

        $report_name = "";

        if ($report_type == 1){
            $report_name = "Work_Order_Report";
        }
        else if ($report_type == 2){
            $report_name = "Sales_Order_Report";
        }
        else if ($report_type == 3){
            $report_name = "Sales_Trends_Report";
        }
        else if ($report_type == 7){
            $report_name = "Delivery_Report";
        }
        else if ($report_type == 8){
            $report_name = "Fast_Moving_Report";
        }



        $data = [
            'date_filter_type'  => $date_filter_type,
            'date_from'         => $date_from,
            'report_type'       => $report_type,
            'filename'          => $report_name,
        ];
        
        if ($export_type == 'excel') {
            return $this->export_excel($data);
        }
        else if ($export_type == 'pdf') {
            return $this->export_pdf($data);
        }

    }

    public function export_excel ($data) {
        
        $date_from          = !empty($data['date_from'])        ? $data['date_from']        : '';
        $report_type        = !empty($data['report_type'])      ? $data['report_type']      : '';
        $date_filter_type   = !empty($data['date_filter_type']) ? $data['date_filter_type'] : '';
        $filename           = !empty($data['filename'])         ? $data['filename']         : '';
        
        return Excel::download(new excel_export($data),$filename . '.xlsx');
    }

    public function export_pdf ($data = []) {
            $paper_size         = !empty($data['paper_size'])       ? $data['paper_size']       : array(0,0,612,1009); // default paper size (A4)
            $file_name          = !empty($data['filename'])         ? $data['filename']         : 'Test.pdf';
            $date_from          = !empty($data['date_from'])        ? $data['date_from']        : '';
            $report_type        = !empty($data['report_type'])      ? $data['report_type']      : '';
            $date_filter_type   = !empty($data['date_filter_type']) ? $data['date_filter_type'] : '';

            if (!empty($data['report_type']) && $data['report_type'] == 1) {

                $table_data = ChartModel::get_table_data($date_from,$date_filter_type);
                $pdf = PDF::loadView('modules.reports.ExcelExportBlade.work_order_report',['table_data' => $table_data,'has_width' => true]);
            }
            else if (!empty($data['report_type']) && $data['report_type'] == 2) {

                $table_data     = ChartModel::get_sales_table_data($date_from,$date_filter_type);
                $sales_data     = ChartModel::get_sales_data($date_from,$date_filter_type);
                $pdf = PDF::loadView('modules.reports.ExcelExportBlade.sales_report',['pie-chart1' => $sales_data, 'table_data' => $table_data,'has_width' => false]);
            }
            else if (!empty($data['report_type']) && $data['report_type'] == 3) {

                $table_data1     = ChartModel::get_sales_trends_table($date_from,$date_filter_type);
                $pdf = PDF::loadView('modules.reports.ExcelExportBlade.sales_trends',['table_data1' => $table_data1, 'has_width' => true]);
            }
            else if (!empty($data['report_type']) && $data['report_type'] == 7) {

                $table_data         = ChartModel::get_delivery_table_data($date_from,$date_filter_type);
                $pdf = PDF::loadView('modules.reports.ExcelExportBlade.delivery_reports',['table_data' => $table_data, 'has_width' => true]);
            }
            else if (!empty($data['report_type']) && $data['report_type'] == 8) {

                $table_data         = ChartModel::get_fast_moving_table($date_from,$date_filter_type);
                $pdf = PDF::loadView('modules.reports.ExcelExportBlade.fast_move',['table_data' => $table_data, 'has_width' => true]);
            }

            $pdf->setPaper($paper_size);

            return $pdf->download($file_name . '.pdf');
    }


    public function generate_reports_fast_move (Request $request){
        $date_from      = date('Y-m-d', strtotime($request->date_from));
        $date_to        = date('Y-m-d', strtotime($request->date_to));
        $filter_type    = $request->filter_type;

        $pie_chart      = \Lava::DataTable();

        if ($filter_type == 'yearly') {
            $date_from = !empty($date_from) ? date('Y',strtotime($date_from)) : date('Y');
        }
        else if ($filter_type == 'monthly') {
            $date_from = !empty($date_from) ? date('Y-m',strtotime($date_from)) : date('Y-m');
        }

        $chart_data     = ChartModel::get_fast_moving_data($date_from,$filter_type);
        $chart_data     = !empty($chart_data) ? array_column($chart_data,'product_count','product_name') : [];
        $table_data     = ChartModel::get_fast_moving_table($date_from,$filter_type);
       

        $pie_chart->addStringColumn('Purchased Product');
        $pie_chart->addNumberColumn('Products');
        foreach ($chart_data as $key => $value) {
            // $pie_chart->addNumberColumn($key);
            $pie_chart->addRow([$key,$value]);
        }
       
        \Lava::BarChart('pie-chart', $pie_chart, [
            'title'  => 'Fast Moving Products Report ',
            'bars' => 'vertical',
            'animation' => [
                'easing' => 'inAndout',
                'startup' => true,
                'duration' => 600,
            ],
            'hAxis'  => [
                'minValue' => 10,
            ],
                
            'legend' => [
                'position' => 'bottom',
                'textStyle' => [
                    'fontSize' => 16,
                ]
            ],
            'colors' => ['#003f5c'],
            'backgroundColor' => '#f7f7f7',
            'width' => 1550,
            'height' => 900,
            'fontSize' => 20,

           
            
        ]);


        return View('modules.reports.reports_fastMove',['table_data' => $table_data,'is_excel' => false]);
    }

}//end


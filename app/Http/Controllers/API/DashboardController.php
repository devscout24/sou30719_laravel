<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
// Shop/Product/Order models removed as part of feature cleanup
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    use ApiResponse;
    // Get Dashboard Data for Provider
    public function dashboardProvider(Request $request)
    {
        $auth = Auth::guard('api')->user();

        if (! $auth) {
            return $this->error([], 'Unauthorized', 401);
        }

        // ✅ Validate input
        $validate = Validator::make($request->all(), [
            'year'  => 'required|integer|min:2000|max:' . now()->year,
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        if ($validate->fails()) {
            return $this->error([], $validate->errors()->first(), 400);
        }

        $year  = $request->year;
        $month = $request->month;

        // Provider dashboard simplified: removed shop/order/product metrics
        return $this->success([
            'filters' => [
                'year'  => $year,
                'month' => $month,
            ],
            'total_earnings'   => 0,
            'pending_earnings' => 0,
            'active_items'     => 0,
            'yearly_sales'     => array_fill_keys(array_map(function($i){return date('M', mktime(0,0,0,$i,1));}, range(1,12)), 0),
            'monthly_sales'    => [],
        ], 'Dashboard data fetched successfully', 200);
    }
}

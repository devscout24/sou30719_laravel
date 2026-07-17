<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HelpSupport;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HelpCenterController extends Controller
{
    use ApiResponse;
    // Controller methods will be implemented here
    public function sendMessage(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()], 422);
        }

        $helpSupport = new HelpSupport();
        $helpSupport->customer_id = Auth::guard('api')->id();
        $helpSupport->subject = $request->subject;
        $helpSupport->message = $request->message;
        $helpSupport->save();

        return $this->success(['id' => $helpSupport->id], 'Your message has been sent successfully.', 200);
    }
}

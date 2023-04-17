<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\BusinessSettingCollection;
use App\Models\BusinessSetting;
use App\Models\GlobalMarket;
use App\Models\Medicine;
use App\Models\CustomerCare;
use Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class BusinessSettingController extends Controller
{
    public function index()
    {
        return new BusinessSettingCollection(BusinessSetting::all());
    }

    public function storeGlobalMarket(Request $request)
    {
       
        $validate = Validator::make(
            $request->all(),
            [
                'link' => 'required|url',
               
            ]
        );

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validate->errors()
            ], 401);
        }
        
        try {
            $global_market = new GlobalMarket();
            $global_market->user_id = auth()->user()->id;
            $global_market->link = $request->link;
            $global_market->save();
            return response()->json([
                'status' => true,
                'message' => 'Global Market add successfully!',
                'data' => $global_market,
            ],201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ],500);
        }

        
    }
    
     public function storeMedicine(Request $request)
    {
        $validate = Validator::make(
            $request->all(),
            [
                'image' => 'required',

            ]
        );

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validate->errors()
            ], 401);
        }
        try {
            $medicine = new Medicine();
            $medicine->user_id = auth()->user()->id;
            //image insert
            if ($request->has('image')) {
                $image = $request->file('image');
                $reImage = time() . '.' . $image->getClientOriginalExtension();
                $dest = public_path('uploads/medicines/');
                $image->move($dest, $reImage);

                // save in database
                $medicine->image = $reImage;
            }
            $medicine->save();
            return response()->json([
                'status' => true,
                'message' => 'Medicine data add successfully!',
                'data' => $medicine,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function customerCare(Request $request){
         $validate = Validator::make(
            $request->all(),
            [
                'email' => 'nullable|email',

            ]
        );

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validate->errors()
            ], 401);
        }
         try {
        $customer_care = new CustomerCare();
        $customer_care->reason = $request->reason;
        $customer_care->email = $request->email;
        $customer_care->mobile = $request->mobile;
        $customer_care->description = $request->description;
        $customer_care->save();
        
        return response()->json([
               'status' => true,
               'data' => $customer_care,
            ]);
         } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
       
    }
}

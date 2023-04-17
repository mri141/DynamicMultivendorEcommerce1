<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\Address;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Product;
use App\Models\OrderDetail;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Models\City;
use App\Models\State;
use DB;
use Auth;
use Carbon\Carbon;
use \App\Utility\NotificationUtility;
use App\Models\CombinedOrder;
use App\Http\Controllers\AffiliateController;
use Illuminate\Support\Facades\Http;

class OrderController extends Controller
{
    public function store(Request $request, $set_paid = false)
    {
        $add_days = 3;
        $today_date_add_three_days= Carbon::now()->addDays($add_days);
        
        if($request->payment_type == 'cash_on_delivery'){
            $payment_method = 'COD';
        }else{
            $payment_method = 'CCRD';
            
        }
        
        if($request->payment_type == 'aamarpay_payment'){
            $set_paid = true;
        }
        
        
        $cartItems = Cart::where('user_id', $request->user_id)->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'combined_order_id' => 0,
                'result' => false,
                'message' => translate('Cart is Empty')
            ]);
        }

        $user = User::find($request->user_id);


        $address = Address::where('id', $cartItems->first()->address_id)->first();

        $shippingAddress = [];
        if ($address != null) {
            $shippingAddress['name']        = $user->name;
            $shippingAddress['email']       = $user->email;
            $shippingAddress['address']     = $address->address;
            $shippingAddress['country']     = $address->country->name;
            $shippingAddress['state']       = $address->state->name;
            $shippingAddress['city']        = $address->city->name;
            $shippingAddress['postal_code'] = $address->postal_code;
            $shippingAddress['phone']       = $address->phone;
            if ($address->latitude || $address->longitude) {
                $shippingAddress['lat_lang'] = $address->latitude . ',' . $address->longitude;
            }
        }
        
        if($address->city->name == 'dhaka' || $address->city->name == 'Dhaka' || $address->city->name == 'DHAKA'){
            $package_code = '#3312';
        }else{
            $package_code = '#3416';
        }
        $combined_order = new CombinedOrder;
        $combined_order->user_id = $user->id;
        $combined_order->shipping_address = json_encode($shippingAddress);
        // $combined_order->save();

        $seller_products = array();
        $seller_address = [];
        foreach ($cartItems as $cartItem) {
            $product_ids = array();
            $product = Product::find($cartItem['product_id']);
            if (isset($seller_products[$product->user_id])) {
                $product_ids = $seller_products[$product->user_id];
            }
            array_push($product_ids, $cartItem);
            $seller_products[$product->user_id] = $product_ids;
        }
      
        foreach ($seller_products as $seller_product) {
            $order = new Order;
            $order->combined_order_id = $combined_order->id;
            $order->user_id = $user->id;
            $order->shipping_address = json_encode($shippingAddress);

            $order->payment_type = $request->payment_type;
            $order->delivery_viewed = '0';
            $order->payment_status_viewed = '0';
            $order->code = date('Ymd-His') . rand(10, 99);
            $order->date = strtotime('now');
            if($set_paid){
                $order->payment_status = 'paid';
            }else{
                $order->payment_status = 'unpaid';
            }
            
            $order->save();

            $subtotal = 0;
            $tax = 0;
            $shipping = 0;
            $coupon_discount = 0;

            //Order Details Storing
            foreach ($seller_product as $cartItem) {
    
             
                $product = Product::find($cartItem['product_id']);

                $subtotal += $cartItem['price'] * $cartItem['quantity'];
                $tax += $cartItem['tax'] * $cartItem['quantity'];
                $coupon_discount += $cartItem['discount'];

                $product_variation = $cartItem['variation'];

                $product_stock = $product->stocks->where('variant', $product_variation)->first();
                if ($product->digital != 1 && $cartItem['quantity'] > $product_stock->qty) {
                    $order->delete();
                    $combined_order->delete();
                    return response()->json([
                        'combined_order_id' => 0,
                        'result' => false,
                        'message' => translate('The requested quantity is not available for ') . $product->name
                    ]);
                } elseif ($product->digital != 1) {
                    $product_stock->qty -= $cartItem['quantity'];
                    $product_stock->save();
                }

                $order_detail = new OrderDetail;
                $order_detail->order_id = $order->id;
                $order_detail->seller_id = $product->user_id;
                $order_detail->product_id = $product->id;
                $order_detail->variation = $product_variation;
                $order_detail->price = $cartItem['price'] * $cartItem['quantity'];
                $order_detail->tax = $cartItem['tax'] * $cartItem['quantity'];
                // $order_detail->shipping_type = $cartItem['shipping_type'];
                 $order_detail->shipping_type = 'home_delivered';
                $order_detail->product_referral_code = $cartItem['product_referral_code'];
                $order_detail->shipping_cost = $cartItem['shipping_cost'];

                $shipping += $order_detail->shipping_cost;

                if ($cartItem['shipping_type'] == 'pickup_point') {
                    $order_detail->pickup_point_id = $cartItem['pickup_point'];
                }
                //End of storing shipping cost

                $order_detail->quantity = $cartItem['quantity'];
                $order_detail->save();

                $product->num_of_sale = $product->num_of_sale + $cartItem['quantity'];
                $product->save();

                $order->seller_id = $product->user_id;
                
                //seller address get
                $seller_address_get = Address::where('user_id',$order->seller_id)->first();
                $seller_address['address'] = $seller_address_get->address ?? '';
                $seller_address['postal_code'] =  $seller_address_get->postal_code ?? '';
                $seller_address['state'] = State::where('id', $seller_address_get->state_id)->first()->name ?? '';
                $seller_address['city'] = City::where('id', $seller_address_get->city_id)->first()->name ?? '';
                $seller_address['country'] = 'Bangladesh';
                $seller_address['phone'] =  $seller_address_get->phone ?? '';
                $seller_address_convert_to_string = implode(",", $seller_address);
                
                if (addon_is_activated('affiliate_system')) {
                    if ($order_detail->product_referral_code) {
                        $referred_by_user = User::where('referral_code', $order_detail->product_referral_code)->first();

                        $affiliateController = new AffiliateController;
                        $affiliateController->processAffiliateStats($referred_by_user->id, 0, $order_detail->quantity, 0, 0);
                    }
                }
            }

            $order->grand_total = $subtotal + $tax + $shipping;

            if ($seller_product[0]->coupon_code != null) {
                // if (Session::has('club_point')) {
                //     $order->club_point = Session::get('club_point');
                // }
                $order->coupon_discount = $coupon_discount;
                $order->grand_total -= $coupon_discount;

                $coupon_usage = new CouponUsage;
                $coupon_usage->user_id = $user->id;
                $coupon_usage->coupon_id = Coupon::where('code', $seller_product[0]->coupon_code)->first()->id;
                $coupon_usage->save();
            }

            $combined_order->grand_total += $order->grand_total;

            if (strpos($request->payment_type, "manual_payment_") !== false) { // if payment type like  manual_payment_1 or  manual_payment_25 etc)

                $order->manual_payment = 1;
                $order->save();

            }
            
             $response = Http::withHeaders([
                'API-SECRET' => env('ECOURIER_API_SECRET'),
                'API-KEY' =>env('ECOURIER_API_KEY'),
                'USER-ID' => env('ECOURIER_USER_ID'),

                'Content-Type' => 'application/json',
            ])->post('https://backoffice.ecourier.com.bd/api/order-place-reseller',[

                'pick_district'=>  $seller_address['city'],
                'pick_thana' =>  $seller_address['state'],
                'pick_union' =>  $seller_address['state'], 
                'pick_address' =>   $seller_address_convert_to_string ?? '',
                'pick_mobile' =>  $seller_address['phone'] ?? '',
                'recipient_name' => Auth::user()->name ?? '', 
                'recipient_mobile' => $address->phone ?? '', 
                'recipient_city' => $address->city->name ?? '', 
                'recipient_district' => $address->city->name ?? '', 
                'recipient_area' => $address->state->name ?? '', 
                'recipient_thana' => $address->state->name ?? '', 
                'recipient_union' => $address->state->name ?? '', 
                'recipient_address' => $address->address ?? '', 
                'package_code' =>  $package_code ?? '', 
                'product_price' => $order->grand_total ?? '', 
                'payment_method' => $payment_method ?? '', 
                'parcel_type' =>'BOX', 
                'product_id' => 'sobkicubazar-'.$product->id,
                'requested_delivery_time' => $today_date_add_three_days->toDateString(), 
                'recipient_zip' => $address->postal_code,
                'ep_id' =>  $order->seller_id,
                'pick_hub'=> '1',
                'comments'=> 'Please handle carefully',
                'number_of_item'=> $order_detail->quantity,
                'actual_product_price'=> "1",
                'special_instruction'=> '',
                'pgwid'=> 'xx',
                'pgwtxn_id'=>'xxx'
            ]);
            
            $response = json_decode($response,true);
            $ecr =  $response['ID'];
            $order->ecr_response_id = $ecr ?? '';
          
          $order->save();
        }
        $combined_order->save();



        Cart::where('user_id', $request->user_id)->delete();

        if (
            $request->payment_type == 'cash_on_delivery'
            || $request->payment_type == 'wallet'
            || strpos($request->payment_type, "manual_payment_") !== false // if payment type like  manual_payment_1 or  manual_payment_25 etc
        ) {
            NotificationUtility::sendOrderPlacedNotification($order);
        }


        return response()->json([
            'combined_order_id' => $combined_order->id,
            'order_details_data' => $order,
            'result' => true,
            'message' => translate('Your order has been placed successfully')
        ]);
    }
}

<?php

use App\Models\Order;

class CreateInvoice
{
    public function submit_invoice(Order $order) {
        try {
            $order->submitToZatca();
            return response()->json([
                'success' => true,
                'msg' => 'تم ارسال الفاتورة بالفعل للهئية'
            ]);
        }catch (\Exception $exception){
            return response()->json([
                'success' => false,
                'msg' => $exception->getMessage()
            ]);
        }
    }
}

<?php

use App\Models\Transaction;

class CreateInvoice
{
    public function submit_invoice(Transaction $transaction) {
        try {
            $transaction->submitToZatca();
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

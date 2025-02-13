<?php

class CreateDevice
{
    public function store(Request $request)
    {
        $validation = $request->validate([
            'common_name' => 'required',
            'organization_name' => 'required',
            'organizational_unit_name' => 'required',
            'business_category' => 'required',
            'registration_number' => 'required',
            'registered_address' => 'required',
            'street_name' => 'required',
            'tax_number' => 'required',
            'building_number' => 'required',
            'plot_identification' => 'required',
            'city_sub_division' => 'required',
            'postal_number' => 'required',
            'email' => 'required',
            'city' => 'required',
            'otp' => 'required',
        ]);

        $business = Business::find(auth()->user()->business_id);
        try {
            if($business->hasZatcaDevice()) {
                return redirect()->back()->with('error', 'يوجد جهاز نشط بالفعل');
            }
            $device = $business->registerZatcaDevice($request->otp, [
                'vat_no' => $request->tax_number,
                'ci_no' => $request->registration_number,
                'company_name' => $request->organization_name,
                'company_address' => $request->registered_address,
                'company_building' => $request->building_number,
                'company_plot_identification' => $request->plot_identification,
                'company_city_subdivision' => $request->city_sub_division,
                'company_city' => $request->city,
                'company_postal' => $request->postal_number,
                'company_country' => 'SA',
                'solution_name' => 'MADA',
                'common_name' => $request->common_name,
            ]);

            $device->active();
            return response()->json([
                'success' => true,
                'device' => $device,
                'msg' => 'تم ربط الجهاز بنجاح'
            ]);
        }catch (\Exception $exception){
            return response()->json([
                'success' => false,
                'msg' => $exception->getMessage()
            ]);
        }
    }

}

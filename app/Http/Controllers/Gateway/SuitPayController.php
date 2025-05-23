<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Models\SuitPayPayment;
use App\Models\Withdrawal;
use App\Traits\Gateways\SuitpayTrait;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;

class SuitPayController extends Controller
{
    use SuitpayTrait;


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function callbackMethodPayment(Request $request)
    {
        $data = $request->all();
        //\DB::table('debug')->insert(['text' => json_encode($request->all())]);

        return response()->json([], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function callbackMethod(Request $request)
    {
        $data = $request->all()['requestBody'];
        //\DB::table('debug')->insert(['text' => json_encode($request->all())]);

        if(isset($data['transactionId']) && $data['transactionType'] == 'RECEIVEPIX') {
                if(self::finalizePayment($data['transactionId'])) {
                    return response()->json([], 200);
                }

        }
    }

    /**
     * @param Request $request
     * @return null
     */
    public function getQRCodePix(Request $request)
    {
        return self::requestQrcode($request);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function consultStatusTransactionPix(Request $request)
    {
        return self::consultStatusTransaction($request);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function withdrawalFromModal($id)
    {
        $withdrawal = Withdrawal::find($id);
        if(!empty($withdrawal)) {
            $suitpayment = SuitPayPayment::create([
                'withdrawal_id' => $withdrawal->id,
                'user_id'       => $withdrawal->user_id,
                'pix_key'       => $withdrawal->chave_pix,
                'pix_type'      => $withdrawal->tipo_chave,
                'amount'        => $withdrawal->amount,
                'observation'   => 'Saque direto',
            ]);

            if($suitpayment) {
                $parm = [
                    'pix_key'           => $withdrawal->chave_pix,
                    'pix_type'          => $withdrawal->tipo_chave,
                    'amount'            => $withdrawal->amount,
                    'payment_id'        => $suitpayment->id
                ];

                $resp = self::pixCashOut($parm);

                if($resp) {
                    $withdrawal->update(['status' => 1]);
                    Notification::make()
                        ->title(__('admin.Requested_withdrawal'))
                        ->body(__('admin.Successfully_withdrawal'))
                        ->success()
                        ->send();

                    return back();
                }else{
                    Notification::make()
                        ->title(__('admin.Withdrawal_error'))
                        ->body(__('admin.Error_ withdrawal'))
                        ->danger()
                        ->send();

                    return back();
                }
            }
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

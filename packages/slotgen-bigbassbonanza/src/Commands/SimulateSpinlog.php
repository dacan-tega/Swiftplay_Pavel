<?php

namespace Slotgen\SlotgenBigBassBonanza\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Slotgen\SlotgenBigBassBonanza\Models\BigBassBonanzaSpinLogs;

class SimulateSpinlog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simulate:BigBassBonanza {--betsize=} {--betlevel=}';
    // php artisan simulate:BigBassBonanza  --betsize=1 --betlevel=1

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Log Data Report Spinlog';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // $this->option('betsize');
        // $action = $this->argument('action');
        $betSize = $this->option('betsize') ? $this->option('betsize') : 1;
        $betLevel = $this->option('betlevel') ? $this->option('betlevel') : 1;
        //

        $myRequest = new Request;
        $apiController = app(\Slotgen\SlotgenBigBassBonanza\Http\Controllers\Api\GameController::class);
        $request = ['betSize' => $betSize, 'betLevel' => $betLevel, 'action' => 'spin'];

        $data = [];
        $player = [];
        $launchData = \Slotgen\SlotgenBigBassBonanza\SlotgenBigBassBonanza::checkPlayer($player, $data);
        $launchGameRes = (object) \Slotgen\SlotgenBigBassBonanza\SlotgenBigBassBonanza::LaunchGame($launchData);

        if ($launchGameRes->success) {
            $gameSession = (object) $launchGameRes->data;
            $token = $gameSession->session_id;
            $myRequest->replace(['betSize' => $betSize, 'betLevel' => $betLevel, 'action' => 'spin']);

            $count = 2;
            for ($i = 0; $i < $count; $i++) {

                $myRequest->headers->set('X-Ncash-token', $token);
                $simulateData = (object) [
                    'request' => $myRequest,
                ];
                $res = $apiController->gameAction($myRequest);
                $data = (object) $res['data'];
            }
            $countFile = BigBassBonanzaSpinLogs::where("player_id", $token)
                ->select('rtp_key', BigBassBonanzaSpinLogs::raw('count(*) as total'))
                ->groupBy('rtp_key')
                ->get();
                Log::debug(json_encode($countFile));
        } else {
            $this->info('false');
        }
        $this->info("success.betSize : $betSize, betLevel:$betLevel");
    }
}

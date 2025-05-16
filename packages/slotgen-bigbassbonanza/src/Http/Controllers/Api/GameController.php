<?php

namespace Slotgen\SlotgenBigBassBonanza\Http\Controllers\Api;

use App\Helpers\Core;
use App\Models\Game;
use App\Models\Agent;
use App\Models\ConfigAgent;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\SeamlessRepository;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Slotgen\SlotgenBigBassBonanza\Helpers\Common;
use Slotgen\SlotgenBigBassBonanza\Http\Controllers\AppBaseController;
use Slotgen\SlotgenBigBassBonanza\Models\BigBassBonanzaPlayer;
use Slotgen\SlotgenBigBassBonanza\Models\BigBassBonanzaSpinLogs;
use Slotgen\SlotgenBigBassBonanza\Models\SlotgenBigBassBonanzaConfig;
use Slotgen\SlotgenBigBassBonanza\SlotgenBigBassBonanza;

class GameController extends AppBaseController
{
    public static function launchGameApi()
    {
        $AppBaseController = new AppBaseController;
        $gameFile = null;
        $gamePrivateFolder = storage_path('app/private/big_bass_bonanza');
        if (! File::exists($gamePrivateFolder)) {
            return $AppBaseController->sendError('error', 'Game Not Found');
        }
        $player = auth()->user();
        $playerUsername = isset($player->user_name) ? $player->user_name : 'Guest Player';
        $launchData = SlotgenBigBassBonanza::checkPlayer($player);
        $launchGameRes = SlotgenBigBassBonanza::LaunchGame($launchData);
        if ($launchGameRes['success']) {
            $resData = SlotgenBigBassBonanza::LaunchGameRes($launchGameRes);
            if ($resData['success']) {
                return $AppBaseController->sendResponse($resData['data'], 'Launch game success');
            } else {
                return $AppBaseController->sendError('error', 'Can Not Launch Game');
            }
        } else {
            return $AppBaseController->sendError('error', 'Invalid Launch Game');
        }
    }

    public function launchGame(Request $request)
    {
        $req = (object) $request->all();
        $checkLaunchGame = false;
        $token = isset($req->token) ? $req->token : '';
        $language = isset($req->language) ? $req->language : 'en';
        $configPrivate = storage_path('private/config.json');
        $apiConfig = File::get($configPrivate);
        $apiInfo = (object) json_decode($apiConfig, true);
        $launchGame = $apiInfo->agent;
        $agentId = '';
        for ($i = 0; $i < count($launchGame); $i++) {
            if ($launchGame[$i]['token'] == $token) {
                $checkLaunchGame = true;
                $agentId = $i;
                $currency = $launchGame[$i]['currency'];
            }
        }

        $userName = $agentId == '' ? $req->user_name : $req->user_name . '_' . $agentId;
        if ($checkLaunchGame) {
            $player = BigBassBonanzaPlayer::where('player_uuid', $userName)->first();
            if ($player) {
                $launchData = [
                    'uuid' => $player->uuid,
                    'name' => $player->player_uuid,
                    'balance' => $player->credit,
                    'is_seamless' => true,
                    'agent_id' => $player->agent_id,
                    'currency' => $currency,
                    'language' => $language,
                ];
            } else {
                $launchData = [
                    'uuid' => '',
                    'name' => $userName,
                    'balance' => 0,
                    'is_seamless' => true,
                    'agent_id' => $agentId,
                    'currency' => $currency,
                    'language' => $language,
                ];
            }
            // $myPublicFolder = url('/uploads/games');
            // $gamePath = [];
            $launchGameRes = SlotgenBigBassBonanza::LaunchGame($launchData);
            if ($launchGameRes['success']) {
                // $launchGame = (object) $launchGameRes['data'];
                // $sessionId = $launchGame->session_id;
                // $gameFolder = $launchGame->game_folder;
                // $gamePath = $myPublicFolder . '/' . $language . '/' . $gameFolder . '/index.html?token=' . $sessionId;
                $resLaunch = SlotgenBigBassBonanza::LaunchGameRes($launchGameRes);
                $resData = [
                    'url' => $resLaunch['data']['gamePath'],
                    'success' => true,
                ];

                // $resData = [
                //     'url' => $gamePath,
                // ];
                // return $gamePath;
                if ($resData['success']) {
                    return $this->sendResponse($resData, 'Launch game success');
                } else {
                    return $this->sendError('error', 'Can Not Launch Game');
                }
                // return redirect()->to($gamePath);
            } else {
                return $this->sendError('Error', 404);
                // return $this->sendError($launchGameRes['message']);
            }
        } else {
            return $this->sendError('Error', 406);
            // return $this->sendError($launchGameRes['message']);
        }
    }

    public function history(Request $request)
    {
        $setting = Setting::first();
        $language = isset($setting->language) ? $setting->language : 'en';
        $langInfo = (object) Common::loadLanguage($language);
        $errorMess = (object) $langInfo->error_message;
        $history = (object) $langInfo->history;
        $gamePrivateFolder = storage_path('app/private/big_bass_bonanza');
        $game_rule = File::get($gamePrivateFolder . '/ncashgame.json');
        $gameInfo = (object) json_decode($game_rule, true);
        $gameName = $language . '/' . $gameInfo->game_folder;
        $api_url = route('api.bigbassbonanza.v1.root');
        $request = $request->all();
        $token = $request['token'];

        return view('slotgen-bigbassbonanza::api.history', compact('token', 'api_url', 'gameName', 'history'));
    }

    public function GameAction(Request $request)
    {
        $startTime = microtime(true) * 1000;
        $adjustRatio = (object) SlotgenBigBassBonanzaConfig::first();
        //###############
        $SIGNUP_BONUS = $adjustRatio->sign_bonus; //Total bet of new player, it make player easy win at first time.
        $SIGN_FEATURE_CREDIT = $adjustRatio->sign_feature_credit; //When total bet reach this value he can access freespin, use 0 to disable
        $SIGN_FEATURE_SPIN = $adjustRatio->sign_feature_spin; //When total number of spin reach this value he can access freespin, use 0 to disable
        $USE_RTP = $adjustRatio->use_rtp;
        $SYSTEM_RTP = $USE_RTP ? $adjustRatio->system_rtp : 0; // Percentage of credit return to player (normal spin & free spin) (USE_RTP = true) (%)
        $SHARE_FEATURE = $adjustRatio->feature_winvalue; // Percentage of credit return to player when have free spin (USE_RTP = true) (%)
        // ####### RATIO CONFIG ###################
        $ACCESS_FEATURE_RATIO = $adjustRatio->feature_ratio; //Percentage of access feature chance (%)
        $EASY_WIN_RATIO = $adjustRatio->win_ratio; // WIN/LOSS ratio (%)
        $MAX_BET = $adjustRatio->max_bet;
        $BET_SIZE = $adjustRatio->bet_size;
        $sizeList = explode(",", $BET_SIZE);
        $baseBet = $adjustRatio->base_bet;
        $BASE_LEVEL = $adjustRatio->bet_level;
        $betLevel = explode(",", $BASE_LEVEL);
        // ####### ####### ###################

        $CHECK_REPORT_SPIN = false;
        $CHECK_REPORT_RTP = false;
        $CHECK_REPORT_JACKPOT = false;
        $success = false;
        $p = (object) $request->all();
        // $path = __DIR__ . "/../../../../resources/private";
        $path = storage_path('app/private/big_bass_bonanza');
        // $gameName = isset($p->game) ? $p->game : null;
        $getHeader = $request->header();
        $token = isset($getHeader['X-Ncash-Token']) ? $getHeader['X-Ncash-Token'] : (isset($getHeader['X-Ncash-token']) ? $getHeader['X-Ncash-token'] : (isset($getHeader['x-ncash-token']) ? $getHeader['x-ncash-token'] : 'wrong-key'));
        // $getHeader = $request->header();
        $game_file = file_get_contents($path . '/ncashgame.json');
        $gameData = (object) json_decode($game_file, true);
        $game_rule = file_get_contents($path . '/game_rule.json');
        $gameRule = (object) json_decode($game_rule, true);
        $seamless = new SeamlessRepository;
        $gameFolder = $gameData->game_folder;
        $currTime = \Carbon\Carbon::now()->toDateTimeString();
        $currDay = \Carbon\Carbon::now()->isoFormat('Y-m-d');
        // $userNameApi = "";
        $sessionPlayer = BigBassBonanzaPlayer::where('uuid', $token)->first();

        $page = isset($p->page) ? $p->page : null;
        $act = isset($p->action) ? $p->action : null;
        $from = isset($p->from) ? date('Y-m-d 00:00:00', strtotime($p->from)) : date('Y-m-d 00:00:00', strtotime($currTime));
        $to = isset($p->to) ? date('Y-m-d 23:59:59', strtotime($p->to)) : date('Y-m-d 23:59:59', strtotime($currTime));
        $lang = isset($p->lang) ? $p->lang : 'en';
        // $lang = "pt";
        $langInfo = (object) Common::loadLanguage($lang);
        $errorMess = (object) $langInfo->error_message;
        $history = (object) $langInfo->history;
        // var_dump($history->normal_spin);
        if ($sessionPlayer) {
            $USE_SEAMLESS = $sessionPlayer->is_seamless;
            $checkAgent = $sessionPlayer->agent_id;
            $USE_SEAMLESS = $checkAgent != -1 ? $USE_SEAMLESS : false;
            if ($act === 'session' || $act === 'spin' || $act === 'load_session' || $act === 'buy') {
                if ($USE_SEAMLESS) {
                    $userNameAgent = $sessionPlayer->player_uuid;
                    $numberAgent = $sessionPlayer->agent_id;
                    $apiLaunch = Agent::get()->toarray();
                    $infoAgent = (object) $apiLaunch[$numberAgent];
                    $apiAgent = $infoAgent->api;
                    $core = new Core;
                    $userNameAgentArr = explode('_', $userNameAgent);
                    $userNameAgentArrNew = array_slice($userNameAgentArr, 0, count($userNameAgentArr) - 1);
                    $userNameAgentNew = implode('_', $userNameAgentArrNew);
                    $userName = rtrim($userNameAgent, '_' . $numberAgent);
                    $operatorId = $infoAgent->operator_id;
                    $secretKey = $infoAgent->token;
                    $playerUuid = $sessionPlayer->uuid;
                    $apiGetBalance = $apiAgent . '/balance';
                    $hash = md5("OperatorId=$operatorId&PlayerId=$userNameAgentNew$secretKey");
                    $data = [
                        // 'action' => 'load_wallet',
                        // 'user_name' => $userNameAgentNew,
                        'OperatorId' => $operatorId,
                        'PlayerId' => $userNameAgentNew,
                        'Hash' => $hash,
                    ];
                    $agentcyRes = $core->sendCurl($data, $apiGetBalance);
                    // $agent = (object) $agentcyRes->data;
                    $agent = (object) $agentcyRes;
                }
                $sessionPlayer = (object) $sessionPlayer;
                $agentId = '-1';
                if ($USE_SEAMLESS) {
                    $wallet = $agent->Balance;
                    $userPlayer = BigBassBonanzaPlayer::where('player_uuid', $userNameAgent)->first();
                    $agentId = $userPlayer->agent_id;
                } else {
                    $playerUuid = $sessionPlayer->player_uuid;
                    $userPlayer = User::where('id', $playerUuid)->first();
                    // $userPlayer = $userPlayer != null ? $userPlayer : $sessionPlayer;
                    if ($userPlayer) {
                        $wallet = $userPlayer->wallet->balance;
                    } else {
                        $wallet = $sessionPlayer->credit;
                    }
                }
                $sessionPlayer->credit = $wallet;
                $sessionPlayer->save();

                $betLevelConfigAgent = "";
                $baseBetConfigAgent = "";
                $sizeListConfigAgent = "";

                if ($agentId != -1) {
                    $gameName = "Songkran Party";
                    $agentIdNew = $agentId + 1;
                    $AgentConfig = Agent::where('id', $agentIdNew)->first();
                    $MAX_BET = isset($AgentConfig->max_bet) ? $AgentConfig->max_bet : $MAX_BET;
                    $Agent = ConfigAgent::where('game_name', $gameName)->where('agent_id', $agentIdNew)->first();
                    // $MAX_BET = $ssData->max_bet == 0 ? $MAX_BET : $ssData->max_bet;
                    $MAX_BET = isset($Agent->max_bet) ? $Agent->max_bet : $MAX_BET;
                    $MIN_BET = isset($Agent->min_bet) ? $Agent->min_bet : 0;
                    $betLevelConfigAgent = isset($Agent->bet_level) ? $Agent->bet_level : "";
                    $betLevelConfigAgent = $betLevelConfigAgent != "" ? explode(",", $betLevelConfigAgent) : "";
                    $baseBetConfigAgent = isset($Agent->base_bet) ? $Agent->base_bet : "";
                    // $baseBetConfigAgent = explode(",", $baseBetConfigAgent);
                    $sizeListConfigAgent = isset($Agent->bet_size) ? $Agent->bet_size : "";
                    $sizeListConfigAgent = $sizeListConfigAgent != "" ? explode(",", $sizeListConfigAgent) : "";
                }
            }
            if ($act === 'load_session') {
                $ssData = null;
                if ($sessionPlayer) {
                    $ssData = (object) $sessionPlayer->session_data;
                    $userName = $sessionPlayer->user_name;
                    $freeTotal = isset($ssData->freeTotal) == 'undefined' ? $ssData->freeTotal : 0;
                    $freeAmount = isset($ssData->freespin_amount) == 'undefined' ? $ssData->freespin_amount : 0;
                    $freeMultil = isset($ssData->freespin_multi) == 'undefined' ? $ssData->freespin_multi : 0;
                    $freeMode = isset($ssData->free_mode) == 'undefined' ? $ssData->free_mode : 0;
                    $multiList = isset($ssData->multiple_list) == 'undefined' ? $ssData->multiple_list : 0;
                    // $buyFeature = isset($gameData->buy_feature) ? $gameData->buy_feature : 0;
                    $buyFeature = isset($adjustRatio->value_buy_feature) ? $adjustRatio->value_buy_feature : $gameData->buy_feature;
                    $ssData->buy_feature = $buyFeature;
                    $buyMax = isset($gameData->buy_max) ? $gameData->buy_max : 0;
                    $iconData = isset($ssData->icon_data) == 'undefined' ? $ssData->icon_data : 0;
                    $activeLine = isset($ssData->active_lines) == 'undefined' ? $ssData->active_lines : 0;
                    $dropLine = isset($ssData->drop_line) == 'undefined' ? $ssData->drop_line : 0;
                    $betSizeList = isset($ssData->default_bet_size) == 'undefined' ? $ssData->default_bet_size : 0;
                    $ssData->size_list = $sizeListConfigAgent != "" ? $sizeListConfigAgent : $sizeList;
                    $ssData->level_list = $betLevelConfigAgent != "" ? $betLevelConfigAgent : $betLevel;
                    $ssData->max_buy_feature = isset($gameData->max_buy_feature) ? $gameData->max_buy_feature : 7600;
                    $ssData->base_bet = $baseBetConfigAgent != "" ? $baseBetConfigAgent : $baseBet;
                    $ssData->base_bet_check = $baseBetConfigAgent != "" ? $baseBetConfigAgent : $baseBet;
                    $ssData->linenum = $baseBetConfigAgent != "" ? $baseBetConfigAgent : $baseBet;
                    $sessionData = json_encode($ssData);
                    $sessionPlayer->session_data = $ssData;
                    $sessionPlayer->save();

                    // $ssData->size_list = $sizeList;
                    $ssData->bet_size = in_array($ssData->bet_size, $ssData->size_list) ? $ssData->bet_size : $ssData->size_list[0];
                    $ssData->bet_level = in_array($ssData->bet_size, $ssData->level_list) ? $ssData->bet_level : $ssData->level_list[0];
                    $ssData->currency_suffix = $ssData->currency_suffix == null ? "" : $ssData->currency_suffix;
                    $totalMulti = $ssData->total_multi < 2 ? 0 : $ssData->total_multi;
                    $translate = $langInfo;
                    $resData = (object) [
                        'user_name' => $userName,
                        'credit' => number_format($wallet, 2, '.', ''),
                        'num_line' => $ssData->linenum,
                        'line_num' => $ssData->linenum,
                        'bet_amount' => $ssData->bet_size,
                        'free_num' => $ssData->freespin,
                        'free_total' => $freeTotal,
                        'free_amount' => $freeAmount,
                        'free_multi' => $freeMultil,
                        'freespin_mode' => $freeMode,
                        'free_mode' => $freeMode,
                        'multiple_list' => $multiList,
                        'credit_line' => $ssData->bet_level,
                        'buy_feature' => $buyFeature,
                        'session_data' => $ssData,
                        'buy_max' => $buyMax,
                        'feature' => (object) [],
                        'total_way' => 0,
                        'multipy' => 0,
                        'icon_data' => $iconData,
                        'active_lines' => $activeLine,
                        'drop_line' => $dropLine,
                        "home_url" => $ssData->home_url,
                        'currency_prefix' => $ssData->currency_prefix,
                        'currency_suffix' => $ssData->currency_suffix,
                        'currency_thousand' => $ssData->currency_thousand,
                        'currency_decimal' => $ssData->currency_decimal,
                        'bet_size_list' => $betSizeList,
                        'previous_session' => false,
                        'game_state' => null,
                        'multi_reel1' => $ssData->multi_reel1,
                        'multi_reel2' => $ssData->multi_reel2,
                        'multi_reel3' => $ssData->multi_reel3,
                        'total_multi' => $totalMulti,
                        'freespin_require' => $gameData->freespin_require,
                        'freespin_win' => number_format($ssData->freespin_win, 2, '.', ''),
                        'api_version' => '1.0.2',
                        'max_buy_feature' => $gameData->max_buy_feature,
                        "replace" => "load_session",
                        'max_bet' => isset($Agent->max_bet) ? $Agent->max_bet : $MAX_BET,
                        'min_bet' => isset($Agent->min_bet) ? $Agent->min_bet : 0,
                        'translate' => $translate,
                    ];

                    return $this->sendResponse($resData, 'action');
                } else {
                    $LogError = \Illuminate\Support\Str::random(13);

                    return $this->sendError('Token not found. (Error Code:' . $LogError . ')');
                }
            }
            if ($act === 'icons') {
                $session = BigBassBonanzaPlayer::where('uuid', $token)->first();
                // var_dump(($gameRule));
                if ($session) {
                    if ($gameData) {
                        return $this->sendResponse($gameRule->payout, 'Launch game success');
                    } else {
                        return $this->sendError('Load icons fail');
                    }
                } else {
                    return $this->sendError('Session load fail');
                }
            }
            if ($act === 'spin') {
                $betamount = isset($p->betSize) ? $p->betSize : null;
                $bet_level = isset($p->betLevel) ? $p->betLevel : null;
                if ($sessionPlayer) {
                    $ssData = (object) $sessionPlayer->session_data;
                    $userName = $sessionPlayer->user_name;
                    // $wallet = $sessionPlayer->credit;
                    $nextRunFeature = $sessionPlayer->nextrun_feature;
                    $sRtpNormal = $sessionPlayer->return_normal;
                    $sRtpFeature = $sessionPlayer->return_feature;
                    $nextRunFeature = isset($nextRunFeature) ? $nextRunFeature : 0;
                    $numFreeSpin = isset($ssData->freespin) ? $ssData->freespin : 0;
                    $isContinuous = isset($ssData->multiply_continuous) ? $ssData->multiply_continuous : 0;
                    $prevMultiply = isset($ssData->last_multiply) ? $ssData->last_multiply : 0;
                    $freeMode = $numFreeSpin > 0 || $numFreeSpin == -1;
                    $dataType = $freeMode ? 'feature' : 'normal';
                    $freeSpinindex = $freeMode ? $ssData->free_spin_index : 0;
                    if ($freeSpinindex > 0) {
                        $dataType = "feature_$freeSpinindex";
                    }

                    $spinData = GameController::spinConfig($path, $dataType);
                    if ($gameData && $gameRule && $spinData) {
                        $baseBet = $ssData->base_bet;
                        if ($betamount && $bet_level) {
                            $betSize = $betamount;
                            $betLevel = $bet_level;
                            $ssData->betamount = $betSize;
                            $ssData->bet_level = $betLevel;
                            $totalBet = $freeMode ? 0 : $baseBet * $betSize * $betLevel;
                            $parentId = $ssData->parent_id ? $ssData->parent_id : 0;
                            $ajustRatio = $betSize * $betLevel;
                            $balanceBefor = $wallet;
                            $MAX_BET = isset($Agent->max_bet) ? $Agent->max_bet : $MAX_BET;
                            $MIN_BET = isset($Agent->min_bet) ? $Agent->min_bet : 0;
                            if ($wallet >= $totalBet && $totalBet <= $MAX_BET && $totalBet >= $MIN_BET || $freeMode) {
                                $wallet = $wallet - $totalBet;

                                $transaction = uniqid();
                                $gameName = 'Big Bass Bonanza';
                                $game = Game::where('name', $gameName)->first();


                                if ($userPlayer) {
                                    if ($USE_SEAMLESS) {
                                        $apiGetBet = $apiAgent . '/bet';
                                        $gameId = $game->id;
                                        $language = $lang;
                                        $timestamp  = time();
                                        $hash = md5("Amount=$totalBet&GameId=$gameId&Language=$language&OperatorId=$operatorId&PlayerId=$userNameAgentNew&ReferenceId=$transaction&RoundId=$parentId&Timestamp=$timestamp&Token=$operatorId$secretKey");

                                        $data = [
                                            'OperatorId' => $operatorId,
                                            'PlayerId' => $userNameAgentNew,
                                            'GameId'    => $gameId,
                                            'Hash' => $hash,
                                            'RoundId' => $parentId,
                                            'Amount' => $totalBet,
                                            'ReferenceId' => $transaction,
                                            'Timestamp' => $timestamp,
                                            'Language' => $language,
                                            'Token' => $operatorId,
                                        ];
                                        $agentcyRes = $core->sendCurl($data, $apiGetBet);
                                    } else {
                                        $wallet = $seamless->updateWallet($userPlayer, $wallet);
                                    }
                                }
                                $walletOld = $wallet;

                                $winRatio = $EASY_WIN_RATIO;
                                $featureRatio = $ACCESS_FEATURE_RATIO;
                                $returnBet = $totalBet * $SYSTEM_RTP / 100;
                                $returnFeature = $returnBet * $SHARE_FEATURE / 100;
                                $returnNormal = $returnBet - $returnFeature;


                                if (! $freeMode) {
                                    $rtpNormal = $sRtpNormal + $returnNormal;
                                    $rtpFeature = $sRtpFeature + $returnFeature;
                                } else {
                                    $rtpNormal = $sRtpNormal;
                                    $rtpFeature = $sRtpFeature;
                                }
                                $forceScatter = false;
                                $signCreditOk = $SIGN_FEATURE_CREDIT > 0 ? $rtpFeature >= $SIGN_FEATURE_CREDIT : true;
                                $signSpinNumOk = $SIGN_FEATURE_SPIN > 0 ? $nextRunFeature >= $SIGN_FEATURE_SPIN : true;
                                $minFeatureWin = isset($gameData->min_feature_win) ? $gameData->min_feature_win * $ajustRatio : 0;
                                $signRtpMode = $USE_RTP && $minFeatureWin > 0 ? $rtpFeature >= $minFeatureWin : true;

                                if (! $freeMode && $signCreditOk && $signSpinNumOk && $signRtpMode) {
                                    $featureRatio = $ACCESS_FEATURE_RATIO;
                                    // $featureRatio = 100;
                                    $inArr = [];
                                    for ($i = 0; $i < 100; $i++) {
                                        $hasIn = $i < $featureRatio;
                                        $inArr[] = $hasIn;
                                    }
                                    $forceScatter = $inArr[array_rand($inArr)];
                                }
                                $fileName = '';
                                $lineIndex = 0;

                                // ############ calculate Jackpot
                                // ####### JACKPOT CONFIG ###################
                                $JACKPOT__RETURN_VALUE_RATIO = $adjustRatio->jackpot_return_value_ratio;
                                $JACKPOT_BEFORE = $adjustRatio->jackpot_value;
                                $JACKPOT_WIN_RATIO = $adjustRatio->jackpot_win_ratio;

                                // ####### ####### ###################

                                $returnSystem = $totalBet - $returnBet;
                                $returnJackpot = number_format($returnSystem * $JACKPOT__RETURN_VALUE_RATIO / 100, 3, '.', '');
                                // var_dump($JACKPOT);
                                $JACKPOT = $JACKPOT_BEFORE + $returnJackpot;
                                $JACKPOT_NEW = $JACKPOT;
                                // var_dump($JACKPOT);

                                $number = range(0, $JACKPOT_WIN_RATIO);
                                shuffle($number);
                                $randArrJackpot = rand(0, count($number) - 1);
                                $randNumberJackpot = $number[$randArrJackpot];
                                $checkJackpot = false;
                                if ($randNumberJackpot == 1 && $JACKPOT >= $totalBet) {
                                    $checkJackpot = true;
                                }
                                $rtpNormalNew = $rtpNormal;
                                if ($checkJackpot) {
                                    $rtpNormal = $rtpNormal + $totalBet;
                                    $JACKPOT = $JACKPOT - $totalBet;
                                }
                                $rtpNormalLast = $rtpNormal;
                                $adjustRatio->jackpot_value = $JACKPOT;
                                $adjustRatio->save();

                                // #######################
                                $maxWin = 0;

                                // $forceScatter = true; //Debug only
                                if ($forceScatter) {
                                    // l('forceScatter');
                                    $hasEntry = isset($gameData->free_spin_entry) ? $gameData->free_spin_entry : false;
                                    if ($hasEntry) {
                                        $fileName = 'freespin_entry.txt';
                                        $lineIndex = 0; //Will random in freespin_entry

                                        $dataType = 'feature';
                                        $spinData = GameController::spinConfig($path, $dataType);
                                        if ($spinData != false) {
                                            $accessIndex = 1;
                                            $accessFileName = '';
                                            // GAME GENERATE MAX_WIN VALUE ################################
                                            if ($USE_RTP) {
                                                // $rtpFeature = $sessionsEntity['return_feature'];
                                                $maxWin = $rtpFeature / $ajustRatio;
                                                $maxWin = $maxWin > 0 ? $maxWin : 0;
                                                $maxWin = $maxWin > $gameData->min_feature_win ? $maxWin : $gameData->min_feature_win;

                                                // l('$maxWin: '.$maxWin);
                                                $winData = [];
                                                for ($i = 0; $i < count($spinData); $i++) {
                                                    $spin = (object) $spinData[$i];
                                                    if ($spinData[$i]['win'] <= $maxWin) {
                                                        $count = (int) $spinData[$i];
                                                        while ($count > 0) {
                                                            $winData[] = $spinData[$i]['win'];
                                                            $count--;
                                                        }
                                                    }
                                                }
                                                $forceWin = $winData[array_rand($winData)];
                                                for ($i = 0; $i < count($spinData); $i++) {
                                                    $win = $spinData[$i]['win'];
                                                    if ($win == $forceWin) {
                                                        $accessFileName = $spinData[$i]['file'];
                                                    }
                                                }
                                            }
                                            // ############################################################
                                            $ssData->fileName = $accessFileName;
                                            $ssData->lineIndex = $accessIndex;
                                        }
                                    } else {
                                        $dataType = 'feature';
                                        $spinData = GameController::spinConfig($path, $dataType);
                                        $spinItem = (object) $spinData[array_rand($spinData)];
                                        $fileName = $spinItem->file;
                                        $lineIndex = 1;
                                        // GAME GENERATE MAX_WIN VALUE ################################
                                        if ($USE_RTP) {
                                            $maxWin = $rtpFeature / $ajustRatio;
                                            $maxWin = $maxWin > 0 ? $maxWin : 0;
                                            $maxWin = $maxWin > $gameData->min_feature_win ? $maxWin : $gameData->min_feature_win;
                                            $winData = [];
                                            for ($i = 0; $i < count($spinData); $i++) {
                                                $spin = (object) $spinData[$i];
                                                if ($spinData[$i]['win'] <= $maxWin) {
                                                    $count = (int) $spinData[$i];
                                                    while ($count > 0) {
                                                        $winData[] = $spinData[$i]['win'];
                                                        $count--;
                                                    }
                                                }
                                            }
                                            $forceWin = $winData[array_rand($winData)];
                                            for ($i = 0; $i < count($spinData); $i++) {
                                                $win = $spinData[$i]['win'];
                                                if ($win == $forceWin) {
                                                    $fileName = $spinData[$i]['file'];
                                                }
                                            }
                                        }
                                        // ############################################################

                                        $nextRunFeature = 0;
                                        $ssData->fileName = $fileName;
                                        $ssData->lineIndex = $lineIndex + 1; //Next turn
                                    }
                                    // $dataType = $hasEntry ? 'freespin_entry.txt' : "feature";

                                } else {
                                    if (! $freeMode) {
                                        // $maxWin = $freeMode ? $rtpFeature / $ajustRatio : $rtpNormal / $ajustRatio;
                                        // $spinData = spinConfig($path, $gameName, $dataType);
                                        $spinItem = (object) $spinData[array_rand($spinData)];
                                        // l(json_encode($spinItem));
                                        $winRatio = $EASY_WIN_RATIO;
                                        $inArr = [];
                                        for ($i = 0; $i < 100; $i++) {
                                            $hasIn = $i < $winRatio;
                                            $inArr[] = $hasIn;
                                        }
                                        $forceData = $inArr[array_rand($inArr)];
                                        if ($forceData) {
                                            // GAME GENERATE MAX_WIN VALUE ################################
                                            $maxWin = $USE_RTP ? $rtpNormal / $ajustRatio : $spinItem->win;
                                            $maxWin = $maxWin > 0 ? $maxWin : 0;
                                            $winData = [];
                                            for ($i = 0; $i < count($spinData); $i++) {
                                                $spin = (object) $spinData[$i];
                                                if ($spinData[$i]['win'] > 0 && $spinData[$i]['win'] <= $maxWin) {
                                                    $count = (int) $spinData[$i];
                                                    while ($count > 0) {
                                                        $winData[] = $spinData[$i]['win'];
                                                        $count--;
                                                    }
                                                }
                                            }
                                            $forceWin = count($winData) > 0 ? $winData[array_rand($winData)] : 0;
                                            for ($i = 0; $i < count($spinData); $i++) {
                                                $win = $spinData[$i]['win'];
                                                if ($win == $forceWin) {
                                                    $fileName = $spinData[$i]['file'];
                                                    $count = (int) $spinData[$i]['count'];
                                                    $lineIndex = rand(1, $count);
                                                }
                                            }
                                        } else {
                                            $forceWin = 0;
                                            for ($i = 0; $i < count($spinData); $i++) {
                                                $win = $spinData[$i]['win'];
                                                if ($win == $forceWin) {
                                                    $fileName = $spinData[$i]['file'];
                                                    $count = (int) $spinData[$i]['count'];
                                                    $lineIndex = rand(1, $count);
                                                }
                                            }
                                        }
                                    } else {
                                        $fileName = $ssData->fileName;
                                        $lineIndex = $ssData->lineIndex; //Current turn
                                        $ssData->lineIndex = $lineIndex + 1;
                                    }
                                }
                                // $fileName = "slotgen_win_34_data.txt";
                                // $lineIndex = 1;
                                $pull = GameController::spinConfigData($path, $fileName, $lineIndex, $dataType);
                                if ($pull) {
                                    $totalResultRep = $pull->total_result_rep;
                                    $winSymbolRep = $pull->win_symbol_rep;
                                    $totalResult = $totalResultRep;
                                    $resulJson = $pull->result_json;
                                    for ($i = 0; $i < count($winSymbolRep); $i++) {
                                        for ($j = 0; $j < count($winSymbolRep[$i][0]); $j++) {
                                            $totalResult = str_replace('-x' . $i . $j . '-', $winSymbolRep[$i][0][$j]->name, $totalResult);
                                            $totalResult = str_replace('-xx' . $i . $j . '-', $winSymbolRep[$i][0][$j]->win * $ajustRatio, $totalResult);
                                        }
                                        for ($c = 0; $c < count($winSymbolRep[$i][1]); $c++) {
                                            $totalResult = str_replace('-xxx' . $i . '-', number_format($winSymbolRep[$i][1][$c]->win_total_multi * $ajustRatio, 2, '.', ''), $totalResult);
                                            $totalResult = str_replace('-xxxx' . $i . '-', number_format($winSymbolRep[$i][1][$c]->win_total * $ajustRatio, 2, '.', ''), $totalResult);
                                        }
                                    }
                                    for ($i = 0; $i < count($resulJson); $i++) {
                                        $totalBetOld = isset($resulJson[count($resulJson) - $i - 1]->bet_amount) ? $resulJson[count($resulJson) - $i - 1]->bet_amount : 0;
                                        $winTotalMultiDrop = $resulJson[$i]->win_total * $ajustRatio;
                                        // var_dump($winTotalMultiDrop);
                                        $resulJson[$i]->credit_drop = number_format($wallet, 2, '.', '');
                                        // $resulJson[$i]->profit = number_format(($resulJson[$i]->profit * $ajustRatio * $baseBet) / $gameData->base_bet, 2, '.', '');
                                        // $resulJson[$i]->bet_amount = number_format($totalBet, 2, '.', '');  
                                        // $resulJson[$i]->total_freespin = number_format($resulJson[$i]->total_freespin * $ajustRatio, 2, '.', '');
                                        // $resulJson[$i]->win_total = number_format($resulJson[$i]->win_total * $ajustRatio, 2, '.', '');
                                        // $resulJson[$i]->win_multi = number_format($resulJson[$i]->win_multi * $ajustRatio, 2, '.', '');
                                        $resulJson[$i]->bet_size = $betSize;
                                        $resulJson[$i]->bet_level = $betLevel;
                                        for ($j = 0; $j < count($resulJson[$i]->win_drop); $j++) {
                                            $resulJson[$i]->win_drop[$j]->win = $resulJson[$i]->win_drop[$j]->win * $ajustRatio;
                                        }
                                        $resulJson[$i]->win_total = number_format($resulJson[$i]->win_total * $ajustRatio, 2, '.', '');
                                        $resulJson[$i]->win_multi = number_format($resulJson[$i]->win_multi * $ajustRatio, 2, '.', '');
                                        // $resulJson[$i]->free_spin_win = number_format($resulJson[$i]->free_spin_win * $ajustRatio, 2, '.', '');
                                        // $resulJson[$i]->total_multi_check =  $resulJson[count($resulJson) - 1]->total_multi_check;
                                    }
                                    $wallet = $wallet + $pull->win_multi * $ajustRatio;
                                    // var_dump(json_encode($resulJson));
                                    // Ajust betsize & level ratio (basic data is 1:1)
                                    // $bonusRatio = $freeMode ? 10 : 1; // x10 in feature mode
                                    // $ajustRatio = $ajustRatio * $bonusRatio;
                                    $pull->win_amount = number_format($pull->win_amount * $ajustRatio, 2, '.', '');
                                    $pull->win_multi = number_format($pull->win_multi * $ajustRatio, 2, '.', '');
                                    $pull->freespin_win = number_format($pull->freespin_win * $ajustRatio, 2, '.', '');
                                    // $pull->WinOnDrop =  number_format($pull->WinOnDrop * $ajustRatio, 2, '.', '');
                                    // for ($i = 0; $i < count($pull->ActiveLines); $i++) {
                                    //     $pull->ActiveLines[$i]->win_amount =  number_format($pull->ActiveLines[$i]->win_amount * $ajustRatio, 2, '.', '');
                                    // }
                                    // for ($i = 0; $i < count($pull->DropLineData); $i++) {
                                    //     $pull->DropLineData[$i]->WinOnDrop =  number_format($pull->DropLineData[$i]->WinOnDrop * $ajustRatio, 2, '.', '');
                                    //     for ($j = 0; $j < count($pull->DropLineData[$i]->ActiveLines); $j++) {
                                    //         $pull->DropLineData[$i]->ActiveLines[$j]->win_amount =  number_format($pull->DropLineData[$i]->ActiveLines[$j]->win_amount * $ajustRatio, 2, '.', '');
                                    //     }
                                    // }
                                    $winAmount = $pull->win_multi;
                                    if ($winAmount > 0) {
                                        if ($userPlayer) {
                                            if ($USE_SEAMLESS) {
                                                $data = [
                                                    'action' => 'settle',
                                                    'user_name' => $userNameAgentNew,
                                                    'amount' => $winAmount,
                                                    'transaction' => $transaction,
                                                    'game_code' => $game->uuid,
                                                    'game_name' => $gameName
                                                ];
                                                $agentcyRes = $core->sendCurl($data, $apiAgent);
                                            } else {
                                                $wallet = $seamless->updateWallet($userPlayer, $wallet);
                                            }
                                        }
                                        if ($USE_RTP) {
                                            if ($freeMode) {
                                                $rtpFeature = $rtpFeature - $winAmount;
                                            } else {
                                                $rtpNormal = $rtpNormal - $winAmount;
                                            }
                                        }
                                    }

                                    if ($freeMode && $isContinuous) {
                                        $ssData->last_multiply = $pull->LastMultiply;
                                    }
                                    if (! $freeMode) {
                                        $nextRunFeature = $nextRunFeature + 1;
                                    }
                                    // $newFreeSpin = $freeMode ? $numFreeSpin - 1 : $pull->FreeSpin;
                                    // l(json_encode($pull));
                                    $newFreeSpin = $pull->free_num;
                                    $ssData->freespin = $newFreeSpin;
                                    $freeSpin = $newFreeSpin > 0 || $newFreeSpin == -1 ? 1 : 0;
                                    if ($freeMode && $newFreeSpin == 0) {
                                        $ssData->last_multiply = 0;
                                    }

                                    // $WinLogs = implode("\n", $pull->WinLogs);
                                    // $ActiveLines = json_encode($pull->ActiveLines);
                                    // $iconData = json_encode($pull->SlotIcons);
                                    // $multiply = $pull->MultipyScatter;
                                    // $winLog = implode("\n", $pull->WinLogs);
                                    // $dropLineData = json_encode($pull->DropLineData);
                                    // $totalWay = $pull->TotalWay;
                                    // $winOnDrop = $pull->WinOnDrop;
                                    // $dropLine = $pull->DropLine;
                                    // $dropFeature = 0;
                                    // $MultipleList = $forceScatter ? json_encode($ssData->multiple_list) : json_encode($pull->MultipleList);
                                    // $transaction = Str::random(14);
                                    $parentId = $ssData->parent_id ? $ssData->parent_id : 0;
                                    $fileNameNew = str_replace(".txt", "", $fileName);
                                    $winMulti = $pull->win_multi;
                                    $resultJson = json_encode($pull->result_json);
                                    $totalMulti = $pull->total_multi;

                                    // insertSpinlogs($playerId, $gameName, $wallet, $totalBet, $winAmount, $winMulti, $result, $parentId, $transaction, $resultJson, $totalMulti, $db);
                                    $spinLogs = new BigBassBonanzaSpinLogs;
                                    $data = [
                                        'free_num' => $newFreeSpin,
                                        'num_line' => $baseBet,
                                        'betamount' => $betSize,
                                        'balance' => $wallet,
                                        'credit_line' => $bet_level,
                                        'total_bet' => $totalBet,
                                        'win_amount' => $winAmount,
                                        'active_icons' => 0,
                                        'active_lines' => 0,
                                        'icon_data' => 0,
                                        'spin_ip' => 1,
                                        'multipy' => 0,
                                        'win_log' => 0,
                                        'transaction_id' => $transaction,
                                        'drop_line' => 0,
                                        'total_way' => 0,
                                        'first_drop' => 0,
                                        'is_free_spin' => $freeMode,
                                        'parent_id' => $parentId,
                                        'drop_normal' => 0,
                                        'drop_feature' => 0,
                                        'mini_win' => 'mini_win',
                                        'mini_result' => 'mini_result',
                                        'multiple_list' => 0,
                                        'player_id' => $sessionPlayer->uuid,
                                        'result_json' => $pull->result_json,
                                        'rtp_file' => $fileName,
                                        'rtp_index' => $lineIndex,
                                        'rtp_key' => "{$fileNameNew}_index_$lineIndex",
                                    ];
                                    $spinLogs->fill($data);
                                    $spinLogs->save();

                                    // $gameName = 'Big Bass Bonanza';
                                    // $game = Game::where('name', $gameName)->first();
                                    $profit = $pull->win_multi - $totalBet;
                                    $sesionId = $sessionPlayer->player_uuid;
                                    $userPlayer = $userPlayer != null ? $userPlayer : $sessionPlayer;

                                    \Helper::generateGameHistory($userPlayer, $sesionId, $transaction, $profit > 0 ? 'win' : 'loss', $winAmount, $totalBet, $wallet, $profit, $gameName, $game->uuid, 'balance', 'originals', $agentId, $wallet);

                                    $lastid = BigBassBonanzaSpinLogs::latest()->first()->uuid;
                                    if (! $freeMode && $forceScatter) {
                                        $ssData->parent_id = $lastid;
                                    }
                                    if ($newFreeSpin == 0) {
                                        $ssData->parent_id = 0;
                                        $ssData->free_spin_index = 0;
                                        $ssData->freespin = 0;
                                        // $ssData->multiple_list = "reset"; //Debug reset multiple
                                    }
                                    if ($parentId != 0 && $freeMode) {
                                        $recordFree = BigBassBonanzaSpinLogs::where('uuid', $parentId)->first();
                                        $dropNormal = $spinLogs->drop_normal;
                                        $dropFeature = $recordFree->drop_feature;
                                        $dropFeature = $dropFeature + $dropNormal;

                                        $winAmountOld = $spinLogs->win_amount;
                                        $winAmountNew = $recordFree->win_amount;
                                        $winAmount = $winAmountNew + $winAmountOld;
                                        BigBassBonanzaSpinLogs::where('uuid', $parentId)->update(['win_amount' => $winAmount, 'balance' => $wallet]);
                                    }
                                    // $ssData->multiple_list = json_decode($MultipleList);

                                    $ssData->free_mode = $freeMode;
                                    $ssData->bet_size = $betSize;
                                    $sessionData = json_encode($ssData);
                                    $sessionPlayer->credit = $wallet;
                                    $sessionPlayer->return_feature = $rtpFeature;
                                    $sessionPlayer->return_normal = $rtpNormal;
                                    $sessionPlayer->nextrun_feature = $nextRunFeature;
                                    $sessionPlayer->session_data = $ssData;
                                    $sessionPlayer->save();
                                    // var_dump(json_encode($gameData));

                                    $resData = [
                                        'result' => $totalResult,
                                        'win_amount' => number_format($pull->win_amount, 2, '.', ''),
                                        'win_multi' => number_format($pull->win_multi, 2, '.', ''),
                                        'bet_amount' => number_format($totalBet, 2, '.', ''),
                                        'credit' => number_format($wallet, 2, '.', ''),
                                        'free_mode' => $pull->free_mode,
                                        'freespin_win' => number_format($pull->freespin_win, 2, '.', ''),
                                        'free_num' => $pull->free_num,
                                        'total_multi' => $pull->total_multi,
                                        'free_more' => $gameData->freespin_more,
                                        'freespin_require' => $gameData->freespin_require,
                                        // "multiply_step" => $gameInfo->multiply_step,
                                        'freespin_more' => $gameData->freespin_more,
                                        "count_scatter" => $pull->count_scatter,
                                        // "drop_freespin" => $pull->drop_freespin,
                                        // "drop_normal" => $pull->drop_normal,
                                        // "freenum_drop" => $pull->freenum_drop,
                                        'result_json' => $pull->result_json,
                                        "money_change" => $pull->money_change,
                                        "check_more" => $pull->check_more,
                                        'total_result_rep' => $pull->total_result_rep,
                                        'file_name' => $fileName,
                                        'line_index' => $lineIndex,
                                        'system_rtp' => $SYSTEM_RTP,
                                        'SHARE_FEATURE' => $SHARE_FEATURE,
                                        'nextrun_feature' => number_format($rtpFeature, 2, '.', ''),
                                        'return_normal' => number_format($rtpNormal, 2, '.', ''),
                                        'credit_old' => number_format($wallet - $pull->win_multi, 2, '.', ''),
                                    ];

                                    if ($CHECK_REPORT_RTP) {
                                        $winMul = number_format($pull->win_multi, 2, '.', '');
                                        $winMulBe = number_format($pull->win_multi, 2, '.', '') / $ajustRatio;
                                        $fileNameNew = str_replace('.txt', '', $fileName);
                                        $rtpNormalNewFirst = $rtpNormalNew - $returnNormal;
                                        $rtpNormalLast = $rtpNormalLast - $returnNormal;
                                        Log::debug("###################### CALCULATE RTP $gameName ################## ");
                                        Log::debug("[BET]  betLevel:$betLevel * betSize:$betSize = ajustRatio $ajustRatio, SYSTEM_RTP:$SYSTEM_RTP, SHARE_FEATURE:$SHARE_FEATURE");

                                        Log::debug("file_name:{$fileNameNew}_index_$lineIndex");
                                        Log::debug("RtpFundNormal Before:$sRtpNormal , RtpFundFeature Before :$sRtpFeature");
                                        Log::debug(" totalBet : $totalBet * SYSTEM_RTP :$SYSTEM_RTP = returnBet : $returnBet");
                                        Log::debug(" returnBet : $returnBet * SHARE_FEATURE :$SHARE_FEATURE = returnFeature : $returnFeature");
                                        Log::debug(" returnBet : $returnBet - returnFeature :$returnFeature = returnNormal : $returnNormal");
                                        if ($CHECK_REPORT_JACKPOT) {
                                            Log::debug('');
                                            Log::debug("------------------ CALCULATE JACKPOT $gameName ------------------");
                                            Log::debug("[BET]  betLevel:$betLevel * betSize:$betSize = ajustRatio $ajustRatio,SYSTEM_RTP:$SYSTEM_RTP, JACKPOT__RETURN_VALUE_RATIO:$JACKPOT__RETURN_VALUE_RATIO");
                                            Log::debug("totalBet:$totalBet * SYSTEM_RTP:$SYSTEM_RTP = returnBet:$returnBet");
                                            Log::debug("totalBet:$totalBet - returnBet:$returnBet = returnSystem:$returnSystem");
                                            Log::debug("returnSystem:$returnSystem * JACKPOT__RETURN_VALUE_RATIO:$JACKPOT__RETURN_VALUE_RATIO /100 = returnJackpot:$returnJackpot");
                                            Log::debug("returnJackpot:$returnJackpot + JACKPOT_BEFORE:$JACKPOT_BEFORE = JACKPOT AFTER:$JACKPOT_NEW");
                                            if ($checkJackpot) {
                                                Log::debug("checkJackpot: TRUE, JACKPOT AFTER:$JACKPOT_NEW - totalBet_jackpot_Value:$totalBet = JACKPOT AFTER:$JACKPOT");
                                                Log::debug("checkJackpot: TRUE, rtpNormal Old:$rtpNormalNewFirst + totalBet:$totalBet = rtpNormal New:$rtpNormalLast");
                                            } else {
                                                Log::debug("checkJackpot: FALSE, JACKPOT AFTER:$JACKPOT");
                                                $rtpNormalLast = $rtpNormalNew;
                                            }

                                            $returnBet = $totalBet * $SYSTEM_RTP / 100;
                                            $returnSystem = $totalBet - $returnBet;
                                            $returnJackpot = number_format($returnSystem * $JACKPOT__RETURN_VALUE_RATIO / 100, 3, '.', '');
                                            // Log::debug("RtpFundNormal Before :$sRtpNormal + returnNormal:$returnNormal - winMul:$winMul = ,RtpFundFeature Before :$sRtpNormal + returnFeature:$returnFeature - winMul:$winMul = RtpFundFeature After:$returnFeature");
                                            Log::debug("------------------ END CALCULATE JACKPOT $gameName ------------------");
                                            Log::debug('');
                                        }
                                        $rtpNormalNewFirst = $rtpNormal + $winMul - $returnNormal;
                                        $totalFund = $rtpNormalNewFirst + $returnNormal;
                                        Log::debug("(returnNormal:$returnNormal + RtpFundNormal Before:$rtpNormalNewFirst) = TotalFund : $totalFund");
                                        $forceData = isset($forceData) ? $forceData : false;
                                        if ($forceData) {
                                            Log::debug("forceData : true , TotalFund:$totalFund / ajustRatio:$ajustRatio  = maxWinNormal : $maxWin");
                                        } else {
                                            Log::debug('forceData : false , maxWinNormal : 0');
                                        }
                                        Log::debug(" winTotal : $winMulBe * ajustRatio :$ajustRatio = winTotal : $winMul");
                                        Log::debug("RtpFundNormal After:$rtpNormal, RtpFundFeature After:$returnFeature");
                                        // Log::debug("RtpFundNormal Before :$sRtpNormal + returnNormal:$returnNormal - winMul:$winMul = ,RtpFundFeature Before :$sRtpNormal + returnFeature:$returnFeature - winMul:$winMul = RtpFundFeature After:$returnFeature");
                                        Log::debug("###################### END CALCULATE RTP $gameName ################## ");
                                        Log::debug('');
                                    }
                                    if ($CHECK_REPORT_SPIN) {
                                        $betSize = $betSize;
                                        $betLevel = $betLevel;
                                        Log::debug("###################### SPIN DEBUG PAYOUT $gameName [$dataType] ################## ");
                                        Log::debug("Balance Before : $balanceBefor");
                                        Log::debug("[BET]  betLevel:$betLevel ,betSize:$betSize, baseBet:$baseBet => $totalBet");
                                        Log::debug('***** debug payout enabled!');
                                        Log::debug('wheelData ============ ~~~~~~~~  ');
                                        $result = $pull->result_json;
                                        $newReel = $result[0]->new_reel;
                                        $totalResultreplace = str_replace('[', '', $totalResult);
                                        $totalResultStrim = substr($totalResultreplace, 0, -1);
                                        $totalResultStrimArr = explode(']', $totalResultStrim);
                                        $reelOnScreen = [];
                                        for ($i = 0; $i < count($totalResultStrimArr); $i++) {
                                            $winDrop = isset($result[$i]->win_drop) ? $result[$i]->win_drop : [];
                                            $reel = [];
                                            $dropArr = explode(',', $totalResultStrimArr[$i]);
                                            $reelDrop = $dropArr[0];
                                            $arrReel = explode('|', $reelDrop);
                                            array_unshift($arrReel, 'blue'); // insert first array
                                            $countReel = count($arrReel);
                                            for ($j = 1; $j < $countReel; $j++) {
                                                $reel[] = $arrReel[$j];
                                                if ($j % (count($newReel)) == 0 && $j != 0) {
                                                    $numberReel = $j / count($newReel);
                                                    $reelOnScreen[] = $reel;
                                                    Log::debug(json_encode($reel));
                                                    $reel = [];
                                                }
                                            }
                                            if ($winDrop != []) {
                                                for ($n = 0; $n < count($winDrop); $n++) {
                                                    Log::debug('Symbol Drop ============ ~~~~~~~~');
                                                    $position = implode(',', $winDrop[$n]->position);
                                                    $symbolWin = $winDrop[$n]->name;
                                                    $win = $winDrop[$n]->win;
                                                    Log::debug("position : $position");
                                                    Log::debug("symbol_name_win : $symbolWin");
                                                    Log::debug("win : $win");
                                                }
                                            } else {
                                                Log::debug('No Drop ============ ~~~~~~~~');
                                            }
                                        }
                                        $winAmount = number_format($pull->win_amount, 2, '.', '');
                                        $winMul = number_format($pull->win_multi, 2, '.', '');
                                        $totalMulti = $pull->total_multi;
                                        $freeNum = $pull->free_num;
                                        Log::debug('stopPos ============ ~~~~~~~~');
                                        // Log::debug("file_name : $fileName , ");
                                        // Log::debug("line_index :$lineIndex");
                                        Log::debug("free_num : $freeNum, total_multi : $totalMulti, win_multi : $winMul, win_amount : $winAmount, LOG TRANSACTION ID: $transaction");
                                        Log::debug("Balance After : $wallet");
                                        Log::debug('END_SPIN_!!!!!!!!!!!!!!!!!!!!!');
                                        // }
                                    }

                                    // $resData = [
                                    //     'credit' =>  number_format($wallet, 2, '.', ''),
                                    //     'freemode' => $freeMode,
                                    //     'jackpot' => 0,
                                    //     'free_spin' => $freeSpin,
                                    //     'free_num' => $newFreeSpin,
                                    //     'scaler' => 0,
                                    //     'num_line' => $baseBet,
                                    //     'betamount' => $betSize,
                                    //     'pull' => $pull,
                                    // ];
                                    if (isset($pull->expand_field)) {
                                        $resData = (object) array_merge((array) $resData, (array) $pull->expand_field);
                                    }
                                    $endTime = microtime(true) * 1000 - $startTime;
                                    Log::debug("Time elapsed $endTime ms");
                                    return $this->sendResponse($resData, 'action');
                                }
                            } else {
                                $LogError = \Illuminate\Support\Str::random(13);
                                if ($wallet < $totalBet) {
                                    return $this->sendError("($errorMess->Insufficient_balance:" . 'S3202UQLXTO20' . ')');
                                } elseif ($totalBet > $MAX_BET) {
                                    return $this->sendError("($errorMess->Error_Max_Bet:" . 'S3202UQLXTO21' . ')');
                                } elseif ($totalBet < $MIN_BET) {
                                    return $this->sendError("($errorMess->Error_Min_Bet:" . 'S3202UQLXTO22' . ')');
                                }
                            }
                        } else {
                            $LogError = \Illuminate\Support\Str::random(13);

                            return $this->sendError('Invalid betsize or bet level. (Error Code:' . $LogError . ')');
                        }
                    } else {
                        $LogError = \Illuminate\Support\Str::random(13);

                        return $this->sendError('Game or Rule is not found.  (Error Code:' . $LogError . ')');
                    }
                } else {
                    $LogError = \Illuminate\Support\Str::random(13);

                    return $this->sendError('Session is not found. (Error Code:' . $LogError . ')');
                }
            }
            if ($act === 'histories') {
                $sort = ['spin_date' => 'DESC'];
                $isFreeSpin = false;
                if ($sessionPlayer) {
                    $search = [
                        'parent_id' => '0',
                        'player_id' => $sessionPlayer->uuid,
                    ];
                    $totalBet = BigBassBonanzaSpinLogs::where($search)->whereBetween('created_at', [$from, $to])->sum('total_bet');
                    $totalWin = BigBassBonanzaSpinLogs::where($search)->whereBetween('created_at', [$from, $to])->sum('win_amount');
                    $totalProfit = $totalWin - $totalBet;
                    $limit = 12;
                    $page = $page ? $page : 1;
                    $recorsPerPage = ($page - 1) * $limit;
                    $paginate = BigBassBonanzaSpinLogs::where($search)->whereBetween('created_at', [$from, $to])
                        ->orderBy('created_at', 'desc')
                        ->select(
                            'balance',
                            'betamount',
                            'credit_line',
                            'drop_feature',
                            'drop_normal',
                            'free_num',
                            'uuid',
                            'id',
                            'multipy',
                            'created_at',
                            'total_bet',
                            'total_way',
                            'transaction_id',
                            'win_amount',
                            'parent_id'
                        )
                        ->paginate($limit);
                    $resData = [];
                    $items = [];
                    for ($i = 0; $i < count($paginate); $i++) {
                        $spinDate = date('m/d', strtotime($paginate[$i]['created_at']));
                        $spinHour = date('H:i:s', strtotime($paginate[$i]['created_at']));

                        $items[] = (object) [
                            'bet_amount' => number_format($paginate[$i]['total_bet'], 2, '.', ''),
                            'win_amount' => number_format($paginate[$i]['win_multi'], 2, '.', ''),
                            'totalProfit' => number_format($paginate[$i]['win_amount'] - $paginate[$i]['total_bet'], 2, '.', ''),
                            'credit' => number_format($paginate[$i]['balance'], 2, '.', ''),
                            'uuid' => $paginate[$i]['uuid'],
                            'date' => $paginate[$i]['created_at']->format('Y-m-d H:i:s'),
                            'result_json' => $paginate[$i]['result_json'],
                            'win_amount' => number_format($paginate[$i]['win_amount'], 2, '.', ''),
                        ];
                    }
                    $resData = [
                        'totalRecord' => $paginate->total(),
                        'totalPage' => $paginate->lastPage(),
                        'perPage' => $limit,
                        'currentPage' => $page,
                        'displayTotal' => $limit,
                        'totalBet' => $totalBet,
                        'totalWin' => number_format($totalProfit, 2, '.', ''),
                        'totalProfit' => number_format($totalProfit, 2, '.', ''),
                        'data' => $items,
                    ];

                    return $this->sendResponse($resData, 'action');
                } else {
                    $msg = 'session not found';
                }
            }
            if ($act === 'history_detail') {
                $request = $request->all();
                $uuid = $request['uuid'];
                $session = BigBassBonanzaPlayer::where('uuid', $token)->first();
                if ($session) {
                    $ssData = (object) $session->session_data;
                    $resultJson = [];
                    $totalMultiJson = [];
                    $history = BigBassBonanzaSpinLogs::where('uuid', $uuid)->first();
                    if ($history) {
                        $historyChild = BigBassBonanzaSpinLogs::where('parent_id', $uuid)->get();
                        $resultJson = $history['result_json'];
                        // $resultJson[0]['win_total'] = number_format($resultJson[0]['win_total'], 2, '.', '');
                        // $resultJson[0]['win_multi'] = number_format($resultJson[0]['win_multi'], 2, '.', '');
                        $totalMultiJson = $history['total_multi'];
                        for ($i = 0; $i < count($historyChild); $i++) {
                            $spinDateChild = date('y/m/d', strtotime($historyChild[$i]['created_at']));
                            $spinHourChild = date('H:i:s', strtotime($historyChild[$i]['created_at']));
                            // for ($j = 0; $j <  count($historyChild[$i]['result_json']; $j++)) {

                            // }
                            // $historyChild[$i]['result_json'][0]['win_total'] = number_for {mat($historyChild[$i]['result_json'][0]['win_total'], 2, '.', '');
                            // $historyChild[$i]['result_json'][0]['win_multi'] = number_format($historyChild[$i]['result_json'][0]['win_multi'], 2, '.', '');
                            $resultJson = array_merge($resultJson, $historyChild[$i]['result_json']);
                        }

                        $resData = (object) [
                            'result_json' => $resultJson,
                            'currency_prefix' => $ssData->currency_prefix
                        ];

                        return $this->sendResponse($resData, 'Load log');
                    } else {
                        return $this->sendError('history not found');
                    }
                } else {
                    return $this->sendError('Session load fail');
                }
            }
            if ($act === 'buy') {
                $betamount = isset($p->betSize) ? $p->betSize : null;
                $bet_level = isset($p->betLevel) ? $p->betLevel : null;
                if ($sessionPlayer) {
                    $ssData = (object) $sessionPlayer->session_data;
                    $userName = $sessionPlayer->user_name;
                    // $wallet = $sessionPlayer->credit;
                    $nextRunFeature = $sessionPlayer->nextrun_feature;
                    $sRtpNormal = $sessionPlayer->return_normal;
                    $sRtpFeature = $sessionPlayer->return_feature;
                    $nextRunFeature = isset($nextRunFeature) ? $nextRunFeature : 0;
                    $numFreeSpin = isset($ssData->freespin) ? $ssData->freespin : 0;
                    $isContinuous = isset($ssData->multiply_continuous) ? $ssData->multiply_continuous : 0;
                    $prevMultiply = isset($ssData->last_multiply) ? $ssData->last_multiply : 0;
                    $freeMode = $numFreeSpin > 0 || $numFreeSpin == -1;
                    $dataType = $freeMode ? 'feature' : 'normal';
                    $freeSpinindex = $freeMode ? $ssData->free_spin_index : 0;
                    if ($freeSpinindex > 0) {
                        $dataType = "feature_$freeSpinindex";
                    }

                    $spinData = GameController::spinConfig($path, $dataType);
                    if ($gameData && $gameRule && $spinData) {
                        $baseBet = $ssData->base_bet;
                        if ($betamount && $bet_level) {
                            $betSize = $betamount;
                            $betLevel = $bet_level;
                            $ssData->betamount = $betSize;
                            $ssData->bet_level = $betLevel;
                            $buyFeature = isset($adjustRatio->value_buy_feature) ? $adjustRatio->value_buy_feature : $gameData->buy_feature;
                            $totalBet = $freeMode ? 0 : $baseBet * $betSize * $betLevel * $buyFeature;
                            $parentId = $ssData->parent_id ? $ssData->parent_id : 0;
                            $ajustRatio = $betSize * $betLevel;
                            $balanceBefor = $wallet;
                            $MAX_BET = isset($Agent->max_bet) ? $Agent->max_bet : $MAX_BET;
                            $MIN_BET = isset($Agent->min_bet) ? $Agent->min_bet : 0;
                            if ($wallet >= $totalBet && $totalBet <= $MAX_BET && $totalBet >= $MIN_BET || $freeMode) {
                                $wallet = $wallet - $totalBet;

                                $transaction = uniqid();
                                $gameName = 'Big Bass Bonanza';
                                $game = Game::where('name', $gameName)->first();

                                if ($userPlayer) {
                                    if ($USE_SEAMLESS) {
                                        $apiGetBet = $apiAgent . '/bet';
                                        $gameId = $game->id;
                                        $language = $lang;
                                        $timestamp  = time();
                                        $hash = md5("Amount=$totalBet&GameId=$gameId&Language=$language&OperatorId=$operatorId&PlayerId=$userNameAgentNew&ReferenceId=$transaction&RoundId=$parentId&Timestamp=$timestamp&Token=$operatorId$secretKey");

                                        $data = [
                                            'OperatorId' => $operatorId,
                                            'PlayerId' => $userNameAgentNew,
                                            'GameId'    => $gameId,
                                            'Hash' => $hash,
                                            'RoundId' => $parentId,
                                            'Amount' => $totalBet,
                                            'ReferenceId' => $transaction,
                                            'Timestamp' => $timestamp,
                                            'Language' => $language,
                                            'Token' => $operatorId,
                                        ];
                                        $agentcyRes = $core->sendCurl($data, $apiGetBet);
                                    } else {
                                        $wallet = $seamless->updateWallet($userPlayer, $wallet);
                                    }
                                }
                                $walletOld = $wallet;

                                $winRatio = $EASY_WIN_RATIO;
                                $featureRatio = $ACCESS_FEATURE_RATIO;
                                $returnBet = $totalBet * $SYSTEM_RTP / 100;
                                $returnFeature = $returnBet * $SHARE_FEATURE / 100;
                                $returnNormal = $returnBet - $returnFeature;


                                if (! $freeMode) {
                                    $rtpNormal = $sRtpNormal + $returnNormal;
                                    $rtpFeature = $sRtpFeature + $returnFeature;
                                } else {
                                    $rtpNormal = $sRtpNormal;
                                    $rtpFeature = $sRtpFeature;
                                }

                                $forceScatter = false;
                                $signCreditOk = $SIGN_FEATURE_CREDIT > 0 ? $rtpFeature >= $SIGN_FEATURE_CREDIT : true;
                                $signSpinNumOk = $SIGN_FEATURE_SPIN > 0 ? $nextRunFeature >= $SIGN_FEATURE_SPIN : true;
                                $minFeatureWin = isset($gameData->min_feature_win) ? $gameData->min_feature_win * $ajustRatio : 0;
                                $signRtpMode = $USE_RTP && $minFeatureWin > 0 ? $rtpFeature >= $minFeatureWin : true;

                                if (! $freeMode && $signCreditOk && $signSpinNumOk && $signRtpMode) {
                                    $featureRatio = $ACCESS_FEATURE_RATIO;
                                    // $featureRatio = 100;
                                    $inArr = [];
                                    for ($i = 0; $i < 100; $i++) {
                                        $hasIn = $i < $featureRatio;
                                        $inArr[] = $hasIn;
                                    }
                                    $forceScatter = $inArr[array_rand($inArr)];
                                }
                                $fileName = '';
                                $lineIndex = 0;

                                // ############ calculate Jackpot
                                // ####### JACKPOT CONFIG ###################
                                $JACKPOT__RETURN_VALUE_RATIO = $adjustRatio->jackpot_return_value_ratio;
                                $JACKPOT_BEFORE = $adjustRatio->jackpot_value;
                                $JACKPOT_WIN_RATIO = $adjustRatio->jackpot_win_ratio;

                                // ####### ####### ###################

                                $returnSystem = $totalBet - $returnBet;
                                $returnJackpot = number_format($returnSystem * $JACKPOT__RETURN_VALUE_RATIO / 100, 3, '.', '');
                                // var_dump($JACKPOT);
                                $JACKPOT = $JACKPOT_BEFORE + $returnJackpot;
                                $JACKPOT_NEW = $JACKPOT;
                                // var_dump($JACKPOT);

                                $number = range(0, $JACKPOT_WIN_RATIO);
                                shuffle($number);
                                $randArrJackpot = rand(0, count($number) - 1);
                                $randNumberJackpot = $number[$randArrJackpot];
                                $checkJackpot = false;
                                if ($randNumberJackpot == 1 && $JACKPOT >= $totalBet) {
                                    $checkJackpot = true;
                                }
                                $rtpNormalNew = $rtpNormal;
                                if ($checkJackpot) {
                                    $rtpNormal = $rtpNormal + $totalBet;
                                    $JACKPOT = $JACKPOT - $totalBet;
                                }
                                $rtpNormalLast = $rtpNormal;
                                $adjustRatio->jackpot_value = $JACKPOT;
                                $adjustRatio->save();

                                // #######################
                                $maxWin = 0;

                                $forceScatter = true; //Debug only
                                if ($forceScatter) {
                                    // l('forceScatter');
                                    $hasEntry = isset($gameData->free_spin_entry) ? $gameData->free_spin_entry : false;
                                    if ($hasEntry) {
                                        $fileName = 'freespin_entry.txt';
                                        $lineIndex = 0; //Will random in freespin_entry

                                        $dataType = 'feature';
                                        $spinData = GameController::spinConfig($path, $dataType);
                                        if ($spinData != false) {
                                            $accessIndex = 1;
                                            $accessFileName = '';
                                            // GAME GENERATE MAX_WIN VALUE ################################
                                            if ($USE_RTP) {
                                                // $rtpFeature = $sessionsEntity['return_feature'];
                                                $maxWin = $rtpFeature / $ajustRatio;
                                                $maxWin = $maxWin > 0 ? $maxWin : 0;
                                                $maxWin = $maxWin > $gameData->min_feature_win ? $maxWin : $gameData->min_feature_win;
                                                // l('$maxWin: '.$maxWin);
                                                $winData = [];
                                                for ($i = 0; $i < count($spinData); $i++) {
                                                    $spin = (object) $spinData[$i];
                                                    if ($spinData[$i]['win'] <= $maxWin) {
                                                        $count = (int) $spinData[$i];
                                                        while ($count > 0) {
                                                            $winData[] = $spinData[$i]['win'];
                                                            $count--;
                                                        }
                                                    }
                                                }
                                                $forceWin = $winData[array_rand($winData)];
                                                for ($i = 0; $i < count($spinData); $i++) {
                                                    $win = $spinData[$i]['win'];
                                                    if ($win == $forceWin) {
                                                        $accessFileName = $spinData[$i]['file'];
                                                    }
                                                }
                                            }
                                            // ############################################################
                                            $ssData->fileName = $accessFileName;
                                            $ssData->lineIndex = $accessIndex;
                                        }
                                    } else {
                                        $dataType = 'feature';
                                        $spinData = GameController::spinConfig($path, $dataType);
                                        $spinItem = (object) $spinData[array_rand($spinData)];
                                        $fileName = $spinItem->file;
                                        $lineIndex = 1;
                                        $valueIncreFreeSpin = 500;
                                        // GAME GENERATE MAX_WIN VALUE ################################
                                        if ($USE_RTP) {
                                            $maxWin = $rtpFeature / $ajustRatio;
                                            $maxWin = $maxWin > 0 ? $maxWin : 0;
                                            $maxWin = $maxWin > $gameData->min_feature_win ? $maxWin + $valueIncreFreeSpin : $gameData->min_feature_win + $valueIncreFreeSpin;
                                            $maxWin = $maxWin > $gameData->min_feature_win ? $maxWin : $gameData->min_feature_win;
                                            if ($CHECK_REPORT_RTP) {
                                                Log::debug(" rtpFeature : $rtpFeature / ajustRatio :$ajustRatio = maxWinFree : $maxWin");
                                            }
                                            $winData = [];
                                            for ($i = 0; $i < count($spinData); $i++) {
                                                $spin = (object) $spinData[$i];
                                                if ($spinData[$i]['win'] <= $maxWin) {
                                                    $count = (int) $spinData[$i];
                                                    while ($count > 0) {
                                                        $winData[] = $spinData[$i]['win'];
                                                        $count--;
                                                    }
                                                }
                                            }
                                            $forceWin = $winData[array_rand($winData)];
                                            for ($i = 0; $i < count($spinData); $i++) {
                                                $win = $spinData[$i]['win'];
                                                if ($win == $forceWin) {
                                                    $fileName = $spinData[$i]['file'];
                                                }
                                            }
                                        }
                                        // ############################################################

                                        $nextRunFeature = 0;
                                        $ssData->fileName = $fileName;
                                        $ssData->lineIndex = $lineIndex + 1; //Next turn
                                    }
                                    // $dataType = $hasEntry ? 'freespin_entry.txt' : "feature";

                                } else {
                                    if (! $freeMode) {
                                        // $maxWin = $freeMode ? $rtpFeature / $ajustRatio : $rtpNormal / $ajustRatio;
                                        // $spinData = spinConfig($path, $gameName, $dataType);
                                        $spinItem = (object) $spinData[array_rand($spinData)];
                                        // l(json_encode($spinItem));
                                        $winRatio = $EASY_WIN_RATIO;
                                        $inArr = [];
                                        for ($i = 0; $i < 100; $i++) {
                                            $hasIn = $i < $winRatio;
                                            $inArr[] = $hasIn;
                                        }
                                        $forceData = $inArr[array_rand($inArr)];

                                        if ($forceData) {
                                            // GAME GENERATE MAX_WIN VALUE ################################
                                            $maxWin = $USE_RTP ? $rtpNormal / $ajustRatio : $spinItem->win;
                                            $maxWin = $maxWin > 0 ? $maxWin : 0;
                                            $winData = [];
                                            for ($i = 0; $i < count($spinData); $i++) {
                                                $spin = (object) $spinData[$i];
                                                if ($spinData[$i]['win'] > 0 && $spinData[$i]['win'] <= $maxWin) {
                                                    $count = (int) $spinData[$i];
                                                    while ($count > 0) {
                                                        $winData[] = $spinData[$i]['win'];
                                                        $count--;
                                                    }
                                                }
                                            }
                                            $forceWin = count($winData) > 0 ? $winData[array_rand($winData)] : 0;
                                            for ($i = 0; $i < count($spinData); $i++) {
                                                $win = $spinData[$i]['win'];
                                                if ($win == $forceWin) {
                                                    $fileName = $spinData[$i]['file'];
                                                    $count = (int) $spinData[$i]['count'];
                                                    $lineIndex = rand(1, $count);
                                                }
                                            }
                                        } else {
                                            $maxWin = 0;
                                            $forceWin = 0;
                                            for ($i = 0; $i < count($spinData); $i++) {
                                                $win = $spinData[$i]['win'];
                                                if ($win == $forceWin) {
                                                    $fileName = $spinData[$i]['file'];
                                                    $count = (int) $spinData[$i]['count'];
                                                    $lineIndex = rand(1, $count);
                                                }
                                            }
                                        }
                                    } else {
                                        $fileName = $ssData->fileName;
                                        $lineIndex = $ssData->lineIndex; //Current turn
                                        $ssData->lineIndex = $lineIndex + 1;
                                    }
                                }

                                // $fileName = "slotgen_win_15_data.txt";
                                // $lineIndex = 1;
                                $pull = GameController::spinConfigData($path, $fileName, $lineIndex, $dataType);
                                if ($pull) {
                                    $totalResultRep = $pull->total_result_rep;
                                    $winSymbolRep = $pull->win_symbol_rep;
                                    $totalResult = $totalResultRep;
                                    $resulJson = $pull->result_json;
                                    for ($i = 0; $i < count($winSymbolRep); $i++) {
                                        for ($j = 0; $j < count($winSymbolRep[$i][0]); $j++) {
                                            $totalResult = str_replace('-x' . $i . $j . '-', $winSymbolRep[$i][0][$j]->name, $totalResult);
                                            $totalResult = str_replace('-xx' . $i . $j . '-', $winSymbolRep[$i][0][$j]->win * $ajustRatio, $totalResult);
                                        }
                                        for ($c = 0; $c < count($winSymbolRep[$i][1]); $c++) {
                                            $totalResult = str_replace('-xxx' . $i . '-', number_format($winSymbolRep[$i][1][$c]->win_total_multi * $ajustRatio, 2, '.', ''), $totalResult);
                                            $totalResult = str_replace('-xxxx' . $i . '-', number_format($winSymbolRep[$i][1][$c]->win_total * $ajustRatio, 2, '.', ''), $totalResult);
                                        }
                                    }
                                    for ($i = 0; $i < count($resulJson); $i++) {
                                        $totalBetOld = isset($resulJson[count($resulJson) - $i - 1]->bet_amount) ? $resulJson[count($resulJson) - $i - 1]->bet_amount : 0;
                                        $winTotalMultiDrop = $resulJson[$i]->win_total * $ajustRatio;
                                        // var_dump($winTotalMultiDrop);
                                        $resulJson[$i]->credit_drop = number_format($wallet, 2, '.', '');
                                        // $resulJson[$i]->profit = number_format(($resulJson[$i]->profit * $ajustRatio * $baseBet) / $gameData->base_bet, 2, '.', '');
                                        // $resulJson[$i]->bet_amount = number_format($totalBet, 2, '.', '');  
                                        // $resulJson[$i]->total_freespin = number_format($resulJson[$i]->total_freespin * $ajustRatio, 2, '.', '');
                                        // $resulJson[$i]->win_total = number_format($resulJson[$i]->win_total * $ajustRatio, 2, '.', '');
                                        // $resulJson[$i]->win_multi = number_format($resulJson[$i]->win_multi * $ajustRatio, 2, '.', '');
                                        $resulJson[$i]->bet_size = $betSize;
                                        $resulJson[$i]->bet_level = $betLevel;
                                        for ($j = 0; $j < count($resulJson[$i]->win_drop); $j++) {
                                            $resulJson[$i]->win_drop[$j]->win = $resulJson[$i]->win_drop[$j]->win * $ajustRatio;
                                        }
                                        $resulJson[$i]->win_total = number_format($resulJson[$i]->win_total * $ajustRatio, 2, '.', '');
                                        $resulJson[$i]->win_multi = number_format($resulJson[$i]->win_multi * $ajustRatio, 2, '.', '');
                                        $resulJson[$i]->free_spin_win = number_format($resulJson[$i]->free_spin_win * $ajustRatio, 2, '.', '');
                                        $resulJson[$i]->total_multi_check =  $resulJson[count($resulJson) - 1]->total_multi_check;
                                    }
                                    $wallet = $wallet + $pull->win_multi * $ajustRatio;
                                    // var_dump(json_encode($resulJson));
                                    // Ajust betsize & level ratio (basic data is 1:1)
                                    // $bonusRatio = $freeMode ? 10 : 1; // x10 in feature mode
                                    // $ajustRatio = $ajustRatio * $bonusRatio;
                                    $pull->win_amount = number_format($pull->win_amount * $ajustRatio, 2, '.', '');
                                    $pull->win_multi = number_format($pull->win_multi * $ajustRatio, 2, '.', '');
                                    $pull->freespin_win = number_format($pull->freespin_win * $ajustRatio, 2, '.', '');
                                    // $pull->WinOnDrop =  number_format($pull->WinOnDrop * $ajustRatio, 2, '.', '');
                                    // for ($i = 0; $i < count($pull->ActiveLines); $i++) {
                                    //     $pull->ActiveLines[$i]->win_amount =  number_format($pull->ActiveLines[$i]->win_amount * $ajustRatio, 2, '.', '');
                                    // }
                                    // for ($i = 0; $i < count($pull->DropLineData); $i++) {
                                    //     $pull->DropLineData[$i]->WinOnDrop =  number_format($pull->DropLineData[$i]->WinOnDrop * $ajustRatio, 2, '.', '');
                                    //     for ($j = 0; $j < count($pull->DropLineData[$i]->ActiveLines); $j++) {
                                    //         $pull->DropLineData[$i]->ActiveLines[$j]->win_amount =  number_format($pull->DropLineData[$i]->ActiveLines[$j]->win_amount * $ajustRatio, 2, '.', '');
                                    //     }
                                    // }
                                    $winAmount = $pull->win_multi;
                                    if ($userPlayer) {
                                        if ($USE_SEAMLESS) {
                                            $apiGetResult = $apiAgent . '/result';
                                            $gameId = $game->id;
                                            $language = $lang;
                                            $timestamp  = time();
                                            $hash = md5("Amount=$winAmount&GameId=$gameId&Language=$language&OperatorId=$operatorId&PlayerId=$userNameAgentNew&ReferenceId=$transaction&RoundId=$parentId&Timestamp=$timestamp&Token=$operatorId$secretKey");

                                            $data = [
                                                // 'action' => 'load_wallet',
                                                // 'user_name' => $userNameAgentNew,
                                                'OperatorId' => $operatorId,
                                                'PlayerId' => $userNameAgentNew,
                                                'GameId'    => $gameId,
                                                'Hash' => $hash,
                                                'RoundId' => $parentId,
                                                'Amount' => $winAmount,
                                                'ReferenceId' => $transaction,
                                                'Timestamp' => $timestamp,
                                                'Language' => $language,
                                                'Token' => $operatorId,
                                            ];
                                            $agentcyRes = $core->sendCurl($data, $apiGetResult);
                                        } else {
                                            $wallet = $seamless->updateWallet($userPlayer, $wallet);
                                        }
                                    }

                                    if ($freeMode && $isContinuous) {
                                        $ssData->last_multiply = $pull->LastMultiply;
                                    }
                                    if (! $freeMode) {
                                        $nextRunFeature = $nextRunFeature + 1;
                                    }
                                    // $newFreeSpin = $freeMode ? $numFreeSpin - 1 : $pull->FreeSpin;
                                    // l(json_encode($pull));
                                    $newFreeSpin = $pull->free_num;
                                    $ssData->freespin = $newFreeSpin;
                                    $freeSpin = $newFreeSpin > 0 || $newFreeSpin == -1 ? 1 : 0;
                                    if ($freeMode && $newFreeSpin == 0) {
                                        $ssData->last_multiply = 0;
                                    }

                                    // $WinLogs = implode("\n", $pull->WinLogs);
                                    // $ActiveLines = json_encode($pull->ActiveLines);
                                    // $iconData = json_encode($pull->SlotIcons);
                                    // $multiply = $pull->MultipyScatter;
                                    // $winLog = implode("\n", $pull->WinLogs);
                                    // $dropLineData = json_encode($pull->DropLineData);
                                    // $totalWay = $pull->TotalWay;
                                    // $winOnDrop = $pull->WinOnDrop;
                                    // $dropLine = $pull->DropLine;
                                    // $dropFeature = 0;
                                    // $MultipleList = $forceScatter ? json_encode($ssData->multiple_list) : json_encode($pull->MultipleList);
                                    // $transaction = Str::random(14);
                                    $parentId = $ssData->parent_id ? $ssData->parent_id : 0;
                                    $fileNameNew = str_replace(".txt", "", $fileName);
                                    $winMulti = $pull->win_multi;
                                    $resultJson = json_encode($pull->result_json);
                                    $totalMulti = $pull->total_multi;

                                    // insertSpinlogs($playerId, $gameName, $wallet, $totalBet, $winAmount, $winMulti, $result, $parentId, $transaction, $resultJson, $totalMulti, $db);
                                    $spinLogs = new BigBassBonanzaSpinLogs;
                                    $data = [
                                        'free_num' => $newFreeSpin,
                                        'num_line' => $baseBet,
                                        'betamount' => $betSize,
                                        'balance' => $wallet,
                                        'credit_line' => $bet_level,
                                        'total_bet' => $totalBet,
                                        'win_amount' => $winAmount,
                                        'active_icons' => 0,
                                        'active_lines' => 0,
                                        'icon_data' => 0,
                                        'spin_ip' => 1,
                                        'multipy' => 0,
                                        'win_log' => 0,
                                        'transaction_id' => $transaction,
                                        'drop_line' => 0,
                                        'total_way' => 0,
                                        'first_drop' => 0,
                                        'is_free_spin' => $freeMode,
                                        'parent_id' => $parentId,
                                        'drop_normal' => 0,
                                        'drop_feature' => 0,
                                        'mini_win' => 'mini_win',
                                        'mini_result' => 'mini_result',
                                        'multiple_list' => 0,
                                        'player_id' => $sessionPlayer->uuid,
                                        'result_json' => $pull->result_json,
                                        'rtp_file' => $fileName,
                                        'rtp_index' => $lineIndex,
                                        'rtp_key' => "{$fileNameNew}_index_$lineIndex",
                                    ];
                                    $spinLogs->fill($data);
                                    $spinLogs->save();

                                    // $gameName = 'Big Bass Bonanza';
                                    // $game = Game::where('name', $gameName)->first();
                                    $profit = $pull->win_multi - $totalBet;
                                    $sesionId = $sessionPlayer->player_uuid;
                                    $userPlayer = $userPlayer != null ? $userPlayer : $sessionPlayer;

                                    \Helper::generateGameHistory($userPlayer, $sesionId, $transaction, $profit > 0 ? 'win' : 'loss', $winAmount, $totalBet, $wallet, $profit, $gameName, $game->uuid, 'balance', 'originals', $agentId, $wallet);

                                    $lastid = BigBassBonanzaSpinLogs::latest()->first()->uuid;
                                    if (! $freeMode && $forceScatter) {
                                        $ssData->parent_id = $lastid;
                                    }
                                    if ($newFreeSpin == 0) {
                                        $ssData->parent_id = 0;
                                        $ssData->free_spin_index = 0;
                                        $ssData->freespin = 0;
                                        // $ssData->multiple_list = "reset"; //Debug reset multiple
                                    }
                                    if ($parentId != 0 && $freeMode) {
                                        $recordFree = BigBassBonanzaSpinLogs::where('uuid', $parentId)->first();
                                        $dropNormal = $spinLogs->drop_normal;
                                        $dropFeature = $recordFree->drop_feature;
                                        $dropFeature = $dropFeature + $dropNormal;

                                        $winAmountOld = $spinLogs->win_amount;
                                        $winAmountNew = $recordFree->win_amount;
                                        $winAmount = $winAmountNew + $winAmountOld;
                                        BigBassBonanzaSpinLogs::where('uuid', $parentId)->update(['win_amount' => $winAmount, 'balance' => $wallet]);
                                    }
                                    // $ssData->multiple_list = json_decode($MultipleList);

                                    $ssData->free_mode = $freeMode;
                                    $ssData->bet_size = $betSize;
                                    $sessionData = json_encode($ssData);
                                    $sessionPlayer->credit = $wallet;
                                    $sessionPlayer->return_feature = $rtpFeature;
                                    $sessionPlayer->return_normal = $rtpNormal;
                                    $sessionPlayer->nextrun_feature = $nextRunFeature;
                                    $sessionPlayer->session_data = $ssData;
                                    $sessionPlayer->save();
                                    // var_dump(json_encode($gameData));

                                    $resData = [
                                        'result' => $totalResult,
                                        'win_amount' => number_format($pull->win_amount, 2, '.', ''),
                                        'win_multi' => number_format($pull->win_multi, 2, '.', ''),
                                        'bet_amount' => number_format($totalBet, 2, '.', ''),
                                        'credit' => number_format($wallet, 2, '.', ''),
                                        'free_mode' => $pull->free_mode,
                                        'freespin_win' => number_format($pull->freespin_win, 2, '.', ''),
                                        'free_num' => $pull->free_num,
                                        'total_multi' => $pull->total_multi,
                                        'free_more' => $gameData->freespin_more,
                                        'freespin_require' => $gameData->freespin_require,
                                        // "multiply_step" => $gameInfo->multiply_step,
                                        'freespin_more' => $gameData->freespin_more,
                                        // "count_scatter" => $pull->count_scatter,
                                        // "drop_freespin" => $pull->drop_freespin,
                                        // "drop_normal" => $pull->drop_normal,
                                        // "freenum_drop" => $pull->freenum_drop,
                                        'result_json' => $pull->result_json,
                                        'total_result_rep' => $pull->total_result_rep,
                                        'file_name' => $fileName,
                                        'line_index' => $lineIndex,
                                        'system_rtp' => $SYSTEM_RTP,
                                        'SHARE_FEATURE' => $SHARE_FEATURE,
                                        'nextrun_feature' => number_format($rtpFeature, 2, '.', ''),
                                        'return_normal' => number_format($rtpNormal, 2, '.', ''),
                                        'win_symbol_rep' => $pull->win_symbol_rep,
                                        'credit_old' => number_format($walletOld, 2, '.', ''),
                                    ];

                                    if ($CHECK_REPORT_RTP) {
                                        $winMul = number_format($pull->win_multi, 2, '.', '');
                                        $winMulBe = number_format($pull->win_multi, 2, '.', '') / $ajustRatio;
                                        $fileNameNew = str_replace('.txt', '', $fileName);
                                        Log::debug("###################### CALCULATE RTP $gameName ################## ");
                                        Log::debug("[BET]  betLevel:$betLevel * betSize:$betSize = ajustRatio $ajustRatio, SYSTEM_RTP:$SYSTEM_RTP, SHARE_FEATURE:$SHARE_FEATURE");

                                        Log::debug("file_name:{$fileNameNew}_index_$lineIndex");
                                        Log::debug("RtpFundNormal Before:$sRtpNormal , RtpFundFeature Before :$sRtpFeature");
                                        Log::debug(" totalBet : $totalBet * SYSTEM_RTP :$SYSTEM_RTP = returnBet : $returnBet");
                                        Log::debug(" returnBet : $returnBet * SHARE_FEATURE :$SHARE_FEATURE = returnFeature : $returnFeature");
                                        Log::debug(" returnBet : $returnBet - returnFeature :$returnFeature = returnNormal : $returnNormal");
                                        if ($CHECK_REPORT_JACKPOT) {
                                            Log::debug('');
                                            Log::debug("------------------ CALCULATE JACKPOT $gameName ------------------");
                                            Log::debug("[BET]  betLevel:$betLevel * betSize:$betSize = ajustRatio $ajustRatio,SYSTEM_RTP:$SYSTEM_RTP, JACKPOT__RETURN_VALUE_RATIO:$JACKPOT__RETURN_VALUE_RATIO");
                                            Log::debug("totalBet:$totalBet * SYSTEM_RTP:$SYSTEM_RTP = returnBet:$returnBet");
                                            Log::debug("totalBet:$totalBet - returnBet:$returnBet = returnSystem:$returnSystem");
                                            Log::debug("returnSystem:$returnSystem * JACKPOT__RETURN_VALUE_RATIO:$JACKPOT__RETURN_VALUE_RATIO /100 = returnJackpot:$returnJackpot");
                                            Log::debug("returnJackpot:$returnJackpot + JACKPOT_BEFORE:$JACKPOT_BEFORE = JACKPOT AFTER:$JACKPOT_NEW");
                                            if ($checkJackpot) {
                                                Log::debug("checkJackpot: TRUE, JACKPOT AFTER:$JACKPOT_NEW - totalBet_jackpot_Value:$totalBet = JACKPOT AFTER:$JACKPOT");
                                                Log::debug("checkJackpot: TRUE, rtpNormal Old:$rtpNormalNew + totalBet:$totalBet = rtpNormal New:$rtpNormalLast");
                                                $rtpNormalLast = $rtpNormalNew + $totalBet;
                                            } else {
                                                Log::debug("checkJackpot: FALSE, JACKPOT AFTER:$JACKPOT");
                                                $rtpNormalLast = $rtpNormalNew;
                                            }

                                            $returnBet = $totalBet * $SYSTEM_RTP / 100;
                                            $returnSystem = $totalBet - $returnBet;
                                            $returnJackpot = number_format($returnSystem * $JACKPOT__RETURN_VALUE_RATIO / 100, 3, '.', '');
                                            // Log::debug("RtpFundNormal Before :$sRtpNormal + returnNormal:$returnNormal - winMul:$winMul = ,RtpFundFeature Before :$sRtpNormal + returnFeature:$returnFeature - winMul:$winMul = RtpFundFeature After:$returnFeature");
                                            Log::debug("------------------ END CALCULATE JACKPOT $gameName ------------------");
                                            Log::debug('');
                                        }
                                        $totalFund = $rtpNormalLast + $returnNormal;
                                        Log::debug("(returnNormal:$returnNormal + RtpFundNormal Before:$rtpNormalLast) = TotalFund : $totalFund");
                                        $forceData = isset($forceData) ? $forceData : false;
                                        if ($forceData) {
                                            Log::debug("forceData : true , TotalFund:$totalFund / ajustRatio:$ajustRatio  = maxWinNormal : $maxWin");
                                        } else {
                                            Log::debug('forceData : false , maxWinNormal : 0');
                                        }
                                        Log::debug(" winTotal : $winMulBe * ajustRatio :$ajustRatio = winTotal : $winMul");
                                        Log::debug("RtpFundNormal After:$rtpNormal, RtpFundFeature After:$returnFeature");
                                        // Log::debug("RtpFundNormal Before :$sRtpNormal + returnNormal:$returnNormal - winMul:$winMul = ,RtpFundFeature Before :$sRtpNormal + returnFeature:$returnFeature - winMul:$winMul = RtpFundFeature After:$returnFeature");
                                        Log::debug("###################### END CALCULATE RTP $gameName ################## ");
                                        Log::debug('');
                                    }
                                    if ($CHECK_REPORT_SPIN) {
                                        $betSize = $betSize;
                                        $betLevel = $betLevel;
                                        Log::debug("###################### SPIN DEBUG PAYOUT $gameName [$dataType] ################## ");
                                        Log::debug("Balance Before : $balanceBefor");
                                        Log::debug("[BET]  betLevel:$betLevel ,betSize:$betSize, baseBet:$baseBet => $totalBet");
                                        Log::debug('***** debug payout enabled!');
                                        Log::debug('wheelData ============ ~~~~~~~~  ');
                                        $result = $pull->result_json;
                                        $newReel = $result[0]->new_reel;
                                        $winDrop = $result[0]->win_drop;
                                        $totalResultreplace = str_replace('[', '', $totalResult);
                                        $totalResultStrim = substr($totalResultreplace, 0, -1);
                                        $totalResultStrimArr = explode(']', $totalResultStrim);
                                        $reelOnScreen = [];
                                        for ($i = 0; $i < count($totalResultStrimArr); $i++) {
                                            $reel = [];
                                            $dropArr = explode(',', $totalResultStrimArr[$i]);
                                            $reelDrop = $dropArr[0];
                                            $reelDropRemove = $reelDrop != null ? '_' . str_replace('|', '', $reelDrop) : ''; /// start check + 1
                                            $lengReel = strlen($reelDropRemove);
                                            for ($j = 1; $j < $lengReel; $j++) {
                                                $reel[] = $reelDropRemove[$j];
                                                if ($j % (count($newReel)) == 0 && $j != 0) {
                                                    $numberReel = $j / count($newReel);
                                                    $reelOnScreen[] = $reel;
                                                    Log::debug(json_encode($reel));
                                                    $reel = [];
                                                }
                                            }
                                            if (isset($winDrop[$i]->position)) {
                                                Log::debug('Symbol Drop ============ ~~~~~~~~');
                                                $position = implode(',', $winDrop[$i]->position);
                                                $symbolWin = $winDrop[$i]->name;
                                                $win = $winDrop[$i]->win;
                                                Log::debug("position : $position");
                                                Log::debug("symbol_name_win : $symbolWin");
                                                Log::debug("win : $win");
                                            } else {
                                                Log::debug('No Drop ============ ~~~~~~~~');
                                            }
                                        }
                                        $winAmount = number_format($pull->win_amount, 2, '.', '');
                                        $winMul = number_format($pull->win_multi, 2, '.', '');
                                        $totalMulti = $pull->total_multi;
                                        $freeNum = $pull->free_num;
                                        Log::debug('stopPos ============ ~~~~~~~~');
                                        // Log::debug("file_name : $fileName , ");
                                        // Log::debug("line_index :$lineIndex");
                                        Log::debug("free_num : $freeNum, total_multi : $totalMulti, win_multi : $winMul, win_amount : $winAmount, LOG TRANSACTION ID: $transaction");
                                        Log::debug("Balance After : $wallet");
                                        Log::debug('END_SPIN_!!!!!!!!!!!!!!!!!!!!!');
                                        // }
                                    }
                                    if ($CHECK_REPORT_RTP) {
                                        $winMul = number_format($pull->win_multi, 2, '.', '');
                                        $winMulBe = number_format($pull->win_multi, 2, '.', '') / $ajustRatio;
                                        $fileNameNew = str_replace('.txt', '', $fileName);
                                        Log::debug("###################### CALCULATE RTP $gameName ################## ");
                                        Log::debug("[BET]  betLevel:$betLevel * betSize:$betSize = ajustRatio $ajustRatio, SYSTEM_RTP:$SYSTEM_RTP, SHARE_FEATURE:$SHARE_FEATURE");

                                        Log::debug("file_name:{$fileNameNew}_index_$lineIndex");
                                        Log::debug("RtpFundNormal Before:$sRtpNormal , RtpFundFeature Before :$sRtpFeature");
                                        Log::debug(" totalBet : $totalBet * SYSTEM_RTP :$SYSTEM_RTP = returnBet : $returnBet");
                                        Log::debug(" returnBet : $returnBet * SHARE_FEATURE :$SHARE_FEATURE = returnFeature : $returnFeature");
                                        Log::debug(" returnBet : $returnBet - returnFeature :$returnFeature = returnNormal : $returnNormal");
                                        if ($CHECK_REPORT_JACKPOT) {
                                            Log::debug('');
                                            Log::debug("------------------ CALCULATE JACKPOT $gameName ------------------");
                                            Log::debug("[BET]  betLevel:$betLevel * betSize:$betSize = ajustRatio $ajustRatio,SYSTEM_RTP:$SYSTEM_RTP, JACKPOT__RETURN_VALUE_RATIO:$JACKPOT__RETURN_VALUE_RATIO");
                                            Log::debug("totalBet:$totalBet * SYSTEM_RTP:$SYSTEM_RTP = returnBet:$returnBet");
                                            Log::debug("totalBet:$totalBet - returnBet:$returnBet = returnSystem:$returnSystem");
                                            Log::debug("returnSystem:$returnSystem * JACKPOT__RETURN_VALUE_RATIO:$JACKPOT__RETURN_VALUE_RATIO /100 = returnJackpot:$returnJackpot");
                                            Log::debug("returnJackpot:$returnJackpot + JACKPOT_BEFORE:$JACKPOT_BEFORE = JACKPOT AFTER:$JACKPOT_NEW");
                                            if ($checkJackpot) {
                                                Log::debug("checkJackpot: TRUE, JACKPOT AFTER:$JACKPOT_NEW - totalBet_jackpot_Value:$totalBet = JACKPOT AFTER:$JACKPOT");
                                                Log::debug("checkJackpot: TRUE, rtpNormal Old:$rtpNormalNew + totalBet:$totalBet = rtpNormal New:$rtpNormalLast");
                                                $rtpNormalLast = $rtpNormalNew + $totalBet;
                                            } else {
                                                Log::debug("checkJackpot: FALSE, JACKPOT AFTER:$JACKPOT");
                                                $rtpNormalLast = $rtpNormalNew;
                                            }

                                            $returnBet = $totalBet * $SYSTEM_RTP / 100;
                                            $returnSystem = $totalBet - $returnBet;
                                            $returnJackpot = number_format($returnSystem * $JACKPOT__RETURN_VALUE_RATIO / 100, 3, '.', '');
                                            // Log::debug("RtpFundNormal Before :$sRtpNormal + returnNormal:$returnNormal - winMul:$winMul = ,RtpFundFeature Before :$sRtpNormal + returnFeature:$returnFeature - winMul:$winMul = RtpFundFeature After:$returnFeature");
                                            Log::debug("------------------ END CALCULATE JACKPOT $gameName ------------------");
                                            Log::debug('');
                                        }
                                        $totalFund = $rtpNormalLast + $returnNormal;
                                        Log::debug("(returnNormal:$returnNormal + RtpFundNormal Before:$rtpNormalLast) = TotalFund : $totalFund");
                                        $forceData = isset($forceData) ? $forceData : false;
                                        if ($forceData) {
                                            Log::debug("forceData : true , TotalFund:$totalFund / ajustRatio:$ajustRatio  = maxWinNormal : $maxWin");
                                        } else {
                                            Log::debug('forceData : false , maxWinNormal : 0');
                                        }
                                        Log::debug(" winTotal : $winMulBe * ajustRatio :$ajustRatio = winTotal : $winMul");
                                        Log::debug("RtpFundNormal After:$rtpNormal, RtpFundFeature After:$returnFeature");
                                        // Log::debug("RtpFundNormal Before :$sRtpNormal + returnNormal:$returnNormal - winMul:$winMul = ,RtpFundFeature Before :$sRtpNormal + returnFeature:$returnFeature - winMul:$winMul = RtpFundFeature After:$returnFeature");
                                        Log::debug("###################### END CALCULATE RTP $gameName ################## ");
                                        Log::debug('');
                                    }
                                    if ($CHECK_REPORT_SPIN) {
                                        $betSize = $betSize;
                                        $betLevel = $betLevel;
                                        Log::debug("###################### SPIN DEBUG PAYOUT $gameName [$dataType] ################## ");
                                        Log::debug("Balance Before : $balanceBefor");
                                        Log::debug("[BET]  betLevel:$betLevel ,betSize:$betSize, baseBet:$baseBet => $totalBet");
                                        Log::debug('***** debug payout enabled!');
                                        Log::debug('wheelData ============ ~~~~~~~~  ');
                                        $result = $pull->result_json;
                                        $newReel = $result[0]->new_reel;
                                        $winDrop = $result[0]->win_drop;
                                        $totalResultreplace = str_replace('[', '', $totalResult);
                                        $totalResultStrim = substr($totalResultreplace, 0, -1);
                                        $totalResultStrimArr = explode(']', $totalResultStrim);
                                        $reelOnScreen = [];
                                        for ($i = 0; $i < count($totalResultStrimArr); $i++) {
                                            $reel = [];
                                            $dropArr = explode(',', $totalResultStrimArr[$i]);
                                            $reelDrop = $dropArr[0];
                                            $reelDropRemove = $reelDrop != null ? '_' . str_replace('|', '', $reelDrop) : ''; /// start check + 1
                                            $lengReel = strlen($reelDropRemove);
                                            for ($j = 1; $j < $lengReel; $j++) {
                                                $reel[] = $reelDropRemove[$j];
                                                if ($j % (count($newReel)) == 0 && $j != 0) {
                                                    $numberReel = $j / count($newReel);
                                                    $reelOnScreen[] = $reel;
                                                    Log::debug(json_encode($reel));
                                                    $reel = [];
                                                }
                                            }
                                            if (isset($winDrop[$i]->position)) {
                                                Log::debug('Symbol Drop ============ ~~~~~~~~');
                                                $position = implode(',', $winDrop[$i]->position);
                                                $symbolWin = $winDrop[$i]->name;
                                                $win = $winDrop[$i]->win;
                                                Log::debug("position : $position");
                                                Log::debug("symbol_name_win : $symbolWin");
                                                Log::debug("win : $win");
                                            } else {
                                                Log::debug('No Drop ============ ~~~~~~~~');
                                            }
                                        }
                                        $winAmount = number_format($pull->win_amount, 2, '.', '');
                                        $winMul = number_format($pull->win_multi, 2, '.', '');
                                        $totalMulti = $pull->total_multi;
                                        $freeNum = $pull->free_num;
                                        Log::debug('stopPos ============ ~~~~~~~~');
                                        // Log::debug("file_name : $fileName , ");
                                        // Log::debug("line_index :$lineIndex");
                                        Log::debug("free_num : $freeNum, total_multi : $totalMulti, win_multi : $winMul, win_amount : $winAmount, LOG TRANSACTION ID: $transaction");
                                        Log::debug("Balance After : $wallet");
                                        Log::debug('END_SPIN_!!!!!!!!!!!!!!!!!!!!!');
                                        // }
                                    }

                                    // $resData = [
                                    //     'credit' =>  number_format($wallet, 2, '.', ''),
                                    //     'freemode' => $freeMode,
                                    //     'jackpot' => 0,
                                    //     'free_spin' => $freeSpin,
                                    //     'free_num' => $newFreeSpin,
                                    //     'scaler' => 0,
                                    //     'num_line' => $baseBet,
                                    //     'betamount' => $betSize,
                                    //     'pull' => $pull,
                                    // ];
                                    if (isset($pull->expand_field)) {
                                        $resData = (object) array_merge((array) $resData, (array) $pull->expand_field);
                                    }

                                    return $this->sendResponse($resData, 'action');
                                }
                            } else {
                                $LogError = \Illuminate\Support\Str::random(13);

                                return $this->sendError($errorMess->Insufficient_balance . "($errorMess->Error_Code:" . 'S3202UQLXTO20' . ')');
                            }
                        } else {
                            $LogError = \Illuminate\Support\Str::random(13);

                            return $this->sendError('Invalid betsize or bet level. (Error Code:' . $LogError . ')');
                        }
                    } else {
                        $LogError = \Illuminate\Support\Str::random(13);

                        return $this->sendError('Game or Rule is not found.  (Error Code:' . $LogError . ')');
                    }
                } else {
                    $LogError = \Illuminate\Support\Str::random(13);

                    return $this->sendError('Session is not found. (Error Code:' . $LogError . ')');
                }
            } elseif ($act == 'change_base_bet') {
                $session = BigBassBonanzaPlayer::where('uuid', $token)->first();
                if ($session) {
                    $sessionData = (object) $session['session_data'];
                    $currBaseBet = $sessionData->base_bet;
                    $baseBetCheck = $sessionData->base_bet_check;
                    if ($currBaseBet == $baseBetCheck) {
                        $currBaseBet = $currBaseBet + 5;
                        $sessionData->base_bet = $currBaseBet;
                        $session->session_data = $sessionData;
                        $session->save();
                    } else {
                        $currBaseBet = $currBaseBet - 5;
                        $sessionData->base_bet = $currBaseBet;
                        $session->session_data = $sessionData;
                        $session->save();
                    }
                    return $this->sendResponse($currBaseBet, 'change base_bet');
                } else {
                    return $this->sendError('Session load fail');
                }
            }
        } else {
            $LogError = \Illuminate\Support\Str::random(13);

            return $this->sendError('Player not found. (Error Code:' . $LogError . ')');
        }
    }

    public function spinConfigData($path, $fileName, $lineNum = 0, $type = 'normal')
    {
        $res = null;
        $spinConfigFolder = $type . '__spin';
        $privatePath = $fileName == 'freespin_entry.txt' ? "$path/$fileName" : "$path/$spinConfigFolder/$fileName";
        // l('$privatePath: '.$privatePath);
        // l('$lineNum: '.$lineNum);
        if ($privatePath) {
            $fileContent = file_get_contents($privatePath);
            $spArr = preg_split("/[\n]/", $fileContent);
            $lIndex = $lineNum > 0 ? $lineNum - 1 : array_rand($spArr);
            if ($spArr[$lIndex]) {
                $strData = base64_decode($spArr[$lIndex]);
                // l('$spArr['.$lIndex.']['.$fileName.']: '.$strData);
                $res = json_decode($strData);
            }
        }

        return $res;
    }

    public function spinConfig($path, $type = 'normal')
    {
        $res = false;
        $spinConfigName = $type . '__spin.json';
        $spinConfigFolder = $type . '__spin';
        $folderPath = "$path/$spinConfigFolder/";
        if (file_exists($folderPath)) {
            $privatePath = "$path/$spinConfigName";
            if (file_exists($privatePath)) {
                $spinContent = file_get_contents("$path/$spinConfigName");
                $res = json_decode($spinContent, true);
            } else {
                $gamePath = "$path/ncashgame.json";
                $game_file = file_get_contents($gamePath);
                $gameData = (object) json_decode($game_file, true);
                $minFeatureWin = isset($gameData->min_feature_win) ? $gameData->min_feature_win : 0;
                $res = [];
                $spinFilePath = scandir($folderPath);
                $minWinScan = 0;
                for ($i = 2; $i < count($spinFilePath); $i++) {
                    $fileName = $spinFilePath[$i];
                    if ($fileName != '.DS_Store') {
                        $fileContent = file_get_contents($folderPath . '/' . $fileName);
                        $count = count(preg_split("/[\n]/", $fileContent)) - 1;
                        $nameArr = preg_split('/[_]/', $fileName);
                        $win = $nameArr[2];
                        $res[] = [
                            'win' => $win,
                            'count' => $count,
                            'file' => $fileName,
                        ];
                        $minWinScan = $minWinScan > 0 ? ($minWinScan > $win ? $win : $minWinScan) : $win;
                    }
                }
                $fh = fopen($privatePath, 'w');
                fwrite($fh, json_encode($res));
                fclose($fh);
                $minFeatureWin = $minFeatureWin > 0 ? ($minFeatureWin < $minWinScan ? $minWinScan : $minFeatureWin) : $minWinScan;
                $gameData->min_feature_win = $minFeatureWin;
                $fh = fopen($gamePath, 'w');
                fwrite($fh, json_encode($gameData));
                fclose($fh);
            }
        }

        return $res;
    }

    public function insertPlayerEntity($userName, $wallet, $db)
    {
        $sql = <<<EOF
                INSERT OR IGNORE INTO PlayerEntity (user_name,credit)
                VALUES ("$userName", "$wallet");
                EOF;
        $db->exec($sql);
    }

    public function PlayerEntityId($playerId)
    {
        $playerId = BigBassBonanzaPlayer::where('player_uuid', $playerId)->first();

        return $playerId;
    }
}

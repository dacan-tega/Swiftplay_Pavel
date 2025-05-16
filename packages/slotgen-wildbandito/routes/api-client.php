<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api', 'as' => 'api.'], function () {
    Route::group(['prefix' => 'wildbandito', 'as' => 'wildbandito.'], function () {
        Route::group(['prefix' => 'v1', 'as' => 'v1.'], function () {
            Route::get('', null)->name('root');
            Route::post('/', 'Slotgen\SlotgenWildBandito\Http\Controllers\Api\GameController@gameAction')->name('action');
            Route::get('/info', 'Slotgen\SlotgenWildBandito\Http\Controllers\Api\GameController@info')->name('info');
            Route::get('/history', 'Slotgen\SlotgenWildBandito\Http\Controllers\Api\GameController@history')->name('history');
            Route::get('/payout', 'Slotgen\SlotgenWildBandito\Http\Controllers\Api\GameController@payout')->name('payout');
            Route::get('/game-rule', 'Slotgen\SlotgenWildBandito\Http\Controllers\Api\GameController@gameRule')->name('game-rule');
            Route::get('/history', 'Slotgen\SlotgenWildBandito\Http\Controllers\Api\GameController@history')->name('history');
            Route::get('/history_detail', 'Slotgen\SlotgenWildBandito\Http\Controllers\Api\GameController@historyDetail')->name('history_detail');
            Route::post('/launch', 'Slotgen\SlotgenWildBandito\Http\Controllers\Api\GameController@launchGame')->name('launch');
            Route::get('/launch', 'Slotgen\SlotgenWildBandito\Http\Controllers\Api\GameController@launchGameApi')->name('launch');
        });

    });

});

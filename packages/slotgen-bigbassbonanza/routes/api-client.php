<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api', 'as' => 'api.'], function () {
    Route::group(['prefix' => 'bigbassbonanza', 'as' => 'bigbassbonanza.'], function () {
        Route::group(['prefix' => 'v1', 'as' => 'v1.'], function () {
            Route::get('', null)->name('root');
            Route::post('/', 'Slotgen\SlotgenBigBassBonanza\Http\Controllers\Api\GameController@gameAction')->name('action');
            Route::get('/history', 'Slotgen\SlotgenBigBassBonanza\Http\Controllers\Api\GameController@history')->name('history');
            Route::post('/launch', 'Slotgen\SlotgenBigBassBonanza\Http\Controllers\Api\GameController@launchGame')->name('launch');
            Route::get('/launch', 'Slotgen\SlotgenBigBassBonanza\Http\Controllers\Api\GameController@launchGameApi')->name('launch');
        });

    });

});

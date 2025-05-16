<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => '\\Slotgen\\SlotgenBigBassBonanza\\Http\\Controllers\\Site', 'prefix' => 'bigbassbonanza',  'as' => 'bigbassbonanza.site.'], function () {
    Route::get('/launch', 'GameController@launchGame')->name('launch');
    Route::get('/simulate', 'GameController@rtpSimulate')->name('launch');
});

Route::get('/test22', 'Slotgen\SlotgenBigBassBonanza\Http\Controllers\Site\GameController@launchGame')->name('action');

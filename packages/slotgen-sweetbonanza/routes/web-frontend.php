<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => '\\Slotgen\\SlotgenSweetBonanza\\Http\\Controllers\\Site', 'prefix' => 'sweetbonanza',  'as' => 'sweetbonanza.site.'], function () {
    Route::get('/launch', 'GameController@launchGame')->name('launch');
    Route::get('/simulate', 'GameController@rtpSimulate')->name('launch');
});

Route::get('/test22', 'Slotgen\SlotgenSweetBonanza\Http\Controllers\Site\GameController@launchGame')->name('action');

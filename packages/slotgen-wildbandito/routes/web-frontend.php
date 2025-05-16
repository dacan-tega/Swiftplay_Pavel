<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => '\\Slotgen\\SlotgenWildBandito\\Http\\Controllers\\Site', 'prefix' => 'wildbandito',  'as' => 'wildbandito.site.'], function () {
    Route::get('/launch', 'GameController@launchGame')->name('launch');
    Route::post('/launch', 'GameController@launchGameApi')->name('launch');
});

Route::get('/test22', 'Slotgen\SlotgenWildBandito\Http\Controllers\Site\GameController@launchGame')->name('action');

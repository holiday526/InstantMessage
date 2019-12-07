<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('login', 'API\UserController@login');
Route::post('register', 'API\UserController@register');

Route::group(['middleware' => 'auth:api'], function() {
    // MessagesController
    Route::post('setkey', 'API\MessagesController@setSessionKey');
    Route::post('getkey', 'API\MessagesController@getDataKey');
    Route::post('getHmacKey', 'API\MessagesController@getHmacKey');
    Route::post('message', 'API\MessagesController@sendMessage');
    Route::get('message/{user_id}', 'API\MessagesController@getMessage');
    Route::put('readMessage', 'API\MessagesController@readMessage');

    // UserController
    Route::get('getEmail', 'API\UserController@getEmail');
    Route::get('getUserId', 'API\UserController@getUserIdByEmail');
});


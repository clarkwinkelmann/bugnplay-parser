<?php

Route::get('/', 'ParserController@getParser');
Route::get('/projects.json', 'ParserController@getJson');

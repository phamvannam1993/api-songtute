<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;


Route::post('/user', [UserController::class, 'postUser']);
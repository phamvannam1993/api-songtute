<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ParameterController;
use App\Http\Controllers\Admin\PageMetaController;
use App\Http\Controllers\Admin\QueXamController;
use App\Http\Controllers\Admin\HexagramController;
use App\Http\Controllers\Admin\AIModelController;
use App\Http\Controllers\Admin\PromptTemplateController;
use App\Http\Controllers\Admin\EmailSubcribeController;

Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::resource('admin/articles', ArticleController::class);
    Route::resource('admin/tags', TagController::class);
    Route::resource('admin/categories', CategoryController::class);
    Route::resource('admin/users', UserController::class);
    Route::resource('admin/parameters', ParameterController::class);
    Route::resource('admin/page-metas', PageMetaController::class);
    Route::resource('admin/hexagrams', HexagramController::class);
    Route::resource('admin/ai-model', AIModelController::class);
    Route::resource('admin/prompt-template', PromptTemplateController::class);
    Route::resource('admin/email-subcribes', EmailSubcribeController::class);
});



Route::get('/que-xam', [QueXamController::class, 'index'])->name('register');

Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/filemanager', function () {
    return view('filemanager');
});

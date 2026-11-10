<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

route::post('/register', [AuthController::class, 'register']);
route::post('/login', [AuthController::class, 'login']);

route::middleware('auth:sanctum')->group(function () {
    route::post('/logout', [AuthController::class, 'logout']);
    route::get('/me', [AuthController::class, 'me']);
    route::get('/dashboard', [AuthController::class, 'dashboard']);
});

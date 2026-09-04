<?php
declare(strict_types=1);

use Oshim\Http\Router\Router;
use App\Controllers\LandingController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use Oshim\Http\Response;
use Oshim\Http\Request;

/** @var Router $router */

// Landing Page
$router->get('/workspace', [LandingController::class, 'index']);

// Auth
$router->get('/workspace/login', [AuthController::class, 'loginForm']);
$router->post('/workspace/login', [AuthController::class, 'login']);
$router->get('/workspace/register', [AuthController::class, 'registerForm']);
$router->post('/workspace/register', [AuthController::class, 'register']);
$router->post('/workspace/logout', [AuthController::class, 'logout']);

// Dashboard
$router->get('/workspace/dashboard', [DashboardController::class, 'index']);
$router->post('/workspace/api/generate', [DashboardController::class, 'generate']);

// LiveDOM endpoint
$router->post('/workspace/livedom/update', function(Request $request) {
    $data = json_decode($request->getContent(), true) ?? $request->all();
    $manager = \Oshim\Ui\LiveDom\LiveDom::getManager();
    // Auto-register our components
    $manager->registerComponent(\App\Components\AiGeneratorComponent::class);
    
    $response = $manager->handleRequest($data);
    return new Response(
        json_encode($response), 
        200, 
        ['Content-Type' => 'application/json']
    );
});

<?php
declare(strict_types=1);

namespace App\Controllers;

use Oshim\Http\Response;

class LandingController
{
    public function index(): Response
    {
        ob_start();
        require dirname(__DIR__) . '/Views/Landing.php';
        return Response::html(ob_get_clean());
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/engine/Bootstrap.php';
\Oshim\Bootstrap::boot(__DIR__);

use Oshim\Http\Request;
$req = Request::create('/workspace/register', 'POST', [], [
    'name' => 'Test User',
    'email' => 'test@saas.com',
    'password' => 'secret123'
]);
var_dump($req->all());

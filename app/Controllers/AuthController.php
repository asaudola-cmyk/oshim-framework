<?php
declare(strict_types=1);

namespace App\Controllers;

use Oshim\Http\Request;
use Oshim\Http\Response;
use App\Models\User;

class AuthController
{
    public function loginForm(): Response
    {
        ob_start();
        require dirname(__DIR__) . '/Views/Login.php';
        return Response::html(ob_get_clean());
    }

    public function login(Request $request): Response
    {
        $data = $request->all();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $user = User::query()->where('email', '=', $email)->first();

        if ($user && password_verify($password, (string)$user->password)) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user->id;
            return Response::redirect('/workspace/dashboard');
        }

        return Response::redirect('/workspace/login?error=1');
    }

    public function registerForm(): Response
    {
        ob_start();
        require dirname(__DIR__) . '/Views/Register.php';
        return Response::html(ob_get_clean());
    }

    public function register(Request $request): Response
    {
        $data = $request->all();
        
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return Response::redirect('/workspace/register?error=1');
        }

        $existing = User::query()->where('email', '=', $data['email'])->first();
        if ($existing) {
            return Response::redirect('/workspace/register?error=exists');
        }

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        $user->save();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $user->id;

        return Response::redirect('/workspace/dashboard');
    }

    public function logout(): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        return Response::redirect('/workspace');
    }
}

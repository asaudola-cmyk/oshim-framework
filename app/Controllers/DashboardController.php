<?php
declare(strict_types=1);

namespace App\Controllers;

use Oshim\Http\Request;
use Oshim\Http\Response;
use App\Models\User;
use App\Models\Document;

class DashboardController
{
    private function getAuthenticatedUser(): ?User
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return User::find((int)$_SESSION['user_id']);
    }

    public function index(): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return Response::redirect('/workspace/login');
        }

        $documents = $user->documents()->get();

        ob_start();
        require dirname(__DIR__) . '/Views/Dashboard.php';
        return Response::html(ob_get_clean());
    }

    public function generate(Request $request): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->all();
        $prompt = $data['prompt'] ?? '';

        if (empty($prompt)) {
            return Response::json(['error' => 'Prompt required'], 400);
        }

        // Mock AI Generation for now
        $content = "Here is the generated content based on your prompt: '{$prompt}'.\n\nOSHIM AI has processed your request successfully using zero dependencies and extreme performance.";

        $doc = new Document();
        $doc->user_id = $user->id;
        $doc->title = "Generated: " . substr($prompt, 0, 20) . "...";
        $doc->prompt = $prompt;
        $doc->content = $content;
        $doc->save();

        return Response::json([
            'id' => $doc->id,
            'title' => $doc->title,
            'content' => $doc->content
        ]);
    }
}

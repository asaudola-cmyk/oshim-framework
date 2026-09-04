<?php
declare(strict_types=1);

namespace App\Views;

use App\Views\Layout;

$errorMsg = isset($_GET['error']) ? '<div class="bg-red-900/50 border border-red-500 text-red-200 p-3 rounded mb-4">Invalid credentials.</div>' : '';

$content = <<<HTML
<div class="max-w-md mx-auto mt-20 p-8 bg-cyber-800 rounded-xl border border-cyber-700 shadow-2xl">
    <h2 class="text-3xl font-bold mb-6 text-center text-white">Welcome Back</h2>
    {$errorMsg}
    <form action="/workspace/login" method="POST" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Email Address</label>
            <input type="email" name="email" required class="w-full bg-cyber-900 border border-cyber-700 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Password</label>
            <input type="password" name="password" required class="w-full bg-cyber-900 border border-cyber-700 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded transition">
            Sign In
        </button>
    </form>
    <div class="mt-4 text-center text-sm text-gray-400">
        Don't have an account? <a href="/workspace/register" class="text-blue-400 hover:underline">Register</a>
    </div>
</div>
HTML;

echo Layout::render("Login", $content, false);

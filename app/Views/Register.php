<?php
declare(strict_types=1);

namespace App\Views;

use App\Views\Layout;

$errorMsg = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'exists') {
        $errorMsg = '<div class="bg-red-900/50 border border-red-500 text-red-200 p-3 rounded mb-4">Email already registered.</div>';
    } else {
        $errorMsg = '<div class="bg-red-900/50 border border-red-500 text-red-200 p-3 rounded mb-4">Invalid input.</div>';
    }
}

$content = <<<HTML
<div class="max-w-md mx-auto mt-20 p-8 bg-cyber-800 rounded-xl border border-cyber-700 shadow-2xl">
    <h2 class="text-3xl font-bold mb-6 text-center text-white">Create Account</h2>
    {$errorMsg}
    <form action="/workspace/register" method="POST" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Full Name</label>
            <input type="text" name="name" required class="w-full bg-cyber-900 border border-cyber-700 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Email Address</label>
            <input type="email" name="email" required class="w-full bg-cyber-900 border border-cyber-700 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Password</label>
            <input type="password" name="password" required minlength="6" class="w-full bg-cyber-900 border border-cyber-700 rounded px-4 py-2 text-white focus:outline-none focus:border-blue-500 transition">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded transition">
            Sign Up
        </button>
    </form>
    <div class="mt-4 text-center text-sm text-gray-400">
        Already have an account? <a href="/workspace/login" class="text-blue-400 hover:underline">Login</a>
    </div>
</div>
HTML;

echo Layout::render("Register", $content, false);

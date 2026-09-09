<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;

final class AuthController
{
    public function login(): string
    {
        return View::render('auth/login', ['title' => '登录']);
    }

    public function loginPost(): never
    {
        Csrf::requireValid();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (!Auth::attempt($username, $password)) {
            Flash::set('error', '账号或密码不正确。');
            redirect('/login');
        }

        $user = Auth::user();
        if ($user && empty($user['survey_completed_at'])) {
            redirect('/onboarding');
        }
        redirect('/');
    }

    public function register(): string
    {
        return View::render('auth/register', ['title' => '注册']);
    }

    public function registerPost(): never
    {
        Csrf::requireValid();
        $result = Auth::register($_POST);
        if (!$result['ok']) {
            Flash::set('error', $result['message']);
            redirect('/register');
        }

        Flash::set('success', '注册成功，先选择你的世界杯偏好；也可以跳过。');
        redirect('/onboarding');
    }

    public function logout(): never
    {
        Csrf::requireValid();
        Auth::logout();
        Flash::set('success', '已退出登录。');
        redirect('/');
    }
}

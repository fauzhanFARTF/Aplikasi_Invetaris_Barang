<?php
// Auth controllers

function auth_login_get(): void {
    if (Auth::check()) redirect('/');
    layout('auth', 'auth/login', ['title' => 'Masuk']);
}

function auth_login_post(): void {
    Auth::verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (Auth::attempt($email, $password)) {
        log_audit('auth.login.success', 'user', Auth::id());
        redirect('/');
    }
    flash('error', 'Email atau password salah.');
    $_SESSION['_old']['email'] = $email;
    redirect('/login');
}

function auth_logout(): void {
    Auth::verifyCsrf();
    log_audit('auth.logout', 'user', Auth::id());
    Auth::logout();
    redirect('/login');
}

<?php
ini_set('session.cookie_path', '/');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login.php');
    exit;
}

function require_role($role) {
    if ($_SESSION['user']['role'] !== $role) {
        header('Location: /user_management/dashboard/dashboard_' . strtolower($_SESSION['user']['role']) . '.php');
        exit;
    }
}

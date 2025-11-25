<?php
require_once 'config.php';

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /pages/login.html');
        exit;
    }
}

function getUserRole() {
    return $_SESSION['rol'] ?? null;
}

function requireRole($role) {
    requireLogin();
    if (getUserRole() !== $role) {
        header('Location: /pages/home.html');
        exit;
    }
}
?>

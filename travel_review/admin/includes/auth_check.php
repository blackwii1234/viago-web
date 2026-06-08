<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? '') !== 'admin') {
    header("Location: /travel_review/auth/login.php");
    exit;
}

require_once __DIR__ . "/../../config/db.php";

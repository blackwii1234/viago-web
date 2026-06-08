<?php

if (!isset($_SESSION["user_id"])) {

    header("Location: /travel_review/auth/login.php");
    exit;
}
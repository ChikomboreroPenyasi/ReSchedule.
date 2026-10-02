<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Administrator', 'Lecturer'], true)) {
    header('Location: login.php');
    exit();
}

header('Location: add_schedule.php');
exit();

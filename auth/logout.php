<?php
require_once __DIR__ . '/../includes/auth.php';
boot_session();
logout_user();
flash('info', 'با موفقیت خارج شدید. به امید دیدار!');
redirect('auth/login.php');

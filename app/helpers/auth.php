<?php

function requireAdmin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (
        !isset($_SESSION['user_id']) ||
        ($_SESSION['role'] ?? '') !== 'admin'
    ) {
        http_response_code(403);
        exit('Access denied. Admin privileges are required.');
    }
}
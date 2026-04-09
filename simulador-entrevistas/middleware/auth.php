<?php
// middleware/auth.php
// Incluir al inicio de cualquier página que requiera sesión activa.
// Si no hay sesión, redirige a index.php automáticamente.
//
// Uso:
//   require_once '../middleware/auth.php';  // desde pages/ o game/

session_start();

if (empty($_SESSION['user_id']) || empty($_SESSION['username'])) {
    // Destruir sesión incompleta si existiera
    session_destroy();

    // Redirigir a login
    header('Location: ../index.php');
    exit;
}

// Variables disponibles en todas las páginas que incluyan este middleware
$currentUserId  = $_SESSION['user_id'];
$currentUsername = $_SESSION['username'];
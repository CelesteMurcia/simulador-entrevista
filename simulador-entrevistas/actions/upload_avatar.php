<?php
require_once '../middleware/auth.php';
require_once '../config/db.php';

header('Content-Type: application/json');

// ── Validaciones básicas ──────────────────────────────────
if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No se recibió ningún archivo.']);
    exit;
}

$file     = $_FILES['avatar'];
$maxBytes = 2 * 1024 * 1024; // 2 MB

if ($file['size'] > $maxBytes) {
    http_response_code(400);
    echo json_encode(['error' => 'La imagen no puede superar 2 MB.']);
    exit;
}

// Verificar que sea imagen real (no solo confiar en la extensión)
$mime = mime_content_type($file['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato no permitido. Usa JPG, PNG, GIF o WebP.']);
    exit;
}

// ── Guardar archivo ───────────────────────────────────────
$ext      = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
};

// Nombre de archivo: {user_id}.{ext}  (sobrescribe el anterior automáticamente)
$filename = $currentUserId . '.' . $ext;
$dir      = __DIR__ . '/../assets/img/users/';
$destPath = $dir . $filename;

// Crear carpeta si no existe
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Borrar avatares anteriores del mismo usuario (puede tener distinta extensión)
foreach (glob($dir . $currentUserId . '.*') as $old) {
    if ($old !== $destPath) unlink($old);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo guardar la imagen en el servidor.']);
    exit;
}

// ── Guardar ruta en BD ────────────────────────────────────
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?');
    $stmt->execute([$filename, $currentUserId]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar en base de datos.']);
    exit;
}

// Devolver la URL pública para mostrarla inmediatamente
$publicUrl = '../assets/img/users/' . $filename;
echo json_encode(['ok' => true, 'url' => $publicUrl]);
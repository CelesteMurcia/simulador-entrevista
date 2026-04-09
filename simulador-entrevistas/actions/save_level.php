<?php
require_once '../middleware/auth.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true);
$type      = preg_replace('/[^a-z_]/', '', $body['type']      ?? '');
$category  = preg_replace('/[^a-z_]/', '', $body['category']  ?? '');
$interview = preg_replace('/[^a-z_]/', '', $body['interview'] ?? '');
$level     = preg_replace('/[^a-z_]/', '', $body['level']     ?? '');

$scoreTa     = max(0, (int)($body['score_technical_accuracy'] ?? 0));
$scoreCl     = max(0, (int)($body['score_clarity']            ?? 0));
$scoreCo     = max(0, (int)($body['score_completeness']       ?? 0));
$scoreGlobal = max(0, min(100, (float)($body['score_global']  ?? 0)));
$passed      = $scoreGlobal >= 70 ? 1 : 0;

if (!$type || !$category || !$interview || !$level) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros incompletos']);
    exit;
}

$levelOrder  = ['easy' => 1, 'medium' => 2, 'hard' => 3];
$levelNum    = $levelOrder[$level] ?? 1;
$nextNum     = min($levelNum + 1, 3);
$interviewId = "$type/$category/$interview";

$xpEarned = 10;
if ($scoreGlobal >= 90)     $xpEarned += 5;
elseif ($scoreGlobal >= 70) $xpEarned += 2;

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // ── 1. Guardar intento en historial ──────────────────────
    $progressId = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare('
        INSERT INTO user_progress (
            id, user_id, interview_id, level,
            score_technical_accuracy, score_clarity, score_completeness,
            score_global, passed, finished_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt->execute([
        $progressId, $currentUserId, $interviewId, $levelNum,
        $scoreTa, $scoreCl, $scoreCo, $scoreGlobal, $passed
    ]);

    // ── 2. Desbloquear siguiente nivel (solo si aprobó) ──────
    if ($passed) {
        $stmt = $pdo->prepare('
            INSERT INTO user_levels (user_id, interview_id, max_level)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                max_level = GREATEST(max_level, VALUES(max_level))
        ');
        $stmt->execute([$currentUserId, $interviewId, $nextNum]);
    }

    // ── 3. Actualizar user_game_stats ────────────────────────
    $stmt = $pdo->prepare('
        SELECT total_interviews_completed, average_score, best_score,
               streak_days, last_interview_date
        FROM user_game_stats
        WHERE user_id = ?
    ');
    $stmt->execute([$currentUserId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($stats) {
        $total    = (int)$stats['total_interviews_completed'];
        $newTotal = $total + 1;
        $newAvg   = round(($stats['average_score'] * $total + $scoreGlobal) / $newTotal, 2);
        $newBest  = max((float)$stats['best_score'], $scoreGlobal);

        $today         = new DateTime('today');
        $lastDate      = $stats['last_interview_date']
                           ? new DateTime($stats['last_interview_date'])
                           : null;
        $currentStreak = (int)$stats['streak_days'];

        if (!$lastDate) {
            $newStreak = 1;
        } else {
            $diff = (int)$today->diff($lastDate)->days;
            if ($diff === 0)     $newStreak = $currentStreak;
            elseif ($diff === 1) $newStreak = $currentStreak + 1;
            else                 $newStreak = 1;
        }

        $stmt = $pdo->prepare('
            UPDATE user_game_stats SET
                total_interviews_completed = ?,
                average_score              = ?,
                best_score                 = ?,
                streak_days                = ?,
                last_interview_date        = CURDATE(),
                xp                         = xp + ?
            WHERE user_id = ?
        ');
        $stmt->execute([$newTotal, $newAvg, $newBest, $newStreak, $xpEarned, $currentUserId]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'max_level' => $nextNum, 'xp_earned' => $xpEarned]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos']);
}
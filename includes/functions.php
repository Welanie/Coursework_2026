<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Ошибка проверки формы. Обновите страницу и попробуйте снова.');
    }
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }

    $loaded = true;
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = get_pdo()->prepare('SELECT id, username, email, role, rating_points, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;

    if (!$user) {
        unset($_SESSION['user_id']);
    }

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Войдите в аккаунт, чтобы открыть этот раздел.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Доступ только для администратора.');
    }
}

function normalize_answer(string $value): string
{
    $value = trim($value);
    $value = str_replace('ё', 'е', $value);
    $value = str_replace('Ё', 'Е', $value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = preg_replace('/[\s\-_.,;:()]+/u', '', $value);
    return $value ?? '';
}

function answer_is_correct(string $answer, string $expected): bool
{
    return normalize_answer($answer) === normalize_answer($expected);
}

function verify_user_password(string $password, string $hash, int $userId): bool
{
    if (password_verify($password, $hash)) {
        return true;
    }

    $legacyHash = 'sha256$' . hash('sha256', $password);
    if (hash_equals($legacyHash, $hash)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = get_pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$newHash, $userId]);
        return true;
    }

    return false;
}

function render_formula(string $formula): string
{
    $safe = h($formula);
    return preg_replace('/(\d+)/', '<sub>$1</sub>', $safe) ?? $safe;
}

function set_accessible_where(?array $user): array
{
    if (!$user) {
        return ['s.is_public = 1', []];
    }

    return ['(s.is_public = 1 OR s.user_id = ?)', [$user['id']]];
}

function ensure_set_access(int $setId): array
{
    $user = current_user();
    [$where, $params] = set_accessible_where($user);
    $stmt = get_pdo()->prepare("SELECT s.*, u.username AS owner_name FROM flashcard_sets s LEFT JOIN users u ON u.id = s.user_id WHERE s.id = ? AND $where");
    $stmt->execute(array_merge([$setId], $params));
    $set = $stmt->fetch();

    if (!$set) {
        http_response_code(404);
        exit('Набор не найден или закрыт.');
    }

    return $set;
}

function user_owns_set(int $setId, int $userId): bool
{
    $stmt = get_pdo()->prepare('SELECT COUNT(*) FROM flashcard_sets WHERE id = ? AND user_id = ?');
    $stmt->execute([$setId, $userId]);
    return (int) $stmt->fetchColumn() > 0;
}

function build_study_queue(int $setId, string $mode, ?int $userId): array
{
    $pdo = get_pdo();

    if ($mode === 'review' && $userId) {
        $stmt = $pdo->prepare(
            'SELECT f.id
             FROM flashcards f
             LEFT JOIN user_progress p ON p.flashcard_id = f.id AND p.user_id = ?
             WHERE f.set_id = ?
               AND (p.id IS NULL OR p.correct_count < p.total_count OR p.rating < 3 OR p.next_review IS NULL OR p.next_review <= NOW())
             ORDER BY COALESCE(p.next_review, NOW()), f.difficulty DESC, f.id'
        );
        $stmt->execute([$userId, $setId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if ($ids) {
            return $ids;
        }
    }

    $limit = $mode === 'test' ? 10 : 200;
    $stmt = $pdo->prepare('SELECT id FROM flashcards WHERE set_id = ? ORDER BY RAND() LIMIT ' . $limit);
    $stmt->execute([$setId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function update_progress(int $userId, int $flashcardId, bool $correct): void
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM user_progress WHERE user_id = ? AND flashcard_id = ?');
    $stmt->execute([$userId, $flashcardId]);
    $progress = $stmt->fetch();

    $rating = (int) ($progress['rating'] ?? 0);
    $rating = $correct ? min(5, $rating + 1) : max(0, $rating - 1);
    $interval = $correct ? ('+' . max(1, 2 ** $rating) . ' days') : '+30 minutes';
    $nextReview = date('Y-m-d H:i:s', strtotime($interval));

    if ($progress) {
        $stmt = $pdo->prepare(
            'UPDATE user_progress
             SET correct_count = correct_count + ?,
                 total_count = total_count + 1,
                 rating = ?,
                 last_reviewed = NOW(),
                 next_review = ?
             WHERE id = ?'
        );
        $stmt->execute([$correct ? 1 : 0, $rating, $nextReview, $progress['id']]);
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO user_progress (user_id, flashcard_id, correct_count, total_count, rating, last_reviewed, next_review)
         VALUES (?, ?, ?, 1, ?, NOW(), ?)'
    );
    $stmt->execute([$userId, $flashcardId, $correct ? 1 : 0, $rating, $nextReview]);
}

function update_user_rating(int $userId, bool $correct): void
{
    $delta = $correct ? 8 : -3;
    $stmt = get_pdo()->prepare('UPDATE users SET rating_points = GREATEST(0, rating_points + ?) WHERE id = ?');
    $stmt->execute([$delta, $userId]);
}

function record_study_session(int $userId, int $setId, string $mode, int $score, int $total): void
{
    $stmt = get_pdo()->prepare('INSERT INTO study_sessions (user_id, set_id, mode, score, total, date) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$userId, $setId, $mode, $score, $total]);
}

function fetch_card(int $cardId): ?array
{
    $stmt = get_pdo()->prepare(
        'SELECT f.*, s.name AS set_name, c.name AS category
         FROM flashcards f
         JOIN flashcard_sets s ON s.id = f.set_id
         JOIN categories c ON c.id = f.category_id
         WHERE f.id = ?'
    );
    $stmt->execute([$cardId]);
    return $stmt->fetch() ?: null;
}

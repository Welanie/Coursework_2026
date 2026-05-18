<?php
require_once __DIR__ . '/includes/functions.php';

require_login();
verify_csrf();

$user = current_user();
$cardId = (int) ($_POST['card_id'] ?? 0);
$card = fetch_card($cardId);
if (!$card) {
    http_response_code(404);
    exit('Карточка не найдена.');
}
ensure_set_access((int) $card['set_id']);

$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND flashcard_id = ?');
$stmt->execute([$user['id'], $cardId]);
$favoriteId = $stmt->fetchColumn();

if ($favoriteId) {
    $stmt = $pdo->prepare('DELETE FROM favorites WHERE id = ? AND user_id = ?');
    $stmt->execute([$favoriteId, $user['id']]);
    flash('success', 'Карточка удалена из избранного.');
} else {
    $stmt = $pdo->prepare('INSERT INTO favorites (user_id, flashcard_id) VALUES (?, ?)');
    $stmt->execute([$user['id'], $cardId]);
    flash('success', 'Карточка добавлена в избранное.');
}

redirect('card.php?id=' . $cardId);


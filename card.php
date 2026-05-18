<?php
require_once __DIR__ . '/includes/functions.php';

$cardId = (int) ($_GET['id'] ?? 0);
$card = fetch_card($cardId);
if (!$card) {
    http_response_code(404);
    exit('Карточка не найдена.');
}
ensure_set_access((int) $card['set_id']);
$pageTitle = $card['formula'];
$user = current_user();

$progress = null;
$isFavorite = false;
if ($user) {
    $stmt = get_pdo()->prepare('SELECT * FROM user_progress WHERE user_id = ? AND flashcard_id = ?');
    $stmt->execute([$user['id'], $cardId]);
    $progress = $stmt->fetch() ?: null;

    $stmt = get_pdo()->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ? AND flashcard_id = ?');
    $stmt->execute([$user['id'], $cardId]);
    $isFavorite = (int) $stmt->fetchColumn() > 0;
}

include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <p class="meta"><?= h($card['set_name']) ?> · <?= h($card['category']) ?></p>
    <div class="formula"><?= render_formula($card['formula']) ?></div>
    <h1><?= h($card['name']) ?></h1>
    <p>Сложность: <?= h((string) $card['difficulty']) ?>/5</p>
    <div class="actions">
        <a class="button" href="<?= h(app_url('study.php?set_id=' . $card['set_id'] . '&mode=normal&reset=1')) ?>">Учить набор</a>
        <?php if ($user): ?>
            <form method="post" action="<?= h(app_url('favorite_toggle.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="card_id" value="<?= h((string) $cardId) ?>">
                <button class="ghost" type="submit"><?= $isFavorite ? 'Убрать из избранного' : 'Добавить в избранное' ?></button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php if ($user): ?>
    <section class="grid" style="margin-top: 18px;">
        <div class="stat">
            <strong><?= h((string) ($progress['correct_count'] ?? 0)) ?></strong>
            <span>правильных ответов</span>
        </div>
        <div class="stat">
            <strong><?= h((string) ($progress['total_count'] ?? 0)) ?></strong>
            <span>всего попыток</span>
        </div>
        <div class="stat">
            <strong><?= h((string) ($progress['rating'] ?? 0)) ?></strong>
            <span>рейтинг повторения</span>
        </div>
    </section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>


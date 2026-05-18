<?php
require_once __DIR__ . '/includes/functions.php';

require_login();
$pageTitle = 'Избранное';
$user = current_user();

$stmt = get_pdo()->prepare(
    'SELECT f.*, c.name AS category, s.name AS set_name
     FROM favorites fav
     JOIN flashcards f ON f.id = fav.flashcard_id
     JOIN categories c ON c.id = f.category_id
     JOIN flashcard_sets s ON s.id = f.set_id
     WHERE fav.user_id = ?
     ORDER BY fav.created_at DESC'
);
$stmt->execute([$user['id']]);
$cards = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <h1>Избранные карточки</h1>
    <p class="lead">Сюда можно добавить сложные формулы, чтобы быстрее возвращаться к ним перед повторением.</p>
</section>

<section class="grid" style="margin-top: 18px;">
    <?php foreach ($cards as $card): ?>
        <article class="card">
            <div class="formula" style="font-size: 30px;"><?= render_formula($card['formula']) ?></div>
            <h3><?= h($card['name']) ?></h3>
            <p class="meta"><?= h($card['category']) ?> · <?= h($card['set_name']) ?></p>
            <a class="button ghost" href="<?= h(app_url('card.php?id=' . $card['id'])) ?>">Открыть</a>
        </article>
    <?php endforeach; ?>
    <?php if (!$cards): ?>
        <div class="card">Пока нет избранных карточек.</div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>


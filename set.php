<?php
require_once __DIR__ . '/includes/functions.php';

$setId = (int) ($_GET['id'] ?? 0);
$set = ensure_set_access($setId);
$pageTitle = $set['name'];
$user = current_user();

$stmt = get_pdo()->prepare(
    'SELECT f.*, c.name AS category,
            COALESCE(p.correct_count, 0) AS correct_count,
            COALESCE(p.total_count, 0) AS total_count
     FROM flashcards f
     JOIN categories c ON c.id = f.category_id
     LEFT JOIN user_progress p ON p.flashcard_id = f.id AND p.user_id = ?
     WHERE f.set_id = ?
     ORDER BY c.name, f.formula'
);
$stmt->execute([$user['id'] ?? 0, $setId]);
$cards = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <span class="badge"><?= $set['is_public'] ? 'Публичный набор' : 'Личный набор' ?></span>
    <h1><?= h($set['name']) ?></h1>
    <p class="lead"><?= h($set['description']) ?></p>
    <div class="actions">
        <a class="button" href="<?= h(app_url('study.php?set_id=' . $setId . '&mode=normal&reset=1')) ?>">Обычная тренировка</a>
        <a class="button ghost" href="<?= h(app_url('study.php?set_id=' . $setId . '&mode=review&reset=1')) ?>">Повторение</a>
        <a class="button ghost" href="<?= h(app_url('study.php?set_id=' . $setId . '&mode=test&reset=1')) ?>">Тест на 10 карточек</a>
    </div>
</section>

<section class="table-wrap" style="margin-top: 18px;">
    <table>
        <thead>
        <tr>
            <th>Формула</th>
            <th>Название</th>
            <th>Категория</th>
            <th>Сложность</th>
            <th>Прогресс</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($cards as $card): ?>
            <tr>
                <td class="formula" style="font-size: 24px;"><?= render_formula($card['formula']) ?></td>
                <td><?= h($card['name']) ?></td>
                <td><?= h($card['category']) ?></td>
                <td><?= h((string) $card['difficulty']) ?>/5</td>
                <td><?= h((string) $card['correct_count']) ?> / <?= h((string) $card['total_count']) ?></td>
                <td><a href="<?= h(app_url('card.php?id=' . $card['id'])) ?>">Детали</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Управление наборами';
$pdo = get_pdo();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM flashcard_sets WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Набор удалён.');
        redirect('admin/sets.php');
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;

        if ($name === '') {
            $errors[] = 'Название набора обязательно.';
        }

        if (!$errors) {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE flashcard_sets SET name = ?, description = ?, is_public = ? WHERE id = ?');
                $stmt->execute([$name, $description, $isPublic, $id]);
                flash('success', 'Набор обновлён.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO flashcard_sets (user_id, name, description, is_public) VALUES (NULL, ?, ?, ?)');
                $stmt->execute([$name, $description, $isPublic]);
                flash('success', 'Набор создан.');
            }
            redirect('admin/sets.php');
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM flashcard_sets WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

$sets = $pdo->query(
    'SELECT s.*, u.username AS owner_name, COUNT(f.id) AS cards_count
     FROM flashcard_sets s
     LEFT JOIN users u ON u.id = s.user_id
     LEFT JOIN flashcards f ON f.set_id = s.id
     GROUP BY s.id
     ORDER BY s.created_at DESC'
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<section class="form-panel">
    <h1><?= $edit ? 'Редактировать набор' : 'Новый глобальный набор' ?></h1>
    <?php foreach ($errors as $error): ?>
        <div class="alert error"><?= h($error) ?></div>
    <?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= h((string) ($edit['id'] ?? 0)) ?>">
        <div class="field">
            <label for="name">Название</label>
            <input id="name" name="name" value="<?= h($edit['name'] ?? '') ?>" required>
        </div>
        <div class="field">
            <label for="description">Описание</label>
            <textarea id="description" name="description"><?= h($edit['description'] ?? '') ?></textarea>
        </div>
        <label><input type="checkbox" name="is_public" value="1" <?= ($edit['is_public'] ?? 1) ? 'checked' : '' ?> style="width:auto;min-height:auto;"> Публичный набор</label>
        <div class="actions" style="margin-top: 16px;">
            <button type="submit">Сохранить</button>
            <?php if ($edit): ?><a class="button ghost" href="<?= h(app_url('admin/sets.php')) ?>">Отмена</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="table-wrap" style="margin-top: 18px;">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Автор</th>
            <th>Публичный</th>
            <th>Карточек</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($sets as $set): ?>
            <tr>
                <td><?= h((string) $set['id']) ?></td>
                <td><?= h($set['name']) ?></td>
                <td><?= h($set['owner_name'] ?: 'администратор') ?></td>
                <td><?= $set['is_public'] ? 'да' : 'нет' ?></td>
                <td><?= h((string) $set['cards_count']) ?></td>
                <td class="inline-actions">
                    <a href="<?= h(app_url('admin/sets.php?edit=' . $set['id'])) ?>">Изменить</a>
                    <form method="post" onsubmit="return confirm('Удалить набор вместе с карточками?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= h((string) $set['id']) ?>">
                        <button class="danger" type="submit">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>


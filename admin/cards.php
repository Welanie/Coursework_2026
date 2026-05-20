<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Управление карточками';
$pdo    = get_pdo();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM flashcards WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Карточка удалена.');
        redirect('admin/cards.php');
    }

    if ($action === 'save') {
        $id         = (int) ($_POST['id'] ?? 0);
        $setId      = (int) ($_POST['set_id'] ?? 0);
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $formula    = trim($_POST['formula'] ?? '');
        $name       = trim($_POST['name'] ?? '');
        $difficulty = min(5, max(1, (int) ($_POST['difficulty'] ?? 1)));

        if ($setId <= 0 || $categoryId <= 0 || $formula === '' || $name === '') {
            $errors[] = 'Заполните набор, категорию, формулу и название.';
        }

        if (!$errors) {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE flashcards SET set_id = ?, category_id = ?, formula = ?, name = ?, difficulty = ? WHERE id = ?');
                $stmt->execute([$setId, $categoryId, $formula, $name, $difficulty, $id]);
                flash('success', 'Карточка обновлена.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO flashcards (set_id, category_id, formula, name, difficulty) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$setId, $categoryId, $formula, $name, $difficulty]);
                flash('success', 'Карточка создана.');
            }
            redirect('admin/cards.php');
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM flashcards WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

$sets       = $pdo->query('SELECT id, name FROM flashcard_sets ORDER BY name')->fetchAll();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$cards      = $pdo->query(
    'SELECT f.*, c.name AS category, s.name AS set_name
     FROM flashcards f
     JOIN categories c ON c.id = f.category_id
     JOIN flashcard_sets s ON s.id = f.set_id
     ORDER BY f.id DESC'
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<section class="form-panel" style="margin-bottom:24px;">
    <div class="eyebrow"><?= $edit ? 'Редактирование' : 'Создание' ?></div>
    <h1 style="font-size:22px;"><?= $edit ? 'Редактировать карточку' : 'Новая карточка' ?></h1>

    <?php foreach ($errors as $error): ?>
        <div class="alert error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= h((string) ($edit['id'] ?? 0)) ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="field">
                <label for="set_id">Набор</label>
                <select id="set_id" name="set_id" required>
                    <?php foreach ($sets as $set): ?>
                        <option value="<?= h((string) $set['id']) ?>"
                            <?= (int) ($edit['set_id'] ?? 0) === (int) $set['id'] ? 'selected' : '' ?>>
                            <?= h($set['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="category_id">Категория</label>
                <select id="category_id" name="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= h((string) $category['id']) ?>"
                            <?= (int) ($edit['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= h($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="field">
                <label for="formula">Формула</label>
                <input id="formula" name="formula"
                       value="<?= h($edit['formula'] ?? '') ?>"
                       placeholder="Например: H2SO4"
                       required>
            </div>
            <div class="field">
                <label for="name">Название вещества</label>
                <input id="name" name="name"
                       value="<?= h($edit['name'] ?? '') ?>"
                       placeholder="Например: Серная кислота"
                       required>
            </div>
        </div>

        <div class="field" style="max-width:160px;">
            <label for="difficulty">Сложность (1–5)</label>
            <input id="difficulty" type="number" min="1" max="5" name="difficulty"
                   value="<?= h((string) ($edit['difficulty'] ?? 1)) ?>">
        </div>

        <div class="actions" style="margin-top:8px;">
            <button type="submit"><?= $edit ? 'Сохранить изменения' : 'Создать карточку' ?></button>
            <?php if ($edit): ?>
                <a class="button ghost" href="<?= h(app_url('admin/cards.php')) ?>">Отмена</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Формула</th>
            <th>Название</th>
            <th>Категория</th>
            <th>Набор</th>
            <th>Слож.</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($cards as $card): ?>
            <tr>
                <td class="text-muted fs-12">#<?= h((string) $card['id']) ?></td>
                <td>
                    <span class="formula-inline"><?= render_formula($card['formula']) ?></span>
                </td>
                <td style="font-weight:500;"><?= h($card['name']) ?></td>
                <td><span class="badge"><?= h($card['category']) ?></span></td>
                <td class="text-muted fs-12"><?= h($card['set_name']) ?></td>
                <td>
                    <!-- dot difficulty indicator -->
                    <span class="difficulty-dots" data-level="<?= (int)$card['difficulty'] ?>">
                        <span></span><span></span><span></span><span></span><span></span>
                    </span>
                </td>
                <td>
                    <div class="inline-actions">
                        <a class="button ghost sm"
                           href="<?= h(app_url('admin/cards.php?edit=' . $card['id'])) ?>">
                            Изменить
                        </a>
                        <form method="post" onsubmit="return confirm('Удалить карточку?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= h((string) $card['id']) ?>">
                            <button class="danger sm" type="submit">Удалить</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

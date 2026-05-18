<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Карточки';
$user = current_user();
$query = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category_id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

[$accessWhere, $accessParams] = set_accessible_where($user);
$where = [$accessWhere];
$params = $accessParams;

if ($query !== '') {
    $where[] = '(f.formula LIKE ? OR f.name LIKE ?)';
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
}

if ($categoryId > 0) {
    $where[] = 'f.category_id = ?';
    $params[] = $categoryId;
}

$categoryStmt = get_pdo()->query('SELECT id, name FROM categories ORDER BY name');
$categories = $categoryStmt->fetchAll();

$countSql = 'SELECT COUNT(*)
             FROM flashcards f
             JOIN flashcard_sets s ON s.id = f.set_id
             WHERE ' . implode(' AND ', $where);
$countStmt = get_pdo()->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$sql = 'SELECT f.*, c.name AS category, s.name AS set_name
        FROM flashcards f
        JOIN categories c ON c.id = f.category_id
        JOIN flashcard_sets s ON s.id = f.set_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY f.id
        LIMIT ' . $perPage . ' OFFSET ' . $offset;
$stmt = get_pdo()->prepare($sql);
$stmt->execute($params);
$cards = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <h1>Каталог карточек</h1>
    <form class="filters" method="get">
        <div class="field">
            <label for="q">Поиск</label>
            <input id="q" name="q" value="<?= h($query) ?>" placeholder="H2SO4 или серная">
        </div>
        <div class="field">
            <label for="category_id">Категория</label>
            <select id="category_id" name="category_id">
                <option value="0">Все категории</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= h((string) $category['id']) ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= h($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="submit">Показать</button>
        </div>
    </form>
</section>

<section class="table-wrap" style="margin-top: 18px;">
    <table>
        <thead>
        <tr>
            <th>Формула</th>
            <th>Название</th>
            <th>Категория</th>
            <th>Набор</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($cards as $card): ?>
            <tr>
                <td class="formula" style="font-size: 24px;"><?= render_formula($card['formula']) ?></td>
                <td><?= h($card['name']) ?></td>
                <td><?= h($card['category']) ?></td>
                <td><?= h($card['set_name']) ?></td>
                <td><a href="<?= h(app_url('card.php?id=' . $card['id'])) ?>">Открыть</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$cards): ?>
            <tr><td colspan="5">Карточки не найдены.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php if ($pages > 1): ?>
    <nav class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <?php
            $url = 'cards.php?' . http_build_query(['q' => $query, 'category_id' => $categoryId, 'page' => $i]);
            ?>
            <?php if ($i === $page): ?>
                <span class="current"><?= h((string) $i) ?></span>
            <?php else: ?>
                <a href="<?= h(app_url($url)) ?>"><?= h((string) $i) ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

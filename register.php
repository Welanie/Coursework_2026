<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$pageTitle = 'Регистрация';
$errors = [];
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $passwordRepeat = $_POST['password_repeat'] ?? '';

    if (!preg_match('/^[A-Za-zА-Яа-яЁё0-9_]{3,50}$/u', $username)) {
        $errors[] = 'Имя пользователя: 3-50 символов, буквы, цифры и подчёркивание.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Пароль должен быть не короче 6 символов.';
    }

    if ($password !== $passwordRepeat) {
        $errors[] = 'Пароли не совпадают.';
    }

    if (!$errors) {
        $stmt = get_pdo()->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'Пользователь с таким именем или email уже существует.';
        }
    }

    if (!$errors) {
        $stmt = get_pdo()->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, "user")');
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
        flash('success', 'Регистрация завершена. Теперь можно войти.');
        redirect('login.php');
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="form-panel">
    <h1>Регистрация</h1>
    <p class="lead">Аккаунт нужен для полного набора карточек, статистики и собственных наборов.</p>

    <?php foreach ($errors as $error): ?>
        <div class="alert error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="field">
            <label for="username">Имя пользователя</label>
            <input id="username" name="username" value="<?= h($username) ?>" required>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="<?= h($email) ?>" required>
        </div>
        <div class="field">
            <label for="password">Пароль</label>
            <input id="password" type="password" name="password" required>
        </div>
        <div class="field">
            <label for="password_repeat">Повтор пароля</label>
            <input id="password_repeat" type="password" name="password_repeat" required>
        </div>
        <button type="submit">Создать аккаунт</button>
    </form>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>


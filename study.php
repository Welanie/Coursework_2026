<?php
require_once __DIR__ . '/includes/functions.php';

$allowedModes = ['normal', 'review', 'test'];
$setId = (int) ($_GET['set_id'] ?? $_POST['set_id'] ?? 0);
$mode = $_GET['mode'] ?? $_POST['mode'] ?? 'normal';
if (!in_array($mode, $allowedModes, true)) {
    $mode = 'normal';
}

$set = ensure_set_access($setId);
$user = current_user();
$pageTitle = 'Тренировка';
$sessionKey = 'study_' . $setId . '_' . $mode;
$shouldReset = isset($_GET['reset']);

if ($shouldReset || empty($_SESSION[$sessionKey]) || ($_SESSION[$sessionKey]['set_id'] ?? 0) !== $setId) {
    $queue = build_study_queue($setId, $mode, $user ? (int) $user['id'] : null);
    if (!$user) {
        $queue = array_slice($queue, 0, DEMO_SET_LIMIT);
    }
    $_SESSION[$sessionKey] = [
        'set_id' => $setId,
        'mode' => $mode,
        'queue' => $queue,
        'answered' => false,
        'last' => null,
        'score' => 0,
        'total' => 0,
    ];
}

$state = $_SESSION[$sessionKey];
$finished = false;
$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'check';

    if ($action === 'check' && !$state['answered'] && !empty($state['queue'])) {
        $card = fetch_card((int) $state['queue'][0]);
        if ($card) {
            $answer = trim($_POST['answer'] ?? '');
            $correct = answer_is_correct($answer, $card['name']);
            $state['answered'] = true;
            $state['last'] = [
                'card_id' => (int) $card['id'],
                'answer' => $answer,
                'expected' => $card['name'],
                'correct' => $correct,
            ];
            $state['total']++;
            if ($correct) {
                $state['score']++;
            }
            if ($user) {
                update_progress((int) $user['id'], (int) $card['id'], $correct);
            }
        }
    }

    if ($action === 'next' && $state['answered']) {
        $last = $state['last'];
        array_shift($state['queue']);
        if ($last && !$last['correct'] && $mode !== 'test') {
            $position = min(2, count($state['queue']));
            array_splice($state['queue'], $position, 0, [(int) $last['card_id']]);
        }
        $state['answered'] = false;
        $state['last'] = null;
    }

    if (empty($state['queue']) && $state['total'] > 0) {
        $finished = true;
        $summary = [
            'score' => (int) $state['score'],
            'total' => (int) $state['total'],
        ];
        if ($user) {
            record_study_session((int) $user['id'], $setId, $mode, $summary['score'], $summary['total']);
        }
        unset($_SESSION[$sessionKey]);
    } else {
        $_SESSION[$sessionKey] = $state;
    }
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($finished && $summary): ?>
    <section class="panel study-card">
        <h1>Тренировка завершена</h1>
        <p class="lead">Результат: <?= h((string) $summary['score']) ?> из <?= h((string) $summary['total']) ?>.</p>
        <?php if (!$user): ?>
            <div class="alert warning">Вы проходили демо-режим. Войдите в аккаунт, чтобы сохранять прогресс.</div>
        <?php endif; ?>
        <div class="actions" style="justify-content: center;">
            <a class="button" href="<?= h(app_url('study.php?set_id=' . $setId . '&mode=' . $mode . '&reset=1')) ?>">Повторить</a>
            <a class="button ghost" href="<?= h(app_url('set.php?id=' . $setId)) ?>">Вернуться к набору</a>
            <?php if ($user): ?>
                <a class="button ghost" href="<?= h(app_url('stats.php')) ?>">Статистика</a>
            <?php endif; ?>
        </div>
    </section>
<?php else: ?>
    <?php
    $state = $_SESSION[$sessionKey] ?? $state;
    $currentId = (int) ($state['queue'][0] ?? 0);
    $card = $currentId ? fetch_card($currentId) : null;
    ?>

    <?php if (!$card): ?>
        <section class="panel">
            <h1>В наборе нет карточек</h1>
            <a class="button ghost" href="<?= h(app_url('sets.php')) ?>">К наборам</a>
        </section>
    <?php else: ?>
        <section class="panel study-card">
            <p class="meta"><?= h($set['name']) ?> · <?= h($mode === 'test' ? 'тест' : ($mode === 'review' ? 'повторение' : 'обычный режим')) ?></p>
            <div class="formula"><?= render_formula($card['formula']) ?></div>
            <p class="lead">Введите название вещества</p>

            <?php if ($state['answered'] && $state['last']): ?>
                <?php if ($state['last']['correct']): ?>
                    <p class="result-correct">Верно: <?= h($state['last']['expected']) ?></p>
                <?php else: ?>
                    <p class="result-wrong">Неверно. Правильный ответ: <?= h($state['last']['expected']) ?></p>
                    <p class="meta">Ваш ответ: <?= h($state['last']['answer'] ?: 'пустой ответ') ?></p>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="set_id" value="<?= h((string) $setId) ?>">
                    <input type="hidden" name="mode" value="<?= h($mode) ?>">
                    <input type="hidden" name="action" value="next">
                    <button type="submit">Следующая карточка</button>
                </form>
            <?php else: ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="set_id" value="<?= h((string) $setId) ?>">
                    <input type="hidden" name="mode" value="<?= h($mode) ?>">
                    <input type="hidden" name="action" value="check">
                    <div class="field">
                        <label for="answer">Ответ</label>
                        <input id="answer" name="answer" autocomplete="off" autofocus required>
                    </div>
                    <button type="submit">Проверить</button>
                </form>
            <?php endif; ?>

            <p class="meta" style="margin-top: 18px;">
                Осталось карточек в очереди: <?= h((string) count($state['queue'])) ?> ·
                Верно сейчас: <?= h((string) $state['score']) ?> / <?= h((string) $state['total']) ?>
            </p>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>


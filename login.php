<?php
/**
 * Autentificarea (login) cu email și parolă.
 */

require_once __DIR__ . '/includes/auth.php';

if (utilizatorCurent()) {
    redirectioneaza('cont.php');
}

$retur = returSigur($_GET['retur'] ?? $_POST['retur'] ?? null);
$eroare = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();

    $email  = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $parola = (string)($_POST['parola'] ?? '');

    $stmt = db()->prepare('SELECT id, nume, email, parola_hash, rol FROM utilizatori WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $utilizator = $stmt->fetch();

    // Dacă emailul nu există, verificăm totuși o parolă pe un hash fals,
    // ca răspunsul să dureze la fel => nu se poate afla după timp dacă un email are cont.
    $hash = $utilizator ? $utilizator['parola_hash'] : '$2y$10$03Z8kxGNMa5uqwM8IQwq3e6e6j.wsQcSLdMmSSQIzcbXJRUiJRs9.';
    $parolaCorecta = password_verify($parola, $hash);

    if ($utilizator && $parolaCorecta) {
        autentifica($utilizator);
        seteazaFlash('succes', 'Bine ai revenit, ' . $utilizator['nume'] . '!');

        if ($retur) {
            header('Location: ' . $retur);
            exit;
        }
        redirectioneaza($utilizator['rol'] === 'admin' ? 'admin/index.php' : 'cont.php');
    }

    // ACELAȘI mesaj pentru email inexistent și pentru parolă greșită:
    // nu îi spunem unui atacator care conturi există.
    $eroare = 'Email sau parolă incorectă.';
}

$titluPagina     = 'Autentificare';
$descrierePagina = 'Autentifică-te în contul tău ' . NUME_MAGAZIN . '.';
require __DIR__ . '/includes/header.php';
?>

<section class="formular-card">
    <h1>Autentificare</h1>

    <?php if ($eroare): ?>
        <p class="mesaj mesaj--eroare" role="alert"><?= e($eroare) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login.php')) ?>">
        <?= campCsrf() ?>
        <?php if ($retur): ?><input type="hidden" name="retur" value="<?= e($retur) ?>"><?php endif; ?>

        <div class="camp">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?= e($email) ?>" required autocomplete="email">
        </div>

        <div class="camp">
            <label for="parola">Parolă</label>
            <input id="parola" name="parola" type="password" required autocomplete="current-password">
        </div>

        <button class="buton buton--lat" type="submit">Intră în cont</button>
    </form>

    <p class="formular-card__alternativa">
        Nu ai cont?
        <a href="<?= e(url('register.php' . ($retur ? '?retur=' . rawurlencode($retur) : ''))) ?>">Creează unul acum</a>
    </p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

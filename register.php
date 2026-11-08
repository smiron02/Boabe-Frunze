<?php
/**
 * Crearea unui cont nou de client.
 */

require_once __DIR__ . '/includes/auth.php';

// Un utilizator deja autentificat nu are ce căuta aici
if (utilizatorCurent()) {
    redirectioneaza('cont.php');
}

$retur  = returSigur($_GET['retur'] ?? $_POST['retur'] ?? null);
$erori  = [];
$nume   = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();

    // ---------- 1. Preluarea datelor ----------
    $nume    = trim((string)($_POST['nume'] ?? ''));
    $email   = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $parola  = (string)($_POST['parola'] ?? '');
    $parola2 = (string)($_POST['parola2'] ?? '');

    // ---------- 2. Validarea (pe server — validarea din browser poate fi ocolită) ----------
    if (mb_strlen($nume) < 2 || mb_strlen($nume) > 100) {
        $erori['nume'] = 'Numele trebuie să aibă între 2 și 100 de caractere.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $erori['email'] = 'Adresa de email nu este validă.';
    }
    // bcrypt (algoritmul implicit) folosește doar primii 72 de octeți ai parolei
    if (strlen($parola) < 8 || strlen($parola) > 72) {
        $erori['parola'] = 'Parola trebuie să aibă între 8 și 72 de caractere.';
    } elseif ($parola !== $parola2) {
        $erori['parola2'] = 'Parolele nu coincid.';
    }

    // Email unic (verificat înainte de INSERT pentru un mesaj prietenos)
    if (!isset($erori['email'])) {
        $stmt = db()->prepare('SELECT 1 FROM utilizatori WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetchColumn()) {
            $erori['email'] = 'Există deja un cont cu această adresă de email.';
        }
    }

    // ---------- 3. Salvarea ----------
    if (!$erori) {
        try {
            $stmt = db()->prepare(
                'INSERT INTO utilizatori (nume, email, parola_hash) VALUES (:nume, :email, :hash)'
            );
            $stmt->execute([
                'nume'  => $nume,
                'email' => $email,
                // password_hash generează automat o „sare” aleatoare și include
                // algoritmul + sarea în rezultat; parola în clar NU se salvează
                'hash'  => password_hash($parola, PASSWORD_DEFAULT),
            ]);

            // Contul nou are rolul implicit „client” (din schema bazei de date)
            autentifica([
                'id' => db()->lastInsertId(), 'nume' => $nume, 'email' => $email, 'rol' => 'client',
            ]);
            seteazaFlash('succes', 'Contul a fost creat. Bine ai venit, ' . $nume . '!');

            if ($retur) {
                header('Location: ' . $retur);
                exit;
            }
            redirectioneaza('cont.php');
        } catch (PDOException $ex) {
            // 23000 = încălcarea constrângerii UNIQUE (două înregistrări simultane)
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }
            $erori['email'] = 'Există deja un cont cu această adresă de email.';
        }
    }
}

$titluPagina     = 'Creează cont';
$descrierePagina = 'Creează-ți un cont pentru a plasa comenzi și a vedea istoricul lor.';
require __DIR__ . '/includes/header.php';
?>

<section class="formular-card">
    <h1>Creează cont</h1>

    <?php if ($erori): ?>
        <p class="mesaj mesaj--eroare" role="alert">Te rugăm să corectezi câmpurile marcate.</p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('register.php')) ?>" novalidate>
        <?= campCsrf() ?>
        <?php if ($retur): ?><input type="hidden" name="retur" value="<?= e($retur) ?>"><?php endif; ?>

        <div class="camp">
            <label for="nume">Nume complet</label>
            <input id="nume" name="nume" type="text" value="<?= e($nume) ?>" required
                   maxlength="100" autocomplete="name" <?= isset($erori['nume']) ? 'aria-invalid="true" aria-describedby="eroare-nume"' : '' ?>>
            <?php if (isset($erori['nume'])): ?><p class="camp__eroare" id="eroare-nume"><?= e($erori['nume']) ?></p><?php endif; ?>
        </div>

        <div class="camp">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?= e($email) ?>" required
                   maxlength="190" autocomplete="email" <?= isset($erori['email']) ? 'aria-invalid="true" aria-describedby="eroare-email"' : '' ?>>
            <?php if (isset($erori['email'])): ?><p class="camp__eroare" id="eroare-email"><?= e($erori['email']) ?></p><?php endif; ?>
        </div>

        <div class="camp">
            <label for="parola">Parolă <span class="nota">(minimum 8 caractere)</span></label>
            <input id="parola" name="parola" type="password" required minlength="8" maxlength="72"
                   autocomplete="new-password" <?= isset($erori['parola']) ? 'aria-invalid="true" aria-describedby="eroare-parola"' : '' ?>>
            <?php if (isset($erori['parola'])): ?><p class="camp__eroare" id="eroare-parola"><?= e($erori['parola']) ?></p><?php endif; ?>
        </div>

        <div class="camp">
            <label for="parola2">Confirmă parola</label>
            <input id="parola2" name="parola2" type="password" required autocomplete="new-password"
                   <?= isset($erori['parola2']) ? 'aria-invalid="true" aria-describedby="eroare-parola2"' : '' ?>>
            <?php if (isset($erori['parola2'])): ?><p class="camp__eroare" id="eroare-parola2"><?= e($erori['parola2']) ?></p><?php endif; ?>
        </div>

        <button class="buton buton--lat" type="submit">Creează contul</button>
    </form>

    <p class="formular-card__alternativa">
        Ai deja cont?
        <a href="<?= e(url('login.php' . ($retur ? '?retur=' . rawurlencode($retur) : ''))) ?>">Autentifică-te</a>
    </p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

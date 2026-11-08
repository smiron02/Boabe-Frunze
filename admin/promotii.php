<?php
/**
 * Administrare promoții: listă cu starea fiecărei promoții (activă / viitoare /
 * expirată), adăugare, editare (?id=5) și ștergere.
 */

require_once __DIR__ . '/../includes/auth.php';
cereAdmin();

$editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$form   = ['produs_id' => '', 'procent' => '', 'data_start' => date('Y-m-d'), 'data_end' => date('Y-m-d', strtotime('+14 days'))];
$erori  = [];

if ($editId) {
    $stmt = db()->prepare('SELECT produs_id, procent, data_start, data_end FROM promotii WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $form = $stmt->fetch() ?: pagina404('Promoția nu există.');
}

/** true dacă $text este o dată validă în formatul AAAA-LL-ZZ (ex.: 2026-02-30 nu e) */
function dataValida(string $text): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $text);
    return $d !== false && $d->format('Y-m-d') === $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();

    if (($_POST['actiune'] ?? '') === 'sterge') {
        $stmt = db()->prepare('DELETE FROM promotii WHERE id = :id');
        $stmt->execute(['id' => filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT)]);
        seteazaFlash('succes', 'Promoția a fost ștearsă.');
        redirectioneaza('admin/promotii.php');
    }

    $form = [
        'produs_id'  => filter_var($_POST['produs_id'] ?? null, FILTER_VALIDATE_INT),
        'procent'    => filter_var($_POST['procent'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 90]]),
        'data_start' => (string)($_POST['data_start'] ?? ''),
        'data_end'   => (string)($_POST['data_end'] ?? ''),
    ];

    $stmt = db()->prepare('SELECT 1 FROM produse WHERE id = :id');
    $stmt->execute(['id' => $form['produs_id'] ?: 0]);
    if (!$stmt->fetchColumn()) {
        $erori['produs_id'] = 'Alege produsul.';
    }
    if ($form['procent'] === false) {
        $erori['procent'] = 'Reducerea este un număr întreg între 1 și 90 (%).';
    }
    if (!dataValida($form['data_start']) || !dataValida($form['data_end'])) {
        $erori['perioada'] = 'Alege datele de început și de sfârșit.';
    } elseif ($form['data_end'] < $form['data_start']) {   // formatul AAAA-LL-ZZ se compară corect ca text
        $erori['perioada'] = 'Data de sfârșit trebuie să fie după data de început.';
    }

    if (!$erori) {
        $date = ['p' => $form['produs_id'], 'pr' => $form['procent'], 's' => $form['data_start'], 'e' => $form['data_end']];
        if ($editId) {
            db()->prepare('UPDATE promotii SET produs_id = :p, procent = :pr, data_start = :s, data_end = :e WHERE id = :id')
                ->execute($date + ['id' => $editId]);
        } else {
            db()->prepare('INSERT INTO promotii (produs_id, procent, data_start, data_end) VALUES (:p, :pr, :s, :e)')
                ->execute($date);
        }
        seteazaFlash('succes', 'Promoția a fost salvată.');
        redirectioneaza('admin/promotii.php');
    }
}

// Starea fiecărei promoții se calculează în SQL, față de data de azi
$promotii = db()->query(
    "SELECT pr.id, pr.procent, pr.data_start, pr.data_end, p.nume, p.pret,
            CASE WHEN CURDATE() < pr.data_start THEN 'viitoare'
                 WHEN CURDATE() > pr.data_end   THEN 'expirata'
                 ELSE 'activa' END AS stare,
            CASE WHEN CURDATE() < pr.data_start THEN 2
                 WHEN CURDATE() > pr.data_end   THEN 3
                 ELSE 1 END AS ordine              -- active, apoi viitoare, apoi expirate
       FROM promotii pr JOIN produse p ON p.id = pr.produs_id
      ORDER BY ordine, pr.data_end"
)->fetchAll();
$produse = db()->query('SELECT id, nume, pret FROM produse ORDER BY nume')->fetchAll();

$titluPagina = 'Promoții';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_meniu.php';
$etichete = ['activa' => 'Activă', 'viitoare' => 'Viitoare', 'expirata' => 'Expirată'];
?>

<h1>Promoții</h1>

<div class="checkout">
    <section class="card">
        <h2>Lista promoțiilor</h2>
        <?php if (!$promotii): ?>
            <p>Nu există promoții.</p>
        <?php else: ?>
        <table class="tabel-simplu">
            <thead>
                <tr><th scope="col">Produs</th><th scope="col">Reducere</th><th scope="col">Perioadă</th><th scope="col">Stare</th><th scope="col"><span class="vizual-ascuns">Acțiuni</span></th></tr>
            </thead>
            <tbody>
            <?php foreach ($promotii as $pr): ?>
                <tr<?= $editId === (int)$pr['id'] ? ' class="rand-selectat"' : '' ?>>
                    <td><?= e($pr['nume']) ?><br>
                        <span class="nota"><?= e(formatPret((float)$pr['pret'])) ?> → <?= e(formatPret(pretFinal((float)$pr['pret'], (int)$pr['procent']))) ?></span></td>
                    <td>−<?= (int)$pr['procent'] ?>%</td>
                    <td><?= e(date('d.m.Y', strtotime($pr['data_start']))) ?> –<br><?= e(date('d.m.Y', strtotime($pr['data_end']))) ?></td>
                    <td><span class="stare-promo stare-promo--<?= e($pr['stare']) ?>"><?= e($etichete[$pr['stare']]) ?></span></td>
                    <td class="actiuni">
                        <a href="<?= e(url('admin/promotii.php?id=' . $pr['id'])) ?>">Editează</a>
                        <form method="post" action="<?= e(url('admin/promotii.php')) ?>" data-confirma="Ștergi promoția pentru „<?= e($pr['nume']) ?>”?">
                            <?= campCsrf() ?>
                            <input type="hidden" name="actiune" value="sterge">
                            <input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
                            <button class="buton-sterge" type="submit">Șterge</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <p class="nota">Dacă un produs are mai multe promoții active în același timp, se aplică reducerea cea mai mare.</p>
    </section>

    <section class="card">
        <h2><?= $editId ? 'Editează promoția' : 'Promoție nouă' ?></h2>
        <form method="post" action="<?= e(url('admin/promotii.php' . ($editId ? '?id=' . $editId : ''))) ?>">
            <?= campCsrf() ?>
            <div class="camp">
                <label for="produs_id">Produs</label>
                <select id="produs_id" name="produs_id" required>
                    <option value="">— alege —</option>
                    <?php foreach ($produse as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (int)$form['produs_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['nume']) ?> (<?= e(formatPret((float)$p['pret'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($erori['produs_id'])): ?><p class="camp__eroare"><?= e($erori['produs_id']) ?></p><?php endif; ?>
            </div>
            <div class="camp">
                <label for="procent">Reducere (%)</label>
                <input id="procent" name="procent" type="number" min="1" max="90" value="<?= e((string)$form['procent']) ?>" required>
                <?php if (isset($erori['procent'])): ?><p class="camp__eroare"><?= e($erori['procent']) ?></p><?php endif; ?>
            </div>
            <div class="campuri-2">
                <div class="camp">
                    <label for="data_start">De la</label>
                    <input id="data_start" name="data_start" type="date" value="<?= e((string)$form['data_start']) ?>" required>
                </div>
                <div class="camp">
                    <label for="data_end">Până la (inclusiv)</label>
                    <input id="data_end" name="data_end" type="date" value="<?= e((string)$form['data_end']) ?>" required>
                </div>
            </div>
            <?php if (isset($erori['perioada'])): ?><p class="camp__eroare"><?= e($erori['perioada']) ?></p><?php endif; ?>
            <div class="butoane-form">
                <button class="buton" type="submit">Salvează</button>
                <?php if ($editId): ?><a class="buton buton--contur" href="<?= e(url('admin/promotii.php')) ?>">Renunță</a><?php endif; ?>
            </div>
        </form>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

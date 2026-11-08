<?php
/**
 * Administrare categorii: listă, adăugare, editare (nume, descriere, cotă TVA)
 * și ștergere (doar pentru categoriile fără produse).
 */

require_once __DIR__ . '/../includes/auth.php';
cereAdmin();

$editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$form   = ['nume' => '', 'descriere' => '', 'cota_tva' => '11'];
$erori  = [];

if ($editId) {
    $stmt = db()->prepare('SELECT nume, descriere, cota_tva FROM categorii WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $form = $stmt->fetch() ?: pagina404('Categoria nu există.');
    $form['cota_tva'] = rtrim(rtrim($form['cota_tva'], '0'), '.');   // „11.00” → „11”
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();
    $actiune = $_POST['actiune'] ?? '';

    if ($actiune === 'sterge') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        try {
            // Cheia străină produse → categorii este ON DELETE RESTRICT:
            // MySQL refuză ștergerea dacă există produse în categorie.
            $stmt = db()->prepare('DELETE FROM categorii WHERE id = :id');
            $stmt->execute(['id' => $id]);
            seteazaFlash('succes', 'Categoria a fost ștearsă.');
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }
            seteazaFlash('eroare', 'Categoria conține produse și nu poate fi ștearsă. Mută sau șterge întâi produsele.');
        }
        redirectioneaza('admin/categorii.php');
    }

    // Adăugare sau editare
    $form['nume']      = trim((string)($_POST['nume'] ?? ''));
    $form['descriere'] = trim((string)($_POST['descriere'] ?? ''));
    $form['cota_tva']  = str_replace(',', '.', trim((string)($_POST['cota_tva'] ?? '')));

    if (mb_strlen($form['nume']) < 2 || mb_strlen($form['nume']) > 100) {
        $erori['nume'] = 'Numele trebuie să aibă între 2 și 100 de caractere.';
    }
    if (!is_numeric($form['cota_tva']) || $form['cota_tva'] < 0 || $form['cota_tva'] > 100) {
        $erori['cota_tva'] = 'Cota TVA este un procent între 0 și 100 (ex.: 11 sau 21).';
    }

    if (!$erori) {
        $date = ['nume' => $form['nume'], 'descriere' => $form['descriere'] ?: null, 'cota' => $form['cota_tva']];
        try {
            if ($editId) {
                db()->prepare('UPDATE categorii SET nume = :nume, descriere = :descriere, cota_tva = :cota WHERE id = :id')
                    ->execute($date + ['id' => $editId]);
            } else {
                db()->prepare('INSERT INTO categorii (nume, descriere, cota_tva) VALUES (:nume, :descriere, :cota)')
                    ->execute($date);
            }
            seteazaFlash('succes', "Categoria „{$form['nume']}” a fost salvată.");
            redirectioneaza('admin/categorii.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }
            $erori['nume'] = 'Există deja o categorie cu acest nume.';   // coloana nume este UNIQUE
        }
    }
}

$categorii = db()->query(
    'SELECT c.id, c.nume, c.descriere, c.cota_tva, COUNT(p.id) AS nr_produse
       FROM categorii c LEFT JOIN produse p ON p.categorie_id = c.id
      GROUP BY c.id, c.nume, c.descriere, c.cota_tva
      ORDER BY c.id'
)->fetchAll();

$titluPagina = 'Categorii';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_meniu.php';
?>

<h1>Categorii</h1>

<div class="checkout">
    <section class="card">
        <h2>Categorii existente</h2>
        <table class="tabel-simplu">
            <thead>
                <tr><th scope="col">Nume</th><th scope="col">Cotă TVA</th><th scope="col">Produse</th><th scope="col"><span class="vizual-ascuns">Acțiuni</span></th></tr>
            </thead>
            <tbody>
            <?php foreach ($categorii as $c): ?>
                <tr<?= $editId === (int)$c['id'] ? ' class="rand-selectat"' : '' ?>>
                    <td><strong><?= e($c['nume']) ?></strong><?php if ($c['descriere']): ?><br><span class="nota"><?= e($c['descriere']) ?></span><?php endif; ?></td>
                    <td><?= (float)$c['cota_tva'] + 0 ?>%</td>
                    <td><a href="<?= e(url('admin/produse.php?categorie=' . $c['id'])) ?>"><?= (int)$c['nr_produse'] ?></a></td>
                    <td class="actiuni">
                        <a href="<?= e(url('admin/categorii.php?id=' . $c['id'])) ?>">Editează</a>
                        <?php if (!$c['nr_produse']): ?>
                            <form method="post" action="<?= e(url('admin/categorii.php')) ?>" data-confirma="Ștergi categoria „<?= e($c['nume']) ?>”?">
                                <?= campCsrf() ?>
                                <input type="hidden" name="actiune" value="sterge">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button class="buton-sterge" type="submit">Șterge</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="nota">O categorie cu produse nu poate fi ștearsă. Cota TVA se aplică produselor la plasarea comenzilor noi; comenzile vechi își păstrează cota.</p>
    </section>

    <section class="card">
        <h2><?= $editId ? 'Editează categoria' : 'Categorie nouă' ?></h2>
        <form method="post" action="<?= e(url('admin/categorii.php' . ($editId ? '?id=' . $editId : ''))) ?>">
            <?= campCsrf() ?>
            <input type="hidden" name="actiune" value="salveaza">
            <div class="camp">
                <label for="nume">Nume</label>
                <input id="nume" name="nume" value="<?= e($form['nume']) ?>" required maxlength="100">
                <?php if (isset($erori['nume'])): ?><p class="camp__eroare"><?= e($erori['nume']) ?></p><?php endif; ?>
            </div>
            <div class="camp">
                <label for="descriere">Descriere</label>
                <textarea id="descriere" name="descriere" rows="3"><?= e((string)$form['descriere']) ?></textarea>
            </div>
            <div class="camp">
                <label for="cota_tva">Cotă TVA (%)</label>
                <input id="cota_tva" name="cota_tva" inputmode="decimal" value="<?= e((string)$form['cota_tva']) ?>" required>
                <p class="nota">11% alimente (cafea, ceai), 21% cota standard.</p>
                <?php if (isset($erori['cota_tva'])): ?><p class="camp__eroare"><?= e($erori['cota_tva']) ?></p><?php endif; ?>
            </div>
            <div class="butoane-form">
                <button class="buton" type="submit">Salvează</button>
                <?php if ($editId): ?><a class="buton buton--contur" href="<?= e(url('admin/categorii.php')) ?>">Renunță</a><?php endif; ?>
            </div>
        </form>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

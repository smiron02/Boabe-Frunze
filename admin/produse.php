<?php
/**
 * Administrare produse: listă cu căutare/filtru, stoc editabil direct,
 * activare/dezactivare și ștergere. Adăugarea/editarea: produs_editare.php.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';
cereAdmin();

// ======================================================================
// 1. Acțiuni (POST): stoc, activ, ștergere
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $actiune = $_POST['actiune'] ?? '';

    $stmt = db()->prepare('SELECT id, nume, imagine, activ FROM produse WHERE id = :id');
    $stmt->execute(['id' => $id ?: 0]);
    $produs = $stmt->fetch();

    if (!$produs) {
        seteazaFlash('eroare', 'Produsul nu există.');
    } elseif ($actiune === 'stoc') {
        $stoc = filter_var($_POST['stoc'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
        if ($stoc === false) {
            seteazaFlash('eroare', 'Stocul trebuie să fie un număr întreg între 0 și 100000.');
        } else {
            db()->prepare('UPDATE produse SET stoc = :s WHERE id = :id')->execute(['s' => $stoc, 'id' => $id]);
            seteazaFlash('succes', "Stocul pentru „{$produs['nume']}” este acum $stoc.");
        }
    } elseif ($actiune === 'activ') {
        // NOT activ: 1 devine 0 și invers
        db()->prepare('UPDATE produse SET activ = NOT activ WHERE id = :id')->execute(['id' => $id]);
        seteazaFlash('succes', "„{$produs['nume']}” a fost " . ($produs['activ'] ? 'ascuns din catalog.' : 'reactivat în catalog.'));
    } elseif ($actiune === 'sterge') {
        // Un produs care apare în comenzi NU se poate șterge (cheia străină
        // comenzi_produse → produse este ON DELETE RESTRICT): se dezactivează.
        $stmt = db()->prepare('SELECT COUNT(*) FROM comenzi_produse WHERE produs_id = :id');
        $stmt->execute(['id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            seteazaFlash('eroare', "„{$produs['nume']}” apare în comenzi și nu poate fi șters (istoricul trebuie păstrat). Folosește „Ascunde”.");
        } else {
            // atribute_cafea și promotii se șterg automat (ON DELETE CASCADE)
            db()->prepare('DELETE FROM produse WHERE id = :id')->execute(['id' => $id]);
            stergeImagineProdus($produs['imagine']);
            unset($_SESSION['cos'][$id]);
            seteazaFlash('succes', "„{$produs['nume']}” a fost șters.");
        }
    }

    // Revenim la aceeași listă (cu aceleași filtre)
    $retur = returSigur($_POST['retur'] ?? null);
    if ($retur) {
        header('Location: ' . $retur);
        exit;
    }
    redirectioneaza('admin/produse.php');
}

// ======================================================================
// 2. Lista (GET), cu căutare după nume și filtru pe categorie
// ======================================================================
$cautare     = trim((string)($_GET['q'] ?? ''));
$categorieId = filter_input(INPUT_GET, 'categorie', FILTER_VALIDATE_INT) ?: null;

$conditii  = [];
$parametri = [];
if ($cautare !== '') {
    // LIKE cu parametru: %...% se adaugă la VALOARE, nu în textul SQL
    $conditii[] = 'p.nume LIKE :q';
    $parametri['q'] = '%' . $cautare . '%';
}
if ($categorieId) {
    $conditii[] = 'p.categorie_id = :c';
    $parametri['c'] = $categorieId;
}
$where = $conditii ? 'WHERE ' . implode(' AND ', $conditii) : '';

$stmt = db()->prepare(
    "SELECT p.id, p.nume, p.pret, p.stoc, p.activ, p.imagine, c.nume AS categorie,
            (SELECT COUNT(*) FROM comenzi_produse cp WHERE cp.produs_id = p.id) AS in_comenzi
       FROM produse p
       JOIN categorii c ON c.id = p.categorie_id
       $where
      ORDER BY c.id, p.nume"
);
$stmt->execute($parametri);
$produse = $stmt->fetchAll();

$titluPagina = 'Produse și stocuri';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_meniu.php';
$returLista = $_SERVER['REQUEST_URI'];
?>

<div class="admin-titlu">
    <h1>Produse și stocuri</h1>
    <a class="buton" href="<?= e(url('admin/produs_editare.php')) ?>">+ Adaugă produs</a>
</div>

<form class="admin-filtre" method="get" action="<?= e(url('admin/produse.php')) ?>">
    <label class="vizual-ascuns" for="q">Caută după nume</label>
    <input id="q" name="q" type="search" value="<?= e($cautare) ?>" placeholder="Caută după nume…">
    <label class="vizual-ascuns" for="categorie">Categorie</label>
    <select id="categorie" name="categorie">
        <option value="">Toate categoriile</option>
        <?php foreach (categoriiMeniu() as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $categorieId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['nume']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="buton buton--mic" type="submit">Filtrează</button>
    <?php if ($cautare !== '' || $categorieId): ?>
        <a href="<?= e(url('admin/produse.php')) ?>">Resetează</a>
    <?php endif; ?>
</form>

<p class="rezultate"><?= count($produse) ?> produse</p>

<div class="card">
<table class="tabel-simplu tabel-admin">
    <thead>
        <tr>
            <th scope="col"><span class="vizual-ascuns">Imagine</span></th>
            <th scope="col">Produs</th>
            <th scope="col">Preț</th>
            <th scope="col">Stoc</th>
            <th scope="col">Stare</th>
            <th scope="col"><span class="vizual-ascuns">Acțiuni</span></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($produse as $p): ?>
        <tr class="<?= $p['activ'] ? '' : 'rand-inactiv' ?>">
            <td><img src="<?= e(imagineProdus($p['imagine'])) ?>" alt="" width="48" height="48" loading="lazy" class="miniatura"></td>
            <td>
                <a href="<?= e(url('admin/produs_editare.php?id=' . $p['id'])) ?>"><strong><?= e($p['nume']) ?></strong></a><br>
                <span class="nota"><?= e($p['categorie']) ?></span>
            </td>
            <td><?= e(formatPret((float)$p['pret'])) ?></td>
            <td>
                <!-- Stocul se modifică direct din listă -->
                <form class="form-stoc" method="post" action="<?= e(url('admin/produse.php')) ?>">
                    <?= campCsrf() ?>
                    <input type="hidden" name="actiune" value="stoc">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="retur" value="<?= e($returLista) ?>">
                    <label class="vizual-ascuns" for="stoc-<?= (int)$p['id'] ?>">Stoc pentru <?= e($p['nume']) ?></label>
                    <input id="stoc-<?= (int)$p['id'] ?>" name="stoc" type="number" min="0" max="100000" value="<?= (int)$p['stoc'] ?>"
                           class="<?= (int)$p['stoc'] < 5 ? 'stoc-mic' : '' ?>">
                    <button class="buton buton--mic buton--contur" type="submit">Salvează</button>
                </form>
            </td>
            <td><?= $p['activ'] ? 'Activ' : '<span class="nota">Ascuns</span>' ?></td>
            <td class="actiuni">
                <a href="<?= e(url('admin/produs_editare.php?id=' . $p['id'])) ?>">Editează</a>
                <form method="post" action="<?= e(url('admin/produse.php')) ?>">
                    <?= campCsrf() ?>
                    <input type="hidden" name="actiune" value="activ">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="retur" value="<?= e($returLista) ?>">
                    <button class="buton-link" type="submit"><?= $p['activ'] ? 'Ascunde' : 'Activează' ?></button>
                </form>
                <?php if (!$p['in_comenzi']): ?>
                    <form method="post" action="<?= e(url('admin/produse.php')) ?>"
                          data-confirma="Ștergi definitiv produsul „<?= e($p['nume']) ?>”?">
                        <?= campCsrf() ?>
                        <input type="hidden" name="actiune" value="sterge">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="retur" value="<?= e($returLista) ?>">
                        <button class="buton-sterge" type="submit">Șterge</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

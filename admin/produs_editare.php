<?php
/**
 * Adăugare produs (fără ?id) sau editare produs (?id=5),
 * inclusiv imaginea și atributele de cafea folosite de quiz.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';
cereAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

// ---------- Valorile inițiale ale formularului ----------
$p = [
    'nume' => '', 'categorie_id' => '', 'descriere' => '', 'pret' => '', 'gramaj' => '',
    'stoc' => 0, 'activ' => 1, 'imagine' => null,
    'e_cafea' => 0, 'intensitate' => 3, 'acid' => 3, 'amar' => 3,
    'origine' => '', 'prajire' => 'medie', 'aparat' => 'filtru',
];
if ($id) {
    $stmt = db()->prepare(
        'SELECT p.*, a.produs_id IS NOT NULL AS e_cafea, a.intensitate, a.acid, a.amar, a.origine, a.prajire, a.aparat
           FROM produse p LEFT JOIN atribute_cafea a ON a.produs_id = p.id
          WHERE p.id = :id'
    );
    $stmt->execute(['id' => $id]);
    $dinBaza = $stmt->fetch() ?: pagina404('Produsul nu există.');
    // Păstrăm valorile implicite pentru atributele lipsă (produse care nu sunt cafea)
    $p = array_merge($p, array_filter($dinBaza, fn($v) => $v !== null));
}

$prajiri = ['deschisă', 'medie', 'închisă'];
$aparate = ['filtru' => 'Filtru / V60', 'espresso' => 'Espressor', 'ibric' => 'Ibric'];
$erori = [];

// ======================================================================
// Salvarea (POST)
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();

    $p['nume']         = trim((string)($_POST['nume'] ?? ''));
    $p['categorie_id'] = filter_var($_POST['categorie_id'] ?? null, FILTER_VALIDATE_INT);
    $p['descriere']    = trim((string)($_POST['descriere'] ?? ''));
    $p['pret']         = str_replace(',', '.', trim((string)($_POST['pret'] ?? '')));   // acceptăm „49,90”
    $p['gramaj']       = trim((string)($_POST['gramaj'] ?? ''));
    $p['stoc']         = filter_var($_POST['stoc'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
    $p['activ']        = isset($_POST['activ']) ? 1 : 0;
    $p['e_cafea']      = isset($_POST['e_cafea']) ? 1 : 0;
    foreach (['intensitate', 'acid', 'amar'] as $c) {
        $p[$c] = filter_var($_POST[$c] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    }
    $p['origine'] = trim((string)($_POST['origine'] ?? ''));
    $p['prajire'] = (string)($_POST['prajire'] ?? '');
    $p['aparat']  = (string)($_POST['aparat'] ?? '');

    // ---------- Validare ----------
    if (mb_strlen($p['nume']) < 2 || mb_strlen($p['nume']) > 150) {
        $erori['nume'] = 'Numele trebuie să aibă între 2 și 150 de caractere.';
    }
    $categoriiValide = array_column(categoriiMeniu(), 'id');
    if (!in_array($p['categorie_id'], array_map('intval', $categoriiValide), true)) {
        $erori['categorie_id'] = 'Alege categoria.';
    }
    if (!is_numeric($p['pret']) || (float)$p['pret'] <= 0 || (float)$p['pret'] > 100000) {
        $erori['pret'] = 'Prețul trebuie să fie un număr pozitiv (ex.: 49,90).';
    }
    if (mb_strlen($p['gramaj']) > 50) {
        $erori['gramaj'] = 'Maximum 50 de caractere.';
    }
    if ($p['stoc'] === false) {
        $erori['stoc'] = 'Stocul trebuie să fie un număr întreg între 0 și 100000.';
    }
    if ($p['e_cafea']) {
        if ($p['intensitate'] === false || $p['acid'] === false || $p['amar'] === false) {
            $erori['atribute'] = 'Intensitatea, aciditatea și amăreala au valori între 1 și 5.';
        }
        if (!in_array($p['prajire'], $prajiri, true) || !isset($aparate[$p['aparat']])) {
            $erori['atribute'] = 'Alege prăjirea și aparatul.';
        }
    }

    // ---------- Imaginea (opțională) ----------
    $imagineNoua = null;
    if (!$erori && ($_FILES['imagine']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $imagineNoua = salveazaImagineProdus($_FILES['imagine']);
        } catch (RuntimeException $ex) {
            $erori['imagine'] = $ex->getMessage();
        }
    }

    // ---------- Salvarea în baza de date (tranzacție: produs + atribute) ----------
    if (!$erori) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $date = [
                'nume' => $p['nume'], 'cat' => $p['categorie_id'], 'descriere' => $p['descriere'] ?: null,
                'pret' => round((float)$p['pret'], 2), 'gramaj' => $p['gramaj'] ?: null,
                'stoc' => $p['stoc'], 'activ' => $p['activ'],
                'imagine' => $imagineNoua ?? $p['imagine'],
            ];
            if ($id) {
                $pdo->prepare(
                    'UPDATE produse SET categorie_id = :cat, nume = :nume, descriere = :descriere, pret = :pret,
                            gramaj = :gramaj, stoc = :stoc, activ = :activ, imagine = :imagine
                      WHERE id = :id'
                )->execute($date + ['id' => $id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO produse (categorie_id, nume, descriere, pret, gramaj, stoc, activ, imagine)
                     VALUES (:cat, :nume, :descriere, :pret, :gramaj, :stoc, :activ, :imagine)'
                )->execute($date);
                $id = (int)$pdo->lastInsertId();
            }

            if ($p['e_cafea']) {
                // INSERT sau, dacă există deja rândul, UPDATE („upsert”)
                $pdo->prepare(
                    'INSERT INTO atribute_cafea (produs_id, intensitate, origine, prajire, acid, amar, aparat)
                     VALUES (:id, :i, :o, :pr, :a, :m, :ap)
                     ON DUPLICATE KEY UPDATE intensitate = VALUES(intensitate), origine = VALUES(origine),
                            prajire = VALUES(prajire), acid = VALUES(acid), amar = VALUES(amar), aparat = VALUES(aparat)'
                )->execute([
                    'id' => $id, 'i' => $p['intensitate'], 'o' => $p['origine'] ?: null, 'pr' => $p['prajire'],
                    'a' => $p['acid'], 'm' => $p['amar'], 'ap' => $p['aparat'],
                ]);
            } else {
                $pdo->prepare('DELETE FROM atribute_cafea WHERE produs_id = :id')->execute(['id' => $id]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            stergeImagineProdus($imagineNoua);   // nu lăsăm fișiere „orfane”
            throw $ex;
        }

        // Imaginea veche se șterge DOAR după ce salvarea a reușit
        if ($imagineNoua && $p['imagine'] !== $imagineNoua) {
            stergeImagineProdus($p['imagine']);
        }
        seteazaFlash('succes', "Produsul „{$p['nume']}” a fost salvat.");
        redirectioneaza('admin/produse.php');
    }
}

$titluPagina = $id ? 'Editează produsul' : 'Produs nou';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_meniu.php';

/** Mesajul de eroare pentru un câmp (dacă există) */
function eroareCamp(array $erori, string $camp): string
{
    return isset($erori[$camp]) ? '<p class="camp__eroare">' . e($erori[$camp]) . '</p>' : '';
}
?>

<nav class="fir-ariadna" aria-label="Fir de navigare">
    <a href="<?= e(url('admin/produse.php')) ?>">Produse</a> › <span aria-current="page"><?= e($titluPagina) ?></span>
</nav>
<h1><?= e($titluPagina) ?></h1>

<?php if ($erori): ?>
    <p class="mesaj mesaj--eroare" role="alert">Produsul nu a fost salvat. Corectează câmpurile marcate.</p>
<?php endif; ?>

<!-- enctype="multipart/form-data" este OBLIGATORIU pentru a trimite fișiere -->
<form class="card form-admin" method="post" enctype="multipart/form-data"
      action="<?= e(url('admin/produs_editare.php' . ($id ? '?id=' . $id : ''))) ?>">
    <?= campCsrf() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= IMAGINE_MAX_OCTETI ?>">

    <div class="campuri-2">
        <div class="camp">
            <label for="nume">Nume produs</label>
            <input id="nume" name="nume" value="<?= e($p['nume']) ?>" required maxlength="150">
            <?= eroareCamp($erori, 'nume') ?>
        </div>
        <div class="camp">
            <label for="categorie_id">Categorie</label>
            <select id="categorie_id" name="categorie_id" required>
                <option value="">— alege —</option>
                <?php foreach (categoriiMeniu() as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$p['categorie_id'] === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['nume']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= eroareCamp($erori, 'categorie_id') ?>
        </div>
    </div>

    <div class="campuri-3">
        <div class="camp">
            <label for="pret">Preț (RON, cu TVA)</label>
            <input id="pret" name="pret" inputmode="decimal" value="<?= e((string)$p['pret']) ?>" required>
            <?= eroareCamp($erori, 'pret') ?>
        </div>
        <div class="camp">
            <label for="gramaj">Gramaj / cantitate</label>
            <input id="gramaj" name="gramaj" value="<?= e((string)$p['gramaj']) ?>" maxlength="50" placeholder="ex.: 250 g">
            <?= eroareCamp($erori, 'gramaj') ?>
        </div>
        <div class="camp">
            <label for="stoc">Stoc (bucăți)</label>
            <input id="stoc" name="stoc" type="number" min="0" max="100000" value="<?= e((string)$p['stoc']) ?>" required>
            <?= eroareCamp($erori, 'stoc') ?>
        </div>
    </div>

    <div class="camp">
        <label for="descriere">Descriere</label>
        <textarea id="descriere" name="descriere" rows="4"><?= e((string)$p['descriere']) ?></textarea>
    </div>

    <div class="camp camp-imagine">
        <img src="<?= e(imagineProdus($p['imagine'])) ?>" alt="Imaginea actuală" width="96" height="96" class="miniatura miniatura--mare">
        <div>
            <label for="imagine">Imagine <?= $p['imagine'] ? '(lasă gol pentru a o păstra pe cea actuală)' : '' ?></label>
            <input id="imagine" name="imagine" type="file" accept="image/jpeg,image/png,image/webp">
            <p class="nota">JPEG, PNG sau WebP, maximum 2 MB. Se redimensionează automat la 800 px și se salvează ca WebP.</p>
            <?= eroareCamp($erori, 'imagine') ?>
        </div>
    </div>

    <div class="camp camp-bifa">
        <input id="activ" name="activ" type="checkbox" <?= $p['activ'] ? 'checked' : '' ?>>
        <label for="activ">Activ (vizibil în catalog)</label>
    </div>

    <fieldset class="atribute-cafea">
        <legend>
            <span class="camp-bifa">
                <input id="e_cafea" name="e_cafea" type="checkbox" <?= $p['e_cafea'] ? 'checked' : '' ?> data-arata="#atribute">
                <label for="e_cafea">Produs de cafea — atribute folosite de quiz</label>
            </span>
        </legend>
        <div id="atribute">
            <?= eroareCamp($erori, 'atribute') ?>
            <div class="campuri-3">
                <?php foreach (['intensitate' => 'Intensitate', 'acid' => 'Aciditate', 'amar' => 'Amăreală'] as $c => $et): ?>
                    <div class="camp">
                        <label for="<?= $c ?>"><?= $et ?> (1–5)</label>
                        <input id="<?= $c ?>" name="<?= $c ?>" type="number" min="1" max="5" value="<?= e((string)$p[$c]) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="campuri-3">
                <div class="camp">
                    <label for="origine">Origine</label>
                    <input id="origine" name="origine" value="<?= e((string)$p['origine']) ?>" maxlength="100">
                </div>
                <div class="camp">
                    <label for="prajire">Prăjire</label>
                    <select id="prajire" name="prajire">
                        <?php foreach ($prajiri as $pr): ?>
                            <option <?= $pr === $p['prajire'] ? 'selected' : '' ?>><?= e($pr) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="camp">
                    <label for="aparat">Recomandată pentru</label>
                    <select id="aparat" name="aparat">
                        <?php foreach ($aparate as $val => $et): ?>
                            <option value="<?= e($val) ?>" <?= $val === $p['aparat'] ? 'selected' : '' ?>><?= e($et) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </fieldset>

    <div class="butoane-form">
        <button class="buton" type="submit">Salvează produsul</button>
        <a class="buton buton--contur" href="<?= e(url('admin/produse.php')) ?>">Renunță</a>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>

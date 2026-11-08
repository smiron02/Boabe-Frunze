<?php
/**
 * Catalogul de produse, cu filtrare pe categorie și ordonare.
 *
 * Parametri GET (opționali):
 *   ?categorie=2        — doar produsele din categoria cu id-ul 2
 *   &ordonare=pret_asc  — una dintre cheile din $ordonari (mai jos)
 */

require_once __DIR__ . '/includes/functii.php';

// ---------- 1. Citirea și validarea parametrilor ----------

// filter_input întoarce un int valid sau false/null => nu ajunge text arbitrar în cod
$categorieId = filter_input(INPUT_GET, 'categorie', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

// ORDER BY nu poate primi parametri în prepared statements, de aceea folosim
// o LISTĂ ALBĂ: utilizatorul alege doar o cheie, SQL-ul vine din codul nostru.
$ordonari = [
    'recomandate' => ['eticheta' => 'Recomandate',          'sql' => 'p.id ASC'],
    // Ordonăm după prețul FINAL (cu reducere), nu după cel din catalog
    'pret_asc'    => ['eticheta' => 'Preț crescător',       'sql' => 'pret_final ASC'],
    'pret_desc'   => ['eticheta' => 'Preț descrescător',    'sql' => 'pret_final DESC'],
    'reduceri'    => ['eticheta' => 'Reduceri',             'sql' => 'procent_promo IS NULL, procent_promo DESC'],
    'nume'        => ['eticheta' => 'Nume (A–Z)',           'sql' => 'p.nume ASC'],
];
$ordonare = $_GET['ordonare'] ?? 'recomandate';
if (!is_string($ordonare) || !isset($ordonari[$ordonare])) {
    $ordonare = 'recomandate';
}

// ---------- 2. Categoria selectată (dacă există) ----------

$categorie = null;
if ($categorieId !== null) {
    $stmt = db()->prepare('SELECT id, nume, descriere FROM categorii WHERE id = :id');
    $stmt->execute(['id' => $categorieId]);
    $categorie = $stmt->fetch() ?: pagina404('Categoria căutată nu există.');
}

// ---------- 3. Produsele ----------

// Interogarea interioară aduce produsele + procentul promoției active (SQL_PROMOTIE).
// Cea exterioară calculează prețul final, ca să putem ordona după el.
$filtru = '';
$parametri = [];
if ($categorie) {
    $filtru = ' AND p.categorie_id = :categorie';
    $parametri['categorie'] = $categorie['id'];
}
$sql = 'SELECT p.*, ROUND(p.pret * (100 - COALESCE(p.procent_promo, 0)) / 100, 2) AS pret_final
          FROM (SELECT p.id, p.nume, p.pret, p.gramaj, p.imagine, p.stoc,
                       c.nume AS categorie, ' . SQL_PROMOTIE . '
                  FROM produse p
                  JOIN categorii c ON c.id = p.categorie_id
                 WHERE p.activ = 1' . $filtru . ') AS p
         ORDER BY (p.stoc = 0), ' . $ordonari[$ordonare]['sql'] . ', p.id';
// (p.stoc = 0) este 0 pentru produsele în stoc și 1 pentru cele epuizate → epuizatele la final

$stmt = db()->prepare($sql);
$stmt->execute($parametri);
$produse = $stmt->fetchAll();

// ---------- 4. Afișarea ----------

$titluPagina     = $categorie ? $categorie['nume'] : 'Catalog';
$descrierePagina = $categorie
    ? ($categorie['descriere'] ?: $categorie['nume'] . ' — ' . NUME_MAGAZIN)
    : 'Toate produsele: cafea boabe și măcinată, ceaiuri și accesorii.';
require __DIR__ . '/includes/header.php';
?>

<h1><?= e($titluPagina) ?></h1>
<?php if ($categorie && $categorie['descriere']): ?>
    <p class="intro"><?= e($categorie['descriere']) ?></p>
<?php endif; ?>

<div class="catalog-bara">
    <!-- Filtrul pe categorii: simple linkuri, funcționează și fără JavaScript -->
    <nav class="filtre" aria-label="Filtrează după categorie">
        <a class="filtru" href="<?= e(url('catalog.php?ordonare=' . $ordonare)) ?>"
           <?= $categorie ? '' : 'aria-current="page"' ?>>Toate</a>
        <?php foreach (categoriiMeniu() as $cat): ?>
            <a class="filtru" href="<?= e(url('catalog.php?categorie=' . $cat['id'] . '&ordonare=' . $ordonare)) ?>"
               <?= $categorie && (int)$categorie['id'] === (int)$cat['id'] ? 'aria-current="page"' : '' ?>>
                <?= e($cat['nume']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Ordonarea: formular GET; main.js îl trimite automat la schimbare -->
    <form class="ordonare" method="get" action="<?= e(url('catalog.php')) ?>">
        <?php if ($categorie): ?>
            <input type="hidden" name="categorie" value="<?= (int)$categorie['id'] ?>">
        <?php endif; ?>
        <label for="ordonare">Ordonează:</label>
        <select id="ordonare" name="ordonare" data-trimite-automat>
            <?php foreach ($ordonari as $cheie => $opt): ?>
                <option value="<?= e($cheie) ?>" <?= $cheie === $ordonare ? 'selected' : '' ?>><?= e($opt['eticheta']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="buton buton--mic" type="submit">Aplică</button>
    </form>
</div>

<p class="rezultate"><?= count($produse) ?> <?= count($produse) === 1 ? 'produs' : 'produse' ?></p>

<?php if ($produse): ?>
    <!-- Titlu pentru cititoarele de ecran: ordinea h1 → h2 → h3 (cardurile) -->
    <h2 class="vizual-ascuns">Produse</h2>
    <div class="produse-grila">
        <?php foreach ($produse as $i => $produs): ?>
            <?php $incarcareImediata = $i < 4;   // primul rând de produse ?>
            <?php require __DIR__ . '/includes/card_produs.php'; ?>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>Nu există produse în această categorie momentan.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

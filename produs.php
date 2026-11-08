<?php
/**
 * Pagina de detalii a unui produs: produs.php?id=5
 */

require_once __DIR__ . '/includes/functii.php';

// ---------- 1. Validarea id-ului ----------
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    pagina404('Produsul căutat nu există.');
}

// ---------- 2. Produsul + categoria + atributele de cafea ----------
// LEFT JOIN: ceaiurile și accesoriile NU au rând în atribute_cafea,
// iar atunci coloanele a.* vin NULL (produsul se afișează totuși).
$stmt = db()->prepare(
    'SELECT p.*, c.nume AS categorie, c.id AS categorie_id,
            a.intensitate, a.origine, a.prajire, a.acid, a.amar, a.aparat,
            ' . SQL_PROMOTIE . '
       FROM produse p
       JOIN categorii c ON c.id = p.categorie_id
       LEFT JOIN atribute_cafea a ON a.produs_id = p.id
      WHERE p.id = :id AND p.activ = 1'
);
$stmt->execute(['id' => $id]);
$produs = $stmt->fetch();

if (!$produs) {
    pagina404('Produsul căutat nu există sau nu mai este disponibil.');
}

$stoc     = (int)$produs['stoc'];
$procent  = $produs['procent_promo'] !== null ? (int)$produs['procent_promo'] : null;
$eCafea   = $produs['intensitate'] !== null;
$aparate  = ['filtru' => 'Filtru / V60', 'espresso' => 'Espressor', 'ibric' => 'Ibric'];

/**
 * Scală vizuală 1–5 (puncte pline/goale) pentru intensitate, aciditate, amăreală.
 * Textul „3 din 5” este pentru cititoarele de ecran.
 */
function scala(int $valoare): string
{
    $html = '<span class="scala" role="img" aria-label="' . $valoare . ' din 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="scala__punct' . ($i <= $valoare ? ' scala__punct--plin' : '') . '"></span>';
    }
    return $html . '</span>';
}

// ---------- 3. Afișarea ----------
$titluPagina     = $produs['nume'];
$descrierePagina = mb_strimwidth($produs['descriere'] ?? $produs['nume'], 0, 155, '…');
require __DIR__ . '/includes/header.php';
?>

<nav class="fir-ariadna" aria-label="Fir de navigare">
    <a href="<?= e(url('catalog.php')) ?>">Catalog</a> ›
    <a href="<?= e(url('catalog.php?categorie=' . $produs['categorie_id'])) ?>"><?= e($produs['categorie']) ?></a> ›
    <span aria-current="page"><?= e($produs['nume']) ?></span>
</nav>

<article class="produs-detalii">
    <div class="produs-detalii__imagine">
        <!-- Imaginea principală NU are loading="lazy": e vizibilă imediat (mai bine la PageSpeed) -->
        <img src="<?= e(imagineProdus($produs['imagine'])) ?>" alt="<?= e($produs['nume']) ?>"
             width="600" height="600">
    </div>

    <div class="produs-detalii__info">
        <span class="produs-card__categorie"><?= e($produs['categorie']) ?></span>
        <h1><?= e($produs['nume']) ?></h1>

        <?php if ($produs['gramaj']): ?>
            <p class="produs-detalii__gramaj"><?= e($produs['gramaj']) ?></p>
        <?php endif; ?>

        <?php if ($procent): ?>
            <p class="produs-detalii__pret">
                <span class="pret-redus"><?= e(formatPret(pretFinal((float)$produs['pret'], $procent))) ?></span>
                <del class="pret-vechi"><span class="vizual-ascuns">Preț vechi: </span><?= e(formatPret((float)$produs['pret'])) ?></del>
                <span class="insigna-reducere insigna-reducere--inline">−<?= $procent ?>%</span>
            </p>
            <p class="nota">Prețul include TVA. Promoție valabilă până la <?= e(date('d.m.Y', strtotime($produs['promo_pana_la']))) ?> inclusiv.</p>
        <?php else: ?>
            <p class="produs-detalii__pret"><?= e(formatPret((float)$produs['pret'])) ?></p>
            <p class="nota">Prețul include TVA.</p>
        <?php endif; ?>

        <!-- Starea stocului -->
        <?php if ($stoc === 0): ?>
            <p class="stoc stoc--epuizat">Stoc epuizat</p>
        <?php elseif ($stoc <= 5): ?>
            <p class="stoc stoc--putin">Ultimele <?= $stoc ?> bucăți în stoc</p>
        <?php else: ?>
            <p class="stoc stoc--disponibil">În stoc</p>
        <?php endif; ?>

        <!-- Adăugarea în coș: trimite către cos.php (funcțional din etapa 5).
             Cantitatea maximă = stocul; serverul o va verifica oricum din nou. -->
        <form class="adauga-cos" method="post" action="<?= e(url('cos.php')) ?>">
            <?= campCsrf() ?>
            <input type="hidden" name="actiune" value="adauga">
            <input type="hidden" name="produs_id" value="<?= (int)$produs['id'] ?>">
            <!-- După adăugare revenim pe această pagină (verificat cu returSigur()) -->
            <input type="hidden" name="retur" value="<?= e($_SERVER['REQUEST_URI']) ?>">
            <label for="cantitate">Cantitate</label>
            <input id="cantitate" name="cantitate" type="number" value="1" min="1"
                   max="<?= max($stoc, 1) ?>" <?= $stoc === 0 ? 'disabled' : '' ?>>
            <button class="buton" type="submit" <?= $stoc === 0 ? 'disabled' : '' ?>>
                Adaugă în coș
            </button>
        </form>

        <?php if ($produs['descriere']): ?>
            <h2>Descriere</h2>
            <p><?= nl2br(e($produs['descriere'])) ?></p>
        <?php endif; ?>

        <?php if ($eCafea): ?>
            <h2>Caracteristici</h2>
            <dl class="caracteristici">
                <dt>Origine</dt>       <dd><?= e($produs['origine']) ?></dd>
                <dt>Prăjire</dt>       <dd><?= e(ucfirst($produs['prajire'])) ?></dd>
                <dt>Recomandată pentru</dt><dd><?= e($aparate[$produs['aparat']] ?? $produs['aparat']) ?></dd>
                <dt>Intensitate</dt>   <dd><?= scala((int)$produs['intensitate']) ?></dd>
                <dt>Aciditate</dt>     <dd><?= scala((int)$produs['acid']) ?></dd>
                <dt>Amăreală</dt>      <dd><?= scala((int)$produs['amar']) ?></dd>
            </dl>
        <?php endif; ?>
    </div>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>

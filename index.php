<?php
/**
 * Pagina de start: promoțiile active, categoriile și produse recomandate.
 */

$titluPagina     = 'Cafea de specialitate și ceaiuri premium';
$descrierePagina = 'Cafea boabe și măcinată, ceaiuri și accesorii. Fă quiz-ul și află ce cafea ți se potrivește.';
require __DIR__ . '/includes/header.php';

// Produse recomandate: 4 produse active, aflate în stoc, alese aleatoriu
// (RAND() e acceptabil la un catalog mic; la mii de produse ar fi lent).
// Interogarea nu are parametri de la utilizator, deci query() e suficient;
// oriunde apar date de la utilizator folosim prepare() + execute().
$recomandate = db()->query(
    'SELECT p.id, p.nume, p.pret, p.gramaj, p.imagine, p.stoc, c.nume AS categorie, ' . SQL_PROMOTIE . '
       FROM produse p
       JOIN categorii c ON c.id = p.categorie_id
      WHERE p.activ = 1 AND p.stoc > 0
      ORDER BY RAND()
      LIMIT 4'
)->fetchAll();

// Promoțiile active azi (cele mai mari reduceri primele). HAVING filtrează
// după coloana calculată procent_promo: rămân doar produsele cu promoție.
$promotii = db()->query(
    'SELECT p.id, p.nume, p.pret, p.gramaj, p.imagine, p.stoc, c.nume AS categorie, ' . SQL_PROMOTIE . '
       FROM produse p
       JOIN categorii c ON c.id = p.categorie_id
      WHERE p.activ = 1 AND p.stoc > 0
     HAVING procent_promo IS NOT NULL
      ORDER BY procent_promo DESC
      LIMIT 4'
)->fetchAll();

// Pictogramă pentru fiecare categorie (după id)
$iconite = [1 => '🫘', 2 => '☕', 3 => '🍃', 4 => '🫖'];
?>

<section class="erou">
    <h1>Cafea proaspăt prăjită și ceaiuri alese cu grijă</h1>
    <p>Descoperă cafele de specialitate din toată lumea, ceaiuri premium și accesoriile potrivite pentru prepararea lor.</p>
    <div class="butoane">
        <a class="buton" href="<?= e(url('catalog.php')) ?>">Vezi catalogul</a>
        <a class="buton buton--contur" href="<?= e(url('quiz.php')) ?>">Găsește-ți cafeaua ideală</a>
    </div>
</section>

<?php if ($promotii): ?>
<section class="sectiune" aria-labelledby="titlu-promotii">
    <h2 id="titlu-promotii">Promoții</h2>
    <div class="produse-grila">
        <?php foreach ($promotii as $produs): ?>
            <?php require __DIR__ . '/includes/card_produs.php'; ?>
        <?php endforeach; ?>
    </div>
    <p><a href="<?= e(url('catalog.php?ordonare=reduceri')) ?>">Vezi toate reducerile →</a></p>
</section>
<?php endif; ?>

<section class="sectiune" aria-labelledby="titlu-categorii">
    <h2 id="titlu-categorii">Categorii</h2>
    <div class="categorii-grila">
        <?php foreach (categoriiMeniu() as $cat): ?>
            <a class="categorie-card" href="<?= e(url('catalog.php?categorie=' . $cat['id'])) ?>">
                <span class="categorie-card__icon" aria-hidden="true"><?= $iconite[$cat['id']] ?? '•' ?></span>
                <?= e($cat['nume']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="sectiune" aria-labelledby="titlu-recomandate">
    <h2 id="titlu-recomandate">Produse recomandate</h2>
    <div class="produse-grila">
        <?php foreach ($recomandate as $produs): ?>
            <?php require __DIR__ . '/includes/card_produs.php'; ?>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

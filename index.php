<?php
/**
 * Pagina de start (versiune provizorie, etapa 2).
 * Promoțiile se adaugă în etapa 7.
 */

$titluPagina     = 'Cafea de specialitate și ceaiuri premium';
$descrierePagina = 'Cafea boabe și măcinată, ceaiuri și accesorii. Fă quiz-ul și află ce cafea ți se potrivește.';
require __DIR__ . '/includes/header.php';

// Produse recomandate: 4 produse active, aflate în stoc, alese aleatoriu
// (RAND() e acceptabil la un catalog mic; la mii de produse ar fi lent).
// Interogarea nu are parametri de la utilizator, deci query() e suficient;
// oriunde apar date de la utilizator folosim prepare() + execute().
$recomandate = db()->query(
    'SELECT p.id, p.nume, p.pret, p.gramaj, p.imagine, p.stoc, c.nume AS categorie
       FROM produse p
       JOIN categorii c ON c.id = p.categorie_id
      WHERE p.activ = 1 AND p.stoc > 0
      ORDER BY RAND()
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
            <article class="produs-card">
                <a href="<?= e(url('produs.php?id=' . $produs['id'])) ?>" tabindex="-1" aria-hidden="true">
                    <img src="<?= e(imagineProdus($produs['imagine'])) ?>" alt=""
                         width="400" height="400" loading="lazy">
                </a>
                <div class="produs-card__corp">
                    <span class="produs-card__categorie"><?= e($produs['categorie']) ?></span>
                    <h3 class="produs-card__nume">
                        <a href="<?= e(url('produs.php?id=' . $produs['id'])) ?>"><?= e($produs['nume']) ?></a>
                    </h3>
                    <?php if ($produs['gramaj']): ?>
                        <span class="produs-card__gramaj"><?= e($produs['gramaj']) ?></span>
                    <?php endif; ?>
                    <span class="produs-card__pret"><?= e(formatPret((float)$produs['pret'])) ?></span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

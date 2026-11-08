<?php
/**
 * Antetul comun al paginilor: <head>, logo, meniu de navigare.
 *
 * Fiecare pagină setează ÎNAINTE de include:
 *     $titluPagina     = 'Catalog';                       // opțional
 *     $descrierePagina = 'Cafea de specialitate ...';     // opțional (SEO)
 */

require_once __DIR__ . '/functii.php';
pornesteSesiunea();

$titluComplet = isset($titluPagina) ? $titluPagina . ' | ' . NUME_MAGAZIN : NUME_MAGAZIN;
$descriere    = $descrierePagina ?? 'Cafea de specialitate și ceaiuri premium, livrate în toată România.';

// Pagina curentă (ex.: „catalog.php”) — pentru a evidenția linkul activ
$paginaCurenta = basename($_SERVER['SCRIPT_NAME']);
$categorieActiva = (int)($_GET['categorie'] ?? 0);
$utilizator = utilizatorCurent();
$nrCos = numarProduseCos();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titluComplet) ?></title>
    <meta name="description" content="<?= e($descriere) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <script src="<?= e(url('assets/js/main.js')) ?>" defer></script>
</head>
<body>
<a class="sari-la-continut" href="#continut">Sari la conținut</a>

<header class="antet">
    <div class="container antet__interior">
        <a class="logo" href="<?= e(url('index.php')) ?>">
            <span class="logo__icon" aria-hidden="true">☕</span>
            <span><?= e(NUME_MAGAZIN) ?></span>
        </a>

        <!-- Butonul „hamburger” apare doar pe ecrane mici (vezi CSS + main.js) -->
        <button class="meniu-buton" type="button" aria-expanded="false" aria-controls="meniu-principal">
            <span class="meniu-buton__linii" aria-hidden="true"></span>
            <span class="vizual-ascuns">Deschide meniul</span>
        </button>

        <nav id="meniu-principal" class="meniu" aria-label="Meniu principal">
            <ul class="meniu__lista">
                <li><a href="<?= e(url('index.php')) ?>"<?= activ('index.php', $paginaCurenta) ?>>Acasă</a></li>

                <li class="meniu__are-submeniu">
                    <a href="<?= e(url('catalog.php')) ?>"<?= activ('catalog.php', $paginaCurenta) ?>>Catalog</a>
                    <ul class="submeniu">
                        <?php foreach (categoriiMeniu() as $cat): ?>
                            <li>
                                <a href="<?= e(url('catalog.php?categorie=' . $cat['id'])) ?>"
                                   <?= $paginaCurenta === 'catalog.php' && $categorieActiva === (int)$cat['id'] ? 'aria-current="page"' : '' ?>>
                                    <?= e($cat['nume']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <li><a href="<?= e(url('quiz.php')) ?>"<?= activ('quiz.php', $paginaCurenta) ?>>Găsește-ți cafeaua</a></li>

                <?php if ($utilizator): ?>
                    <?php if ($utilizator['rol'] === 'admin'): ?>
                        <li><a href="<?= e(url('admin/index.php')) ?>">Administrare</a></li>
                    <?php endif; ?>
                    <li><a href="<?= e(url('cont.php')) ?>"<?= activ('cont.php', $paginaCurenta) ?>>Contul meu</a></li>
                    <li>
                        <!-- Deconectarea se face prin POST + CSRF: un simplu link (GET)
                             ar putea fi declanșat de pe alt site (ex.: <img src=".../logout.php">) -->
                        <form method="post" action="<?= e(url('logout.php')) ?>">
                            <?= campCsrf() ?>
                            <button type="submit" class="meniu__buton-link">Ieșire</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a href="<?= e(url('login.php')) ?>"<?= activ('login.php', $paginaCurenta) ?>>Autentificare</a></li>
                <?php endif; ?>

                <li>
                    <a class="meniu__cos" href="<?= e(url('cos.php')) ?>"<?= activ('cos.php', $paginaCurenta) ?>>
                        Coș <span class="insigna" aria-label="<?= $nrCos ?> produse în coș"><?= $nrCos ?></span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</header>

<main id="continut" class="container continut">
<?= afiseazaFlash() ?>

<?php
/**
 * Cardul unui produs din liste (pagina de start, catalog).
 * Se include într-un foreach care definește $produs cu cheile:
 *   id, nume, pret, gramaj, imagine, stoc, categorie
 */
$linkProdus = url('produs.php?id=' . (int)$produs['id']);
?>
<article class="produs-card<?= (int)$produs['stoc'] === 0 ? ' produs-card--epuizat' : '' ?>">
    <!-- Imaginea e decorativă (alt=""), numele produsului e linkul principal -->
    <a href="<?= e($linkProdus) ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e(imagineProdus($produs['imagine'])) ?>" alt=""
             width="400" height="400" loading="lazy">
    </a>
    <div class="produs-card__corp">
        <span class="produs-card__categorie"><?= e($produs['categorie']) ?></span>
        <h3 class="produs-card__nume">
            <a href="<?= e($linkProdus) ?>"><?= e($produs['nume']) ?></a>
        </h3>
        <?php if ($produs['gramaj']): ?>
            <span class="produs-card__gramaj"><?= e($produs['gramaj']) ?></span>
        <?php endif; ?>
        <span class="produs-card__pret"><?= e(formatPret((float)$produs['pret'])) ?></span>
        <?php if ((int)$produs['stoc'] === 0): ?>
            <span class="stoc-epuizat">Stoc epuizat</span>
        <?php endif; ?>
    </div>
</article>

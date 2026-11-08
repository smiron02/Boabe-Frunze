<?php
/**
 * Cardul unui produs din liste (pagina de start, catalog).
 * Se include într-un foreach care definește $produs cu cheile:
 *   id, nume, pret, gramaj, imagine, stoc, categorie, procent_promo (din SQL_PROMOTIE)
 */
$linkProdus = url('produs.php?id=' . (int)$produs['id']);
$procent    = $produs['procent_promo'] !== null ? (int)$produs['procent_promo'] : null;
?>
<article class="produs-card<?= (int)$produs['stoc'] === 0 ? ' produs-card--epuizat' : '' ?>">
    <!-- Imaginea e decorativă (alt=""), numele produsului e linkul principal -->
    <a class="produs-card__imagine" href="<?= e($linkProdus) ?>" tabindex="-1" aria-hidden="true">
        <?php if ($procent): ?><span class="insigna-reducere">−<?= $procent ?>%</span><?php endif; ?>
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
        <span class="produs-card__pret">
            <?php if ($procent): ?>
                <del class="pret-vechi"><span class="vizual-ascuns">Preț vechi: </span><?= e(formatPret((float)$produs['pret'])) ?></del>
                <span class="pret-redus"><?= e(formatPret(pretFinal((float)$produs['pret'], $procent))) ?></span>
            <?php else: ?>
                <?= e(formatPret((float)$produs['pret'])) ?>
            <?php endif; ?>
        </span>
        <?php if ((int)$produs['stoc'] === 0): ?>
            <span class="stoc-epuizat">Stoc epuizat</span>
        <?php endif; ?>
    </div>
</article>

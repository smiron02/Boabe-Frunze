<?php
/**
 * Subsolul comun al paginilor. Închide <main> deschis în header.php.
 */
?>
</main>

<footer class="subsol">
    <div class="container subsol__grila">
        <section>
            <h2 class="subsol__titlu"><?= e(NUME_MAGAZIN) ?></h2>
            <p>Cafea de specialitate și ceaiuri premium, alese cu grijă și livrate în toată România.</p>
        </section>

        <section>
            <h2 class="subsol__titlu">Magazin</h2>
            <ul class="subsol__lista">
                <?php foreach (categoriiMeniu() as $cat): ?>
                    <li><a href="<?= e(url('catalog.php?categorie=' . $cat['id'])) ?>"><?= e($cat['nume']) ?></a></li>
                <?php endforeach; ?>
                <li><a href="<?= e(url('quiz.php')) ?>">Găsește-ți cafeaua</a></li>
            </ul>
        </section>

        <section>
            <h2 class="subsol__titlu">Contact</h2>
            <address>
                <?= e(FIRMA_DENUMIRE) ?><br>
                <?= e(FIRMA_ADRESA) ?><br>
                CUI: <?= e(FIRMA_CUI) ?>
            </address>
        </section>
    </div>

    <p class="container subsol__copyright">
        &copy; <?= date('Y') ?> <?= e(NUME_MAGAZIN) ?>. Proiect universitar — plăți doar în mod test.
    </p>
</footer>
</body>
</html>

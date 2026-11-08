<?php
/**
 * Quiz „Găsește-ți cafeaua ideală” (modulul de personalizare).
 *
 * GET  quiz.php           → întrebările
 * POST quiz.php           → calculează profilul, îl salvează, redirecționează
 * GET  quiz.php?rezultat  → profilul + primele 3 cafele recomandate
 */

require_once __DIR__ . '/includes/functii.php';
require_once __DIR__ . '/includes/quiz.php';
pornesteSesiunea();

$utilizator   = utilizatorCurent();
$utilizatorId = $utilizator ? (int)$utilizator['id'] : null;
$intrebari    = intrebariQuiz();
$eroare       = '';
$alese        = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();
    $alese = is_array($_POST['raspuns'] ?? null) ? $_POST['raspuns'] : [];
    try {
        $profil = calculeazaProfil($intrebari, $alese);
        salveazaProfil($profil, $utilizatorId);
        redirectioneaza('quiz.php?rezultat');
    } catch (RuntimeException $ex) {
        $eroare = $ex->getMessage();
    }
}

// Rezultatul (după redirecționare sau la o vizită ulterioară)
$profil = isset($_GET['rezultat']) ? profilCurent($utilizatorId) : null;
$recomandari = $profil ? recomandariCafea($profil) : [];

$titluPagina     = $profil ? 'Cafelele potrivite pentru tine' : 'Găsește-ți cafeaua ideală';
$descrierePagina = 'Răspunde la 5 întrebări și află ce cafea ți se potrivește: intensitate, aciditate, amăreală și metoda de preparare.';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$profil): ?>
    <!-- ================= Întrebările ================= -->
    <section class="quiz">
        <h1>Găsește-ți cafeaua ideală</h1>
        <p class="intro">Răspunde la <?= count($intrebari) ?> întrebări scurte și îți recomandăm cafelele care ți se potrivesc cel mai bine.</p>

        <?php if ($eroare): ?>
            <p class="mesaj mesaj--eroare" role="alert"><?= e($eroare) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('quiz.php')) ?>">
            <?= campCsrf() ?>
            <?php $nr = 0; foreach ($intrebari as $idI => $intrebare): $nr++; ?>
                <!-- fieldset + legend: întrebarea este „eticheta” grupului de butoane radio -->
                <fieldset class="quiz__intrebare card">
                    <legend><span class="quiz__nr"><?= $nr ?></span> <?= e($intrebare['text']) ?></legend>
                    <div class="quiz__variante">
                        <?php foreach ($intrebare['raspunsuri'] as $idR => $r): ?>
                            <label class="quiz__varianta">
                                <input type="radio" name="raspuns[<?= (int)$idI ?>]" value="<?= (int)$idR ?>" required
                                       <?= (int)($alese[$idI] ?? 0) === (int)$idR ? 'checked' : '' ?>>
                                <span><?= e($r['text']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>
            <button class="buton" type="submit">Vezi recomandările</button>
            <?php if (!$utilizator): ?>
                <p class="nota"><a href="<?= e(url('login.php?retur=' . rawurlencode(parse_url(URL_BAZA, PHP_URL_PATH) . '/quiz.php'))) ?>">Autentifică-te</a> ca să-ți salvăm profilul de gust în cont.</p>
            <?php endif; ?>
        </form>
    </section>

<?php else: ?>
    <!-- ================= Rezultatul ================= -->
    <h1>Cafelele potrivite pentru tine</h1>

    <section class="card profil-gust" aria-labelledby="titlu-profil">
        <h2 id="titlu-profil">Profilul tău de gust</h2>
        <dl class="caracteristici">
            <dt>Preparare</dt>   <dd><?= e(ucfirst(numeAparat($profil['aparat']))) ?></dd>
            <dt>Intensitate</dt> <dd><?= scala((int)$profil['intensitate']) ?></dd>
            <dt>Aciditate</dt>   <dd><?= scala((int)$profil['acid']) ?></dd>
            <dt>Amăreală</dt>    <dd><?= scala((int)$profil['amar']) ?></dd>
        </dl>
        <p class="nota">
            <?= $utilizator ? 'Profilul este salvat în contul tău' : 'Profilul este păstrat până închizi browserul' ?>.
            <a href="<?= e(url('quiz.php')) ?>">Refă quiz-ul</a>
        </p>
    </section>

    <?php if (!$recomandari): ?>
        <p>Momentan nu avem în stoc cafele pentru <?= e(numeAparat($profil['aparat'])) ?>. <a href="<?= e(url('catalog.php')) ?>">Vezi tot catalogul</a></p>
    <?php else: ?>
        <div class="recomandari">
            <?php foreach ($recomandari as $loc => $p):
                $procent = $p['procent_promo'] !== null ? (int)$p['procent_promo'] : null; ?>
                <article class="recomandare card">
                    <span class="recomandare__potrivire" aria-label="Potrivire <?= (int)$p['potrivire'] ?> la sută">
                        <?= (int)$p['potrivire'] ?>%<small>potrivire</small>
                    </span>
                    <?php if ($loc === 0): ?><span class="recomandare__eticheta">Cea mai bună alegere</span><?php endif; ?>
                    <a href="<?= e(url('produs.php?id=' . $p['id'])) ?>" tabindex="-1" aria-hidden="true">
                        <img src="<?= e(imagineProdus($p['imagine'])) ?>" alt="" width="400" height="400" loading="lazy">
                    </a>
                    <h2 class="produs-card__nume"><a href="<?= e(url('produs.php?id=' . $p['id'])) ?>"><?= e($p['nume']) ?></a></h2>
                    <p class="nota"><?= e($p['origine'] ?? '') ?><?= $p['gramaj'] ? ' · ' . e($p['gramaj']) : '' ?></p>
                    <dl class="caracteristici caracteristici--mici">
                        <dt>Intensitate</dt><dd><?= scala((int)$p['intensitate']) ?></dd>
                        <dt>Aciditate</dt>  <dd><?= scala((int)$p['acid']) ?></dd>
                        <dt>Amăreală</dt>   <dd><?= scala((int)$p['amar']) ?></dd>
                    </dl>
                    <p class="produs-card__pret">
                        <?php if ($procent): ?>
                            <del class="pret-vechi"><span class="vizual-ascuns">Preț vechi: </span><?= e(formatPret((float)$p['pret'])) ?></del>
                            <span class="pret-redus"><?= e(formatPret(pretFinal((float)$p['pret'], $procent))) ?></span>
                        <?php else: ?>
                            <?= e(formatPret((float)$p['pret'])) ?>
                        <?php endif; ?>
                    </p>
                    <form method="post" action="<?= e(url('cos.php')) ?>">
                        <?= campCsrf() ?>
                        <input type="hidden" name="actiune" value="adauga">
                        <input type="hidden" name="produs_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="cantitate" value="1">
                        <input type="hidden" name="retur" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                        <button class="buton buton--lat" type="submit">Adaugă în coș</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="nota">Potrivirea se calculează după diferența dintre profilul tău și caracteristicile fiecărei cafele (100% = identice).</p>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

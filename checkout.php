<?php
/**
 * Finalizarea comenzii: datele de livrare + rezumatul comenzii.
 * La trimitere se creează comanda (status „noua”) și se ajunge pe pagina ei.
 * Plata cu Stripe pornește de pe pagina comenzii (plata.php).
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/comenzi.php';

$utilizator = cereAutentificare();   // doar utilizatorii autentificați pot comanda

$cos = continutCos();
if (!$cos['linii']) {
    seteazaFlash('eroare', 'Coșul este gol.');
    redirectioneaza('cos.php');
}
if ($cos['probleme']) {
    seteazaFlash('eroare', 'Unele produse din coș nu mai sunt disponibile în cantitatea dorită.');
    redirectioneaza('cos.php');
}

$judete = ['Alba', 'Arad', 'Argeș', 'Bacău', 'Bihor', 'Bistrița-Năsăud', 'Botoșani', 'Brașov', 'Brăila',
    'București', 'Buzău', 'Caraș-Severin', 'Călărași', 'Cluj', 'Constanța', 'Covasna', 'Dâmbovița', 'Dolj',
    'Galați', 'Giurgiu', 'Gorj', 'Harghita', 'Hunedoara', 'Ialomița', 'Iași', 'Ilfov', 'Maramureș',
    'Mehedinți', 'Mureș', 'Neamț', 'Olt', 'Prahova', 'Satu Mare', 'Sălaj', 'Sibiu', 'Suceava', 'Teleorman',
    'Timiș', 'Tulcea', 'Vaslui', 'Vâlcea', 'Vrancea'];

// Valorile formularului (precompletăm numele din cont)
$date  = ['nume' => $utilizator['nume'], 'telefon' => '', 'adresa' => '', 'oras' => '', 'judet' => '', 'cod' => ''];
$erori = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();
    foreach ($date as $camp => $_) {
        $date[$camp] = trim((string)($_POST[$camp] ?? ''));
    }

    // ---------- Validare ----------
    if (mb_strlen($date['nume']) < 2 || mb_strlen($date['nume']) > 100) {
        $erori['nume'] = 'Introdu numele destinatarului.';
    }
    $telefon = preg_replace('/[\s.\-]/', '', $date['telefon']);    // fără spații, puncte, cratime
    if (!preg_match('/^(\+40|0)7\d{8}$|^(\+40|0)[23]\d{8}$/', $telefon)) {
        $erori['telefon'] = 'Introdu un număr de telefon românesc valid (ex.: 0722 123 456).';
    }
    if (mb_strlen($date['adresa']) < 5 || mb_strlen($date['adresa']) > 200) {
        $erori['adresa'] = 'Introdu strada, numărul și, dacă e cazul, blocul și apartamentul.';
    }
    if (mb_strlen($date['oras']) < 2 || mb_strlen($date['oras']) > 100) {
        $erori['oras'] = 'Introdu localitatea.';
    }
    if (!in_array($date['judet'], $judete, true)) {
        $erori['judet'] = 'Alege județul.';
    }
    if (!preg_match('/^\d{6}$/', $date['cod'])) {
        $erori['cod'] = 'Codul poștal are 6 cifre.';
    }

    if (!$erori) {
        // Adresa completă se salvează ca text în comenzi.adresa
        $adresa = $date['nume'] . "\n"
            . 'Telefon: ' . $telefon . "\n"
            . $date['adresa'] . "\n"
            . $date['oras'] . ', jud. ' . $date['judet'] . ', cod poștal ' . $date['cod'];

        try {
            $comandaId = plaseazaComanda($utilizator['id'], $adresa);
            seteazaFlash('succes', "Comanda nr. $comandaId a fost înregistrată.");
            redirectioneaza('comanda.php?id=' . $comandaId);
        } catch (RuntimeException $ex) {
            seteazaFlash('eroare', $ex->getMessage());
            redirectioneaza('cos.php');
        }
    }
}

$tva = detaliereTva($cos['linii']);

$titluPagina = 'Finalizează comanda';
require __DIR__ . '/includes/header.php';

/** Atributele de accesibilitate pentru un câmp cu eroare */
function atributeEroare(array $erori, string $camp): string
{
    return isset($erori[$camp]) ? 'aria-invalid="true" aria-describedby="eroare-' . $camp . '"' : '';
}
function mesajEroare(array $erori, string $camp): string
{
    return isset($erori[$camp]) ? '<p class="camp__eroare" id="eroare-' . $camp . '">' . e($erori[$camp]) . '</p>' : '';
}
?>

<h1>Finalizează comanda</h1>

<div class="checkout">
    <section class="card">
        <h2>Date de livrare</h2>
        <?php if ($erori): ?>
            <p class="mesaj mesaj--eroare" role="alert">Te rugăm să corectezi câmpurile marcate.</p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('checkout.php')) ?>" novalidate>
            <?= campCsrf() ?>
            <div class="camp">
                <label for="nume">Nume destinatar</label>
                <input id="nume" name="nume" value="<?= e($date['nume']) ?>" required maxlength="100" autocomplete="name" <?= atributeEroare($erori, 'nume') ?>>
                <?= mesajEroare($erori, 'nume') ?>
            </div>
            <div class="camp">
                <label for="telefon">Telefon</label>
                <input id="telefon" name="telefon" type="tel" value="<?= e($date['telefon']) ?>" required autocomplete="tel" <?= atributeEroare($erori, 'telefon') ?>>
                <?= mesajEroare($erori, 'telefon') ?>
            </div>
            <div class="camp">
                <label for="adresa">Adresă (stradă, număr, bloc, apartament)</label>
                <input id="adresa" name="adresa" value="<?= e($date['adresa']) ?>" required maxlength="200" autocomplete="street-address" <?= atributeEroare($erori, 'adresa') ?>>
                <?= mesajEroare($erori, 'adresa') ?>
            </div>
            <div class="campuri-rand">
                <div class="camp">
                    <label for="oras">Localitate</label>
                    <input id="oras" name="oras" value="<?= e($date['oras']) ?>" required maxlength="100" autocomplete="address-level2" <?= atributeEroare($erori, 'oras') ?>>
                    <?= mesajEroare($erori, 'oras') ?>
                </div>
                <div class="camp">
                    <label for="judet">Județ</label>
                    <select id="judet" name="judet" required <?= atributeEroare($erori, 'judet') ?>>
                        <option value="">— alege —</option>
                        <?php foreach ($judete as $j): ?>
                            <option <?= $j === $date['judet'] ? 'selected' : '' ?>><?= e($j) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= mesajEroare($erori, 'judet') ?>
                </div>
                <div class="camp">
                    <label for="cod">Cod poștal</label>
                    <input id="cod" name="cod" value="<?= e($date['cod']) ?>" required inputmode="numeric" maxlength="6" autocomplete="postal-code" <?= atributeEroare($erori, 'cod') ?>>
                    <?= mesajEroare($erori, 'cod') ?>
                </div>
            </div>

            <button class="buton buton--lat" type="submit">Plasează comanda</button>
            <p class="nota">Plata online se va face pe pagina următoare.</p>
        </form>
    </section>

    <aside class="card checkout__rezumat" aria-labelledby="titlu-rezumat">
        <h2 id="titlu-rezumat">Rezumatul comenzii</h2>
        <ul class="rezumat-lista">
            <?php foreach ($cos['linii'] as $l): ?>
                <li>
                    <span><?= (int)$l['cantitate'] ?> × <?= e($l['nume']) ?></span>
                    <span><?= e(formatPret($l['valoare'])) ?></span>
                </li>
            <?php endforeach; ?>
            <li><span>Livrare</span><span>Gratuită</span></li>
        </ul>
        <?php require __DIR__ . '/includes/sumar_tva.php'; ?>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Coșul de cumpărături.
 *
 * GET  → afișează coșul
 * POST → modifică coșul, apoi redirecționează (Post/Redirect/Get):
 *        actiune=adauga       produs_id, cantitate [, retur]
 *        actiune=actualizeaza produs_id, cantitate (0 = elimină)
 *        actiune=sterge       produs_id
 *
 * Coșul din sesiune: $_SESSION['cos'] = [produs_id => cantitate]
 */

require_once __DIR__ . '/includes/functii.php';
pornesteSesiunea();

// ======================================================================
// 1. Procesarea formularelor (POST)
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();

    $actiune   = $_POST['actiune'] ?? '';
    $produsId  = filter_var($_POST['produs_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $cantitate = filter_var($_POST['cantitate'] ?? 1, FILTER_VALIDATE_INT);

    if (!$produsId) {
        seteazaFlash('eroare', 'Produs invalid.');
        redirectioneaza('cos.php');
    }

    // Citim produsul din baza de date — nu ne bazăm pe nimic venit din formular
    $stmt = db()->prepare('SELECT id, nume, stoc, activ FROM produse WHERE id = :id');
    $stmt->execute(['id' => $produsId]);
    $produs = $stmt->fetch();
    $stoc = $produs ? (int)$produs['stoc'] : 0;

    switch ($actiune) {
        case 'adauga':
            if (!$produs || !$produs['activ'] || $stoc === 0) {
                seteazaFlash('eroare', 'Produsul nu este disponibil.');
                break;
            }
            if ($cantitate === false || $cantitate < 1) {
                $cantitate = 1;
            }
            $existent = $_SESSION['cos'][$produsId] ?? 0;
            $nou = $existent + $cantitate;

            // Nu permitem mai multe bucăți decât sunt în stoc
            if ($nou > $stoc) {
                $nou = $stoc;
                seteazaFlash('eroare', "Stoc limitat: în coș ai acum $nou buc. din „{$produs['nume']}” (maximul disponibil).");
            } else {
                seteazaFlash('succes', "„{$produs['nume']}” a fost adăugat în coș.");
            }
            $_SESSION['cos'][$produsId] = $nou;
            break;

        case 'actualizeaza':
            if (!isset($_SESSION['cos'][$produsId])) {
                break;
            }
            if ($cantitate === false || $cantitate < 1) {
                // Cantitate 0 (sau invalidă) = eliminare din coș
                unset($_SESSION['cos'][$produsId]);
                seteazaFlash('succes', 'Produsul a fost eliminat din coș.');
            } elseif ($produs && $cantitate > $stoc) {
                $_SESSION['cos'][$produsId] = max($stoc, 1);
                seteazaFlash('eroare', "Sunt disponibile doar $stoc bucăți din „{$produs['nume']}”.");
            } else {
                $_SESSION['cos'][$produsId] = $cantitate;
                seteazaFlash('succes', 'Cantitatea a fost actualizată.');
            }
            break;

        case 'sterge':
            unset($_SESSION['cos'][$produsId]);
            seteazaFlash('succes', 'Produsul a fost eliminat din coș.');
            break;

        default:
            seteazaFlash('eroare', 'Acțiune necunoscută.');
    }

    // „Adaugă în coș” de pe pagina produsului: ne întoarcem acolo
    $retur = returSigur($_POST['retur'] ?? null);
    if ($actiune === 'adauga' && $retur) {
        header('Location: ' . $retur);
        exit;
    }
    redirectioneaza('cos.php');
}

// ======================================================================
// 2. Afișarea coșului (GET)
// ======================================================================
$cos = continutCos();

$titluPagina = 'Coșul de cumpărături';
require __DIR__ . '/includes/header.php';
?>

<h1>Coșul de cumpărături</h1>

<?php if (!$cos['linii']): ?>
    <div class="card cos-gol">
        <p>Coșul tău este gol.</p>
        <a class="buton" href="<?= e(url('catalog.php')) ?>">Vezi catalogul</a>
    </div>
<?php else: ?>

    <table class="tabel-cos">
        <caption class="vizual-ascuns">Produsele din coș</caption>
        <thead>
            <tr>
                <th scope="col">Produs</th>
                <th scope="col">Preț unitar</th>
                <th scope="col">Cantitate</th>
                <th scope="col">Valoare</th>
                <th scope="col"><span class="vizual-ascuns">Acțiuni</span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cos['linii'] as $linie): ?>
            <tr class="<?= $linie['problema'] ? 'rand-problema' : '' ?>">
                <td data-eticheta="Produs">
                    <div class="cos-produs">
                        <img src="<?= e(imagineProdus($linie['imagine'])) ?>" alt="" width="64" height="64" loading="lazy">
                        <div>
                            <a href="<?= e(url('produs.php?id=' . $linie['id'])) ?>"><?= e($linie['nume']) ?></a>
                            <?php if ($linie['gramaj']): ?><br><span class="nota"><?= e($linie['gramaj']) ?></span><?php endif; ?>
                            <?php if ($linie['problema']): ?>
                                <p class="camp__eroare"><?= e($linie['problema']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td data-eticheta="Preț unitar"><?= e(formatPret($linie['pret_unitar'])) ?></td>
                <td data-eticheta="Cantitate">
                    <!-- Un formular mic pe fiecare rând: modifică doar acest produs -->
                    <form class="cos-cantitate" method="post" action="<?= e(url('cos.php')) ?>">
                        <?= campCsrf() ?>
                        <input type="hidden" name="actiune" value="actualizeaza">
                        <input type="hidden" name="produs_id" value="<?= (int)$linie['id'] ?>">
                        <label class="vizual-ascuns" for="cant-<?= (int)$linie['id'] ?>">Cantitate pentru <?= e($linie['nume']) ?></label>
                        <input id="cant-<?= (int)$linie['id'] ?>" name="cantitate" type="number"
                               value="<?= (int)$linie['cantitate'] ?>" min="0" max="<?= max((int)$linie['stoc'], 1) ?>">
                        <button class="buton buton--mic buton--contur" type="submit">Actualizează</button>
                    </form>
                </td>
                <td data-eticheta="Valoare"><strong><?= e(formatPret($linie['valoare'])) ?></strong></td>
                <td>
                    <form method="post" action="<?= e(url('cos.php')) ?>">
                        <?= campCsrf() ?>
                        <input type="hidden" name="actiune" value="sterge">
                        <input type="hidden" name="produs_id" value="<?= (int)$linie['id'] ?>">
                        <button class="buton-sterge" type="submit" aria-label="Elimină <?= e($linie['nume']) ?> din coș">
                            Elimină
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="cos-sumar card">
        <p class="cos-sumar__total">
            <span>Total (TVA inclus)</span>
            <strong><?= e(formatPret($cos['total'])) ?></strong>
        </p>
        <p class="nota">Livrare gratuită. Detalierea TVA apare la finalizarea comenzii.</p>

        <?php if ($cos['probleme']): ?>
            <p class="mesaj mesaj--eroare">Rezolvă mai întâi problemele marcate în coș.</p>
        <?php endif; ?>

        <div class="cos-sumar__butoane">
            <a class="buton buton--contur" href="<?= e(url('catalog.php')) ?>">Continuă cumpărăturile</a>
            <?php if ($cos['probleme']): ?>
                <button class="buton" type="button" disabled>Finalizează comanda</button>
            <?php else: ?>
                <a class="buton" href="<?= e(url('checkout.php')) ?>">Finalizează comanda</a>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

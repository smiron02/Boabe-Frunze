<?php
/**
 * Administrare comenzi: lista (cu filtru pe status) și detaliile unei
 * comenzi (?id=5), cu schimbarea statusului.
 *
 * Tranziții permise:
 *   noua    → anulata     (clientul nu a plătit)
 *   platita → expediata   (coletul a plecat)
 * O comandă plătită NU se anulează de aici: ar necesita rambursare în Stripe
 * și readucerea produselor în stoc.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comenzi.php';
cereAdmin();

const TRANZITII = ['noua' => ['anulata'], 'platita' => ['expediata']];

// ======================================================================
// Schimbarea statusului (POST)
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();
    $id    = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $nou   = (string)($_POST['status'] ?? '');

    $stmt = db()->prepare('SELECT status FROM comenzi WHERE id = :id');
    $stmt->execute(['id' => $id ?: 0]);
    $vechi = $stmt->fetchColumn();

    if ($vechi === false) {
        seteazaFlash('eroare', 'Comanda nu există.');
        redirectioneaza('admin/comenzi.php');
    }
    if (!in_array($nou, TRANZITII[$vechi] ?? [], true)) {
        seteazaFlash('eroare', 'Statusul „' . etichetaStatus($vechi) . '” nu poate fi schimbat în „' . etichetaStatus($nou) . '”.');
    } else {
        // WHERE status = :vechi: dacă între timp statusul s-a schimbat (ex.: clientul
        // tocmai a plătit), UPDATE-ul nu modifică nimic și nu anulăm o comandă plătită.
        $stmt = db()->prepare('UPDATE comenzi SET status = :nou WHERE id = :id AND status = :vechi');
        $stmt->execute(['nou' => $nou, 'id' => $id, 'vechi' => $vechi]);
        seteazaFlash(
            $stmt->rowCount() ? 'succes' : 'eroare',
            $stmt->rowCount() ? "Comanda nr. $id: " . etichetaStatus($nou) . '.' : 'Statusul comenzii s-a schimbat între timp. Reîncearcă.'
        );
    }
    redirectioneaza('admin/comenzi.php?id=' . $id);
}

// ======================================================================
// Detaliile unei comenzi
// ======================================================================
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id) {
    $comanda = comandaUtilizator($id, null) ?: pagina404('Comanda nu există.');   // null = orice client
    $factura = facturaComenzii($comanda['id']);
    $tva     = detaliereTva($comanda['linii']);

    $titluPagina = 'Comanda nr. ' . $comanda['id'];
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/admin_meniu.php';
    ?>
    <nav class="fir-ariadna" aria-label="Fir de navigare">
        <a href="<?= e(url('admin/comenzi.php')) ?>">Comenzi</a> › <span aria-current="page">Comanda nr. <?= (int)$comanda['id'] ?></span>
    </nav>
    <h1>Comanda nr. <?= (int)$comanda['id'] ?></h1>
    <p>
        <?= e(date('d.m.Y, H:i', strtotime($comanda['data']))) ?> ·
        <?= e($comanda['client_nume']) ?> (<?= e($comanda['client_email']) ?>) ·
        <span class="status status--<?= e($comanda['status']) ?>"><?= e(etichetaStatus($comanda['status'])) ?></span>
    </p>

    <div class="butoane-form">
        <?php foreach (TRANZITII[$comanda['status']] ?? [] as $s): ?>
            <form method="post" action="<?= e(url('admin/comenzi.php')) ?>"
                  <?= $s === 'anulata' ? 'data-confirma="Anulezi comanda nr. ' . (int)$comanda['id'] . '?"' : '' ?>>
                <?= campCsrf() ?>
                <input type="hidden" name="id" value="<?= (int)$comanda['id'] ?>">
                <input type="hidden" name="status" value="<?= e($s) ?>">
                <button class="buton <?= $s === 'anulata' ? 'buton--contur' : '' ?>" type="submit">
                    <?= $s === 'expediata' ? 'Marchează ca expediată' : 'Anulează comanda' ?>
                </button>
            </form>
        <?php endforeach; ?>
        <?php if ($factura): ?>
            <a class="buton buton--contur" href="<?= e(url('factura.php?comanda=' . $comanda['id'])) ?>">Factura <?= e($factura['serie_numar']) ?></a>
        <?php endif; ?>
    </div>

    <div class="checkout" style="margin-top: 24px">
        <section class="card">
            <h2>Produse</h2>
            <table class="tabel-simplu">
                <thead><tr><th scope="col">Produs</th><th scope="col">Preț unitar</th><th scope="col">Cant.</th><th scope="col">Valoare</th></tr></thead>
                <tbody>
                <?php foreach ($comanda['linii'] as $l): ?>
                    <tr>
                        <td><?= e($l['nume']) ?></td>
                        <td><?= e(formatPret((float)$l['pret_unitar'])) ?></td>
                        <td><?= (int)$l['cantitate'] ?></td>
                        <td><?= e(formatPret((float)$l['valoare'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php require __DIR__ . '/../includes/sumar_tva.php'; ?>
        </section>
        <aside class="card">
            <h2>Livrare la</h2>
            <address class="adresa"><?= nl2br(e($comanda['adresa'])) ?></address>
            <?php if ($comanda['stripe_id']): ?>
                <h2>Plată</h2>
                <p class="nota">Sesiune Stripe:<br><code><?= e($comanda['stripe_id']) ?></code></p>
            <?php endif; ?>
        </aside>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// ======================================================================
// Lista comenzilor
// ======================================================================
$statusuri = ['noua', 'platita', 'expediata', 'anulata'];
$filtru = in_array($_GET['status'] ?? '', $statusuri, true) ? $_GET['status'] : '';

$sql = 'SELECT c.id, c.data, c.total, c.status, u.nume, u.email, f.serie_numar
          FROM comenzi c
          JOIN utilizatori u ON u.id = c.utilizator_id
          LEFT JOIN facturi f ON f.comanda_id = c.id';
$parametri = [];
if ($filtru) {
    $sql .= ' WHERE c.status = :s';
    $parametri['s'] = $filtru;
}
$stmt = db()->prepare($sql . ' ORDER BY c.data DESC, c.id DESC');
$stmt->execute($parametri);
$comenzi = $stmt->fetchAll();

// Numărul de comenzi pe fiecare status (pentru filtre)
$numar = db()->query('SELECT status, COUNT(*) FROM comenzi GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);

$titluPagina = 'Comenzi';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_meniu.php';
?>

<h1>Comenzi</h1>

<nav class="filtre" aria-label="Filtrează după status">
    <a class="filtru" href="<?= e(url('admin/comenzi.php')) ?>" <?= $filtru ? '' : 'aria-current="page"' ?>>Toate (<?= array_sum($numar) ?>)</a>
    <?php foreach ($statusuri as $s): ?>
        <a class="filtru" href="<?= e(url('admin/comenzi.php?status=' . $s)) ?>" <?= $filtru === $s ? 'aria-current="page"' : '' ?>>
            <?= e(etichetaStatus($s)) ?> (<?= (int)($numar[$s] ?? 0) ?>)
        </a>
    <?php endforeach; ?>
</nav>

<div class="card" style="margin-top: 16px">
<?php if (!$comenzi): ?>
    <p>Nu există comenzi<?= $filtru ? ' cu acest status' : '' ?>.</p>
<?php else: ?>
    <table class="tabel-simplu">
        <thead>
            <tr><th scope="col">Nr.</th><th scope="col">Data</th><th scope="col">Client</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col">Factură</th></tr>
        </thead>
        <tbody>
        <?php foreach ($comenzi as $c): ?>
            <tr>
                <td><a href="<?= e(url('admin/comenzi.php?id=' . $c['id'])) ?>">#<?= (int)$c['id'] ?></a></td>
                <td><?= e(date('d.m.Y H:i', strtotime($c['data']))) ?></td>
                <td><?= e($c['nume']) ?><br><span class="nota"><?= e($c['email']) ?></span></td>
                <td><?= e(formatPret((float)$c['total'])) ?></td>
                <td><span class="status status--<?= e($c['status']) ?>"><?= e(etichetaStatus($c['status'])) ?></span></td>
                <td><?= $c['serie_numar'] ? '<a href="' . e(url('factura.php?comanda=' . $c['id'])) . '">' . e($c['serie_numar']) . '</a>' : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

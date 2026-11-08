<?php
/**
 * Detaliile unei comenzi: comanda.php?id=5
 * Fiecare client își vede DOAR propriile comenzi.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/comenzi.php';

$utilizator = cereAutentificare();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
// WHERE id = :id AND utilizator_id = :u  →  comanda altcuiva = „nu există” (404)
$comanda = $id ? comandaUtilizator($id, $utilizator['id']) : null;
if (!$comanda) {
    pagina404('Comanda nu există.');
}

// Întoarcere de pe pagina Stripe prin „Înapoi” / anulare
if (($_GET['plata'] ?? '') === 'anulata' && $comanda['status'] === 'noua') {
    seteazaFlash('eroare', 'Plata a fost anulată. Comanda te așteaptă — poți încerca din nou oricând.');
    redirectioneaza('comanda.php?id=' . $comanda['id']);
}

$tva     = detaliereTva($comanda['linii']);
$factura = facturaComenzii($comanda['id']);

$titluPagina = 'Comanda nr. ' . $comanda['id'];
require __DIR__ . '/includes/header.php';
?>

<nav class="fir-ariadna" aria-label="Fir de navigare">
    <a href="<?= e(url('cont.php')) ?>">Contul meu</a> › <span aria-current="page">Comanda nr. <?= (int)$comanda['id'] ?></span>
</nav>

<h1>Comanda nr. <?= (int)$comanda['id'] ?></h1>
<p>
    Plasată la <?= e(date('d.m.Y, H:i', strtotime($comanda['data']))) ?> ·
    <span class="status status--<?= e($comanda['status']) ?>"><?= e(etichetaStatus($comanda['status'])) ?></span>
</p>

<?php if ($factura): ?>
    <p><a class="buton buton--contur" href="<?= e(url('factura.php?comanda=' . $comanda['id'])) ?>">Vezi factura <?= e($factura['serie_numar']) ?></a></p>
<?php endif; ?>

<?php if ($comanda['status'] === 'noua'): ?>
    <div class="card card--atentie">
        <h2>Plata</h2>
        <p>Total de plată: <strong><?= e(formatPret((float)$comanda['total'])) ?></strong></p>
        <!-- POST + CSRF către plata.php, care creează sesiunea Stripe Checkout -->
        <form method="post" action="<?= e(url('plata.php')) ?>">
            <?= campCsrf() ?>
            <input type="hidden" name="comanda_id" value="<?= (int)$comanda['id'] ?>">
            <button class="buton" type="submit">Plătește cu cardul</button>
        </form>
        <p class="nota">
            Plată securizată prin Stripe, în <strong>mod test</strong> — nu se încasează bani reali.
            Card de test: 4242 4242 4242 4242, orice dată viitoare, orice CVC.
        </p>
    </div>
<?php endif; ?>

<div class="checkout">
    <section class="card">
        <h2>Produse</h2>
        <table class="tabel-simplu">
            <thead>
                <tr><th scope="col">Produs</th><th scope="col">Preț unitar</th><th scope="col">Cant.</th><th scope="col">Valoare</th></tr>
            </thead>
            <tbody>
            <?php foreach ($comanda['linii'] as $l): ?>
                <tr>
                    <td><a href="<?= e(url('produs.php?id=' . $l['produs_id'])) ?>"><?= e($l['nume']) ?></a>
                        <?php if ($l['gramaj']): ?><span class="nota"> · <?= e($l['gramaj']) ?></span><?php endif; ?></td>
                    <td><?= e(formatPret((float)$l['pret_unitar'])) ?></td>
                    <td><?= (int)$l['cantitate'] ?></td>
                    <td><?= e(formatPret((float)$l['valoare'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php require __DIR__ . '/includes/sumar_tva.php'; ?>
        <p class="nota">Livrare gratuită.</p>
    </section>

    <aside class="card">
        <h2>Livrare la</h2>
        <address class="adresa"><?= nl2br(e($comanda['adresa'])) ?></address>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

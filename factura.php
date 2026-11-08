<?php
/**
 * Factura unei comenzi plătite: factura.php?comanda=5
 * Se tipărește / se salvează ca PDF din browser (Ctrl+P → „Salvează ca PDF”);
 * la tipărire, foaia de stil ascunde meniul, subsolul și butoanele.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/comenzi.php';

$utilizator = cereAutentificare();

$id = filter_input(INPUT_GET, 'comanda', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$comanda = $id ? comandaUtilizator($id, $utilizator['id']) : null;   // doar comenzile proprii
$factura = $comanda ? facturaComenzii($comanda['id']) : null;
if (!$factura) {
    pagina404('Factura nu există. Factura se emite după confirmarea plății.');
}

$tva = detaliereTva($comanda['linii']);

$titluPagina     = 'Factura ' . $factura['serie_numar'];
$descrierePagina = 'Factura fiscală pentru comanda nr. ' . $comanda['id'];
require __DIR__ . '/includes/header.php';
?>

<div class="fara-tiparire factura-actiuni">
    <a href="<?= e(url('comanda.php?id=' . $comanda['id'])) ?>">← Înapoi la comandă</a>
    <button class="buton" type="button" data-tipareste>Tipărește / Salvează PDF</button>
</div>

<article class="factura">
    <header class="factura__antet">
        <div>
            <h1>Factură fiscală</h1>
            <p>
                Seria și nr.: <strong><?= e($factura['serie_numar']) ?></strong><br>
                Data emiterii: <?= e(date('d.m.Y', strtotime($factura['emisa_la']))) ?><br>
                Comanda nr.: <?= (int)$comanda['id'] ?>
            </p>
        </div>
        <p class="factura__marca"><?= e(NUME_MAGAZIN) ?></p>
    </header>

    <div class="factura__parti">
        <section>
            <h2>Furnizor</h2>
            <p>
                <strong><?= e(FIRMA_DENUMIRE) ?></strong><br>
                CUI: <?= e(FIRMA_CUI) ?><br>
                Reg. Com.: <?= e(FIRMA_REG_COM) ?><br>
                <?= e(FIRMA_ADRESA) ?><br>
                IBAN: <?= e(FIRMA_IBAN) ?>
            </p>
        </section>
        <section>
            <h2>Client</h2>
            <p>
                <strong><?= e($comanda['client_nume']) ?></strong><br>
                <?= e($comanda['client_email']) ?>
            </p>
            <h2>Adresa de livrare</h2>
            <p><?= nl2br(e($comanda['adresa'])) ?></p>
        </section>
    </div>

    <table class="tabel-factura">
        <thead>
            <tr>
                <th scope="col">Nr.</th>
                <th scope="col">Denumire produs</th>
                <th scope="col">U.M.</th>
                <th scope="col">Cant.</th>
                <th scope="col">Preț unitar<br>(cu TVA)</th>
                <th scope="col">Cotă TVA</th>
                <th scope="col">Valoare<br>fără TVA</th>
                <th scope="col">Valoare<br>TVA</th>
                <th scope="col">Valoare<br>totală</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($comanda['linii'] as $i => $l):
            // Aceeași formulă ca în detaliereTva(), deci liniile adunate = totalurile de jos
            $t = tvaLinie((float)$l['valoare'], (float)$l['cota_tva']); ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($l['nume']) ?><?= $l['gramaj'] ? ' (' . e($l['gramaj']) . ')' : '' ?></td>
                <td>buc.</td>
                <td><?= (int)$l['cantitate'] ?></td>
                <td><?= e(formatPret((float)$l['pret_unitar'])) ?></td>
                <td><?= (int)$l['cota_tva'] ?>%</td>
                <td><?= e(formatPret($t['baza'])) ?></td>
                <td><?= e(formatPret($t['tva'])) ?></td>
                <td><?= e(formatPret((float)$l['valoare'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="factura__totaluri">
        <?php require __DIR__ . '/includes/sumar_tva.php'; ?>
        <p class="factura__total">Total de plată: <strong><?= e(formatPret((float)$factura['total'])) ?></strong></p>
    </div>

    <footer class="factura__subsol">
        <p>Livrare gratuită. Plătită online cu cardul<?= $comanda['stripe_id'] ? ' (Stripe: ' . e($comanda['stripe_id']) . ')' : '' ?>.</p>
        <p>Document generat electronic — proiect universitar, plată în mod test.</p>
    </footer>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Tabelul cu detalierea TVA pe cote (folosit în checkout, comandă, factură).
 * Necesită variabila $tva = detaliereTva(...).
 */
?>
<table class="tabel-tva">
    <caption>Detaliere TVA</caption>
    <thead>
        <tr>
            <th scope="col">Cota TVA</th>
            <th scope="col">Bază impozabilă</th>
            <th scope="col">TVA</th>
            <th scope="col">Total</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($tva['cote'] as $c): ?>
        <tr>
            <td><?= e(rtrim(rtrim(number_format($c['cota'], 2, ',', ''), '0'), ',')) ?>%</td>
            <td><?= e(formatPret($c['baza'])) ?></td>
            <td><?= e(formatPret($c['tva'])) ?></td>
            <td><?= e(formatPret($c['total'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th scope="row">Total</th>
            <td><?= e(formatPret($tva['baza'])) ?></td>
            <td><?= e(formatPret($tva['tva'])) ?></td>
            <td><strong><?= e(formatPret($tva['total'])) ?></strong></td>
        </tr>
    </tfoot>
</table>

<?php
/**
 * Contul utilizatorului: datele profilului
 * și istoricul comenzilor.
 */

require_once __DIR__ . '/includes/auth.php';
$utilizator = cereAutentificare();

// Citim datele proaspete din baza de date (sesiunea are doar strictul necesar)
$stmt = db()->prepare('SELECT nume, email, rol, creat_la FROM utilizatori WHERE id = :id');
$stmt->execute(['id' => $utilizator['id']]);
$profil = $stmt->fetch();

// Istoricul comenzilor, cele mai noi primele. SUM(cantitate) = numărul de bucăți.
$stmt = db()->prepare(
    'SELECT c.id, c.data, c.total, c.status, SUM(cp.cantitate) AS nr_produse
       FROM comenzi c
       JOIN comenzi_produse cp ON cp.comanda_id = c.id
      WHERE c.utilizator_id = :id
      GROUP BY c.id
      ORDER BY c.data DESC, c.id DESC'
);
$stmt->execute(['id' => $utilizator['id']]);
$comenzi = $stmt->fetchAll();

$titluPagina = 'Contul meu';
require __DIR__ . '/includes/header.php';
?>

<h1>Contul meu</h1>

<section class="card">
    <h2>Date personale</h2>
    <dl class="caracteristici">
        <dt>Nume</dt>          <dd><?= e($profil['nume']) ?></dd>
        <dt>Email</dt>         <dd><?= e($profil['email']) ?></dd>
        <dt>Tip cont</dt>      <dd><?= $profil['rol'] === 'admin' ? 'Administrator' : 'Client' ?></dd>
        <dt>Client din</dt>    <dd><?= e(date('d.m.Y', strtotime($profil['creat_la']))) ?></dd>
    </dl>
</section>

<section class="card">
    <h2>Comenzile mele</h2>
    <?php if (!$comenzi): ?>
        <p>Nu ai plasat încă nicio comandă. <a href="<?= e(url('catalog.php')) ?>">Vezi catalogul</a></p>
    <?php else: ?>
        <table class="tabel-simplu">
            <thead>
                <tr><th scope="col">Nr.</th><th scope="col">Data</th><th scope="col">Produse</th><th scope="col">Total</th><th scope="col">Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($comenzi as $c): ?>
                <tr>
                    <td><a href="<?= e(url('comanda.php?id=' . $c['id'])) ?>">#<?= (int)$c['id'] ?></a></td>
                    <td><?= e(date('d.m.Y', strtotime($c['data']))) ?></td>
                    <td><?= (int)$c['nr_produse'] ?></td>
                    <td><?= e(formatPret((float)$c['total'])) ?></td>
                    <td><span class="status status--<?= e($c['status']) ?>"><?= e(etichetaStatus($c['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

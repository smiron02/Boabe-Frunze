<?php
/**
 * Contul utilizatorului: datele profilului
 * și istoricul comenzilor.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/quiz.php';
$utilizator = cereAutentificare();
$profilGust = profilCurent((int)$utilizator['id']);

// Citim datele proaspete din baza de date (sesiunea are doar strictul necesar)
$stmt = db()->prepare('SELECT nume, email, rol, creat_la FROM utilizatori WHERE id = :id');
$stmt->execute(['id' => $utilizator['id']]);
$profil = $stmt->fetch();

// Istoricul comenzilor, cele mai noi primele. SUM(cantitate) = numărul de bucăți.
$stmt = db()->prepare(
    'SELECT c.id, c.data, c.total, c.status, SUM(cp.cantitate) AS nr_produse, f.serie_numar
       FROM comenzi c
       JOIN comenzi_produse cp ON cp.comanda_id = c.id
       LEFT JOIN facturi f ON f.comanda_id = c.id
      WHERE c.utilizator_id = :id
      GROUP BY c.id, f.serie_numar
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
    <h2>Profilul meu de gust</h2>
    <?php if (!$profilGust): ?>
        <p>Nu ai completat încă quiz-ul. <a href="<?= e(url('quiz.php')) ?>">Găsește-ți cafeaua ideală</a></p>
    <?php else: ?>
        <dl class="caracteristici">
            <dt>Preparare</dt>   <dd><?= e(ucfirst(numeAparat($profilGust['aparat']))) ?></dd>
            <dt>Intensitate</dt> <dd><?= scala((int)$profilGust['intensitate']) ?></dd>
            <dt>Aciditate</dt>   <dd><?= scala((int)$profilGust['acid']) ?></dd>
            <dt>Amăreală</dt>    <dd><?= scala((int)$profilGust['amar']) ?></dd>
        </dl>
        <p class="nota">
            <?php if (isset($profilGust['actualizat_la'])): ?>Actualizat la <?= e(date('d.m.Y', strtotime($profilGust['actualizat_la']))) ?>. <?php endif; ?>
            <a href="<?= e(url('quiz.php?rezultat')) ?>">Vezi recomandările</a> · <a href="<?= e(url('quiz.php')) ?>">Refă quiz-ul</a>
        </p>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Comenzile mele</h2>
    <?php if (!$comenzi): ?>
        <p>Nu ai plasat încă nicio comandă. <a href="<?= e(url('catalog.php')) ?>">Vezi catalogul</a></p>
    <?php else: ?>
        <table class="tabel-simplu">
            <thead>
                <tr><th scope="col">Nr.</th><th scope="col">Data</th><th scope="col">Produse</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col">Factură</th></tr>
            </thead>
            <tbody>
            <?php foreach ($comenzi as $c): ?>
                <tr>
                    <td><a href="<?= e(url('comanda.php?id=' . $c['id'])) ?>">#<?= (int)$c['id'] ?></a></td>
                    <td><?= e(date('d.m.Y', strtotime($c['data']))) ?></td>
                    <td><?= (int)$c['nr_produse'] ?></td>
                    <td><?= e(formatPret((float)$c['total'])) ?></td>
                    <td><span class="status status--<?= e($c['status']) ?>"><?= e(etichetaStatus($c['status'])) ?></span></td>
                    <td>
                        <?php if ($c['serie_numar']): ?>
                            <a href="<?= e(url('factura.php?comanda=' . $c['id'])) ?>"><?= e($c['serie_numar']) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

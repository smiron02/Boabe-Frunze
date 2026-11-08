<?php
/**
 * Contul utilizatorului: datele profilului.
 * Istoricul comenzilor se adaugă în etapa 6.
 */

require_once __DIR__ . '/includes/auth.php';
$utilizator = cereAutentificare();

// Citim datele proaspete din baza de date (sesiunea are doar strictul necesar)
$stmt = db()->prepare('SELECT nume, email, rol, creat_la FROM utilizatori WHERE id = :id');
$stmt->execute(['id' => $utilizator['id']]);
$profil = $stmt->fetch();

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
    <p>Nu ai plasat încă nicio comandă. <a href="<?= e(url('catalog.php')) ?>">Vezi catalogul</a></p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

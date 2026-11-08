<?php
/**
 * Panoul de administrare — pagina de start (dashboard) cu indicatori.
 */

require_once __DIR__ . '/../includes/auth.php';
$admin = cereAdmin();   // vizitator → login; client → 403 „Acces interzis”

// Comenzile „încasate” sunt cele plătite sau deja expediate
$incasate = "status IN ('platita', 'expediata')";

$azi = db()->query(
    "SELECT COUNT(*) AS nr, COALESCE(SUM(CASE WHEN $incasate THEN total END), 0) AS incasat
       FROM comenzi WHERE DATE(data) = CURDATE()"
)->fetch();

// Luna curentă: de la prima zi a lunii până azi
$luna = db()->query(
    "SELECT COUNT(*) AS nr, COALESCE(SUM(total), 0) AS incasat
       FROM comenzi
      WHERE $incasate AND data >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
)->fetch();

$neplatite = (int)db()->query("SELECT COUNT(*) FROM comenzi WHERE status = 'noua'")->fetchColumn();
$deExpediat = (int)db()->query("SELECT COUNT(*) FROM comenzi WHERE status = 'platita'")->fetchColumn();

$stocMic = db()->query(
    'SELECT id, nume, stoc FROM produse WHERE activ = 1 AND stoc < 5 ORDER BY stoc, nume'
)->fetchAll();

$titluPagina = 'Administrare';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_meniu.php';
?>

<h1>Panou de administrare</h1>
<p>Bun venit, <?= e($admin['nume']) ?>.</p>

<div class="indicatori">
    <div class="indicator">
        <span class="indicator__eticheta">Comenzi azi</span>
        <strong class="indicator__valoare"><?= (int)$azi['nr'] ?></strong>
        <span class="nota">încasat: <?= e(formatPret((float)$azi['incasat'])) ?></span>
    </div>
    <div class="indicator">
        <span class="indicator__eticheta">Încasări luna aceasta</span>
        <strong class="indicator__valoare"><?= e(formatPret((float)$luna['incasat'])) ?></strong>
        <span class="nota"><?= (int)$luna['nr'] ?> comenzi plătite</span>
    </div>
    <a class="indicator indicator--link" href="<?= e(url('admin/comenzi.php?status=platita')) ?>">
        <span class="indicator__eticheta">De expediat</span>
        <strong class="indicator__valoare"><?= $deExpediat ?></strong>
        <span class="nota">comenzi plătite →</span>
    </a>
    <a class="indicator indicator--link" href="<?= e(url('admin/comenzi.php?status=noua')) ?>">
        <span class="indicator__eticheta">Neplătite</span>
        <strong class="indicator__valoare"><?= $neplatite ?></strong>
        <span class="nota">comenzi noi →</span>
    </a>
</div>

<section class="card">
    <h2>Stoc redus (sub 5 bucăți)</h2>
    <?php if (!$stocMic): ?>
        <p>Toate produsele active au stoc suficient.</p>
    <?php else: ?>
        <table class="tabel-simplu">
            <thead><tr><th scope="col">Produs</th><th scope="col">Stoc</th><th scope="col"><span class="vizual-ascuns">Acțiuni</span></th></tr></thead>
            <tbody>
            <?php foreach ($stocMic as $p): ?>
                <tr>
                    <td><?= e($p['nume']) ?></td>
                    <td><?= (int)$p['stoc'] === 0 ? '<span class="stoc-epuizat">epuizat</span>' : (int)$p['stoc'] ?></td>
                    <td><a href="<?= e(url('admin/produs_editare.php?id=' . $p['id'])) ?>">Editează</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>

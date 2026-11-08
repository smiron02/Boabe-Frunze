<?php
/**
 * Administrare utilizatori: listă cu numărul de comenzi și suma cheltuită,
 * plus schimbarea rolului (client ↔ admin).
 */

require_once __DIR__ . '/../includes/auth.php';
$admin = cereAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();
    $id  = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $rol = $_POST['rol'] ?? '';

    if (!in_array($rol, ['client', 'admin'], true) || !$id) {
        seteazaFlash('eroare', 'Date invalide.');
    } elseif ($id === (int)$admin['id']) {
        // Protecție: altfel ultimul administrator s-ar putea bloca singur pe dinafară
        seteazaFlash('eroare', 'Nu îți poți schimba propriul rol.');
    } else {
        $stmt = db()->prepare('UPDATE utilizatori SET rol = :rol WHERE id = :id');
        $stmt->execute(['rol' => $rol, 'id' => $id]);
        seteazaFlash('succes', $rol === 'admin' ? 'Utilizatorul este acum administrator.' : 'Utilizatorul este acum client.');
    }
    redirectioneaza('admin/utilizatori.php');
}

// Statistici pe utilizator: comenzi plasate și suma comenzilor încasate
$utilizatori = db()->query(
    "SELECT u.id, u.nume, u.email, u.rol, u.creat_la,
            COUNT(c.id) AS nr_comenzi,
            COALESCE(SUM(CASE WHEN c.status IN ('platita', 'expediata') THEN c.total END), 0) AS cheltuit
       FROM utilizatori u
       LEFT JOIN comenzi c ON c.utilizator_id = u.id
      GROUP BY u.id, u.nume, u.email, u.rol, u.creat_la
      ORDER BY u.creat_la DESC, u.id DESC"
)->fetchAll();

$titluPagina = 'Utilizatori';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_meniu.php';
?>

<h1>Utilizatori</h1>
<p class="rezultate"><?= count($utilizatori) ?> conturi</p>

<div class="card">
<table class="tabel-simplu">
    <thead>
        <tr><th scope="col">Nume</th><th scope="col">Înregistrat</th><th scope="col">Comenzi</th><th scope="col">Cheltuit</th><th scope="col">Rol</th></tr>
    </thead>
    <tbody>
    <?php foreach ($utilizatori as $u): ?>
        <tr>
            <td><strong><?= e($u['nume']) ?></strong><br><span class="nota"><?= e($u['email']) ?></span></td>
            <td><?= e(date('d.m.Y', strtotime($u['creat_la']))) ?></td>
            <td><?= (int)$u['nr_comenzi'] ?></td>
            <td><?= e(formatPret((float)$u['cheltuit'])) ?></td>
            <td>
                <?php if ((int)$u['id'] === (int)$admin['id']): ?>
                    Administrator <span class="nota">(tu)</span>
                <?php else: ?>
                    <form class="form-stoc" method="post" action="<?= e(url('admin/utilizatori.php')) ?>">
                        <?= campCsrf() ?>
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <label class="vizual-ascuns" for="rol-<?= (int)$u['id'] ?>">Rol pentru <?= e($u['nume']) ?></label>
                        <select id="rol-<?= (int)$u['id'] ?>" name="rol">
                            <option value="client" <?= $u['rol'] === 'client' ? 'selected' : '' ?>>Client</option>
                            <option value="admin" <?= $u['rol'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                        <button class="buton buton--mic buton--contur" type="submit">Salvează</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

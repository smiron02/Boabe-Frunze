<?php
/**
 * Meniul secundar al panoului de administrare (inclus după header.php).
 */
$paginiAdmin = [
    'index.php'      => 'Panou',
    'produse.php'    => 'Produse și stocuri',
    'categorii.php'  => 'Categorii',
];
$paginaAdmin = basename($_SERVER['SCRIPT_NAME']);
// Formularul de produs ține de secțiunea „Produse”
$sectiuneActiva = $paginaAdmin === 'produs_editare.php' ? 'produse.php' : $paginaAdmin;
?>
<nav class="admin-meniu" aria-label="Meniu administrare">
    <?php foreach ($paginiAdmin as $fisier => $eticheta): ?>
        <a href="<?= e(url('admin/' . $fisier)) ?>"<?= $fisier === $sectiuneActiva ? ' aria-current="page"' : '' ?>><?= e($eticheta) ?></a>
    <?php endforeach; ?>
</nav>

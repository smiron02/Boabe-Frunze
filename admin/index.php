<?php
/**
 * Panoul de administrare — deocamdată doar pagina de start, protejată.
 * Gestionarea produselor, comenzilor etc. se adaugă în etapa 9.
 */

require_once __DIR__ . '/../includes/auth.php';
$admin = cereAdmin();   // vizitator → login; client → 403 „Acces interzis”

$titluPagina = 'Administrare';
require __DIR__ . '/../includes/header.php';
?>

<h1>Panou de administrare</h1>
<p>Bun venit, <?= e($admin['nume']) ?>. Modulele de administrare (produse, comenzi, utilizatori, promoții) vor fi disponibile aici.</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Autentificare și roluri.
 *
 * La începutul unei pagini protejate:
 *     require_once __DIR__ . '/includes/auth.php';
 *     $utilizator = cereAutentificare();   // orice utilizator logat
 *     // sau
 *     $admin = cereAdmin();                // doar rol „admin”
 */

require_once __DIR__ . '/functii.php';
pornesteSesiunea();

/**
 * Salvează utilizatorul în sesiune după login/înregistrare.
 * session_regenerate_id(true) schimbă ID-ul sesiunii: dacă un atacator
 * reușise să-i „impună” victimei un ID cunoscut (session fixation),
 * acel ID devine inutil exact în momentul autentificării.
 */
function autentifica(array $utilizator): void
{
    session_regenerate_id(true);
    $_SESSION['utilizator'] = [
        'id'    => (int)$utilizator['id'],
        'nume'  => $utilizator['nume'],
        'email' => $utilizator['email'],
        'rol'   => $utilizator['rol'],
    ];
}

/**
 * Oprește accesul vizitatorilor neautentificați: îi trimite la login,
 * cu adresa curentă în ?retur=, ca să revină aici după autentificare.
 */
function cereAutentificare(): array
{
    $utilizator = utilizatorCurent();
    if (!$utilizator) {
        seteazaFlash('eroare', 'Trebuie să te autentifici pentru a continua.');
        redirectioneaza('login.php?retur=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    return $utilizator;
}

/**
 * true dacă utilizatorul autentificat este administrator. Rolul se verifică
 * DIN BAZA DE DATE la fiecare cerere (nu doar din sesiune): dacă un admin își
 * pierde rolul, pierde accesul imediat, fără să fie nevoie să se delogheze.
 */
function esteAdmin(): bool
{
    $utilizator = utilizatorCurent();
    if (!$utilizator) {
        return false;
    }
    $stmt = db()->prepare('SELECT rol FROM utilizatori WHERE id = :id');
    $stmt->execute(['id' => $utilizator['id']]);
    return $stmt->fetchColumn() === 'admin';
}

/**
 * Doar pentru administratori: vizitator → login, client → 403.
 */
function cereAdmin(): array
{
    $utilizator = cereAutentificare();
    if (!esteAdmin()) {
        http_response_code(403);
        $titluPagina = 'Acces interzis';
        require __DIR__ . '/header.php';
        echo '<h1>Acces interzis</h1><p>Această pagină este disponibilă doar administratorilor.</p>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $utilizator;
}

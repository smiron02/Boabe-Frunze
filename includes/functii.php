<?php
/**
 * Funcții ajutătoare folosite în tot site-ul.
 * (Se vor adăuga pe parcurs: CSRF, mesaje flash, preț cu promoție etc.)
 */

require_once __DIR__ . '/db.php';

/**
 * Prescurtare pentru htmlspecialchars(): ORICE text afișat în pagină
 * trece prin e(), ca să nu se poată injecta HTML/JavaScript (XSS).
 */
function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Construiește un link absolut pornind de la URL_BAZA din config.
 * Exemplu: url('catalog.php?categorie=2') → http://localhost/ceai-cafea/catalog.php?categorie=2
 */
function url(string $cale = ''): string
{
    return URL_BAZA . '/' . ltrim($cale, '/');
}

/**
 * Pornește sesiunea (o singură dată) cu setări sigure pentru cookie:
 *  - httponly: cookie-ul nu poate fi citit din JavaScript
 *  - samesite=Lax: cookie-ul nu se trimite la cereri POST venite de pe alte site-uri
 *  - secure: doar prin HTTPS, când site-ul rulează pe HTTPS
 */
function pornesteSesiunea(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }
}

/**
 * Coșul se păstrează în sesiune ca tablou: [produs_id => cantitate].
 * Returnează numărul total de bucăți (pentru insigna din meniu).
 */
function numarProduseCos(): int
{
    return array_sum($_SESSION['cos'] ?? []);
}

/**
 * Marchează linkul paginii curente din meniu (aria-current="page"):
 * cititoarele de ecran îl anunță, iar CSS-ul îl evidențiază.
 */
function activ(string $pagina, string $paginaCurenta): string
{
    return $pagina === $paginaCurenta ? ' aria-current="page"' : '';
}

/**
 * Utilizatorul autentificat (setat la login, în etapa 4) sau null.
 */
function utilizatorCurent(): ?array
{
    return $_SESSION['utilizator'] ?? null;
}

/**
 * Categoriile pentru meniu (citite o singură dată pe pagină).
 */
function categoriiMeniu(): array
{
    static $categorii = null;
    if ($categorii === null) {
        $categorii = db()->query('SELECT id, nume FROM categorii ORDER BY id')->fetchAll();
    }
    return $categorii;
}

/**
 * Calea imaginii unui produs. Dacă fișierul nu există încă în
 * assets/img, se afișează o imagine implicită (placeholder.svg).
 */
function imagineProdus(?string $fisier): string
{
    if ($fisier !== null && $fisier !== '' && is_file(__DIR__ . '/../assets/img/' . basename($fisier))) {
        return url('assets/img/' . rawurlencode(basename($fisier)));
    }
    return url('assets/img/placeholder.svg');
}

/**
 * Afișează pagina „Nu a fost găsit” (cod HTTP 404) și oprește scriptul.
 * Folosită când un produs/categorie nu există sau nu este activ.
 */
function pagina404(string $mesaj = 'Pagina căutată nu există.'): never
{
    http_response_code(404);
    $titluPagina = 'Pagina nu a fost găsită';
    require __DIR__ . '/header.php';
    echo '<h1>Nu am găsit pagina</h1><p>' . e($mesaj) . '</p>';
    echo '<p><a class="buton" href="' . e(url('catalog.php')) . '">Înapoi la catalog</a></p>';
    require __DIR__ . '/footer.php';
    exit;
}

// ---------------------------------------------------------------
// Protecție CSRF (Cross-Site Request Forgery)
// Fiecare formular care modifică date conține un token secret, generat
// o dată pe sesiune. Un site străin nu poate cunoaște tokenul, deci nu
// poate trimite formulare „în numele” utilizatorului.
// ---------------------------------------------------------------

/** Tokenul sesiunii curente (se generează la prima utilizare). */
function csrfToken(): string
{
    pornesteSesiunea();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Câmpul ascuns care se pune în fiecare <form method="post">. */
function campCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

/** Se apelează la începutul oricărui script care primește POST. */
function verificaCsrf(): void
{
    $trimis = $_POST['csrf'] ?? '';
    // hash_equals compară în timp constant (nu „scapă” informații prin durată)
    if (!is_string($trimis) || !hash_equals(csrfToken(), $trimis)) {
        http_response_code(403);
        exit('Cererea nu a putut fi verificată (token CSRF invalid). Reîncarcă pagina și încearcă din nou.');
    }
}

// ---------------------------------------------------------------
// Mesaje „flash”: se salvează în sesiune înainte de o redirecționare
// și se afișează O SINGURĂ DATĂ pe pagina următoare (în header.php).
// ---------------------------------------------------------------

/** $tip: 'succes' sau 'eroare' */
function seteazaFlash(string $tip, string $text): void
{
    pornesteSesiunea();
    $_SESSION['flash'][] = ['tip' => $tip, 'text' => $text];
}

function afiseazaFlash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $mesaj) {
        $html .= '<p class="mesaj mesaj--' . e($mesaj['tip']) . '" role="status">' . e($mesaj['text']) . '</p>';
    }
    unset($_SESSION['flash']);   // după afișare, mesajele se șterg
    return $html;
}

/**
 * Redirecționează către o pagină din site și oprește scriptul.
 * Folosit după orice POST reușit (modelul Post/Redirect/Get), ca
 * reîncărcarea paginii (F5) să nu retrimită formularul.
 */
function redirectioneaza(string $cale): never
{
    header('Location: ' . url($cale));
    exit;
}

/**
 * Verifică dacă adresa de întoarcere (?retur=...) este o cale DIN SITE.
 * Fără verificare, cineva ar putea trimite un link de tipul
 * login.php?retur=https://site-fals.ro și ar redirecționa victima
 * după autentificare („open redirect”).
 */
function returSigur(?string $retur): ?string
{
    if (!is_string($retur) || $retur === '') {
        return null;
    }
    $baza = parse_url(URL_BAZA, PHP_URL_PATH) ?? '';   // ex.: „/ceai-cafea”
    $valid = str_starts_with($retur, $baza . '/')        // începe cu folderul site-ului
        && !str_starts_with($retur, '//')                // nu e „//alt-site.ro”
        && !preg_match('/[\\\\\r\n]/', $retur);          // fără backslash / linii noi
    return $valid ? $retur : null;
}

/**
 * Formatează un preț în stil românesc: 1234.5 → „1.234,50 RON”.
 */
function formatPret(float $pret): string
{
    return number_format($pret, 2, ',', '.') . ' ' . MONEDA;
}

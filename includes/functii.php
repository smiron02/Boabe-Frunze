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
 * Formatează un preț în stil românesc: 1234.5 → „1.234,50 RON”.
 */
function formatPret(float $pret): string
{
    return number_format($pret, 2, ',', '.') . ' ' . MONEDA;
}

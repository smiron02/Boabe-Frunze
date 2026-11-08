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
 * Link către un fișier static (CSS/JS/imagine) cu „versiune” în adresă:
 * asset('assets/css/style.css') → .../style.css?v=1731012345
 * Numărul este data ultimei modificări a fișierului. Browserul păstrează
 * fișierul în cache un an (.htaccess), dar când îl modificăm, adresa se
 * schimbă și browserul descarcă automat varianta nouă („cache busting”).
 */
function asset(string $cale): string
{
    $fisier = __DIR__ . '/../' . $cale;
    return url($cale) . (is_file($fisier) ? '?v=' . filemtime($fisier) : '');
}

/**
 * Pornește sesiunea (o singură dată) cu setări sigure pentru cookie:
 *  - httponly: cookie-ul nu poate fi citit din JavaScript
 *  - samesite=Lax: cookie-ul nu se trimite la cereri POST venite de pe alte site-uri
 *  - secure: doar prin HTTPS, când site-ul rulează pe HTTPS. Decidem după
 *    URL_BAZA din config.php, NU după $_SERVER['HTTPS']: pe unele găzduiri
 *    (proxy în față) serverul raportează HTTPS chiar și pentru pagini http://,
 *    iar atunci browserul ar refuza cookie-ul și sesiunea s-ar pierde.
 */
function pornesteSesiunea(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure'   => str_starts_with(URL_BAZA, 'https://'),
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

// ---------------------------------------------------------------
// Promoții
// ---------------------------------------------------------------

/**
 * Fragment SQL (scris de noi, fără date de la utilizator) care adaugă la un
 * SELECT pe tabelul `produse p` două coloane:
 *   procent_promo  — cea mai mare reducere ACTIVĂ AZI (NULL dacă nu există)
 *   promo_pana_la  — ultima zi a acelei promoții
 * O promoție e activă dacă CURDATE() BETWEEN data_start AND data_end.
 */
const SQL_PROMOTIE = "
    (SELECT MAX(pr.procent) FROM promotii pr
      WHERE pr.produs_id = p.id AND CURDATE() BETWEEN pr.data_start AND pr.data_end) AS procent_promo,
    (SELECT pr.data_end FROM promotii pr
      WHERE pr.produs_id = p.id AND CURDATE() BETWEEN pr.data_start AND pr.data_end
      ORDER BY pr.procent DESC, pr.data_end DESC LIMIT 1) AS promo_pana_la";

/**
 * Prețul final după aplicarea reducerii procentuale (rotunjit la bani).
 * Exemplu: pretFinal(49.90, 15) = 42.42
 */
function pretFinal(float $pret, ?int $procent): float
{
    return $procent ? round($pret * (100 - $procent) / 100, 2) : $pret;
}

/**
 * Conținutul coșului, cu date PROASPETE din baza de date.
 *
 * În sesiune ținem doar [produs_id => cantitate]. Numele, prețul și stocul
 * se citesc de fiecare dată din baza de date, deci nimeni nu poate modifica
 * prețul din browser, iar o schimbare de preț/stoc se vede imediat în coș.
 *
 * Returnează:
 *   'linii'    => listă de produse cu cantitate, valoare și eventuala problemă
 *   'total'    => suma valorilor liniilor disponibile
 *   'probleme' => true dacă vreo linie nu poate fi comandată (blochează checkout)
 */
function continutCos(): array
{
    pornesteSesiunea();
    $cos = $_SESSION['cos'] ?? [];
    $rezultat = ['linii' => [], 'total' => 0.0, 'probleme' => false];
    if (!$cos) {
        return $rezultat;
    }

    // Un singur SELECT pentru toate produsele: WHERE id IN (?, ?, ?)
    // Construim doar SEMNELE DE ÎNTREBARE din cod; valorile merg ca parametri.
    $iduri = array_keys($cos);
    $semne = implode(',', array_fill(0, count($iduri), '?'));
    $stmt = db()->prepare(
        "SELECT p.id, p.nume, p.pret, p.gramaj, p.imagine, p.stoc, p.activ,
                c.nume AS categorie, c.cota_tva, " . SQL_PROMOTIE . "
           FROM produse p JOIN categorii c ON c.id = p.categorie_id
          WHERE p.id IN ($semne)"
    );
    $stmt->execute($iduri);
    $produse = [];
    foreach ($stmt->fetchAll() as $p) {
        $produse[(int)$p['id']] = $p;
    }

    foreach ($cos as $id => $cantitate) {
        if (!isset($produse[$id])) {
            unset($_SESSION['cos'][$id]);   // produsul a fost șters între timp
            continue;
        }
        $p = $produse[$id];
        // Prețul final = prețul din catalog minus promoția activă (dacă există)
        $pret = pretFinal((float)$p['pret'], $p['procent_promo'] !== null ? (int)$p['procent_promo'] : null);
        $stoc = (int)$p['stoc'];

        $problema = null;
        if (!$p['activ'] || $stoc === 0) {
            $problema = 'Produsul nu mai este disponibil. Te rugăm să-l elimini din coș.';
        } elseif ($cantitate > $stoc) {
            $problema = "Mai sunt doar $stoc bucăți în stoc. Actualizează cantitatea.";
        }

        $linie = $p + [
            'pret_unitar' => $pret,
            'cantitate'   => $cantitate,
            'valoare'     => round($pret * $cantitate, 2),
            'problema'    => $problema,
        ];
        $rezultat['linii'][] = $linie;

        if ($problema) {
            $rezultat['probleme'] = true;
        } else {
            $rezultat['total'] += $linie['valoare'];
        }
    }
    $rezultat['total'] = round($rezultat['total'], 2);
    return $rezultat;
}

/**
 * Textul afișat pentru fiecare status de comandă.
 */
function etichetaStatus(string $status): string
{
    return [
        'noua'      => 'Nouă — în așteptarea plății',
        'platita'   => 'Plătită',
        'expediata' => 'Expediată',
        'anulata'   => 'Anulată',
    ][$status] ?? $status;
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
 * Scală vizuală 1–5 (puncte pline/goale) pentru intensitate, aciditate, amăreală
 * (pagina de produs, quiz, contul utilizatorului).
 * Textul „3 din 5” este pentru cititoarele de ecran.
 */
function scala(int $valoare): string
{
    $html = '<span class="scala" role="img" aria-label="' . $valoare . ' din 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="scala__punct' . ($i <= $valoare ? ' scala__punct--plin' : '') . '"></span>';
    }
    return $html . '</span>';
}

/**
 * Formatează un preț în stil românesc: 1234.5 → „1.234,50 RON”.
 */
function formatPret(float $pret): string
{
    return number_format($pret, 2, ',', '.') . ' ' . MONEDA;
}

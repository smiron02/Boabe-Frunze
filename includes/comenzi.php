<?php
/**
 * Logica comenzilor: calculul TVA, plasarea comenzii, confirmarea plății.
 * Folosit de checkout.php, comanda.php și (în etapa 8) de plata_succes.php.
 */

require_once __DIR__ . '/functii.php';

/**
 * Detalierea TVA pentru o listă de linii. Fiecare linie trebuie să aibă:
 *   'valoare'  — preț unitar × cantitate, CU TVA inclus
 *   'cota_tva' — ex.: 11.00 sau 21.00
 *
 * Prețurile includ TVA, deci pentru fiecare linie:
 *   baza = valoare / (1 + cota/100)     (rotunjit la 2 zecimale)
 *   tva  = valoare − baza                (astfel baza + tva = valoarea exactă)
 *
 * Rezultat: ['cote' => [11 => [baza, tva, total], 21 => [...]], 'baza', 'tva', 'total']
 */
function tvaLinie(float $valoare, float $cota): array
{
    $baza = round($valoare / (1 + $cota / 100), 2);
    return ['baza' => $baza, 'tva' => round($valoare - $baza, 2)];
}

function detaliereTva(array $linii): array
{
    $rez = ['cote' => [], 'baza' => 0.0, 'tva' => 0.0, 'total' => 0.0];

    foreach ($linii as $l) {
        $valoare = (float)$l['valoare'];
        $cota    = (float)$l['cota_tva'];
        ['baza' => $baza, 'tva' => $tva] = tvaLinie($valoare, $cota);

        $cheie = (string)$cota;   // cheie text: „11.00”, „21.00”
        $rez['cote'][$cheie] ??= ['cota' => $cota, 'baza' => 0.0, 'tva' => 0.0, 'total' => 0.0];
        $rez['cote'][$cheie]['baza']  += $baza;
        $rez['cote'][$cheie]['tva']   += $tva;
        $rez['cote'][$cheie]['total'] += $valoare;

        $rez['baza']  += $baza;
        $rez['tva']   += $tva;
        $rez['total'] += $valoare;
    }

    ksort($rez['cote'], SORT_NUMERIC);
    // Rotunjim sumele finale (adunarea de zecimale în virgulă mobilă poate lăsa „resturi”)
    foreach ($rez['cote'] as &$c) {
        $c['baza'] = round($c['baza'], 2); $c['tva'] = round($c['tva'], 2); $c['total'] = round($c['total'], 2);
    }
    unset($c);
    $rez['baza'] = round($rez['baza'], 2); $rez['tva'] = round($rez['tva'], 2); $rez['total'] = round($rez['total'], 2);
    return $rez;
}

/**
 * Creează comanda (status „noua”) din coșul curent.
 * Totul într-o TRANZACȚIE: fie se salvează comanda cu toate liniile ei,
 * fie (la orice eroare) nu se salvează nimic.
 *
 * Stocul NU se modifică aici — doar la confirmarea plății (confirmaPlata()).
 *
 * @return int ID-ul comenzii create
 */
function plaseazaComanda(int $utilizatorId, string $adresa): int
{
    // Recalculăm coșul din baza de date chiar în momentul plasării
    $cos = continutCos();
    if (!$cos['linii'] || $cos['probleme']) {
        throw new RuntimeException('Coșul este gol sau conține produse indisponibile.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO comenzi (utilizator_id, total, status, adresa) VALUES (:u, :total, 'noua', :adresa)"
        );
        $stmt->execute(['u' => $utilizatorId, 'total' => $cos['total'], 'adresa' => $adresa]);
        $comandaId = (int)$pdo->lastInsertId();

        // Prețul unitar și cota TVA se COPIAZĂ acum („instantaneu”):
        // dacă se schimbă mai târziu în catalog, comanda și factura rămân corecte.
        $stmt = $pdo->prepare(
            'INSERT INTO comenzi_produse (comanda_id, produs_id, cantitate, pret_unitar, cota_tva)
             VALUES (:c, :p, :cant, :pret, :cota)'
        );
        foreach ($cos['linii'] as $l) {
            $stmt->execute([
                'c' => $comandaId, 'p' => $l['id'], 'cant' => $l['cantitate'],
                'pret' => $l['pret_unitar'], 'cota' => $l['cota_tva'],
            ]);
        }

        $pdo->commit();
        return $comandaId;
    } catch (Throwable $ex) {
        $pdo->rollBack();   // anulăm tot ce s-a scris în această tranzacție
        throw $ex;
    }
}

/**
 * Confirmă plata unei comenzi: scade stocul și trece comanda în „platita”.
 * Apelată din butonul temporar de test (etapa 6) și, din etapa 8,
 * după verificarea plății la Stripe.
 *
 * SELECT ... FOR UPDATE blochează rândurile citite până la COMMIT: dacă doi
 * clienți cumpără ultima bucată în același timp, al doilea așteaptă și vede
 * stocul deja scăzut — stocul nu poate ajunge negativ.
 *
 * @return bool true = confirmată acum; false = era deja confirmată/anulată
 * @throws RuntimeException dacă nu mai este stoc suficient
 */
function confirmaPlata(int $comandaId, ?string $stripeId = null): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status FROM comenzi WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $comandaId]);
        $status = $stmt->fetchColumn();

        // Doar o comandă „noua” poate fi confirmată (o a doua confirmare nu
        // trebuie să scadă stocul încă o dată)
        if ($status !== 'noua') {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT cp.produs_id, cp.cantitate, p.nume, p.stoc
               FROM comenzi_produse cp
               JOIN produse p ON p.id = cp.produs_id
              WHERE cp.comanda_id = :id
                FOR UPDATE'
        );
        $stmt->execute(['id' => $comandaId]);
        $linii = $stmt->fetchAll();

        $scade = $pdo->prepare('UPDATE produse SET stoc = stoc - :cant WHERE id = :id');
        foreach ($linii as $l) {
            if ((int)$l['stoc'] < (int)$l['cantitate']) {
                throw new RuntimeException("Stoc insuficient pentru „{$l['nume']}” (disponibil: {$l['stoc']}).");
            }
            $scade->execute(['cant' => $l['cantitate'], 'id' => $l['produs_id']]);
        }

        $stmt = $pdo->prepare(
            "UPDATE comenzi SET status = 'platita', stripe_id = COALESCE(:stripe, stripe_id) WHERE id = :id"
        );
        $stmt->execute(['stripe' => $stripeId, 'id' => $comandaId]);

        // În ACEEAȘI tranzacție emitem factura: plata, stocul și factura
        // se salvează împreună sau deloc.
        emiteFactura($pdo, $comandaId);

        $pdo->commit();
        return true;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

/**
 * Emite factura unei comenzi (apelată din confirmaPlata, în tranzacție).
 *
 * Numărul facturii vine din id-ul AUTO_INCREMENT al rândului: MySQL garantează
 * că e unic și crescător, chiar dacă două facturi se emit în același timp.
 * Seria completă: SERIE-AN-NUMĂR, ex.: BF-2026-00001.
 */
function emiteFactura(PDO $pdo, int $comandaId): string
{
    $stmt = $pdo->prepare(
        'SELECT ROUND(pret_unitar * cantitate, 2) AS valoare, cota_tva
           FROM comenzi_produse WHERE comanda_id = :id'
    );
    $stmt->execute(['id' => $comandaId]);
    $tva = detaliereTva($stmt->fetchAll());

    // 1. Inserăm factura cu o serie provizorie (unică, deoarece comanda_id e unic)...
    $stmt = $pdo->prepare(
        'INSERT INTO facturi (comanda_id, serie_numar, baza, tva, total)
         VALUES (:c, :provizoriu, :baza, :tva, :total)'
    );
    $stmt->execute([
        'c' => $comandaId, 'provizoriu' => 'PROVIZORIU-' . $comandaId,
        'baza' => $tva['baza'], 'tva' => $tva['tva'], 'total' => $tva['total'],
    ]);
    $id = (int)$pdo->lastInsertId();

    // 2. ...apoi o numerotăm definitiv folosind id-ul primit
    $serie = sprintf('%s-%s-%05d', SERIE_FACTURA, date('Y'), $id);
    $pdo->prepare('UPDATE facturi SET serie_numar = :s WHERE id = :id')
        ->execute(['s' => $serie, 'id' => $id]);
    return $serie;
}

/**
 * Factura unei comenzi (sau null dacă nu a fost emisă — comanda neplătită).
 */
function facturaComenzii(int $comandaId): ?array
{
    $stmt = db()->prepare('SELECT * FROM facturi WHERE comanda_id = :id');
    $stmt->execute(['id' => $comandaId]);
    return $stmt->fetch() ?: null;
}

/**
 * O comandă a utilizatorului dat (sau null dacă nu există / nu e a lui),
 * împreună cu liniile ei.
 */
function comandaUtilizator(int $comandaId, int $utilizatorId): ?array
{
    $stmt = db()->prepare(
        'SELECT c.*, u.nume AS client_nume, u.email AS client_email
           FROM comenzi c
           JOIN utilizatori u ON u.id = c.utilizator_id
          WHERE c.id = :id AND c.utilizator_id = :u'
    );
    $stmt->execute(['id' => $comandaId, 'u' => $utilizatorId]);
    $comanda = $stmt->fetch();
    if (!$comanda) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT cp.produs_id, cp.cantitate, cp.pret_unitar, cp.cota_tva,
                ROUND(cp.pret_unitar * cp.cantitate, 2) AS valoare,
                p.nume, p.gramaj, p.imagine
           FROM comenzi_produse cp
           JOIN produse p ON p.id = cp.produs_id
          WHERE cp.comanda_id = :id
          ORDER BY p.nume'
    );
    $stmt->execute(['id' => $comandaId]);
    $comanda['linii'] = $stmt->fetchAll();
    return $comanda;
}

<?php
/**
 * Întoarcerea de la Stripe după plată: plata_succes.php?session_id=cs_test_...
 *
 * NU avem încredere în faptul că browserul a ajuns aici (oricine poate scrie
 * adresa de mână). Cerem sesiunea DIRECT de la Stripe, de pe server, și
 * confirmăm comanda doar dacă Stripe spune că plata e făcută.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/comenzi.php';
require_once __DIR__ . '/includes/stripe.php';

$utilizator = cereAutentificare();

// ---------- 1. ID-ul sesiunii: doar formatul Stripe (cs_test_...) ----------
$sessionId = $_GET['session_id'] ?? '';
if (!is_string($sessionId) || !preg_match('/^cs_test_[A-Za-z0-9]+$/', $sessionId)) {
    pagina404('Sesiunea de plată nu este validă.');
}

// ---------- 2. Comanda acestui utilizator care are această sesiune ----------
$stmt = db()->prepare('SELECT id FROM comenzi WHERE stripe_id = :s AND utilizator_id = :u');
$stmt->execute(['s' => $sessionId, 'u' => $utilizator['id']]);
$comandaId = (int)$stmt->fetchColumn();
$comanda = $comandaId ? comandaUtilizator($comandaId, $utilizator['id']) : null;
if (!$comanda) {
    pagina404('Nu am găsit comanda pentru această plată.');
}

// Comanda deja confirmată (ex.: utilizatorul a reîncărcat pagina) → doar o afișăm
if ($comanda['status'] !== 'noua') {
    redirectioneaza('comanda.php?id=' . $comanda['id']);
}

// ---------- 3. Întrebăm Stripe care este starea REALĂ a plății ----------
try {
    $sesiune = clientStripe()->checkout->sessions->retrieve($sessionId);
} catch (\Stripe\Exception\ApiErrorException | RuntimeException $ex) {
    seteazaFlash('eroare', 'Nu am putut verifica plata la Stripe: ' . $ex->getMessage());
    redirectioneaza('comanda.php?id=' . $comanda['id']);
}

$valid = $sesiune->payment_status === 'paid'                              // banii au fost încasați
      && (string)($sesiune->metadata['comanda_id'] ?? '') === (string)$comanda['id']   // pentru ACEASTĂ comandă
      && $sesiune->currency === 'ron'
      && (int)$sesiune->amount_total === inBani((float)$comanda['total']); // suma corectă

if (!$valid) {
    seteazaFlash('eroare', 'Plata nu a fost confirmată de Stripe. Poți încerca din nou.');
    redirectioneaza('comanda.php?id=' . $comanda['id']);
}

// ---------- 4. Confirmăm: stoc scăzut + factură + status „platita” (tranzacție) ----------
try {
    confirmaPlata($comanda['id'], $sesiune->id);
    unset($_SESSION['cos']);
    seteazaFlash('succes', 'Plata a fost efectuată cu succes. Mulțumim pentru comandă!');
} catch (RuntimeException $ex) {
    // Caz rar: între timp s-a epuizat stocul. Plata există la Stripe, deci
    // comanda trebuie rezolvată manual (rambursare din panoul Stripe).
    seteazaFlash('eroare', 'Plata a fost primită, dar: ' . $ex->getMessage()
        . ' Te vom contacta pentru rambursare.');
}
redirectioneaza('comanda.php?id=' . $comanda['id']);

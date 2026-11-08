<?php
/**
 * Inițierea plății: creează o sesiune Stripe Checkout pentru o comandă
 * și trimite clientul pe pagina de plată Stripe.
 *
 * Primește doar POST (butonul „Plătește cu cardul” din comanda.php).
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/comenzi.php';
require_once __DIR__ . '/includes/stripe.php';

$utilizator = cereAutentificare();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectioneaza('cont.php');
}
verificaCsrf();

$id = filter_var($_POST['comanda_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$comanda = $id ? comandaUtilizator($id, $utilizator['id']) : null;   // doar comenzile proprii
if (!$comanda) {
    pagina404('Comanda nu există.');
}
if ($comanda['status'] !== 'noua') {
    seteazaFlash('eroare', 'Această comandă a fost deja plătită sau anulată.');
    redirectioneaza('comanda.php?id=' . $comanda['id']);
}

// Verificăm stocul ÎNAINTE de plată, ca să nu încasăm bani pentru produse epuizate.
// (Verificarea definitivă, cu blocarea rândurilor, se face în confirmaPlata().)
$stmt = db()->prepare('SELECT nume, stoc FROM produse WHERE id = :id');
foreach ($comanda['linii'] as $l) {
    $stmt->execute(['id' => $l['produs_id']]);
    $p = $stmt->fetch();
    if ((int)$p['stoc'] < (int)$l['cantitate']) {
        seteazaFlash('eroare', "Ne pare rău, „{$p['nume']}” nu mai este în stoc în cantitatea comandată.");
        redirectioneaza('comanda.php?id=' . $comanda['id']);
    }
}

// Produsele trimise la Stripe vin din comenzi_produse (prețuri salvate la plasarea
// comenzii), NU din formular — clientul nu poate modifica suma.
$produse = [];
foreach ($comanda['linii'] as $l) {
    $produse[] = [
        'quantity'   => (int)$l['cantitate'],
        'price_data' => [
            'currency'     => 'ron',
            'unit_amount'  => inBani((float)$l['pret_unitar']),   // în bani
            'product_data' => ['name' => $l['nume'] . ($l['gramaj'] ? ' (' . $l['gramaj'] . ')' : '')],
        ],
    ];
}

try {
    $sesiune = clientStripe()->checkout->sessions->create([
        'mode'                => 'payment',
        'line_items'          => $produse,
        'customer_email'      => $comanda['client_email'],
        'client_reference_id' => (string)$comanda['id'],
        'metadata'            => ['comanda_id' => (string)$comanda['id']],
        'locale'              => 'ro',
        // Stripe înlocuiește singur {CHECKOUT_SESSION_ID} cu ID-ul sesiunii
        'success_url'         => url('plata_succes.php') . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'          => url('comanda.php?id=' . $comanda['id'] . '&plata=anulata'),
    ]);
} catch (\Stripe\Exception\ApiErrorException | RuntimeException $ex) {
    seteazaFlash('eroare', 'Plata nu a putut fi inițiată: ' . $ex->getMessage());
    redirectioneaza('comanda.php?id=' . $comanda['id']);
}

// Salvăm ID-ul sesiunii pe comandă: la întoarcere verificăm că plata
// confirmată este exact sesiunea creată pentru această comandă.
db()->prepare("UPDATE comenzi SET stripe_id = :s WHERE id = :id AND status = 'noua'")
    ->execute(['s' => $sesiune->id, 'id' => $comanda['id']]);

// 303 See Other: browserul face GET pe pagina de plată Stripe
header('Location: ' . $sesiune->url, true, 303);
exit;

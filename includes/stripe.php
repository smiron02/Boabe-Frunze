<?php
/**
 * Conexiunea cu Stripe (biblioteca oficială stripe/stripe-php, instalată cu Composer).
 *
 * Instalare (o singură dată, în folderul proiectului):
 *     composer install
 */

require_once __DIR__ . '/functii.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('Lipsește biblioteca Stripe. Rulează „composer install” în folderul proiectului.');
}
require_once $autoload;

/**
 * Clientul Stripe, configurat cu cheia secretă din config.php.
 * Acceptăm DOAR chei de test (sk_test_...): proiectul nu trebuie să poată
 * încasa bani reali, nici din greșeală.
 */
function clientStripe(): \Stripe\StripeClient
{
    if (!str_starts_with(STRIPE_CHEIE_SECRETA, 'sk_test_') || strlen(STRIPE_CHEIE_SECRETA) < 20) {
        throw new RuntimeException('Cheia Stripe de test lipsește din includes/config.php (trebuie să înceapă cu sk_test_).');
    }
    return new \Stripe\StripeClient(STRIPE_CHEIE_SECRETA);
}

/**
 * Suma în subunități (bani), cum o cere Stripe: 42.42 RON → 4242.
 * round() înainte de (int): 0.29 * 100 = 28.999999... în virgulă mobilă.
 */
function inBani(float $suma): int
{
    return (int)round($suma * 100);
}

<?php
/**
 * Fișier de configurare — MODEL.
 *
 * Copiază acest fișier ca `config.php` (în același folder) și completează
 * datele reale. `config.php` este în .gitignore, deci parolele și cheile
 * Stripe nu ajung niciodată în Git.
 */

// ---------------------------------------------------------------
// Magazin
// ---------------------------------------------------------------
define('NUME_MAGAZIN', 'Boabe & Frunze');            // se schimbă doar aici
define('URL_BAZA', 'http://localhost/ceai-cafea');   // fără „/” la final
define('MONEDA', 'RON');

// Datele vânzătorului (apar pe factură)
define('FIRMA_DENUMIRE', 'Boabe & Frunze SRL');
define('FIRMA_CUI', 'RO12345678');
define('FIRMA_REG_COM', 'J40/1234/2026');
define('FIRMA_ADRESA', 'Str. Exemplu nr. 1, București');
define('FIRMA_IBAN', 'RO49AAAA1B31007593840000');
define('SERIE_FACTURA', 'BF');                       // ex.: BF-2026-00001

// Notă TVA: cota NU este globală. Fiecare categorie are propria cotă
// (coloana `categorii.cota_tva`): 11% cafea și ceai, 21% accesorii.

// ---------------------------------------------------------------
// Baza de date (MySQL / MariaDB)
// ---------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NUME', 'ceai_cafea');
define('DB_UTILIZATOR', 'root');
define('DB_PAROLA', '');                             // XAMPP: gol implicit

// ---------------------------------------------------------------
// Stripe — DOAR chei de test (încep cu pk_test_ / sk_test_)
// https://dashboard.stripe.com/test/apikeys
// ---------------------------------------------------------------
define('STRIPE_CHEIE_PUBLICA', 'pk_test_...');
define('STRIPE_CHEIE_SECRETA', 'sk_test_...');

// ---------------------------------------------------------------
// Mediu: true = afișează erorile (doar local!), false = pe server
// ---------------------------------------------------------------
define('MOD_DEZVOLTARE', true);

<?php
/**
 * Deconectarea. Acceptă DOAR cereri POST cu token CSRF valid
 * (butonul „Ieșire” din meniu este un mic formular).
 */

require_once __DIR__ . '/includes/functii.php';
pornesteSesiunea();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);            // Method Not Allowed
    header('Allow: POST');
    exit('Deconectarea se face din butonul „Ieșire” din meniu.');
}
verificaCsrf();

// 1. Golim toate datele sesiunii (utilizatorul, coșul etc.)
$_SESSION = [];

// 2. Ștergem sesiunea veche de pe server și primim un ID nou (true = șterge
//    fișierul vechi). Cookie-ul vechi devine inutil, chiar dacă a fost furat.
session_regenerate_id(true);

// 3. Mesajul de confirmare se salvează în sesiunea nouă, curată
seteazaFlash('succes', 'Te-ai deconectat. Te mai așteptăm!');
redirectioneaza('index.php');

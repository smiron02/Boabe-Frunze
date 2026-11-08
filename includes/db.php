<?php
/**
 * Conexiunea la baza de date prin PDO.
 *
 * Utilizare în orice pagină:
 *     require_once __DIR__ . '/includes/db.php';
 *     $stmt = db()->prepare('SELECT * FROM produse WHERE id = :id');
 *     $stmt->execute(['id' => $id]);
 *     $produs = $stmt->fetch();
 */

require_once __DIR__ . '/config.php';

if (MOD_DEZVOLTARE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

/**
 * Returnează mereu ACEEAȘI conexiune PDO (se creează o singură dată
 * pe cerere HTTP, la primul apel — variabila `static` o păstrează).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NUME . ';charset=utf8mb4';

        $optiuni = [
            // Orice eroare SQL aruncă o excepție (nu trece neobservată)
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // fetch() returnează tablouri asociative: $rand['nume']
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Prepared statements REALE, trimise separat de date către MySQL
            // => protecție împotriva SQL injection
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_UTILIZATOR, DB_PAROLA, $optiuni);
        } catch (PDOException $e) {
            // Nu afișăm detaliile (pot conține date sensibile) decât local
            http_response_code(500);
            exit(MOD_DEZVOLTARE
                ? 'Eroare conexiune DB: ' . htmlspecialchars($e->getMessage())
                : 'Serviciul este temporar indisponibil. Încercați mai târziu.');
        }
    }

    return $pdo;
}

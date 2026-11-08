<?php
/**
 * Modulul de personalizare: quiz-ul „Găsește-ți cafeaua ideală”.
 *
 * 1. Fiecare răspuns are ponderi (ajustări) pentru intensitate, aciditate și
 *    amăreală; un răspuns (întrebarea despre aparat) setează aparatul.
 * 2. Profil = 3 + suma ponderilor, limitat la intervalul 1..5.
 * 3. Recomandarea: cafelele pentru acel aparat, ordonate după DISTANȚA față
 *    de profil: |intensitate − i| + |acid − a| + |amar − m|.
 *    Distanța maximă posibilă este 4 + 4 + 4 = 12, deci
 *    potrivire (%) = 100 − distanță / 12 × 100.
 */

require_once __DIR__ . '/functii.php';

const DISTANTA_MAXIMA = 12;

/** Întrebările quiz-ului, fiecare cu lista ei de răspunsuri. */
function intrebariQuiz(): array
{
    $intrebari = db()->query('SELECT id, text FROM quiz_intrebari ORDER BY ordine, id')->fetchAll(PDO::FETCH_UNIQUE);
    foreach ($intrebari as &$i) {
        $i['raspunsuri'] = [];
    }
    unset($i);
    foreach (db()->query('SELECT * FROM quiz_raspunsuri ORDER BY id') as $r) {
        $intrebari[$r['intrebare_id']]['raspunsuri'][$r['id']] = $r;
    }
    return $intrebari;   // [intrebare_id => ['text' => ..., 'raspunsuri' => [raspuns_id => rând]]]
}

/**
 * Calculează profilul din răspunsurile trimise: [intrebare_id => raspuns_id].
 * Răspunsurile se validează față de baza de date: fiecare întrebare trebuie să
 * aibă un răspuns care îi aparține (un formular modificat nu poate trimite
 * ponderi inventate — ponderile se citesc din baza de date, nu din formular).
 *
 * @return array ['intensitate', 'acid', 'amar', 'aparat']
 * @throws RuntimeException dacă lipsesc răspunsuri sau sunt invalide
 */
function calculeazaProfil(array $intrebari, array $raspunsuri): array
{
    $suma = ['intensitate' => 0, 'acid' => 0, 'amar' => 0];
    $aparat = null;

    foreach ($intrebari as $idIntrebare => $intrebare) {
        $idRaspuns = (int)($raspunsuri[$idIntrebare] ?? 0);
        if (!isset($intrebare['raspunsuri'][$idRaspuns])) {
            throw new RuntimeException('Te rugăm să răspunzi la toate întrebările.');
        }
        $r = $intrebare['raspunsuri'][$idRaspuns];
        $suma['intensitate'] += (int)$r['pondere_intensitate'];
        $suma['acid']        += (int)$r['pondere_acid'];
        $suma['amar']        += (int)$r['pondere_amar'];
        $aparat = $r['aparat'] ?? $aparat;
    }
    if ($aparat === null) {
        throw new RuntimeException('Alege cum îți prepari cafeaua.');
    }

    // 3 (neutru) + ajustări, limitat la 1..5
    $profil = ['aparat' => $aparat];
    foreach ($suma as $cheie => $s) {
        $profil[$cheie] = max(1, min(5, 3 + $s));
    }
    return $profil;
}

/**
 * Salvează profilul: în sesiune pentru oricine; în baza de date doar pentru
 * utilizatorii autentificați. Un singur rând per utilizator — refacerea
 * quiz-ului îl SUPRASCRIE (INSERT ... ON DUPLICATE KEY UPDATE).
 */
function salveazaProfil(array $profil, ?int $utilizatorId): void
{
    $_SESSION['profil_gust'] = $profil;
    if ($utilizatorId === null) {
        return;
    }
    db()->prepare(
        'INSERT INTO profil_gust (utilizator_id, intensitate, acid, amar, aparat, actualizat_la)
         VALUES (:u, :i, :a, :m, :ap, NOW())
         ON DUPLICATE KEY UPDATE intensitate = VALUES(intensitate), acid = VALUES(acid),
                amar = VALUES(amar), aparat = VALUES(aparat), actualizat_la = NOW()'
    )->execute([
        'u' => $utilizatorId, 'i' => $profil['intensitate'], 'a' => $profil['acid'],
        'm' => $profil['amar'], 'ap' => $profil['aparat'],
    ]);
}

/**
 * Profilul curent: din baza de date (utilizator autentificat) sau din sesiune
 * (vizitator). null dacă quiz-ul nu a fost făcut.
 */
function profilCurent(?int $utilizatorId): ?array
{
    if ($utilizatorId !== null) {
        $stmt = db()->prepare('SELECT intensitate, acid, amar, aparat, actualizat_la FROM profil_gust WHERE utilizator_id = :u');
        $stmt->execute(['u' => $utilizatorId]);
        if ($profil = $stmt->fetch()) {
            return $profil;
        }
    }
    return $_SESSION['profil_gust'] ?? null;
}

/**
 * Cafelele cele mai potrivite profilului (doar active și în stoc, pentru
 * aparatul ales), cu procentul de potrivire.
 */
function recomandariCafea(array $profil, int $cate = 3): array
{
    $stmt = db()->prepare(
        'SELECT p.id, p.nume, p.pret, p.gramaj, p.imagine, p.stoc, c.nume AS categorie,
                a.intensitate, a.acid, a.amar, a.origine, ' . SQL_PROMOTIE . ',
                ABS(a.intensitate - :i) + ABS(a.acid - :a) + ABS(a.amar - :m) AS distanta
           FROM produse p
           JOIN atribute_cafea a ON a.produs_id = p.id
           JOIN categorii c ON c.id = p.categorie_id
          WHERE a.aparat = :ap AND p.activ = 1 AND p.stoc > 0
          ORDER BY distanta, p.pret
          LIMIT ' . (int)$cate        // (int): LIMIT primește doar un număr, din codul nostru
    );
    $stmt->execute([
        'i' => $profil['intensitate'], 'a' => $profil['acid'], 'm' => $profil['amar'], 'ap' => $profil['aparat'],
    ]);
    $produse = $stmt->fetchAll();
    foreach ($produse as &$p) {
        $p['potrivire'] = (int)round(100 - $p['distanta'] / DISTANTA_MAXIMA * 100);
    }
    unset($p);
    return $produse;
}

/** Numele aparatului pentru afișare */
function numeAparat(string $aparat): string
{
    return ['filtru' => 'filtru / V60', 'espresso' => 'espressor', 'ibric' => 'ibric'][$aparat] ?? $aparat;
}

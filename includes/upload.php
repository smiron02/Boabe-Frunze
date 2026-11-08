<?php
/**
 * Încărcarea imaginilor de produs (panoul de administrare).
 *
 * Măsuri de securitate:
 *  1. Tipul se verifică după CONȚINUTUL fișierului (finfo), nu după extensie
 *     sau după tipul trimis de browser — ambele pot fi falsificate.
 *  2. Mărime maximă 2 MB.
 *  3. Numele fișierului este generat de server (aleator), deci nu se poate
 *     suprascrie alt fișier și nu se poate încărca „virus.php”.
 *  4. Dacă extensia GD există, imaginea este REDESENATĂ: redimensionată la
 *     maximum 800 px și salvată ca WebP. Orice conținut ascuns în fișierul
 *     original se pierde, iar imaginea devine mică (bine pentru PageSpeed).
 */

require_once __DIR__ . '/functii.php';

const IMAGINE_MAX_OCTETI = 2 * 1024 * 1024;   // 2 MB
const IMAGINE_MAX_LATURA = 800;               // px
const IMAGINE_TIPURI = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/**
 * Salvează imaginea încărcată în assets/img și întoarce numele fișierului.
 * @param array $fisier un element din $_FILES (ex.: $_FILES['imagine'])
 * @throws RuntimeException cu un mesaj pentru utilizator
 */
function salveazaImagineProdus(array $fisier): string
{
    if (($fisier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $mesaje = [
            UPLOAD_ERR_INI_SIZE  => 'Imaginea depășește limita serverului.',
            UPLOAD_ERR_FORM_SIZE => 'Imaginea depășește 2 MB.',
            UPLOAD_ERR_PARTIAL   => 'Imaginea a fost încărcată doar parțial. Încearcă din nou.',
        ];
        throw new RuntimeException($mesaje[$fisier['error']] ?? 'Imaginea nu a putut fi încărcată.');
    }
    // is_uploaded_file: fișierul chiar vine dintr-un upload HTTP, nu e o cale oarecare
    if (!is_uploaded_file($fisier['tmp_name'])) {
        throw new RuntimeException('Fișier invalid.');
    }
    if ($fisier['size'] > IMAGINE_MAX_OCTETI) {
        throw new RuntimeException('Imaginea depășește 2 MB.');
    }

    // Tipul REAL, citit din primii octeți ai fișierului („semnătura”)
    $tip = (new finfo(FILEINFO_MIME_TYPE))->file($fisier['tmp_name']);
    if (!isset(IMAGINE_TIPURI[$tip]) || @getimagesize($fisier['tmp_name']) === false) {
        throw new RuntimeException('Sunt acceptate doar imagini JPEG, PNG sau WebP.');
    }

    $folder = __DIR__ . '/../assets/img/';
    $nume   = 'produs-' . bin2hex(random_bytes(8));

    // Varianta preferată: redesenăm imaginea ca WebP de max. 800 px (necesită GD)
    if (function_exists('imagewebp') && function_exists('imagecreatefromstring')) {
        $sursa = @imagecreatefromstring(file_get_contents($fisier['tmp_name']));
        if ($sursa === false) {
            throw new RuntimeException('Imaginea nu a putut fi citită.');
        }
        $l = imagesx($sursa);
        $h = imagesy($sursa);
        $scara = min(1, IMAGINE_MAX_LATURA / max($l, $h));     // nu mărim imaginile mici
        $lNou = max(1, (int)round($l * $scara));
        $hNou = max(1, (int)round($h * $scara));

        $dest = imagecreatetruecolor($lNou, $hNou);
        imagealphablending($dest, false);
        imagesavealpha($dest, true);                           // păstrăm transparența PNG
        imagecopyresampled($dest, $sursa, 0, 0, 0, 0, $lNou, $hNou, $l, $h);

        $nume .= '.webp';
        if (!imagewebp($dest, $folder . $nume, 80)) {          // calitate 80%
            throw new RuntimeException('Imaginea nu a putut fi salvată.');
        }
        imagedestroy($sursa);
        imagedestroy($dest);
        return $nume;
    }

    // Fără GD: păstrăm fișierul original, cu extensia corespunzătoare tipului real
    $nume .= '.' . IMAGINE_TIPURI[$tip];
    if (!move_uploaded_file($fisier['tmp_name'], $folder . $nume)) {
        throw new RuntimeException('Imaginea nu a putut fi salvată.');
    }
    return $nume;
}

/**
 * Șterge o imagine încărcată anterior (doar fișierele „produs-...” create de
 * noi; nu atinge placeholder.svg sau alte fișiere ale site-ului).
 */
function stergeImagineProdus(?string $nume): void
{
    if ($nume && preg_match('/^produs-[0-9a-f]{16}\.(webp|jpg|png)$/', $nume)) {
        $cale = __DIR__ . '/../assets/img/' . $nume;
        if (is_file($cale)) {
            unlink($cale);
        }
    }
}

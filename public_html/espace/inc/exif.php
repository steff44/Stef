<?php
/*
 * Lecture des métadonnées EXIF d'une photo hébergée sur ce site (choix
 * explicite de l'utilisatrice, 09/09/2026 : « voir les métadonnées de la
 * photo, si elles existent »). Fonctions pures, sans base de données ni
 * session, pour rester utilisables aussi bien depuis espace/photo-exif.php
 * (photos privées, connexion requise) que depuis un futur point d'accès
 * public — même principe de séparation que le reste du site.
 *
 * exif_read_data() (extension PHP « exif », présente par défaut chez la
 * plupart des hébergeurs, Hostinger compris) ne lit que le JPEG et le TIFF —
 * un PNG ou un WebP n'a jamais d'EXIF, ce qui n'est pas une erreur : le
 * tableau renvoyé est alors simplement vide, à afficher comme « aucune
 * métadonnée disponible » plutôt qu'un message d'échec.
 */

declare(strict_types=1);

// Convertit une fraction EXIF ("1/500", "26/10", ou déjà un nombre simple)
// en valeur décimale. Une fraction invalide (dénominateur nul) renvoie 0.0
// plutôt que de déclencher une division par zéro.
function exif_fraction_valeur(string $fraction): float
{
    if (strpos($fraction, '/') === false) {
        return (float) $fraction;
    }
    [$numerateur, $denominateur] = array_pad(explode('/', $fraction, 2), 2, '1');
    $denominateur = (float) $denominateur;
    return $denominateur !== 0.0 ? ((float) $numerateur) / $denominateur : 0.0;
}

// Vitesse lisible : "1/500 s" au-dessus d'une seconde... pardon, EN-DESSOUS
// d'une seconde (cas courant), "2,5 s" pour une pose plus longue.
function exif_vitesse_lisible(string $fraction): ?string
{
    $valeur = exif_fraction_valeur($fraction);
    if ($valeur <= 0) {
        return null;
    }
    if ($valeur >= 1) {
        return rtrim(rtrim(number_format($valeur, 1, ',', ''), '0'), ',') . ' s';
    }
    return '1/' . (string) round(1 / $valeur) . ' s';
}

/*
 * Extrait les métadonnées utiles d'un fichier photo, dans un tableau associatif
 * déjà prêt à afficher (clés en français, valeurs formatées). Tableau vide si
 * le fichier n'a pas d'EXIF (PNG/WebP, métadonnées retirées par un logiciel de
 * retouche, extension exif absente du serveur…) — jamais d'exception.
 */
function extraire_exif(string $chemin): array
{
    if (!function_exists('exif_read_data') || !is_file($chemin)) {
        return [];
    }

    $brut = @exif_read_data($chemin, null, true);
    if ($brut === false || !is_array($brut)) {
        return [];
    }

    $ifd0 = $brut['IFD0'] ?? [];
    $exif = $brut['EXIF'] ?? [];

    $appareil = trim(($ifd0['Make'] ?? '') . ' ' . ($ifd0['Model'] ?? ''));
    // Le nom de l'objectif n'a pas d'étiquette EXIF standard reconnue par
    // exif_read_data() : il atterrit dans une « étiquette non définie »,
    // à l'emplacement 0xA434 (LensModel), présent sur la plupart des
    // boîtiers récents qui l'enregistrent.
    $objectif = $exif['UndefinedTag:0xA434'] ?? null;

    // Format EXIF natif : "2025:12:03 10:53:56" — reformaté en "03/12/2025 à
    // 10:53" sans dépendre de date_en_francais() (inc/page.php), pour que ce
    // fichier reste utilisable sans le reste de l'espace adhérents.
    $date = $exif['DateTimeOriginal'] ?? ($ifd0['DateTime'] ?? null);
    if (is_string($date) && preg_match('/^(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2}):\d{2}$/', $date, $m)) {
        $date = "{$m[3]}/{$m[2]}/{$m[1]} à {$m[4]}:{$m[5]}";
    } else {
        $date = null;
    }

    $vitesse = isset($exif['ExposureTime']) ? exif_vitesse_lisible((string) $exif['ExposureTime']) : null;

    $ouverture = null;
    if (isset($exif['FNumber'])) {
        $valeur = exif_fraction_valeur((string) $exif['FNumber']);
        if ($valeur > 0) {
            $ouverture = 'f/' . rtrim(rtrim(number_format($valeur, 1, ',', ''), '0'), ',');
        }
    }

    $iso = $exif['ISOSpeedRatings'] ?? null;
    if (is_array($iso)) {
        $iso = $iso[0] ?? null;
    }

    $focale = null;
    if (isset($exif['FocalLength'])) {
        $valeur = exif_fraction_valeur((string) $exif['FocalLength']);
        if ($valeur > 0) {
            $focale = (string) round($valeur) . ' mm';
        }
    }

    return array_filter([
        'Appareil'  => $appareil !== '' ? $appareil : null,
        'Objectif'  => $objectif,
        'Date'      => $date,
        'Vitesse'   => $vitesse,
        'Ouverture' => $ouverture,
        'ISO'       => $iso !== null ? (string) $iso : null,
        'Focale'    => $focale,
    ], static fn($valeur) => $valeur !== null && $valeur !== '');
}

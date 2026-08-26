<?php
/*
 * Réception des fichiers envoyés par les adhérents (photos et documents).
 *
 * Trois précautions, car un fichier déposé par un visiteur est la porte
 * d'entrée classique d'un site piraté :
 *   1. le type réel est déduit du contenu, jamais du nom ni de ce qu'annonce
 *      le navigateur (tous deux falsifiables) ;
 *   2. le nom d'enregistrement est réinventé (aléatoire + extension connue),
 *      donc jamais un nom choisi par l'utilisateur ;
 *   3. les dossiers de dépôt sont fermés par .htaccess et servis uniquement
 *      par telecharger.php.
 */

declare(strict_types=1);

// Pour taille_lisible(), utilisée dans les messages d'erreur ci-dessous.
require_once __DIR__ . '/page.php';

const TAILLE_MAX_OCTETS = 8388608; // 8 Mo

// Plafond plus strict pour les photos déposées par les adhérents (Galerie
// privée, Galerie du Club) — choix explicite de l'utilisateur, 21/08/2026 :
// contrairement aux documents et aux photos de sortie, qui gardent le
// plafond général ci-dessus. Relevé de 600 Ko à 1000 Ko le 26/08/2026, à la
// demande explicite de l'utilisateur.
const TAILLE_MAX_PHOTO_ADHERENT = 1024000; // 1000 Ko

/* Types acceptés, et l'extension qu'on leur donne. */
const IMAGES_ACCEPTEES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

const DOCUMENTS_ACCEPTES = [
    'application/pdf'    => 'pdf',
    'image/jpeg'         => 'jpg',
    'image/png'          => 'png',
    'text/plain'         => 'txt',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
    'application/vnd.oasis.opendocument.text' => 'odt',
];

/*
 * Renvoie ['nom' => 'fichier enregistré', 'erreur' => null]
 * ou        ['nom' => null, 'erreur' => 'message à afficher'].
 *
 * $taille_max et $message_trop_lourd permettent un plafond et un message
 * différents de ceux par défaut — voir TAILLE_MAX_PHOTO_ADHERENT plus haut.
 */
function enregistrer_fichier_envoye(
    ?array $envoi,
    string $dossier,
    string $categorie,
    int $taille_max = TAILLE_MAX_OCTETS,
    ?string $message_trop_lourd = null
): array {
    $echec = static fn(string $message): array => ['nom' => null, 'erreur' => $message];
    $message_trop_lourd ??= "Le fichier est trop volumineux (maximum " . taille_lisible($taille_max) . ").";

    if (!$envoi || !isset($envoi['error']) || $envoi['error'] === UPLOAD_ERR_NO_FILE) {
        return $echec("Aucun fichier sélectionné.");
    }

    if ($envoi['error'] !== UPLOAD_ERR_OK) {
        // Cas le plus fréquent : le fichier dépasse la limite du serveur.
        if (in_array($envoi['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return $echec($message_trop_lourd);
        }
        return $echec("L'envoi du fichier a échoué. Réessayez.");
    }

    // Confirme que le fichier provient bien d'un formulaire, et non d'un
    // chemin glissé dans la requête.
    if (!is_uploaded_file($envoi['tmp_name'])) {
        return $echec("Envoi invalide.");
    }

    if ($envoi['size'] > $taille_max) {
        return $echec($message_trop_lourd);
    }

    $autorises = $categorie === 'image' ? IMAGES_ACCEPTEES : DOCUMENTS_ACCEPTES;

    // Type réel, lu dans le contenu du fichier.
    $mime = mime_content_type($envoi['tmp_name']) ?: '';
    if (!isset($autorises[$mime])) {
        return $echec($categorie === 'image'
            ? "Format d'image non accepté. Utilisez JPEG, PNG, WebP ou GIF."
            : "Format de document non accepté (PDF, Word, Excel, OpenDocument, texte ou image).");
    }

    // Pour une image, on vérifie en plus qu'elle s'ouvre réellement : un
    // fichier peut se faire passer pour une image sans en être une.
    if ($categorie === 'image' && @getimagesize($envoi['tmp_name']) === false) {
        return $echec("Ce fichier n'est pas une image valide.");
    }

    if (!is_dir($dossier) && !@mkdir($dossier, 0755, true)) {
        return $echec("Le dossier de dépôt est inaccessible.");
    }

    // Nom neuf, imprévisible, avec une extension issue de notre liste.
    $nom    = bin2hex(random_bytes(16)) . '.' . $autorises[$mime];
    $chemin = rtrim($dossier, '/') . '/' . $nom;

    if (!move_uploaded_file($envoi['tmp_name'], $chemin)) {
        return $echec("Impossible d'enregistrer le fichier sur le serveur.");
    }

    @chmod($chemin, 0644);

    return ['nom' => $nom, 'erreur' => null];
}

/*
 * Recadre et redimensionne une image en carré $taille×$taille (recadrage
 * centré, comme un « object-fit: cover »), en écrasant le fichier d'origine.
 * Utilisé pour les photos de sortie (agenda) : toutes tiennent alors dans une
 * vignette identique. Échoue silencieusement (photo laissée telle quelle) si
 * l'extension GD manque ou que l'image ne peut pas être décodée — mieux vaut
 * une vignette de taille inattendue qu'une sortie qui ne s'enregistre pas.
 */
function redimensionner_en_carre(string $chemin, int $taille): void
{
    if (!extension_loaded('gd')) {
        return;
    }

    $info = @getimagesize($chemin);
    if ($info === false) {
        return;
    }
    [$largeur, $hauteur, $type] = $info;

    $source = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($chemin),
        IMAGETYPE_PNG  => @imagecreatefrompng($chemin),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($chemin) : false,
        IMAGETYPE_GIF  => @imagecreatefromgif($chemin),
        default        => false,
    };
    if (!$source) {
        return;
    }

    $cote       = min($largeur, $hauteur);
    $decalage_x = (int) (($largeur - $cote) / 2);
    $decalage_y = (int) (($hauteur - $cote) / 2);

    $destination = imagecreatetruecolor($taille, $taille);
    // Fond transparent plutôt que noir pour les formats qui gèrent l'alpha.
    imagealphablending($destination, false);
    imagesavealpha($destination, true);
    $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
    imagefill($destination, 0, 0, $transparent);

    imagecopyresampled($destination, $source, 0, 0, $decalage_x, $decalage_y, $taille, $taille, $cote, $cote);

    match ($type) {
        IMAGETYPE_JPEG => imagejpeg($destination, $chemin, 85),
        IMAGETYPE_PNG  => imagepng($destination, $chemin),
        IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($destination, $chemin, 85) : null,
        IMAGETYPE_GIF  => imagegif($destination, $chemin),
        default        => null,
    };

    imagedestroy($source);
    imagedestroy($destination);
}

/*
 * Remet à plat le tableau que PHP construit pour un champ `name="x[]"
 * multiple` — $_FILES['x'] arrive alors sous la forme ['name' => [...],
 * 'tmp_name' => [...], ...] (un tableau par propriété) plutôt qu'un tableau
 * par fichier. Renvoie une liste de tableaux au format attendu par
 * enregistrer_fichier_envoye() ; une case laissée vide dans le sélecteur
 * (aucun fichier choisi à cet emplacement) est ignorée plutôt que traitée
 * comme un envoi en échec.
 */
function fichiers_multiples(array $bloc): array
{
    $fichiers = [];
    $total    = count($bloc['name'] ?? []);

    for ($i = 0; $i < $total; $i++) {
        if (($bloc['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $fichiers[] = [
            'name'     => $bloc['name'][$i],
            'type'     => $bloc['type'][$i],
            'tmp_name' => $bloc['tmp_name'][$i],
            'error'    => $bloc['error'][$i],
            'size'     => $bloc['size'][$i],
        ];
    }

    return $fichiers;
}

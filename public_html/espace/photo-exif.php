<?php
/*
 * Métadonnées EXIF d'une photo hébergée sur ce site, en JSON — choix
 * explicite de l'utilisatrice, 09/09/2026 : « voir les métadonnées de la
 * photo, si elles existent », proposé depuis le menu contextuel personnalisé
 * qui remplace le clic droit natif sur une photo (voir js/main.js).
 *
 * Même logique de résolution type→table→dossier et les mêmes règles d'accès
 * que telecharger.php (photo/document exigent une connexion, les autres
 * types sont publics comme la page Galerie qui les affiche) — dupliquée ici
 * plutôt que factorisée : les deux scripts restent ainsi indépendants l'un
 * de l'autre, chacun modifiable sans risquer de casser l'autre.
 *
 * Ne sert jamais le fichier lui-même, seulement ses métadonnées : aucun
 * rapport avec le blocage du clic droit, qui empêche l'enregistrement de
 * l'image affichée, pas la lecture de ses informations techniques.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/exif.php';

header('Content-Type: application/json; charset=utf-8');

$type = (string) ($_GET['type'] ?? '');
$id   = (int) ($_GET['id'] ?? 0);

const TYPES_PUBLICS_EXIF = ['sortie', 'galerie_club', 'blog', 'sortie_album'];

if ($id <= 0 || !in_array($type, ['photo', 'sortie', 'galerie_club', 'blog', 'sortie_album'], true)) {
    http_response_code(404);
    echo json_encode(['exif' => []]);
    exit;
}

$adherent = null;
if (!in_array($type, TYPES_PUBLICS_EXIF, true)) {
    $adherent = exige_connexion();
}

if ($type === 'photo') {
    $requete = base_de_donnees()->prepare('SELECT fichier, depose_par FROM photos_privees WHERE id = ?');
    $dossier = __DIR__ . '/photos/';
} elseif ($type === 'galerie_club') {
    $requete = base_de_donnees()->prepare('SELECT fichier FROM photos_club WHERE id = ?');
    $dossier = __DIR__ . '/photos_club/';
} elseif ($type === 'blog') {
    $requete = base_de_donnees()->prepare('SELECT image AS fichier FROM articles_blog WHERE id = ?');
    $dossier = __DIR__ . '/photos_blog/';
} elseif ($type === 'sortie_album') {
    $requete = base_de_donnees()->prepare('SELECT fichier FROM photos_sorties WHERE id = ?');
    $dossier = __DIR__ . '/photos_sorties/';
} else {
    $requete = base_de_donnees()->prepare('SELECT photo AS fichier FROM sorties WHERE id = ?');
    $dossier = __DIR__ . '/photos/';
}

$requete->execute([$id]);
$ligne = $requete->fetch();

if (!$ligne || !$ligne['fichier']) {
    http_response_code(404);
    echo json_encode(['exif' => []]);
    exit;
}

if ($type === 'photo' && (int) $ligne['depose_par'] !== $adherent['id'] && !est_administrateur()) {
    http_response_code(404);
    echo json_encode(['exif' => []]);
    exit;
}

$chemin = $dossier . basename((string) $ligne['fichier']);

// Contenu dérivé d'une photo réservée pour type=photo : ni cache partagé, ni
// indexation, comme telecharger.php. Les types publics peuvent être mis en
// cache brièvement, sans conséquence.
header(in_array($type, TYPES_PUBLICS_EXIF, true)
    ? 'Cache-Control: public, max-age=600'
    : 'Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

echo json_encode(['exif' => extraire_exif($chemin)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

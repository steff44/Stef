<?php
/*
 * Sert un fichier privé (photo ou document) après avoir vérifié que la
 * personne est bien connectée — sauf les photos de sortie (type=sortie),
 * celles de la Galerie du Club (type=galerie_club), les photos d'un album
 * « Nos Sorties » hébergé sur ce site (type=sortie_album) et les photos de
 * couverture du blog (type=blog), publiques comme l'agenda et la page
 * Galerie qui les affichent.
 *
 * Les dossiers espace/photos/, espace/photos_club/, espace/photos_sorties/,
 * espace/photos_blog/ et espace/fichiers/ sont fermés par .htaccess : ce
 * script est la seule porte d'entrée. Le nom du fichier n'est jamais pris
 * dans l'URL — on passe par un identifiant en base, puis on relit le nom
 * enregistré. Impossible, donc, de réclamer « ../inc/config.local.php ».
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';

$type = (string) ($_GET['type'] ?? '');
$id   = (int) ($_GET['id'] ?? 0);

const TYPES_PUBLICS = ['sortie', 'galerie_club', 'blog', 'sortie_album'];

if ($id <= 0 || !in_array($type, ['photo', 'document', 'sortie', 'galerie_club', 'blog', 'sortie_album'], true)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$adherent = null;
if (!in_array($type, TYPES_PUBLICS, true)) {
    $adherent = exige_connexion();
}

if ($type === 'photo') {
    // Chacun ne voit que ses propres photos dans galerie.php (choix
    // explicite de l'utilisateur, 21/08/2026) : même restriction ici, sans
    // quoi un identifiant deviné dans l'URL donnerait accès au fichier
    // malgré tout. Un responsable garde accès à tout, pour la modération.
    $requete = base_de_donnees()->prepare('SELECT fichier, titre, depose_par FROM photos_privees WHERE id = ?');
    $dossier = __DIR__ . '/photos/';
} elseif ($type === 'document') {
    $requete = base_de_donnees()->prepare('SELECT fichier, nom_origine AS titre FROM documents WHERE id = ?');
    $dossier = __DIR__ . '/fichiers/';
} elseif ($type === 'galerie_club') {
    $requete = base_de_donnees()->prepare('SELECT fichier, titre FROM photos_club WHERE id = ?');
    $dossier = __DIR__ . '/photos_club/';
} elseif ($type === 'blog') {
    $requete = base_de_donnees()->prepare('SELECT image AS fichier, titre FROM articles_blog WHERE id = ?');
    $dossier = __DIR__ . '/photos_blog/';
} elseif ($type === 'sortie_album') {
    $requete = base_de_donnees()->prepare('SELECT fichier, titre FROM photos_sorties WHERE id = ?');
    $dossier = __DIR__ . '/photos_sorties/';
} else {
    $requete = base_de_donnees()->prepare('SELECT photo AS fichier, titre FROM sorties WHERE id = ?');
    $dossier = __DIR__ . '/photos/';
}

$requete->execute([$id]);
$ligne = $requete->fetch();

if (!$ligne || !$ligne['fichier']) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

if ($type === 'photo' && (int) $ligne['depose_par'] !== $adherent['id'] && !est_administrateur()) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// basename() en ceinture et bretelles, même si le nom vient de notre base.
$chemin = $dossier . basename((string) $ligne['fichier']);

if (!is_file($chemin)) {
    http_response_code(404);
    exit('Fichier introuvable sur le serveur.');
}

$mime = mime_content_type($chemin) ?: 'application/octet-stream';

// Les photos s'affichent toujours dans la page. Un document s'affiche par
// défaut (clic sur son nom, choix explicite de l'utilisatrice, 08/09/2026 —
// avant cette date, cliquer sur le nom forçait aussi le téléchargement) ; le
// bouton « Télécharger » ajoute &telecharger=1 pour forcer l'enregistrement.
$disposition = ($type === 'document' && isset($_GET['telecharger'])) ? 'attachment' : 'inline';
$nom_affiche = $type === 'document' ? (string) $ligne['titre'] : basename($chemin);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($chemin));
header(sprintf(
    "Content-Disposition: %s; filename*=UTF-8''%s",
    $disposition,
    rawurlencode($nom_affiche)
));
// Les types publics (sortie/galerie_club/blog/sortie_album) sont accessibles
// sans connexion : rien n'empêche le CDN de Hostinger (hcdn) de les mettre en
// cache à l'edge, comme il le fait déjà pour style.css/main.js. Corrigé le
// 10/09/2026 (choix explicite de l'utilisatrice, qui a signalé des vignettes
// vides et changeantes à chaque rafraîchissement de « Notre Galerie ») —
// jusqu'ici, TOUS les types recevaient `private`, ce qui empêchait ce cache
// et forçait chaque vignette (jusqu'à 32 par page depuis la pagination) à
// relancer un `readfile()` PHP à chaque visite : sous forte charge simultanée,
// certaines requêtes échouaient au hasard sur cet hébergement mutualisé.
// Chaque photo/fichier étant identifié par un id immuable (jamais réutilisé,
// jamais modifié après dépôt — seule une suppression est possible), une
// semaine de cache reste sans risque, même principe que le cache-busting
// `?v=` déjà accepté ailleurs sur le site. Les types privés (photo/document)
// gardent `private` : leur accès dépend de qui est connecté, jamais du même
// contenu pour tout le monde.
$est_public = in_array($type, TYPES_PUBLICS, true);
header('Cache-Control: ' . ($est_public ? 'public, max-age=604800' : 'private, max-age=600'));
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

readfile($chemin);

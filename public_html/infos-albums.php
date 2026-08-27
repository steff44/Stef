<?php
/*
 * Point d'accès PUBLIC, en lecture seule, aux albums de « Nos Sorties »
 * (nos-sorties.html) — choix explicite de l'utilisateur, 27/08/2026.
 * Remplace infos-expo-2026.php, qui ne savait servir qu'un seul dossier
 * Drive figé dans config.local.php : le club crée maintenant un album par
 * sortie (« Expo 2026 », « Croisière Penbron », « Fête de la mer »…) depuis
 * Réglages du site, sans intervention sur le dépôt.
 *
 * Deux modes, pour éviter des pages trop longues (l'objectif de
 * l'utilisatrice) et ne pas user le quota gratuit de l'API Google :
 *   - sans paramètre      : la liste des albums (nom + vignette de
 *                           couverture + nombre de dossiers d'adhérents) ;
 *   - ?album=ID           : le contenu d'un album, groupé par adhérent,
 *                           exactement la structure que servait
 *                           infos-expo-2026.php.
 *
 * Chaque album a un `type` (voir albums_sorties.type, inc/albums.php) :
 *   - 'drive' (par défaut) : les photos restent hébergées sur Google Drive,
 *     jamais copiées sur ce serveur. La clé API Google reste un réglage
 *     global de espace/inc/config.local.php ('google_drive_cle_api') : c'est
 *     un secret, il n'a rien à faire en base ni dans une interface web.
 *     Chaque dossier Drive d'album doit être partagé en « Accessible à tous
 *     les utilisateurs disposant du lien » — une clé API (sans OAuth) ne
 *     peut lire que des fichiers Drive publics, jamais un dossier resté
 *     privé.
 *   - 'local' (choix explicite de l'utilisateur, 27/08/2026, réservé aux
 *     sorties avec peu de photos) : les photos sont déposées directement par
 *     les adhérents sur cet hébergement (table `photos_sorties`, voir
 *     espace/album.php). Toujours lu en direct depuis la base, jamais mis en
 *     cache ni soumis à la clé API — une simple requête SQL, sans quota à
 *     ménager, contrairement à Drive.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

const CACHE_DUREE_SECONDES = 900; // 15 minutes
const CACHE_DOSSIER        = __DIR__ . '/espace/inc';

// Bornes de l'exploration des sous-dossiers, à l'intérieur du dossier de
// chaque adhérent (le dossier racine d'un album n'est parcouru qu'un niveau,
// juste pour lister les adhérents).
const PROFONDEUR_MAX       = 5;  // niveaux de sous-dossiers parcourus
const REQUETES_MAX         = 40; // appels à l'API Google par adhérent
const PARENTS_PAR_REQUETE  = 8;  // dossiers interrogés en un seul appel

// Pour la vignette de couverture d'un album, on s'arrête à la première image
// trouvée : inutile de parcourir tout le dossier d'un adhérent alors qu'une
// seule photo suffit à illustrer la carte de l'album.
const REQUETES_MAX_COUVERTURE = 4;

const MIME_DOSSIER = 'application/vnd.google-apps.folder';

/*
 * Récupère une URL avec un délai VRAIMENT respecté. file_get_contents +
 * stream_context_create('timeout') n'applique pas toujours fiablement son
 * délai sur les flux HTTPS (bug PHP connu, dépend de la version/plateforme)
 * — un appel qui devrait échouer au bout de 6 secondes peut alors rester
 * bloqué plusieurs minutes, gelant tout le chargement de la page. cURL,
 * quand il est disponible (presque toujours en hébergement mutualisé),
 * respecte ses délais de façon bien plus fiable.
 */
function recuperer_url(string $url, int $delai_secondes): string|false
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $delai_secondes,
            CURLOPT_CONNECTTIMEOUT => $delai_secondes,
        ]);
        $brut = curl_exec($ch);
        curl_close($ch);
        return $brut === false ? false : $brut;
    }

    $contexte = stream_context_create(['http' => ['timeout' => $delai_secondes, 'ignore_errors' => true]]);
    return @file_get_contents($url, false, $contexte);
}

/* Chemin du fichier de cache d'un album (ou de la liste des albums). */
function chemin_cache(string $cle): string
{
    return CACHE_DOSSIER . '/.cache-albums-' . $cle . '.json';
}

function lire_cache(string $cle, bool $meme_expire = false): ?array
{
    $chemin = chemin_cache($cle);
    if (!is_file($chemin)) {
        return null;
    }
    if (!$meme_expire && (time() - (int) @filemtime($chemin)) >= CACHE_DUREE_SECONDES) {
        return null;
    }
    $brut    = @file_get_contents($chemin);
    $donnees = $brut !== false ? json_decode($brut, true) : null;
    return is_array($donnees) ? $donnees : null;
}

function ecrire_cache(string $cle, array $donnees): void
{
    @file_put_contents(chemin_cache($cle), json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function repondre(array $donnees): never
{
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* Sert le dernier résultat connu (même expiré) plutôt qu'une page vide, si
   l'appel à l'API échoue — une panne ou un quota dépassé côté Google ne doit
   pas faire disparaître tout le contenu pour les visiteurs. */
function repondre_depuis_le_cache(string $cle, array $repli): never
{
    repondre(lire_cache($cle, true) ?? $repli);
}

/* Construit la clause `q` pour interroger le contenu d'un ou plusieurs
   dossiers parents en un seul appel. */
function construire_requete_dossier(array $ids): string
{
    $clauses = [];
    foreach ($ids as $id) {
        $clauses[] = "'" . $id . "' in parents";
    }
    return '(' . implode(' or ', $clauses) . ')'
        . " and trashed = false"
        . " and (mimeType contains 'image/' or mimeType = '" . MIME_DOSSIER . "')";
}

/*
 * Parcourt le dossier d'UN adhérent et ses sous-dossiers (ex. « 1920 »), et
 * renvoie les images trouvées. $interroger reçoit une clause `q` et renvoie
 * la liste de fichiers (tableau), ou null si l'appel a échoué — l'injecter
 * en argument plutôt que d'appeler l'API en dur permet de tester ce
 * parcours hors ligne. $echec passe à true seulement si le TOUT PREMIER
 * appel échoue : au-delà, on préfère afficher les photos déjà récoltées
 * plutôt que rien. $requetes_max borne le nombre d'appels : la vignette de
 * couverture d'un album s'arrête bien plus tôt que l'affichage complet.
 */
function collecter_images_drive(
    string $racine,
    callable $interroger,
    ?bool &$echec = null,
    int $requetes_max = REQUETES_MAX,
    int $images_voulues = 0
): array {
    $echec    = false;
    $images   = [];
    $niveau   = [$racine];
    $vus      = [$racine => true];
    $requetes = 0;

    // Trie les fichiers d'une réponse en images (accumulées dans $images)
    // et sous-dossiers à visiter ensuite (ajoutés à $suivant, par référence).
    $ranger_fichiers = static function (array $fichiers, array &$suivant) use (&$images, &$vus): void {
        foreach ($fichiers as $fichier) {
            if (!isset($fichier['id'], $fichier['name'], $fichier['mimeType'])) {
                continue;
            }
            $id = (string) $fichier['id'];

            if ($fichier['mimeType'] === MIME_DOSSIER) {
                // Un raccourci Drive peut faire pointer deux dossiers
                // l'un vers l'autre : sans ce garde-fou, boucle infinie.
                if (!isset($vus[$id])) {
                    $vus[$id]  = true;
                    $suivant[] = $id;
                }
                continue;
            }

            $images[$id] = [
                // Nom du fichier sans son extension, comme pour un
                // document déposé dans l'espace adhérents — pas de titre
                // à saisir à la main.
                'titre' => pathinfo((string) $fichier['name'], PATHINFO_FILENAME),
                // Adresse de vignette publique de Google Drive :
                // fonctionne pour n'importe quel fichier partagé « avec
                // le lien », sans passer par une API à chaque affichage
                // d'image. sz=wNNN : largeur maximale demandée, Google
                // renvoie une image plus petite si l'original l'est déjà.
                // Deux tailles plutôt qu'une seule : la grille affiche des
                // vignettes de quelques centaines de pixels (1000 suffit,
                // écrans à haute densité compris), tandis que
                // l'agrandissement va jusqu'à 1920 (voir .lightbox-image
                // dans css/style.css) — servir 1920 partout alourdirait
                // inutilement le chargement de la grille, et servir 1000
                // partout rendrait la photo agrandie floue.
                'image'        => 'https://drive.google.com/thumbnail?id=' . rawurlencode($id) . '&sz=w1000',
                'image_grande' => 'https://drive.google.com/thumbnail?id=' . rawurlencode($id) . '&sz=w1920',
            ];
        }
    };

    for ($profondeur = 0; $profondeur < PROFONDEUR_MAX && $niveau !== []; $profondeur++) {
        $suivant = [];

        foreach (array_chunk($niveau, PARENTS_PAR_REQUETE) as $lot) {
            if ($requetes >= $requetes_max) {
                break 2;
            }
            if ($images_voulues > 0 && count($images) >= $images_voulues) {
                break 2;
            }
            $requetes++;

            $fichiers = $interroger(construire_requete_dossier($lot));

            if ($fichiers === null) {
                // Premier appel raté : rien à afficher pour cet adhérent, on
                // repassera par le cache.
                if ($requetes === 1) {
                    $echec = true;
                    return [];
                }
                // Un seul dossier du lot suffit à faire échouer toute la
                // requête groupée (constaté le 24/08/2026 : un sous-dossier
                // resté sans partage individuel, malgré le dossier parent
                // bien partagé, renvoyait « The user does not have
                // sufficient permissions » pour tout le lot). On relance
                // chaque dossier séparément plutôt que de perdre aussi les
                // photos des autres, correctement partagés.
                foreach ($lot as $id_isole) {
                    if ($requetes >= $requetes_max) {
                        break 2;
                    }
                    $requetes++;
                    $fichiers_isoles = $interroger(construire_requete_dossier([$id_isole]));
                    if ($fichiers_isoles !== null) {
                        $ranger_fichiers($fichiers_isoles, $suivant);
                    }
                    // Sinon : ce dossier précis reste inaccessible, on
                    // l'ignore silencieusement plutôt que de faire
                    // disparaître toute la page.
                }
                continue;
            }

            $ranger_fichiers($fichiers, $suivant);
        }

        $niveau = $suivant;
    }

    $images = array_values($images);
    usort($images, static fn(array $a, array $b): int => strnatcasecmp($a['titre'], $b['titre']));

    return $images;
}

/* ------------------------------------------------------------------ */

$album_demande = isset($_GET['album']) ? (int) $_GET['album'] : 0;
$cle_cache     = $album_demande > 0 ? 'album-' . $album_demande : 'liste';
$repli_vide    = $album_demande > 0 ? ['nom' => '', 'dossiers' => []] : ['albums' => []];

$chemin_config = __DIR__ . '/espace/inc/config.local.php';
if (!is_file($chemin_config)) {
    repondre_depuis_le_cache($cle_cache, $repli_vide);
}

$config  = require $chemin_config;
$cle_api = (string) ($config['google_drive_cle_api'] ?? '');

// Les albums vivent en base : connexion autonome, même principe que
// infos-club.php / infos-galerie-club.php (base_de_donnees() afficherait une
// page d'erreur HTML, inadaptée à un point d'accès JSON).
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['hote'], $config['base']),
        $config['utilisateur'],
        $config['mot_de_passe'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    require_once __DIR__ . '/espace/inc/migration.php';
    require_once __DIR__ . '/espace/inc/albums.php';
    appliquer_migrations($pdo);
    $albums = albums_sorties($pdo);
} catch (PDOException $e) {
    error_log('infos-albums.php — base injoignable : ' . $e->getMessage());
    repondre_depuis_le_cache($cle_cache, $repli_vide);
}

// Aucun album créé : page simplement vide, pas une erreur (même philosophie
// que le reste du site). Contrairement à avant l'introduction des albums
// locaux, une clé API absente ne suffit plus à elle seule à vider la page :
// un album local n'en a jamais eu besoin (voir plus bas).
if ($albums === []) {
    repondre($repli_vide);
}

/*
 * Contenu d'UN album local (photos_sorties, groupées par adhérent — même
 * forme que le mode 1 pour un album Drive) : toujours lu en direct, jamais
 * mis en cache ni conditionné à la clé API Google, pour que la photo tout
 * juste déposée par un adhérent (espace/album.php) apparaisse aussitôt.
 */
function contenu_album_local(PDO $pdo, int $album_id, string $nom_album): array
{
    $requete = $pdo->prepare(
        'SELECT p.id, p.titre, p.nom_affiche, p.depose_par, a.nom AS auteur
           FROM photos_sorties p
           LEFT JOIN adherents a ON a.id = p.depose_par
          WHERE p.album_id = ?
          ORDER BY p.cree_le DESC'
    );
    $requete->execute([$album_id]);

    $groupes = [];
    foreach ($requete->fetchAll() as $ligne) {
        // Regroupées par adhérent (comme un sous-dossier Drive) — une clé
        // dédiée par photo si l'auteur a été supprimé depuis (depose_par
        // NULL), pour ne jamais mélanger deux comptes différents.
        $cle = $ligne['depose_par'] !== null ? 'a' . $ligne['depose_par'] : 'p' . $ligne['id'];
        if (!isset($groupes[$cle])) {
            $groupes[$cle] = [
                'nom'    => $ligne['nom_affiche'] ?: ($ligne['auteur'] ?: 'Adhérent retiré'),
                'photos' => [],
            ];
        }
        $groupes[$cle]['photos'][] = [
            'titre' => $ligne['titre'],
            // Une seule taille disponible pour une photo hébergée ici,
            // contrairement à Drive (image/image_grande) — buildPhotoCard()
            // et renderLightbox() (js/main.js) savent déjà retomber sur
            // `image` quand `image_grande` est absent.
            'image' => 'espace/telecharger.php?type=sortie_album&id=' . (int) $ligne['id'],
        ];
    }

    $dossiers = [];
    foreach ($groupes as $groupe) {
        $dossiers[] = [
            'nom'      => $groupe['nom'],
            'vignette' => $groupe['photos'][0]['image'],
            'photos'   => $groupe['photos'],
        ];
    }

    return ['nom' => $nom_album, 'type' => 'local', 'dossiers' => $dossiers];
}

if ($album_demande > 0 && isset($albums[$album_demande]) && $albums[$album_demande]['type'] === 'local') {
    repondre(contenu_album_local($pdo, $album_demande, $albums[$album_demande]['nom']));
}

// Un album précis (mode 1) et de type Drive : sans clé API, impossible d'en
// lire quoi que ce soit — page vide comme avant l'introduction des albums
// locaux. En mode 2 (liste), en revanche, une clé absente ne vide pas la
// page : les albums locaux s'affichent quand même, seuls les albums Drive
// retombent alors sur une carte sans vignette (voir plus bas).
if ($album_demande > 0 && $cle_api === '') {
    repondre($repli_vide);
}

// Cache encore valable : on ne rappelle pas l'API Google à chaque visite. Ce
// cache protège le quota Drive, pas les albums locaux (voir
// contenu_album_local() plus haut, jamais mis en cache) — en mode 1
// (?album=ID), on n'arrive ici que pour un album Drive, donc rien de plus à
// faire. En mode 2 (liste), le bloc mis en cache mélange les deux types :
// les entrées locales qu'il contient sont ré-actualisées avant de répondre,
// pour qu'une photo tout juste déposée apparaisse sans attendre 15 minutes.
$cache = lire_cache($cle_cache);
if ($cache !== null) {
    if ($album_demande === 0 && isset($cache['albums']) && is_array($cache['albums'])) {
        // Le type ACTUEL (table albums_sorties, $albums) fait foi, jamais
        // celui figé dans le cache : un responsable peut avoir basculé un
        // album de Drive vers local (ou l'inverse) depuis parametres.php
        // depuis que ce cache a été écrit.
        foreach ($cache['albums'] as &$entree_en_cache) {
            $id_entree = $entree_en_cache['id'] ?? null;
            if ($id_entree !== null && isset($albums[$id_entree]) && $albums[$id_entree]['type'] === 'local') {
                $entree_en_cache = resume_album_local($pdo, $id_entree, $albums[$id_entree]['nom']);
            }
        }
        unset($entree_en_cache);
    }
    repondre($cache);
}

/* Délai court : une API Google lente ou injoignable ne doit pas faire
   attendre indéfiniment le chargement de la page. */
$interroger = static function (string $q) use ($cle_api): ?array {
    $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
        'q'        => $q,
        'fields'   => 'files(id,name,mimeType)',
        'orderBy'  => 'name',
        'pageSize' => 1000,
        // Nécessaires si le dossier vit dans un Drive partagé
        // (« Shared Drive ») plutôt que dans « Mon Drive » — sans ça,
        // ses fichiers restent invisibles même bien partagés.
        'supportsAllDrives'         => 'true',
        'includeItemsFromAllDrives' => 'true',
        'key'      => $cle_api,
    ]);

    $brut    = recuperer_url($url, 6);
    $reponse = $brut !== false ? json_decode($brut, true) : null;

    if (!is_array($reponse) || !isset($reponse['files']) || !is_array($reponse['files'])) {
        $motif = is_array($reponse) && isset($reponse['error']['message'])
            ? $reponse['error']['message']
            : ($brut === false ? 'appel réseau échoué (délai dépassé ou allow_url_fopen désactivé)' : 'réponse invalide');
        error_log('infos-albums.php — appel Google Drive échoué : ' . $motif);
        return null;
    }

    return $reponse['files'];
};

/* Liste les sous-dossiers directs d'un dossier Drive : dans un album, ce
   sont les adhérents (Croisière Penbron / {Prénom} / …). */
function sous_dossiers(string $dossier, callable $interroger): ?array
{
    $enfants = $interroger(construire_requete_dossier([$dossier]));
    if ($enfants === null) {
        return null;
    }

    $dossiers = [];
    foreach ($enfants as $entree) {
        // Un fichier posé directement à la racine (hors d'un dossier
        // d'adhérent) n'a pas de nom d'adhérent à afficher : ignoré.
        if (($entree['mimeType'] ?? null) === MIME_DOSSIER && isset($entree['id'], $entree['name'])) {
            $dossiers[] = ['id' => (string) $entree['id'], 'nom' => (string) $entree['name']];
        }
    }
    usort($dossiers, static fn(array $a, array $b): int => strnatcasecmp($a['nom'], $b['nom']));

    return $dossiers;
}

/* ---- Mode 1 : le contenu d'un album, groupé par adhérent ---- */
if ($album_demande > 0) {
    if (!isset($albums[$album_demande])) {
        repondre($repli_vide);
    }

    $album    = $albums[$album_demande];
    $dossiers = sous_dossiers($album['dossier_drive'], $interroger);

    if ($dossiers === null) {
        repondre_depuis_le_cache($cle_cache, $repli_vide);
    }

    $adherents = [];
    foreach ($dossiers as $entree) {
        $echec_adherent = null;
        $photos = collecter_images_drive($entree['id'], $interroger, $echec_adherent);

        if ($echec_adherent || $photos === []) {
            // Dossier inaccessible (pas de partage individuel, comme
            // « Logo Focal Club » le 24/08/2026) ou simplement encore vide :
            // pas d'erreur, cet adhérent n'a juste pas de carte ici.
            continue;
        }

        $adherents[] = [
            'nom'      => $entree['nom'],
            'vignette' => $photos[0]['image'],
            'photos'   => $photos,
        ];
    }

    $resultat = ['nom' => $album['nom'], 'type' => 'drive', 'dossiers' => $adherents];
    ecrire_cache($cle_cache, $resultat);
    repondre($resultat);
}

/* Résumé d'UN album local pour le mode liste : nombre d'adhérents ayant
   déposé au moins une photo, et vignette de la plus récente. Toujours lu en
   direct — même principe que contenu_album_local() plus haut, une simple
   requête SQL n'a pas besoin d'être ménagée comme l'API Google. */
function resume_album_local(PDO $pdo, int $id, string $nom): array
{
    $requete = $pdo->prepare('SELECT id, depose_par FROM photos_sorties WHERE album_id = ? ORDER BY cree_le DESC');
    $requete->execute([$id]);
    $lignes = $requete->fetchAll();

    $participants = [];
    foreach ($lignes as $ligne) {
        $participants[$ligne['depose_par'] !== null ? 'a' . $ligne['depose_par'] : 'p' . $ligne['id']] = true;
    }

    return [
        'id'       => $id,
        'nom'      => $nom,
        'type'     => 'local',
        'vignette' => $lignes ? 'espace/telecharger.php?type=sortie_album&id=' . (int) $lignes[0]['id'] : null,
        'dossiers' => count($participants),
    ];
}

/* ---- Mode 2 : la liste des albums ----
   Volontairement économe pour les albums Drive : un appel pour lister les
   adhérents de l'album, puis au plus quelques appels pour trouver UNE photo
   de couverture. Faire la collecte complète de chaque album ici rendrait la
   page d'accueil des albums très lente et userait le quota gratuit pour
   rien. Les albums locaux, eux, passent par resume_album_local() ci-dessus
   — une requête SQL, jamais l'API Google. */
$liste = [];
foreach ($albums as $id => $album) {
    if ($album['type'] === 'local') {
        $liste[] = resume_album_local($pdo, $id, $album['nom']);
        continue;
    }

    // Pas de clé API : inutile de tenter l'appel (échouerait de toute façon,
    // au prix d'un délai d'attente inutile) — même repli que ci-dessous pour
    // un dossier mal partagé ou une API en panne.
    $dossiers = $cle_api !== '' ? sous_dossiers($album['dossier_drive'], $interroger) : null;
    if ($dossiers === null) {
        // Album mal partagé ou API en panne : on le montre quand même, sans
        // vignette, plutôt que de le faire disparaître de la liste.
        $liste[] = ['id' => $id, 'nom' => $album['nom'], 'type' => 'drive', 'vignette' => null, 'dossiers' => 0];
        continue;
    }

    $couverture = null;
    foreach ($dossiers as $entree) {
        $photos = collecter_images_drive(
            $entree['id'],
            $interroger,
            $ignore,
            REQUETES_MAX_COUVERTURE,
            1
        );
        if ($photos !== []) {
            $couverture = $photos[0]['image'];
            break;
        }
    }

    $liste[] = [
        'id'       => $id,
        'nom'      => $album['nom'],
        'type'     => 'drive',
        'vignette' => $couverture,
        'dossiers' => count($dossiers),
    ];
}

$resultat = ['albums' => $liste];
ecrire_cache($cle_cache, $resultat);
repondre($resultat);

<?php
/*
 * Point d'accès PUBLIC, en lecture seule, à un dossier Google Drive
 * (choix explicite de l'utilisateur, 23/08/2026) : liste les images qu'il
 * contient, pour la section « Photos Google Drive » de galerie.html — les
 * photos restent hébergées sur Google, jamais copiées sur ce serveur (c'est
 * tout l'intérêt : ne pas encombrer l'hébergement Hostinger).
 *
 * Nécessite deux réglages dans espace/inc/config.local.php :
 *   'google_drive_cle_api'    => une clé API Google Cloud (API Key, pas
 *                                 OAuth — voir CLAUDE.md pour la procédure),
 *   'google_drive_dossier_id' => l'identifiant du dossier Drive à afficher
 *                                 (dans son URL : drive.google.com/drive/
 *                                 folders/CET_IDENTIFIANT).
 * Le dossier doit être partagé en « Accessible à tous les utilisateurs
 * disposant du lien » — une clé API (sans OAuth) ne peut lire que des
 * fichiers Drive publics, jamais un dossier resté privé.
 *
 * Les SOUS-DOSSIERS sont explorés (24/08/2026) : le dossier du club range
 * ses photos par adhérent (Expo FOCAL 2026 / {Prénom} / …), donc se limiter
 * aux images posées directement dans le dossier racine ne remonterait
 * jamais rien. L'exploration est bornée (PROFONDEUR_MAX / REQUETES_MAX) :
 * l'API Google ne sait pas répondre « et tout ce qu'il y a en dessous », il
 * faut descendre niveau par niveau, et une arborescence profonde ne doit ni
 * user le quota gratuit ni faire attendre la page.
 *
 * Même principe que infos-club.php : autonome (ne dépend pas de la base ni
 * de espace/inc/), pour rester debout même si le reste du site est cassé.
 * Résultat mis en cache sur disque (voir CACHE_DUREE_SECONDES) : sans ça,
 * chaque visite de la page Galerie interrogerait l'API Google, ce qui
 * userait vite le quota gratuit et ralentirait la page.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

const CACHE_DUREE_SECONDES = 900; // 15 minutes
const CACHE_CHEMIN         = __DIR__ . '/espace/inc/.cache-galerie-drive.json';

// Bornes de l'exploration des sous-dossiers.
const PROFONDEUR_MAX       = 5;  // niveaux de sous-dossiers parcourus
const REQUETES_MAX         = 15; // appels à l'API Google par rafraîchissement
const PARENTS_PAR_REQUETE  = 20; // dossiers interrogés en un seul appel

const MIME_DOSSIER = 'application/vnd.google-apps.folder';

/*
 * Récupère une URL avec un délai VRAIMENT respecté. file_get_contents +
 * stream_context_create('timeout') n'applique pas toujours fiablement son
 * délai sur les flux HTTPS (bug PHP connu, dépend de la version/plateforme)
 * — un appel qui devrait échouer au bout de 6 secondes peut alors rester
 * bloqué plusieurs minutes, gelant tout le chargement de la page Galerie.
 * cURL, quand il est disponible (presque toujours en hébergement mutualisé),
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

/* Sert le dernier résultat connu (même expiré) plutôt qu'un tableau vide,
   si l'appel à l'API échoue — une panne ou un quota dépassé côté Google ne
   doit pas faire disparaître toute la section pour les visiteurs. */
function repondre_depuis_le_cache(): never
{
    if (is_file(CACHE_CHEMIN)) {
        $brut = @file_get_contents(CACHE_CHEMIN);
        $donnees = $brut !== false ? json_decode($brut, true) : null;
        if (is_array($donnees)) {
            echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    echo '[]';
    exit;
}

/*
 * Parcourt le dossier racine et ses sous-dossiers, et renvoie les images
 * trouvées. $interroger reçoit une clause `q` et renvoie la liste de
 * fichiers (tableau), ou null si l'appel a échoué — l'injecter en argument
 * plutôt que d'appeler l'API en dur permet de tester ce parcours hors ligne.
 * $echec passe à true seulement si le TOUT PREMIER appel échoue : au-delà,
 * on préfère afficher les photos déjà récoltées plutôt que rien.
 */
function collecter_images_drive(string $racine, callable $interroger, ?bool &$echec = null): array
{
    $echec    = false;
    $images   = [];
    $niveau   = [$racine];
    $vus      = [$racine => true];
    $requetes = 0;

    for ($profondeur = 0; $profondeur < PROFONDEUR_MAX && $niveau !== []; $profondeur++) {
        $suivant = [];

        foreach (array_chunk($niveau, PARENTS_PAR_REQUETE) as $lot) {
            if ($requetes >= REQUETES_MAX) {
                break 2;
            }
            $requetes++;

            $clauses = [];
            foreach ($lot as $id) {
                $clauses[] = "'" . $id . "' in parents";
            }
            $fichiers = $interroger(
                '(' . implode(' or ', $clauses) . ')'
                . " and trashed = false"
                . " and (mimeType contains 'image/' or mimeType = '" . MIME_DOSSIER . "')"
            );

            if ($fichiers === null) {
                // Premier appel raté : rien à afficher, on repassera par le
                // cache. Sinon, on garde ce qui a déjà été récolté.
                if ($requetes === 1) {
                    $echec = true;
                    return [];
                }
                continue;
            }

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
                    // d'image. sz=w1000 : largeur maximale demandée, Google
                    // renvoie une image plus petite si l'original l'est déjà.
                    'image' => 'https://drive.google.com/thumbnail?id=' . rawurlencode($id) . '&sz=w1000',
                ];
            }
        }

        $niveau = $suivant;
    }

    $images = array_values($images);
    usort($images, static fn(array $a, array $b): int => strnatcasecmp($a['titre'], $b['titre']));

    return $images;
}

$chemin_config = __DIR__ . '/espace/inc/config.local.php';
if (!is_file($chemin_config)) {
    repondre_depuis_le_cache();
}

$config   = require $chemin_config;
$cle_api  = (string) ($config['google_drive_cle_api'] ?? '');
$dossier  = (string) ($config['google_drive_dossier_id'] ?? '');

if ($cle_api === '' || $dossier === '') {
    // Réglage facultatif non renseigné : section simplement absente du
    // site, pas une erreur.
    echo '[]';
    exit;
}

// Un identifiant Drive n'est fait que de lettres, chiffres, tiret et
// souligné : tout le reste sortirait de la clause `q` construite plus bas.
if (preg_match('/^[A-Za-z0-9_-]+$/', $dossier) !== 1) {
    error_log('infos-galerie-drive.php — identifiant de dossier invalide.');
    echo '[]';
    exit;
}

// Cache encore valable : on ne rappelle pas l'API à chaque visite.
if (is_file(CACHE_CHEMIN) && (time() - (int) @filemtime(CACHE_CHEMIN)) < CACHE_DUREE_SECONDES) {
    repondre_depuis_le_cache();
}

$photos = collecter_images_drive(
    $dossier,
    /* Délai court : une API Google lente ou injoignable ne doit pas faire
       attendre indéfiniment le chargement de la page Galerie. */
    static function (string $q) use ($cle_api): ?array {
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
            error_log('infos-galerie-drive.php — appel Google Drive échoué : ' . $motif);
            return null;
        }

        return $reponse['files'];
    },
    $echec
);

if ($echec) {
    repondre_depuis_le_cache();
}

@file_put_contents(CACHE_CHEMIN, json_encode($photos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo json_encode($photos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

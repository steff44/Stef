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

// Cache encore valable : on ne rappelle pas l'API à chaque visite.
if (is_file(CACHE_CHEMIN) && (time() - (int) @filemtime(CACHE_CHEMIN)) < CACHE_DUREE_SECONDES) {
    repondre_depuis_le_cache();
}

$requete = 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
    'q'        => "'" . $dossier . "' in parents and mimeType contains 'image/' and trashed = false",
    'fields'   => 'files(id,name)',
    'orderBy'  => 'name',
    'pageSize' => 1000,
    'key'      => $cle_api,
]);

// Délai court : une API Google lente ou injoignable ne doit pas faire
// attendre indéfiniment le chargement de la page Galerie.
$contexte = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
$brut     = @file_get_contents($requete, false, $contexte);
$reponse  = $brut !== false ? json_decode($brut, true) : null;

if (!is_array($reponse) || !isset($reponse['files']) || !is_array($reponse['files'])) {
    $motif = is_array($reponse) && isset($reponse['error']['message'])
        ? $reponse['error']['message']
        : 'réponse invalide';
    error_log('infos-galerie-drive.php — appel Google Drive échoué : ' . $motif);
    repondre_depuis_le_cache();
}

$photos = [];
foreach ($reponse['files'] as $fichier) {
    if (!isset($fichier['id'], $fichier['name'])) {
        continue;
    }
    $photos[] = [
        // Nom du fichier sans son extension, comme pour un document déposé
        // dans l'espace adhérents — pas de titre à saisir à la main.
        'titre' => pathinfo((string) $fichier['name'], PATHINFO_FILENAME),
        // Adresse de vignette publique de Google Drive : fonctionne pour
        // n'importe quel fichier partagé « avec le lien », sans passer par
        // une API à chaque affichage d'image. sz=w1000 : largeur maximale
        // demandée, Google renvoie une image plus petite si l'original
        // l'est déjà.
        'image' => 'https://drive.google.com/thumbnail?id=' . rawurlencode((string) $fichier['id']) . '&sz=w1000',
    ];
}

@file_put_contents(CACHE_CHEMIN, json_encode($photos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo json_encode($photos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

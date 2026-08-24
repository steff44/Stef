<?php
/*
 * Diagnostic temporaire (à supprimer) : rejoue exactement
 * collecter_images_drive() en journalisant chaque appel (requête + nombre
 * de résultats), pour voir où le parcours des sous-dossiers s'arrête.
 * N'expose jamais la clé API.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

const PROFONDEUR_MAX       = 5;
const REQUETES_MAX         = 15;
const PARENTS_PAR_REQUETE  = 20;
const MIME_DOSSIER = 'application/vnd.google-apps.folder';

function recuperer_url(string $url, int $delai_secondes): string|false
{
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
                    if (!isset($vus[$id])) {
                        $vus[$id]  = true;
                        $suivant[] = $id;
                    }
                    continue;
                }

                $images[$id] = [
                    'titre' => pathinfo((string) $fichier['name'], PATHINFO_FILENAME),
                    'image' => 'https://drive.google.com/thumbnail?id=' . rawurlencode($id) . '&sz=w1000',
                ];
            }
        }

        $niveau = $suivant;
    }

    return array_values($images);
}

$config  = require __DIR__ . '/espace/inc/config.local.php';
$cle_api = (string) ($config['google_drive_cle_api'] ?? '');
$dossier = (string) ($config['google_drive_dossier_id'] ?? '');

$journal = [];

$photos = collecter_images_drive(
    $dossier,
    function (string $q) use ($cle_api, &$journal): ?array {
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
            'q'                         => $q,
            'fields'                    => 'files(id,name,mimeType)',
            'orderBy'                   => 'name',
            'pageSize'                  => 1000,
            'supportsAllDrives'         => 'true',
            'includeItemsFromAllDrives' => 'true',
            'key'                       => $cle_api,
        ]);
        $brut    = recuperer_url($url, 6);
        $reponse = $brut !== false ? json_decode($brut, true) : null;

        $entree = ['q_longueur' => strlen($q), 'q_debut' => substr($q, 0, 120)];
        if (!is_array($reponse) || !isset($reponse['files']) || !is_array($reponse['files'])) {
            $entree['erreur'] = is_array($reponse) ? ($reponse['error']['message'] ?? 'reponse invalide') : 'appel echoue';
            $journal[] = $entree;
            return null;
        }
        $entree['nb_resultats'] = count($reponse['files']);
        $entree['noms']         = array_column($reponse['files'], 'name');
        $journal[] = $entree;

        return $reponse['files'];
    },
    $echec
);

echo json_encode(['echec' => $echec, 'nb_photos' => count($photos), 'journal' => $journal], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

<?php
/*
 * Albums de la page publique « Nos Sorties » — table `albums_sorties` (voir
 * schema.sql), modifiables par un responsable depuis parametres.php.
 * Un album = une sortie du club, dont les photos vivent soit dans un dossier
 * Google Drive (type='drive', un sous-dossier par adhérent à l'intérieur),
 * soit déposées directement par les adhérents sur cet hébergement
 * (type='local', voir espace/album.php — réservé aux sorties avec peu de
 * photos, choix explicite de l'utilisateur, 27/08/2026).
 *
 * Même principe que inc/galerie_categories.php : une seule fonction de
 * lecture, mise en cache pour la durée de la page.
 */

declare(strict_types=1);

// Albums dans l'ordre d'affichage :
// [id => ['nom' => ..., 'dossier_drive' => ..., 'type' => 'drive'|'local']].
function albums_sorties(PDO $pdo): array
{
    static $albums = null;
    if ($albums !== null) {
        return $albums;
    }

    $albums = [];
    $lignes = $pdo->query('SELECT id, nom, dossier_drive, type FROM albums_sorties ORDER BY ordre, id')->fetchAll();
    foreach ($lignes as $ligne) {
        $albums[(int) $ligne['id']] = [
            'nom'           => (string) $ligne['nom'],
            'dossier_drive' => (string) $ligne['dossier_drive'],
            'type'          => (string) $ligne['type'],
        ];
    }

    return $albums;
}

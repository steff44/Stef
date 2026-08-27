<?php
/*
 * Albums de la page publique « Nos Sorties » — table `albums_sorties` (voir
 * schema.sql), modifiables par un responsable depuis parametres.php.
 * Un album = une sortie du club, dont les photos vivent dans un dossier
 * Google Drive (un sous-dossier par adhérent à l'intérieur).
 *
 * Même principe que inc/galerie_categories.php : une seule fonction de
 * lecture, mise en cache pour la durée de la page.
 */

declare(strict_types=1);

// Albums dans l'ordre d'affichage : [id => ['nom' => ..., 'dossier_drive' => ...]].
function albums_sorties(PDO $pdo): array
{
    static $albums = null;
    if ($albums !== null) {
        return $albums;
    }

    $albums = [];
    $lignes = $pdo->query('SELECT id, nom, dossier_drive FROM albums_sorties ORDER BY ordre, id')->fetchAll();
    foreach ($lignes as $ligne) {
        $albums[(int) $ligne['id']] = [
            'nom'           => (string) $ligne['nom'],
            'dossier_drive' => (string) $ligne['dossier_drive'],
        ];
    }

    return $albums;
}

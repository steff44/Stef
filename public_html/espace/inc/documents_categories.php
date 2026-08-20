<?php
/*
 * Rubriques des documents du club, partagées entre le formulaire de dépôt et
 * l'affichage de documents.php — un seul endroit à modifier pour ajouter une
 * rubrique ou une catégorie (voir COLONNES_DOCUMENTS_ATTENDUES dans
 * migration.php pour la colonne correspondante).
 */

declare(strict_types=1);

const RUBRIQUES_DOCUMENTS = [
    'Débuter la photo' => [
        'Bases techniques',
        'Premiers réglages',
        'Guides simplifiés pour les seniors',
    ],
    'Ateliers techniques du club' => [
        'Lumière',
        'Exposition',
        'Composition',
        'Matériel',
        'Post-traitement',
    ],
    'Thèmes photographiques' => [
        'Portrait',
        'Paysage',
        'Macro',
        'Nature',
        'Street',
        'Architecture',
        'Noir & Blanc',
        'Créatif',
    ],
    'Administration du club' => [
        'Planning',
        'Comptes rendus',
        'Documents internes',
    ],
];

// Catégories à plat, pour valider une valeur postée sans reparcourir les
// rubriques à chaque fois.
function categories_documents_a_plat(): array
{
    static $plat = null;
    if ($plat === null) {
        $plat = array_merge(...array_values(RUBRIQUES_DOCUMENTS));
    }
    return $plat;
}

// Rubrique à laquelle appartient une catégorie — null si elle ne correspond
// à aucune rubrique connue (ne devrait arriver que si RUBRIQUES_DOCUMENTS a
// été modifié après le dépôt d'un document portant l'ancienne valeur).
function rubrique_de_categorie(string $categorie): ?string
{
    foreach (RUBRIQUES_DOCUMENTS as $rubrique => $categories) {
        if (in_array($categorie, $categories, true)) {
            return $rubrique;
        }
    }
    return null;
}

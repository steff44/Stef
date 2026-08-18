<?php
/*
 * Catégories de sortie, partagées entre le calendrier (agenda.php) et la
 * liste des sorties (sorties-a-venir.php) — un seul endroit à modifier pour
 * ajouter une catégorie (voir COLONNES_SORTIES_ATTENDUES dans migration.php
 * pour la colonne correspondante).
 */

declare(strict_types=1);

const CATEGORIES_SORTIES = ['Sortie photo', 'Cours', 'Réunion'];

/* Nom de classe CSS pour une catégorie — jamais interpolée telle quelle
   dans le HTML, pour rester indépendant des accents/espaces du libellé. */
function classe_categorie(string $categorie): string
{
    return match ($categorie) {
        'Cours'    => 'cours',
        'Réunion'  => 'reunion',
        default    => 'sortie',
    };
}

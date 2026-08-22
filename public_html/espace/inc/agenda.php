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

/* Vacances scolaires, zone B (académie de Nantes), dates officielles
   2026-2027 — source : ministère de l'Éducation nationale, vérifiées en
   ligne (pas inventées). À compléter à la même source lors d'une prochaine
   année scolaire. */
const VACANCES_SCOLAIRES = [
    ['titre' => "Vacances d'Été",          'debut' => '2026-07-04', 'fin' => '2026-08-31'],
    ['titre' => 'Vacances de la Toussaint', 'debut' => '2026-10-17', 'fin' => '2026-11-02'],
    ['titre' => 'Vacances de Noël',        'debut' => '2026-12-19', 'fin' => '2027-01-04'],
    ["titre" => "Vacances d'Hiver",         'debut' => '2027-02-20', 'fin' => '2027-03-08'],
    ['titre' => 'Vacances de Printemps',   'debut' => '2027-04-17', 'fin' => '2027-05-03'],
];

/* La vacance couvrant un jour donné (AAAA-MM-JJ), ou null hors vacances. */
function vacances_du_jour(string $iso): ?array
{
    foreach (VACANCES_SCOLAIRES as $vacance) {
        if ($iso >= $vacance['debut'] && $iso <= $vacance['fin']) {
            return $vacance;
        }
    }
    return null;
}

/* Les vacances qui chevauchent une période [$debut, $fin] (bornes AAAA-MM-JJ
   incluses) — pour le bandeau au-dessus du calendrier. */
function vacances_chevauchant(string $debut, string $fin): array
{
    return array_values(array_filter(
        VACANCES_SCOLAIRES,
        static fn(array $v) => $v['debut'] <= $fin && $v['fin'] >= $debut
    ));
}

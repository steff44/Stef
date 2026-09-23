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

/* Grille d'un mois, complétée aux semaines pleines (lundi à dimanche) —
   utilisée par la vue mois et par les douze mini-mois de la vue année. */
function grille_mois(DateTime $premier_jour_mois): array
{
    $decalage_lundi  = ((int) $premier_jour_mois->format('N')) - 1; // 0 = lundi
    $jours_dans_mois = (int) $premier_jour_mois->format('t');
    $nb_semaines     = (int) ceil(($decalage_lundi + $jours_dans_mois) / 7);
    $debut_grille    = (clone $premier_jour_mois)->modify("-{$decalage_lundi} days");

    $jours = [];
    for ($i = 0; $i < $nb_semaines * 7; $i++) {
        $jours[] = (clone $debut_grille)->modify("+{$i} days");
    }
    return $jours;
}

/*
 * « Ajouter au calendrier » (Google Agenda, Outlook.com, fichier .ics),
 * choix explicite de l'utilisatrice, 24/09/2026. Fonctions partagées par
 * sorties-a-venir.php (les deux liens) et sortie-ics.php (le fichier).
 */

/* Fin utilisée pour ces trois exports : la vraie fin de la sortie si elle
   est renseignée, sinon un forfait de 2h après le début (une sortie sans
   fin précisée n'a par ailleurs aucune autre indication de durée en base). */
function fin_calendrier_sortie(string $debut_sql, ?string $fin_sql): DateTimeImmutable
{
    $paris = new DateTimeZone('Europe/Paris');
    if ($fin_sql) {
        return new DateTimeImmutable($fin_sql, $paris);
    }
    return (new DateTimeImmutable($debut_sql, $paris))->modify('+2 hours');
}

/* Lien « Ajouter à Google Agenda » — dates données en heure de Paris avec
   ctz=Europe/Paris, pour que Google Agenda ne convertisse jamais un horaire
   local en UTC à notre place. */
function lien_calendrier_google(array $sortie, string $lien_retour): string
{
    $debut = new DateTimeImmutable($sortie['debut'], new DateTimeZone('Europe/Paris'));
    $fin   = fin_calendrier_sortie($sortie['debut'], $sortie['fin']);

    $details = trim((string) ($sortie['description'] ?? ''));
    $details = $details !== '' ? $details . "\n\n" . $lien_retour : $lien_retour;

    $parametres = [
        'action'   => 'TEMPLATE',
        'text'     => (string) $sortie['titre'],
        'dates'    => $debut->format('Ymd\THis') . '/' . $fin->format('Ymd\THis'),
        'details'  => $details,
        'location' => (string) ($sortie['lieu'] ?? ''),
        'ctz'      => 'Europe/Paris',
    ];
    return 'https://calendar.google.com/calendar/render?' . http_build_query($parametres);
}

/* Lien « Ajouter à Outlook.com » — même principe, format ISO 8601 sans
   décalage horaire explicite (interprété dans le fuseau du compte Outlook
   du visiteur, comme ces liens de composition le font habituellement). */
function lien_calendrier_outlook(array $sortie, string $lien_retour): string
{
    $debut = new DateTimeImmutable($sortie['debut'], new DateTimeZone('Europe/Paris'));
    $fin   = fin_calendrier_sortie($sortie['debut'], $sortie['fin']);

    $corps = trim((string) ($sortie['description'] ?? ''));
    $corps = $corps !== '' ? $corps . "\n\n" . $lien_retour : $lien_retour;

    $parametres = [
        'path'     => '/calendar/action/compose',
        'rru'      => 'addevent',
        'subject'  => (string) $sortie['titre'],
        'startdt'  => $debut->format('Y-m-d\TH:i:s'),
        'enddt'    => $fin->format('Y-m-d\TH:i:s'),
        'location' => (string) ($sortie['lieu'] ?? ''),
        'body'     => $corps,
    ];
    return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query($parametres);
}

/* Échappe un texte pour du contenu ICS (RFC 5545, §3.3.11) : antislash,
   virgule, point-virgule et retour à la ligne doivent y être protégés. */
function ics_texte_echappe(string $texte): string
{
    $texte = str_replace('\\', '\\\\', $texte);
    $texte = str_replace([',', ';'], ['\\,', '\\;'], $texte);
    return str_replace(["\r\n", "\n", "\r"], '\\n', $texte);
}

/* Découpe une ligne ICS à 75 octets (RFC 5545, §3.1 — « line folding »),
   chaque ligne de poursuite commençant par un espace. Recule si besoin
   jusqu'à une frontière de caractère UTF-8 valide, pour ne jamais couper
   une lettre accentuée en deux. */
function ics_ligne_pliee(string $ligne): string
{
    if (strlen($ligne) <= 75) {
        return $ligne;
    }
    $morceaux = [];
    $limite   = 75;
    while (strlen($ligne) > $limite) {
        $coupe = $limite;
        while ($coupe > 0 && (ord($ligne[$coupe]) & 0xC0) === 0x80) {
            $coupe--;
        }
        $morceaux[] = substr($ligne, 0, $coupe);
        $ligne      = substr($ligne, $coupe);
        $limite     = 74; // 75 moins l'espace de continuité ajouté par implode()
    }
    $morceaux[] = $ligne;
    return implode("\r\n ", $morceaux);
}

/* Fichier .ics complet d'une sortie (un seul VEVENT), pour Apple Calendar,
   Outlook de bureau, Thunderbird... — les liens Google/Outlook.com
   couvrent déjà les agendas en ligne les plus courants, celui-ci couvre
   tout le reste. Dates converties en UTC (suffixe Z) plutôt qu'en heure
   locale + TZID, pour rester correct sans avoir à fournir un bloc
   VTIMEZONE complet. */
function ics_evenement_sortie(array $sortie, string $lien_retour): string
{
    $paris = new DateTimeZone('Europe/Paris');
    $utc   = new DateTimeZone('UTC');
    $debut = (new DateTimeImmutable($sortie['debut'], $paris))->setTimezone($utc);
    $fin   = fin_calendrier_sortie($sortie['debut'], $sortie['fin'])->setTimezone($utc);
    $maintenant = new DateTimeImmutable('now', $utc);

    $description = trim((string) ($sortie['description'] ?? ''));
    $description = $description !== '' ? $description . "\n\n" . $lien_retour : $lien_retour;

    $lignes = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Focal Club Turballais//Sorties//FR',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:sortie-' . (int) $sortie['id'] . '@focalclub.fr',
        'DTSTAMP:' . $maintenant->format('Ymd\THis\Z'),
        'DTSTART:' . $debut->format('Ymd\THis\Z'),
        'DTEND:' . $fin->format('Ymd\THis\Z'),
        'SUMMARY:' . ics_texte_echappe((string) $sortie['titre']),
    ];
    if (!empty($sortie['lieu'])) {
        $lignes[] = 'LOCATION:' . ics_texte_echappe((string) $sortie['lieu']);
    }
    $lignes[] = 'DESCRIPTION:' . ics_texte_echappe($description);
    $lignes[] = 'URL:' . $lien_retour;
    $lignes[] = 'END:VEVENT';
    $lignes[] = 'END:VCALENDAR';

    return implode("\r\n", array_map('ics_ligne_pliee', $lignes)) . "\r\n";
}

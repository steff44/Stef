<?php
/*
 * Agenda des sorties — calendrier du mois ou de la semaine, visible de tous
 * (choix explicite de l'utilisateur, 17/08/2026). La liste détaillée des
 * sorties (inscription, ajout, suppression) vit dans sorties-a-venir.php :
 * cliquer sur une sortie du calendrier y renvoie directement, à l'ancre
 * #sortie-{id}.
 *
 * Vue mois/semaine ajoutée le 22/08/2026 (choix explicite de l'utilisateur),
 * avec les vacances scolaires (zone B, académie de Nantes) affichées en
 * fond sur les jours concernés + un bandeau récapitulatif au-dessus du
 * calendrier pour les vacances qui chevauchent la période affichée.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/agenda.php';

$pdo = base_de_donnees();

$sorties = $pdo->query('SELECT id, titre, categorie, debut FROM sorties ORDER BY debut ASC')->fetchAll();

$sorties_par_jour = [];
foreach ($sorties as $s) {
    $sorties_par_jour[date('Y-m-d', strtotime($s['debut']))][] = $s;
}

$aujourdhui = date('Y-m-d');
$noms_mois  = [
    1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
];
$noms_mois_minuscule = [
    1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
    'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'
];

/* Date « 17 octobre 2026 », pour le bandeau des vacances. */
$date_longue = static function (string $iso) use ($noms_mois_minuscule): string {
    $t = strtotime($iso);
    return (int) date('j', $t) . ' ' . $noms_mois_minuscule[(int) date('n', $t)] . ' ' . date('Y', $t);
};

/* ---------------------------------------------------------------------------
 * Vue mois ou semaine (choix explicite de l'utilisateur, 22/08/2026),
 * connectées aux mêmes lignes de la table `sorties`. Navigation par
 * ?vue=mois&mois=AAAA-MM ou ?vue=semaine&semaine=AAAA-MM-JJ (le lundi de la
 * semaine), en rechargement de page classique : pas besoin de JavaScript.
 * ------------------------------------------------------------------------- */
$vue = ($_GET['vue'] ?? '') === 'semaine' ? 'semaine' : 'mois';

$mois_parametre = (string) ($_GET['mois'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $mois_parametre)) {
    $mois_parametre = date('Y-m');
}
$premier_jour_mois = DateTime::createFromFormat('Y-m-d', $mois_parametre . '-01');

$semaine_parametre = (string) ($_GET['semaine'] ?? '');
$date_semaine = preg_match('/^\d{4}-\d{2}-\d{2}$/', $semaine_parametre)
    ? DateTime::createFromFormat('Y-m-d', $semaine_parametre)
    : false;
if ($date_semaine === false) {
    $date_semaine = new DateTime($aujourdhui);
}
// Toujours ramené au lundi de sa semaine, même si le paramètre pointait
// ailleurs dans la semaine (lien partagé, saisie manuelle dans l'URL...).
$lundi_semaine = (clone $date_semaine)->modify('-' . (((int) $date_semaine->format('N')) - 1) . ' days');

// Date qui « ancre » la vue actuelle, pour que les onglets Mois/Semaine
// se basculent en préservant le contexte plutôt qu'en revenant à aujourd'hui.
$ancre = $vue === 'semaine' ? $lundi_semaine : $premier_jour_mois;

$lien_vue_mois = '?vue=mois&mois=' . $ancre->format('Y-m');
$ancre_lundi   = (clone $ancre)->modify('-' . (((int) $ancre->format('N')) - 1) . ' days');
$lien_vue_semaine = '?vue=semaine&semaine=' . $ancre_lundi->format('Y-m-d');

if ($vue === 'semaine') {
    $jours_affiches = [];
    for ($i = 0; $i < 7; $i++) {
        $jours_affiches[] = (clone $lundi_semaine)->modify("+{$i} days");
    }
    $debut_periode = $lundi_semaine->format('Y-m-d');
    $fin_periode   = $jours_affiches[6]->format('Y-m-d');

    $dimanche_semaine = $jours_affiches[6];
    if ($lundi_semaine->format('n') === $dimanche_semaine->format('n')) {
        $titre_periode = (int) $lundi_semaine->format('j') . ' au ' . (int) $dimanche_semaine->format('j')
            . ' ' . $noms_mois_minuscule[(int) $lundi_semaine->format('n')] . ' ' . $lundi_semaine->format('Y');
    } else {
        $titre_periode = (int) $lundi_semaine->format('j') . ' ' . $noms_mois_minuscule[(int) $lundi_semaine->format('n')]
            . ' au ' . (int) $dimanche_semaine->format('j') . ' ' . $noms_mois_minuscule[(int) $dimanche_semaine->format('n')]
            . ' ' . $dimanche_semaine->format('Y');
    }

    $periode_precedente = '?vue=semaine&semaine=' . (clone $lundi_semaine)->modify('-7 days')->format('Y-m-d');
    $periode_suivante   = '?vue=semaine&semaine=' . (clone $lundi_semaine)->modify('+7 days')->format('Y-m-d');
} else {
    $decalage_lundi  = ((int) $premier_jour_mois->format('N')) - 1; // 0 = lundi
    $jours_dans_mois = (int) $premier_jour_mois->format('t');
    $nb_semaines     = (int) ceil(($decalage_lundi + $jours_dans_mois) / 7);
    $debut_grille    = (clone $premier_jour_mois)->modify("-{$decalage_lundi} days");

    $jours_affiches = [];
    for ($i = 0; $i < $nb_semaines * 7; $i++) {
        $jours_affiches[] = (clone $debut_grille)->modify("+{$i} days");
    }
    $debut_periode = $jours_affiches[0]->format('Y-m-d');
    $fin_periode   = end($jours_affiches)->format('Y-m-d');
    $titre_periode = $noms_mois[(int) $premier_jour_mois->format('n')] . ' ' . $premier_jour_mois->format('Y');

    $periode_precedente = '?vue=mois&mois=' . (clone $premier_jour_mois)->modify('-1 month')->format('Y-m');
    $periode_suivante   = '?vue=mois&mois=' . (clone $premier_jour_mois)->modify('+1 month')->format('Y-m');
}

$vacances_affichees = vacances_chevauchant($debut_periode, $fin_periode);

debut_page("Agenda", 'agenda');
titre_page("Agenda des sorties", "Le calendrier des sorties, cours et réunions du club.");
?>
<section class="section"><div class="container">
  <p style="margin:-8px 0 24px;">
    <a class="btn btn-ghost" href="sorties-a-venir.php">Voir la liste des sorties à venir →</a>
  </p>

  <div class="agenda-cal-onglets" role="tablist">
    <a role="tab" aria-selected="<?= $vue === 'mois' ? 'true' : 'false' ?>"
       class="agenda-cal-onglet<?= $vue === 'mois' ? ' agenda-cal-onglet--actif' : '' ?>"
       href="<?= e($lien_vue_mois) ?>">Mois</a>
    <a role="tab" aria-selected="<?= $vue === 'semaine' ? 'true' : 'false' ?>"
       class="agenda-cal-onglet<?= $vue === 'semaine' ? ' agenda-cal-onglet--actif' : '' ?>"
       href="<?= e($lien_vue_semaine) ?>">Semaine</a>
  </div>

  <?php if ($vacances_affichees): ?>
    <p class="agenda-cal-vacances-bandeau">
      🏖️
      <?php foreach ($vacances_affichees as $i => $vacance): ?>
        <?= $i > 0 ? ' · ' : '' ?><strong><?= e($vacance['titre']) ?></strong>
        (du <?= e($date_longue($vacance['debut'])) ?> au <?= e($date_longue($vacance['fin'])) ?>)
      <?php endforeach; ?>
    </p>
  <?php endif; ?>

  <div class="agenda-calendrier">
    <div class="agenda-cal-nav">
      <a class="agenda-cal-nav-btn" href="<?= e($periode_precedente) ?>" aria-label="Période précédente">‹</a>
      <h2 class="agenda-cal-titre"><?= e($titre_periode) ?></h2>
      <a class="agenda-cal-nav-btn" href="<?= e($periode_suivante) ?>" aria-label="Période suivante">›</a>
    </div>
    <div class="agenda-cal-grille-entetes">
      <span>Lun</span><span>Mar</span><span>Mer</span><span>Jeu</span><span>Ven</span><span>Sam</span><span>Dim</span>
    </div>
    <div class="agenda-cal-grille<?= $vue === 'semaine' ? ' agenda-cal-grille--semaine' : '' ?>">
      <?php foreach ($jours_affiches as $jour_courant): ?>
        <?php
        $iso = $jour_courant->format('Y-m-d');
        $hors_mois = $vue === 'mois' && $jour_courant->format('n') !== $premier_jour_mois->format('n');
        $evenements_du_jour = $sorties_par_jour[$iso] ?? [];
        $vacance_du_jour = vacances_du_jour($iso);
        ?>
        <div class="agenda-cal-jour<?= $hors_mois ? ' hors-mois' : '' ?><?= $iso === $aujourdhui ? ' aujourdhui' : '' ?><?= $vacance_du_jour ? ' vacances' : '' ?>"
             <?= $vacance_du_jour ? 'title="' . e($vacance_du_jour['titre']) . '"' : '' ?>>
          <span class="agenda-cal-jour-numero"><?= (int) $jour_courant->format('j') ?></span>
          <?php foreach ($evenements_du_jour as $evenement): ?>
            <a class="agenda-cal-pastille agenda-cal-pastille--<?= classe_categorie($evenement['categorie']) ?>"
               href="sorties-a-venir.php#sortie-<?= (int) $evenement['id'] ?>" title="<?= e($evenement['titre']) ?>">
              <?= e($evenement['titre']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="agenda-cal-legende">
      <span><span class="agenda-cal-pastille agenda-cal-pastille--sortie" style="display:inline-block;width:12px;height:12px;padding:0;"></span> Sortie photo</span>
      <span><span class="agenda-cal-pastille agenda-cal-pastille--cours" style="display:inline-block;width:12px;height:12px;padding:0;"></span> Cours</span>
      <span><span class="agenda-cal-pastille agenda-cal-pastille--reunion" style="display:inline-block;width:12px;height:12px;padding:0;"></span> Réunion</span>
      <span><span class="agenda-cal-vacances-pastille" style="display:inline-block;width:12px;height:12px;padding:0;"></span> Vacances scolaires (zone B)</span>
    </p>
  </div>
</div></section>
<?php
fin_page();

<?php
/*
 * Agenda des sorties — calendrier du mois, visible de tous (choix explicite
 * de l'utilisateur, 17/08/2026). La liste détaillée des sorties (inscription,
 * ajout, suppression) vit dans sorties-a-venir.php : cliquer sur une sortie
 * du calendrier y renvoie directement, à l'ancre #sortie-{id}.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/agenda.php';

$pdo = base_de_donnees();

$sorties = $pdo->query('SELECT id, titre, categorie, debut FROM sorties ORDER BY debut ASC')->fetchAll();

/* ---------------------------------------------------------------------------
 * Calendrier du mois, connecté aux mêmes lignes de la table `sorties` que
 * sorties-a-venir.php (aucune donnée séparée). Navigation par ?mois=AAAA-MM,
 * en rechargement de page classique : pas besoin de JavaScript ici.
 * ------------------------------------------------------------------------- */
$mois_parametre = (string) ($_GET['mois'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $mois_parametre)) {
    $mois_parametre = date('Y-m');
}
$premier_jour_mois = DateTime::createFromFormat('Y-m-d', $mois_parametre . '-01');

$decalage_lundi  = ((int) $premier_jour_mois->format('N')) - 1; // 0 = lundi
$jours_dans_mois = (int) $premier_jour_mois->format('t');
$nb_semaines     = (int) ceil(($decalage_lundi + $jours_dans_mois) / 7);

$debut_grille = (clone $premier_jour_mois)->modify("-{$decalage_lundi} days");
$aujourdhui   = date('Y-m-d');

$sorties_par_jour = [];
foreach ($sorties as $s) {
    $sorties_par_jour[date('Y-m-d', strtotime($s['debut']))][] = $s;
}

$mois_precedent = (clone $premier_jour_mois)->modify('-1 month')->format('Y-m');
$mois_suivant   = (clone $premier_jour_mois)->modify('+1 month')->format('Y-m');
$noms_mois = [
    1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
];

debut_page("Agenda", 'agenda');
titre_page("Agenda des sorties", "Le calendrier des sorties, cours et réunions du club.");
?>
<section class="section"><div class="container">
  <p style="margin:-8px 0 24px;">
    <a class="btn btn-ghost" href="sorties-a-venir.php">Voir la liste des sorties à venir →</a>
  </p>

  <div class="agenda-calendrier">
    <div class="agenda-cal-nav">
      <a class="agenda-cal-nav-btn" href="?mois=<?= e($mois_precedent) ?>" aria-label="Mois précédent">‹</a>
      <h2 class="agenda-cal-titre"><?= e($noms_mois[(int) $premier_jour_mois->format('n')]) ?> <?= e($premier_jour_mois->format('Y')) ?></h2>
      <a class="agenda-cal-nav-btn" href="?mois=<?= e($mois_suivant) ?>" aria-label="Mois suivant">›</a>
    </div>
    <div class="agenda-cal-grille-entetes">
      <span>Lun</span><span>Mar</span><span>Mer</span><span>Jeu</span><span>Ven</span><span>Sam</span><span>Dim</span>
    </div>
    <div class="agenda-cal-grille">
      <?php for ($i = 0; $i < $nb_semaines * 7; $i++): ?>
        <?php
        $jour_courant = (clone $debut_grille)->modify("+{$i} days");
        $iso = $jour_courant->format('Y-m-d');
        $hors_mois = $jour_courant->format('n') !== $premier_jour_mois->format('n');
        $evenements_du_jour = $sorties_par_jour[$iso] ?? [];
        ?>
        <div class="agenda-cal-jour<?= $hors_mois ? ' hors-mois' : '' ?><?= $iso === $aujourdhui ? ' aujourdhui' : '' ?>">
          <span class="agenda-cal-jour-numero"><?= (int) $jour_courant->format('j') ?></span>
          <?php foreach ($evenements_du_jour as $evenement): ?>
            <a class="agenda-cal-pastille agenda-cal-pastille--<?= classe_categorie($evenement['categorie']) ?>"
               href="sorties-a-venir.php#sortie-<?= (int) $evenement['id'] ?>" title="<?= e($evenement['titre']) ?>">
              <?= e($evenement['titre']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
    </div>
    <p class="agenda-cal-legende">
      <span><span class="agenda-cal-pastille agenda-cal-pastille--sortie" style="display:inline-block;width:12px;height:12px;padding:0;"></span> Sortie photo</span>
      <span><span class="agenda-cal-pastille agenda-cal-pastille--cours" style="display:inline-block;width:12px;height:12px;padding:0;"></span> Cours</span>
      <span><span class="agenda-cal-pastille agenda-cal-pastille--reunion" style="display:inline-block;width:12px;height:12px;padding:0;"></span> Réunion</span>
    </p>
  </div>
</div></section>
<?php
fin_page();

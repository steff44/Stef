<?php
/*
 * Statistiques de fréquentation du site — réservé au responsable, même
 * principe que export-adherents.php (jamais un éditeur, cohérent avec
 * parametres.php). Lit la table `visites`, alimentée en arrière-plan par
 * enregistrer-visite.php (racine) à chaque page vue, sur toutes les pages du
 * site (voir js/main.js).
 *
 * La période affichée dans les tableaux détaillés est choisie par
 * l'utilisateur (?debut=AAAA-MM-JJ&fin=AAAA-MM-JJ, avec des raccourcis —
 * choix explicite de l'utilisatrice, 04/09/2026 : « je voudrais pouvoir
 * choisir les jours »). Les quatre chiffres de la vue d'ensemble
 * (aujourd'hui/7j/30j/depuis toujours) restent fixes, indépendants de la
 * période choisie : ce sont des repères rapides, pas le détail.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

exige_administrateur();
$pdo = base_de_donnees();

$total_toujours   = (int) $pdo->query('SELECT COUNT(*) FROM visites')->fetchColumn();
$total_30j        = (int) $pdo->query("SELECT COUNT(*) FROM visites WHERE cree_le >= NOW() - INTERVAL 30 DAY")->fetchColumn();
$total_7j         = (int) $pdo->query("SELECT COUNT(*) FROM visites WHERE cree_le >= NOW() - INTERVAL 7 DAY")->fetchColumn();
$total_aujourdhui = (int) $pdo->query('SELECT COUNT(*) FROM visites WHERE DATE(cree_le) = CURDATE()')->fetchColumn();

/* Période sélectionnée pour les tableaux détaillés : deux dates dans l'URL
   (?debut=...&fin=...), avec repli sur les 30 derniers jours si absentes ou
   invalides. createFromFormat() renvoie false sur une valeur mal formée —
   le repli s'applique alors sans erreur, y compris pour une URL trafiquée. */
$aujourdhui   = new DateTimeImmutable('today');
$debut_defaut = $aujourdhui->modify('-29 days');

$debut = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['debut'] ?? '')) ?: $debut_defaut;
$fin   = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['fin'] ?? '')) ?: $aujourdhui;
if ($debut > $fin) {
    [$debut, $fin] = [$fin, $debut];
}

$debut_sql = $debut->format('Y-m-d') . ' 00:00:00';
$fin_sql   = $fin->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

/* Raccourcis de période, calculés par rapport à aujourd'hui. */
$periodes = [
    "Aujourd'hui"       => [$aujourdhui, $aujourdhui],
    '7 derniers jours'  => [$aujourdhui->modify('-6 days'), $aujourdhui],
    '30 derniers jours' => [$aujourdhui->modify('-29 days'), $aujourdhui],
    'Ce mois-ci'        => [new DateTimeImmutable($aujourdhui->format('Y-m') . '-01'), $aujourdhui],
    'Cette année'       => [new DateTimeImmutable($aujourdhui->format('Y') . '-01-01'), $aujourdhui],
    'Depuis toujours'   => [new DateTimeImmutable('2000-01-01'), $aujourdhui],
];

$requete_page = $pdo->prepare(
    "SELECT page, COUNT(*) AS total FROM visites
      WHERE cree_le >= ? AND cree_le < ?
      GROUP BY page ORDER BY total DESC LIMIT 50"
);
$requete_page->execute([$debut_sql, $fin_sql]);
$top_pages = $requete_page->fetchAll();

$requete_referent = $pdo->prepare(
    "SELECT referent, COUNT(*) AS total FROM visites
      WHERE cree_le >= ? AND cree_le < ?
      GROUP BY referent ORDER BY total DESC LIMIT 50"
);
$requete_referent->execute([$debut_sql, $fin_sql]);
$top_referents = $requete_referent->fetchAll();

$requete_pays = $pdo->prepare(
    "SELECT pays, COUNT(*) AS total FROM visites
      WHERE cree_le >= ? AND cree_le < ?
      GROUP BY pays ORDER BY total DESC LIMIT 50"
);
$requete_pays->execute([$debut_sql, $fin_sql]);
$top_pays = $requete_pays->fetchAll();

$requete_ville = $pdo->prepare(
    "SELECT ville, COUNT(*) AS total FROM visites
      WHERE cree_le >= ? AND cree_le < ? AND ville IS NOT NULL AND ville <> ''
      GROUP BY ville ORDER BY total DESC LIMIT 50"
);
$requete_ville->execute([$debut_sql, $fin_sql]);
$top_villes = $requete_ville->fetchAll();

$total_periode = array_sum(array_column($top_pages, 'total'));

/* Tableau classé, avec rang et pourcentage — $lignes est un tableau de
   [libellé, total]. Remplace l'ancien affichage en barres, jugé pas assez
   précis (choix explicite de l'utilisatrice, 04/09/2026). */
function afficher_tableau_stats(array $lignes, string $colonne): void
{
    if (!$lignes) {
        echo '<p class="form-note" style="margin:16px 0 0;">Aucune donnée pour cette période.</p>';
        return;
    }
    $total_general = array_sum(array_column($lignes, 1));
    echo '<div class="tableau-defilant"><table class="tableau-adherents tableau-stats"><thead><tr>';
    echo '<th>#</th><th>' . e($colonne) . '</th><th>Visites</th><th>%</th>';
    echo '</tr></thead><tbody>';
    $rang = 1;
    foreach ($lignes as [$libelle, $total]) {
        $pourcentage = $total_general > 0 ? round($total / $total_general * 100, 1) : 0.0;
        echo '<tr>';
        echo '<td>' . $rang++ . '</td>';
        echo '<td>' . e($libelle) . '</td>';
        echo '<td>' . $total . '</td>';
        echo '<td>' . str_replace('.', ',', (string) $pourcentage) . '&nbsp;%</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

debut_page('Statistiques', 'statistiques');
titre_page('Statistiques du site', "Vue d'ensemble fixe, tableaux détaillés sur la période choisie ci-dessous.");
?>
<section class="section"><div class="container">

  <div class="form-card">
    <h2>Vue d'ensemble</h2>
    <div class="stat-apercu">
      <div class="stat-chiffre"><strong><?= $total_aujourdhui ?></strong><span>Aujourd'hui</span></div>
      <div class="stat-chiffre"><strong><?= $total_7j ?></strong><span>7 derniers jours</span></div>
      <div class="stat-chiffre"><strong><?= $total_30j ?></strong><span>30 derniers jours</span></div>
      <div class="stat-chiffre"><strong><?= $total_toujours ?></strong><span>Depuis toujours</span></div>
    </div>
    <p class="form-note" style="margin-top:20px;">
      Chaque page vue est comptée automatiquement, sans cookie ni compte
      requis. Le pays et la ville sont estimés à partir d'une version
      anonymisée de l'adresse IP (jamais l'adresse complète), et peuvent
      rester vides si l'estimation échoue.
    </p>
  </div>

  <div class="form-card" style="margin-top:32px;">
    <h2>Période des tableaux ci-dessous</h2>
    <nav class="stat-periodes" aria-label="Raccourcis de période">
      <?php foreach ($periodes as $libelle => [$d, $f]): ?>
        <?php $actif = $d->format('Y-m-d') === $debut->format('Y-m-d') && $f->format('Y-m-d') === $fin->format('Y-m-d'); ?>
        <a class="stat-periode-lien" href="statistiques.php?debut=<?= $d->format('Y-m-d') ?>&fin=<?= $f->format('Y-m-d') ?>"<?= $actif ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
      <?php endforeach; ?>
    </nav>
    <form method="get" class="stat-periode-form">
      <div class="field" style="max-width:170px;">
        <label for="debut">Du</label>
        <input type="date" id="debut" name="debut" value="<?= $debut->format('Y-m-d') ?>">
      </div>
      <div class="field" style="max-width:170px;">
        <label for="fin">Au</label>
        <input type="date" id="fin" name="fin" value="<?= $fin->format('Y-m-d') ?>">
      </div>
      <button type="submit" class="btn btn-primary">Afficher</button>
    </form>
    <p class="form-note" style="margin-top:16px;">
      Du <?= e(date_en_francais($debut->format('Y-m-d 00:00:00'), false)) ?>
      au <?= e(date_en_francais($fin->format('Y-m-d 00:00:00'), false)) ?> :
      <strong><?= $total_periode ?></strong> visite<?= $total_periode > 1 ? 's' : '' ?>.
    </p>
  </div>

  <div class="reglages-grid" style="margin-top:32px;">
    <div class="form-card">
      <h2>Pages les plus vues</h2>
      <?php
      afficher_tableau_stats(array_map(
          static fn($ligne) => [$ligne['page'], (int) $ligne['total']],
          $top_pages
      ), 'Page');
      ?>
    </div>

    <div class="form-card">
      <h2>D'où viennent les visiteurs</h2>
      <?php
      afficher_tableau_stats(array_map(
          static fn($ligne) => [$ligne['referent'] !== null && $ligne['referent'] !== '' ? $ligne['referent'] : 'Direct / lien interne', (int) $ligne['total']],
          $top_referents
      ), 'Provenance');
      ?>
    </div>

    <div class="form-card">
      <h2>Pays</h2>
      <?php
      afficher_tableau_stats(array_map(
          static fn($ligne) => [$ligne['pays'] !== null && $ligne['pays'] !== '' ? $ligne['pays'] : 'Inconnu', (int) $ligne['total']],
          $top_pays
      ), 'Pays');
      ?>
    </div>

    <div class="form-card">
      <h2>Villes</h2>
      <?php afficher_tableau_stats(array_map(
          static fn($ligne) => [$ligne['ville'], (int) $ligne['total']],
          $top_villes
      ), 'Ville'); ?>
    </div>
  </div>

</div></section>
<?php
fin_page();

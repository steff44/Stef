<?php
/*
 * Statistiques de fréquentation du site — réservé au responsable, même
 * principe que export-adherents.php (jamais un éditeur, cohérent avec
 * parametres.php). Lit la table `visites`, alimentée en arrière-plan par
 * enregistrer-visite.php (racine) à chaque page vue, sur toutes les pages du
 * site (voir js/main.js).
 *
 * Toutes les listes ci-dessous portent sur les 30 derniers jours — assez
 * pour voir une tendance récente sans faire dériver le classement sur des
 * mois d'historique. Le total « depuis toujours » reste affiché à part.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

exige_administrateur();
$pdo = base_de_donnees();

$total_toujours   = (int) $pdo->query('SELECT COUNT(*) FROM visites')->fetchColumn();
$total_30j        = (int) $pdo->query("SELECT COUNT(*) FROM visites WHERE cree_le >= NOW() - INTERVAL 30 DAY")->fetchColumn();
$total_7j         = (int) $pdo->query("SELECT COUNT(*) FROM visites WHERE cree_le >= NOW() - INTERVAL 7 DAY")->fetchColumn();
$total_aujourdhui = (int) $pdo->query('SELECT COUNT(*) FROM visites WHERE DATE(cree_le) = CURDATE()')->fetchColumn();

$top_pages = $pdo->query(
    "SELECT page, COUNT(*) AS total FROM visites
      WHERE cree_le >= NOW() - INTERVAL 30 DAY
      GROUP BY page ORDER BY total DESC LIMIT 10"
)->fetchAll();

$top_referents = $pdo->query(
    "SELECT referent, COUNT(*) AS total FROM visites
      WHERE cree_le >= NOW() - INTERVAL 30 DAY
      GROUP BY referent ORDER BY total DESC LIMIT 10"
)->fetchAll();

$top_pays = $pdo->query(
    "SELECT pays, COUNT(*) AS total FROM visites
      WHERE cree_le >= NOW() - INTERVAL 30 DAY
      GROUP BY pays ORDER BY total DESC LIMIT 10"
)->fetchAll();

$top_villes = $pdo->query(
    "SELECT ville, COUNT(*) AS total FROM visites
      WHERE cree_le >= NOW() - INTERVAL 30 DAY AND ville IS NOT NULL AND ville <> ''
      GROUP BY ville ORDER BY total DESC LIMIT 10"
)->fetchAll();

/* Affiche une liste classée avec une petite barre proportionnelle au plus
   grand total de la liste — $lignes est un tableau de [libellé, total]. */
function afficher_liste_stats(array $lignes): void
{
    if (!$lignes) {
        echo '<p class="form-note" style="margin:16px 0 0;">Aucune donnée pour l\'instant.</p>';
        return;
    }
    $max = max(array_column($lignes, 1));
    echo '<ul class="stat-liste">';
    foreach ($lignes as [$libelle, $total]) {
        $largeur = $max > 0 ? round($total / $max * 100) : 0;
        echo '<li class="stat-ligne">';
        echo '<span class="stat-libelle" title="' . e($libelle) . '">' . e($libelle) . '</span>';
        echo '<span class="stat-barre-fond"><span class="stat-barre" style="width:' . $largeur . '%"></span></span>';
        echo '<span class="stat-valeur">' . $total . '</span>';
        echo '</li>';
    }
    echo '</ul>';
}

debut_page('Statistiques', 'statistiques');
titre_page('Statistiques du site', 'Fréquentation des 30 derniers jours, sauf mention contraire.');
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

  <div class="reglages-grid">
    <div class="form-card">
      <h2>Pages les plus vues</h2>
      <?php
      afficher_liste_stats(array_map(
          static fn($ligne) => [$ligne['page'], (int) $ligne['total']],
          $top_pages
      ));
      ?>
    </div>

    <div class="form-card">
      <h2>D'où viennent les visiteurs</h2>
      <?php
      afficher_liste_stats(array_map(
          static fn($ligne) => [$ligne['referent'] !== null && $ligne['referent'] !== '' ? $ligne['referent'] : 'Direct / lien interne', (int) $ligne['total']],
          $top_referents
      ));
      ?>
    </div>

    <div class="form-card">
      <h2>Pays</h2>
      <?php
      afficher_liste_stats(array_map(
          static fn($ligne) => [$ligne['pays'] !== null && $ligne['pays'] !== '' ? $ligne['pays'] : 'Inconnu', (int) $ligne['total']],
          $top_pays
      ));
      ?>
    </div>

    <div class="form-card">
      <h2>Villes</h2>
      <?php afficher_liste_stats(array_map(
          static fn($ligne) => [$ligne['ville'], (int) $ligne['total']],
          $top_villes
      )); ?>
    </div>
  </div>

</div></section>
<?php
fin_page();

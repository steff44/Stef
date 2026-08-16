<?php
/*
 * Agenda des sorties, avec inscription des adhérents.
 * Les responsables créent et suppriment les sorties ; chacun s'inscrit ou se retire.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

$adherent = exige_connexion();
$pdo      = base_de_donnees();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'inscription') {
        // INSERT IGNORE : la clé unique empêche déjà la double inscription.
        $pdo->prepare('INSERT IGNORE INTO inscriptions (sortie_id, adherent_id) VALUES (?, ?)')
            ->execute([$id, $adherent['id']]);
        definir_message('succes', "Vous êtes inscrit à cette sortie.");

    } elseif ($action === 'desinscription') {
        $pdo->prepare('DELETE FROM inscriptions WHERE sortie_id = ? AND adherent_id = ?')
            ->execute([$id, $adherent['id']]);
        definir_message('succes', "Vous n'êtes plus inscrit à cette sortie.");

    } elseif ($action === 'supprimer') {
        exige_administrateur();
        $pdo->prepare('DELETE FROM sorties WHERE id = ?')->execute([$id]);
        definir_message('succes', "Sortie supprimée.");

    } elseif ($action === 'creer') {
        exige_administrateur();
        $titre = trim((string) ($_POST['titre'] ?? ''));
        $debut = trim((string) ($_POST['debut'] ?? ''));

        // Le champ datetime-local renvoie « 2026-09-12T14:30 ».
        $horodatage = strtotime($debut);

        if ($titre === '' || $horodatage === false) {
            definir_message('erreur', "Le titre et la date sont obligatoires.");
        } else {
            $pdo->prepare(
                'INSERT INTO sorties (titre, description, lieu, debut, rendez_vous, covoiturage)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $titre,
                trim((string) ($_POST['description'] ?? '')) ?: null,
                trim((string) ($_POST['lieu'] ?? '')) ?: null,
                date('Y-m-d H:i:s', $horodatage),
                trim((string) ($_POST['rendez_vous'] ?? '')) ?: null,
                isset($_POST['covoiturage']) ? 1 : 0,
            ]);
            definir_message('succes', "Sortie ajoutée à l'agenda.");
        }
    }

    header('Location: agenda.php');
    exit;
}

// À venir d'abord, puis les sorties passées.
$sorties = $pdo->query(
    'SELECT s.*,
            (SELECT COUNT(*) FROM inscriptions i WHERE i.sortie_id = s.id) AS nb_inscrits
       FROM sorties s
      ORDER BY s.debut ASC'
)->fetchAll();

// Sur quelles sorties suis-je inscrit ?
$requete = $pdo->prepare('SELECT sortie_id FROM inscriptions WHERE adherent_id = ?');
$requete->execute([$adherent['id']]);
$mes_inscriptions = array_column($requete->fetchAll(), 'sortie_id');

// Qui participe à quoi (pour afficher les noms).
$participants = [];
foreach ($pdo->query(
    'SELECT i.sortie_id, a.nom FROM inscriptions i JOIN adherents a ON a.id = i.adherent_id ORDER BY a.nom'
)->fetchAll() as $ligne) {
    $participants[(int) $ligne['sortie_id']][] = $ligne['nom'];
}

$maintenant = time();
$a_venir    = array_filter($sorties, static fn($s) => strtotime($s['debut']) >= $maintenant);
$passees    = array_reverse(array_filter($sorties, static fn($s) => strtotime($s['debut']) < $maintenant));

debut_page("Agenda", 'agenda');
titre_page("Agenda des sorties", "Les prochaines sorties du club, et qui y participe.");
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <?php if (est_administrateur()): ?>
    <details class="depot-bloc">
      <summary>Ajouter une sortie</summary>
      <form method="post" class="form-card" style="margin-top:16px;">
        <?= champ_csrf() ?>
        <input type="hidden" name="action" value="creer">
        <div class="field">
          <label for="titre">Titre</label>
          <input type="text" id="titre" name="titre" required maxlength="190"
                 placeholder="Sortie photo au port de La Turballe">
        </div>
        <div class="field">
          <label for="debut">Date et heure</label>
          <input type="datetime-local" id="debut" name="debut" required>
        </div>
        <div class="field">
          <label for="lieu">Lieu</label>
          <input type="text" id="lieu" name="lieu" maxlength="190" placeholder="Port de La Turballe">
        </div>
        <div class="field">
          <label for="rendez_vous">Point de rendez-vous</label>
          <input type="text" id="rendez_vous" name="rendez_vous" maxlength="190"
                 placeholder="Parking de la criée, 9h45">
        </div>
        <div class="field">
          <label for="description">Précisions (facultatif)</label>
          <textarea id="description" name="description" rows="3"></textarea>
        </div>
        <label class="case-a-cocher">
          <input type="checkbox" name="covoiturage" value="1">
          Covoiturage proposé
        </label>
        <button type="submit" class="btn btn-primary" style="margin-top:16px;">Ajouter la sortie</button>
      </form>
    </details>
  <?php endif; ?>

  <h2 class="titre-section">À venir</h2>
  <?php if (!$a_venir): ?>
    <div class="empty-state"><p>Aucune sortie programmée pour le moment.</p></div>
  <?php else: ?>
    <ul class="liste-sorties">
      <?php foreach ($a_venir as $sortie): ?>
        <?php $inscrit = in_array((int) $sortie['id'], array_map('intval', $mes_inscriptions), true); ?>
        <li class="sortie-carte<?= $inscrit ? ' sortie-inscrite' : '' ?>">
          <div class="sortie-date">
            <span class="sortie-jour"><?= (int) date('j', strtotime($sortie['debut'])) ?></span>
            <span class="sortie-mois"><?= e(mois_court($sortie['debut'])) ?></span>
          </div>
          <div class="sortie-corps">
            <h3><?= e($sortie['titre']) ?></h3>
            <p class="sortie-quand"><?= e(date_en_francais($sortie['debut'])) ?></p>
            <?php if ($sortie['lieu']): ?>
              <p class="sortie-detail">📍 <?= e($sortie['lieu']) ?></p>
            <?php endif; ?>
            <?php if ($sortie['rendez_vous']): ?>
              <p class="sortie-detail">🕘 Rendez-vous : <?= e($sortie['rendez_vous']) ?></p>
            <?php endif; ?>
            <?php if ($sortie['covoiturage']): ?>
              <p class="sortie-detail">🚗 Covoiturage proposé</p>
            <?php endif; ?>
            <?php if ($sortie['description']): ?>
              <p class="sortie-description"><?= nl2br(e($sortie['description'])) ?></p>
            <?php endif; ?>

            <p class="sortie-detail">
              <strong><?= (int) $sortie['nb_inscrits'] ?></strong> inscrit<?= $sortie['nb_inscrits'] > 1 ? 's' : '' ?>
              <?php if (!empty($participants[(int) $sortie['id']])): ?>
                : <?= e(implode(', ', $participants[(int) $sortie['id']])) ?>
              <?php endif; ?>
            </p>

            <div class="sortie-actions">
              <form method="post">
                <?= champ_csrf() ?>
                <input type="hidden" name="id" value="<?= (int) $sortie['id'] ?>">
                <input type="hidden" name="action" value="<?= $inscrit ? 'desinscription' : 'inscription' ?>">
                <button type="submit" class="btn <?= $inscrit ? 'btn-ghost' : 'btn-primary' ?>">
                  <?= $inscrit ? 'Me désinscrire' : "Je participe" ?>
                </button>
              </form>
              <?php if (est_administrateur()): ?>
                <form method="post" onsubmit="return confirm('Supprimer cette sortie et ses inscriptions ?');">
                  <?= champ_csrf() ?>
                  <input type="hidden" name="action" value="supprimer">
                  <input type="hidden" name="id" value="<?= (int) $sortie['id'] ?>">
                  <button type="submit" class="lien-danger">Supprimer</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($passees): ?>
    <h2 class="titre-section" style="margin-top:44px;">Sorties passées</h2>
    <ul class="liste-sorties liste-sorties-passees">
      <?php foreach (array_slice($passees, 0, 10) as $sortie): ?>
        <li class="sortie-carte">
          <div class="sortie-corps">
            <h3><?= e($sortie['titre']) ?></h3>
            <p class="sortie-quand"><?= e(date_en_francais($sortie['debut'], false)) ?>
              <?= $sortie['lieu'] ? ' — ' . e($sortie['lieu']) : '' ?></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div></section>
<?php
fin_page();

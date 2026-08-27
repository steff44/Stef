<?php
/*
 * Sorties à venir — liste détaillée des sorties (inscriptions, ajout,
 * suppression), sous-page de l'Agenda à côté du calendrier (agenda.php).
 * Seuls s'inscrire/se désinscrire exigent d'être connecté, et créer/modifier/
 * supprimer une sortie d'être responsable ou éditeur (choix explicite de
 * l'utilisateur, 17/08/2026, hérité de l'ancien agenda.php ; rôle éditeur
 * ajouté le 23/08/2026 — voir est_gestionnaire() dans inc/auth.php).
 *
 * Cliquer sur une sortie dans le calendrier de agenda.php renvoie ici, à
 * l'ancre #sortie-{id} — posée sur chaque carte, à venir comme passée.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/agenda.php';
require_once __DIR__ . '/inc/televersement.php';

const TAILLE_PHOTO_SORTIE = 400;

$adherent = adherent_connecte();
$pdo      = base_de_donnees();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'inscription') {
        $adherent = exige_connexion();
        // INSERT IGNORE : la clé unique empêche déjà la double inscription.
        $pdo->prepare('INSERT IGNORE INTO inscriptions (sortie_id, adherent_id) VALUES (?, ?)')
            ->execute([$id, $adherent['id']]);
        definir_message('succes', "Vous êtes inscrit à cette sortie.");

    } elseif ($action === 'desinscription') {
        $adherent = exige_connexion();
        $pdo->prepare('DELETE FROM inscriptions WHERE sortie_id = ? AND adherent_id = ?')
            ->execute([$id, $adherent['id']]);
        definir_message('succes', "Vous n'êtes plus inscrit à cette sortie.");

    } elseif ($action === 'supprimer') {
        exige_gestionnaire();
        $requete = $pdo->prepare('SELECT photo FROM sorties WHERE id = ?');
        $requete->execute([$id]);
        $sortie_supprimee = $requete->fetch();

        $pdo->prepare('DELETE FROM sorties WHERE id = ?')->execute([$id]);
        if ($sortie_supprimee && $sortie_supprimee['photo']) {
            @unlink(__DIR__ . '/photos/' . basename((string) $sortie_supprimee['photo']));
        }
        definir_message('succes', "Sortie supprimée.");

    } elseif ($action === 'modifier') {
        exige_gestionnaire();
        $titre     = trim((string) ($_POST['titre'] ?? ''));
        $debut     = trim((string) ($_POST['debut'] ?? ''));
        $categorie = (string) ($_POST['categorie'] ?? '');
        if (!in_array($categorie, CATEGORIES_SORTIES, true)) {
            $categorie = CATEGORIES_SORTIES[0];
        }

        $horodatage = strtotime($debut);

        if ($titre === '' || $horodatage === false) {
            definir_message('erreur', "Le titre et la date sont obligatoires.");
        } else {
            $requete = $pdo->prepare('SELECT photo FROM sorties WHERE id = ?');
            $requete->execute([$id]);
            $sortie_existante = $requete->fetch();

            if (!$sortie_existante) {
                definir_message('erreur', "Cette sortie n'existe plus.");
            } else {
                // La photo n'est remplacée que si un nouveau fichier est envoyé ;
                // sinon la photo déjà en place est conservée telle quelle.
                $photo       = $sortie_existante['photo'];
                $envoi_photo = $_FILES['photo'] ?? null;
                if ($envoi_photo && ($envoi_photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $resultat_photo = enregistrer_fichier_envoye($envoi_photo, __DIR__ . '/photos', 'image');
                    if ($resultat_photo['erreur'] !== null) {
                        definir_message('erreur', $resultat_photo['erreur']);
                        header('Location: sorties-a-venir.php');
                        exit;
                    }
                    if ($photo) {
                        @unlink(__DIR__ . '/photos/' . basename((string) $photo));
                    }
                    $photo = $resultat_photo['nom'];
                    redimensionner_en_carre(__DIR__ . '/photos/' . $photo, TAILLE_PHOTO_SORTIE);
                }

                $pdo->prepare(
                    'UPDATE sorties SET titre = ?, categorie = ?, description = ?, lieu = ?, debut = ?, rendez_vous = ?, covoiturage = ?, photo = ?
                     WHERE id = ?'
                )->execute([
                    $titre,
                    $categorie,
                    trim((string) ($_POST['description'] ?? '')) ?: null,
                    trim((string) ($_POST['lieu'] ?? '')) ?: null,
                    date('Y-m-d H:i:s', $horodatage),
                    trim((string) ($_POST['rendez_vous'] ?? '')) ?: null,
                    isset($_POST['covoiturage']) ? 1 : 0,
                    $photo,
                    $id,
                ]);
                definir_message('succes', "Sortie modifiée.");
            }
        }

    } elseif ($action === 'creer') {
        exige_gestionnaire();
        $titre     = trim((string) ($_POST['titre'] ?? ''));
        $debut     = trim((string) ($_POST['debut'] ?? ''));
        $categorie = (string) ($_POST['categorie'] ?? '');
        if (!in_array($categorie, CATEGORIES_SORTIES, true)) {
            $categorie = CATEGORIES_SORTIES[0];
        }

        // Le champ datetime-local renvoie « 2026-09-12T14:30 ».
        $horodatage = strtotime($debut);

        if ($titre === '' || $horodatage === false) {
            definir_message('erreur', "Le titre et la date sont obligatoires.");
        } else {
            // La photo est facultative : un fichier absent n'est pas une erreur.
            $photo       = null;
            $envoi_photo = $_FILES['photo'] ?? null;
            if ($envoi_photo && ($envoi_photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $resultat_photo = enregistrer_fichier_envoye($envoi_photo, __DIR__ . '/photos', 'image');
                if ($resultat_photo['erreur'] !== null) {
                    definir_message('erreur', $resultat_photo['erreur']);
                    header('Location: sorties-a-venir.php');
                    exit;
                }
                $photo = $resultat_photo['nom'];
                redimensionner_en_carre(__DIR__ . '/photos/' . $photo, TAILLE_PHOTO_SORTIE);
            }

            $pdo->prepare(
                'INSERT INTO sorties (titre, categorie, description, lieu, debut, rendez_vous, covoiturage, photo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $titre,
                $categorie,
                trim((string) ($_POST['description'] ?? '')) ?: null,
                trim((string) ($_POST['lieu'] ?? '')) ?: null,
                date('Y-m-d H:i:s', $horodatage),
                trim((string) ($_POST['rendez_vous'] ?? '')) ?: null,
                isset($_POST['covoiturage']) ? 1 : 0,
                $photo,
            ]);
            definir_message('succes', "Ajouté à l'agenda.");
        }
    }

    header('Location: sorties-a-venir.php');
    exit;
}

// À venir d'abord, puis les sorties passées.
$sorties = $pdo->query(
    'SELECT s.*,
            (SELECT COUNT(*) FROM inscriptions i WHERE i.sortie_id = s.id) AS nb_inscrits
       FROM sorties s
      ORDER BY s.debut ASC'
)->fetchAll();

// Sur quelles sorties suis-je inscrit ? (rien à calculer pour un visiteur anonyme)
$mes_inscriptions = [];
if ($adherent) {
    $requete = $pdo->prepare('SELECT sortie_id FROM inscriptions WHERE adherent_id = ?');
    $requete->execute([$adherent['id']]);
    $mes_inscriptions = array_column($requete->fetchAll(), 'sortie_id');
}

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

debut_page("Sorties à venir", 'sorties');
titre_page("Sorties à venir", "Les prochaines sorties du club, et qui y participe.", false, true);
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <p style="margin:-8px 0 24px;">
    <a class="btn btn-ghost" href="agenda.php">← Voir le calendrier</a>
  </p>

  <?php if (est_gestionnaire()): ?>
    <details class="depot-bloc">
      <summary>Ajouter une sortie</summary>
      <form method="post" enctype="multipart/form-data" class="form-card" style="margin-top:16px;">
        <?= champ_csrf() ?>
        <input type="hidden" name="action" value="creer">
        <div class="field">
          <label for="titre">Titre</label>
          <input type="text" id="titre" name="titre" required maxlength="190"
                 placeholder="Sortie photo au port de La Turballe">
        </div>
        <div class="field">
          <label for="categorie">Catégorie</label>
          <select id="categorie" name="categorie">
            <?php foreach (CATEGORIES_SORTIES as $categorie): ?>
              <option value="<?= e($categorie) ?>"><?= e($categorie) ?></option>
            <?php endforeach; ?>
          </select>
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
        <div class="field">
          <label for="photo">Photo (facultatif — JPEG, PNG, WebP ou GIF, recadrée automatiquement en carré <?= TAILLE_PHOTO_SORTIE ?>×<?= TAILLE_PHOTO_SORTIE ?>, <?= taille_lisible(TAILLE_MAX_OCTETS) ?> maximum)</label>
          <input type="file" id="photo" name="photo" accept="image/*"
                 data-taille-max="<?= TAILLE_MAX_OCTETS ?>"
                 data-taille-max-lisible="<?= e(taille_lisible(TAILLE_MAX_OCTETS)) ?>">
          <p class="form-avertissement" data-avertissement-taille hidden></p>
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
        <li id="sortie-<?= (int) $sortie['id'] ?>" class="sortie-carte sortie-carte--<?= classe_categorie($sortie['categorie']) ?><?= $inscrit ? ' sortie-inscrite' : '' ?>">
          <div class="sortie-date">
            <span class="sortie-jour"><?= (int) date('j', strtotime($sortie['debut'])) ?></span>
            <span class="sortie-mois"><?= e(mois_court($sortie['debut'])) ?></span>
          </div>
          <?php if ($sortie['photo']): ?>
            <img class="sortie-photo" src="telecharger.php?type=sortie&amp;id=<?= (int) $sortie['id'] ?><?= e(version_fichier(__DIR__ . '/photos/' . $sortie['photo'])) ?>"
                 alt="" loading="lazy">
          <?php endif; ?>
          <div class="sortie-corps">
            <h3><?= e($sortie['titre']) ?>
              <span class="categorie-badge categorie-badge--<?= classe_categorie($sortie['categorie']) ?>"><?= e($sortie['categorie']) ?></span>
            </h3>
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
              <p class="sortie-description"><?= texte_avec_liens_html((string) $sortie['description']) ?></p>
            <?php endif; ?>

            <p class="sortie-detail">
              <strong><?= (int) $sortie['nb_inscrits'] ?></strong> inscrit<?= $sortie['nb_inscrits'] > 1 ? 's' : '' ?>
              <?php if (!empty($participants[(int) $sortie['id']])): ?>
                : <?= e(implode(', ', $participants[(int) $sortie['id']])) ?>
              <?php endif; ?>
            </p>

            <div class="sortie-actions">
              <?php if ($adherent): ?>
                <form method="post">
                  <?= champ_csrf() ?>
                  <input type="hidden" name="id" value="<?= (int) $sortie['id'] ?>">
                  <input type="hidden" name="action" value="<?= $inscrit ? 'desinscription' : 'inscription' ?>">
                  <button type="submit" class="btn <?= $inscrit ? 'btn-ghost' : 'btn-primary' ?>">
                    <?= $inscrit ? 'Me désinscrire' : "Je participe" ?>
                  </button>
                </form>
              <?php else: ?>
                <a class="btn btn-ghost" href="connexion.php">Se connecter pour participer</a>
              <?php endif; ?>
              <?php if (est_gestionnaire()): ?>
                <details class="sortie-modifier">
                  <summary class="btn btn-ghost">Modifier</summary>
                  <form method="post" enctype="multipart/form-data" class="form-card" style="margin-top:16px;">
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="modifier">
                    <input type="hidden" name="id" value="<?= (int) $sortie['id'] ?>">
                    <div class="field">
                      <label for="titre-<?= (int) $sortie['id'] ?>">Titre</label>
                      <input type="text" id="titre-<?= (int) $sortie['id'] ?>" name="titre" required maxlength="190"
                             value="<?= e($sortie['titre']) ?>">
                    </div>
                    <div class="field">
                      <label for="categorie-<?= (int) $sortie['id'] ?>">Catégorie</label>
                      <select id="categorie-<?= (int) $sortie['id'] ?>" name="categorie">
                        <?php foreach (CATEGORIES_SORTIES as $categorie_option): ?>
                          <option value="<?= e($categorie_option) ?>" <?= $categorie_option === $sortie['categorie'] ? 'selected' : '' ?>><?= e($categorie_option) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="field">
                      <label for="debut-<?= (int) $sortie['id'] ?>">Date et heure</label>
                      <input type="datetime-local" id="debut-<?= (int) $sortie['id'] ?>" name="debut" required
                             value="<?= e(date('Y-m-d\TH:i', strtotime($sortie['debut']))) ?>">
                    </div>
                    <div class="field">
                      <label for="lieu-<?= (int) $sortie['id'] ?>">Lieu</label>
                      <input type="text" id="lieu-<?= (int) $sortie['id'] ?>" name="lieu" maxlength="190"
                             value="<?= e((string) $sortie['lieu']) ?>">
                    </div>
                    <div class="field">
                      <label for="rendez_vous-<?= (int) $sortie['id'] ?>">Point de rendez-vous</label>
                      <input type="text" id="rendez_vous-<?= (int) $sortie['id'] ?>" name="rendez_vous" maxlength="190"
                             value="<?= e((string) $sortie['rendez_vous']) ?>">
                    </div>
                    <div class="field">
                      <label for="description-<?= (int) $sortie['id'] ?>">Précisions (facultatif)</label>
                      <textarea id="description-<?= (int) $sortie['id'] ?>" name="description" rows="3"><?= e((string) $sortie['description']) ?></textarea>
                    </div>
                    <div class="field">
                      <label for="photo-<?= (int) $sortie['id'] ?>">Photo (facultatif — <?= $sortie['photo'] ? 'laissez vide pour garder la photo actuelle, ou remplacez-la' : 'recadrée automatiquement en carré ' . TAILLE_PHOTO_SORTIE . '×' . TAILLE_PHOTO_SORTIE ?>, <?= taille_lisible(TAILLE_MAX_OCTETS) ?> maximum)</label>
                      <input type="file" id="photo-<?= (int) $sortie['id'] ?>" name="photo" accept="image/*"
                             data-taille-max="<?= TAILLE_MAX_OCTETS ?>"
                             data-taille-max-lisible="<?= e(taille_lisible(TAILLE_MAX_OCTETS)) ?>">
                      <p class="form-avertissement" data-avertissement-taille hidden></p>
                    </div>
                    <label class="case-a-cocher">
                      <input type="checkbox" name="covoiturage" value="1" <?= $sortie['covoiturage'] ? 'checked' : '' ?>>
                      Covoiturage proposé
                    </label>
                    <button type="submit" class="btn btn-primary" style="margin-top:16px;">Enregistrer les modifications</button>
                  </form>
                </details>
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
        <li id="sortie-<?= (int) $sortie['id'] ?>" class="sortie-carte sortie-carte--<?= classe_categorie($sortie['categorie']) ?>">
          <div class="sortie-corps">
            <h3><?= e($sortie['titre']) ?>
              <span class="categorie-badge categorie-badge--<?= classe_categorie($sortie['categorie']) ?>"><?= e($sortie['categorie']) ?></span>
            </h3>
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

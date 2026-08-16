<?php
/*
 * Gestion des comptes — réservée aux responsables.
 * Créer un adhérent, réinitialiser son mot de passe, le désactiver.
 *
 * On ne supprime jamais un compte : on le désactive. Les photos et documents
 * qu'il a déposés restent ainsi rattachés à son nom.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

$adherent = exige_administrateur();
$pdo      = base_de_donnees();

/* Mot de passe provisoire lisible, à transmettre au nouvel adhérent. */
function mot_de_passe_provisoire(): string
{
    // Sans caractères ambigus (0/O, 1/l/I) : il sera recopié à la main.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $mot      = '';
    for ($i = 0; $i < 12; $i++) {
        $mot .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $mot;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'creer') {
        $identifiant = trim((string) ($_POST['identifiant'] ?? ''));
        $nom         = trim((string) ($_POST['nom'] ?? ''));
        $email       = trim((string) ($_POST['email'] ?? ''));

        if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $identifiant)) {
            definir_message('erreur', "L'identifiant doit faire 3 à 60 caractères, sans espace ni accent.");
        } elseif ($nom === '') {
            definir_message('erreur', "Le nom est obligatoire.");
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            definir_message('erreur', "L'adresse e-mail n'est pas valide.");
        } else {
            $provisoire = mot_de_passe_provisoire();
            try {
                $pdo->prepare(
                    'INSERT INTO adherents (identifiant, nom, email, telephone, mot_de_passe, administrateur)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $identifiant,
                    $nom,
                    $email ?: null,
                    trim((string) ($_POST['telephone'] ?? '')) ?: null,
                    password_hash($provisoire, PASSWORD_DEFAULT),
                    isset($_POST['administrateur']) ? 1 : 0,
                ]);
                // Affiché une seule fois : il n'est stocké nulle part en clair.
                definir_message('succes', "Compte créé. Mot de passe provisoire de {$nom} : {$provisoire} — notez-le et transmettez-le, il ne sera plus affiché.");
            } catch (PDOException $e) {
                // 23000 = contrainte violée, ici l'identifiant déjà pris.
                if ($e->getCode() === '23000') {
                    definir_message('erreur', "L'identifiant « {$identifiant} » est déjà utilisé.");
                } else {
                    throw $e;
                }
            }
        }

    } elseif ($action === 'reinitialiser') {
        $provisoire = mot_de_passe_provisoire();
        $requete    = $pdo->prepare('SELECT nom FROM adherents WHERE id = ?');
        $requete->execute([$id]);

        if ($nom = $requete->fetchColumn()) {
            $pdo->prepare('UPDATE adherents SET mot_de_passe = ? WHERE id = ?')
                ->execute([password_hash($provisoire, PASSWORD_DEFAULT), $id]);
            definir_message('succes', "Nouveau mot de passe provisoire de {$nom} : {$provisoire} — notez-le, il ne sera plus affiché.");
        }

    } elseif ($action === 'deconnecter') {
        // On ne supprime pas la session côté serveur — on ne peut pas
        // atteindre celle d'un autre visiteur. On date la coupure : à sa
        // requête suivante, signaler_presence() constate que sa session est
        // antérieure et la ferme. La personne peut se reconnecter ensuite.
        $requete = $pdo->prepare('SELECT nom FROM adherents WHERE id = ?');
        $requete->execute([$id]);

        if ($nom = $requete->fetchColumn()) {
            $pdo->prepare(
                'UPDATE adherents SET deconnecte_le = NOW(), derniere_activite = NULL WHERE id = ?'
            )->execute([$id]);
            $precision = $id === $adherent['id'] ? " Vous allez être renvoyé à la page de connexion." : '';
            definir_message('succes', "Session de {$nom} fermée.{$precision}");
        }

    } elseif ($action === 'basculer_actif') {
        // Un responsable ne peut pas se désactiver lui-même : ce serait le
        // meilleur moyen de se fermer la porte au nez.
        if ($id === $adherent['id']) {
            definir_message('erreur', "Vous ne pouvez pas désactiver votre propre compte.");
        } else {
            $pdo->prepare('UPDATE adherents SET actif = 1 - actif WHERE id = ?')->execute([$id]);
            definir_message('succes', "Statut du compte modifié.");
        }

    } elseif ($action === 'basculer_admin') {
        if ($id === $adherent['id']) {
            definir_message('erreur', "Vous ne pouvez pas retirer votre propre rôle de responsable.");
        } else {
            $pdo->prepare('UPDATE adherents SET administrateur = 1 - administrateur WHERE id = ?')->execute([$id]);
            definir_message('succes', "Rôle modifié.");
        }
    }

    header('Location: adherents.php');
    exit;
}

// « Connecté » = une page consultée dans les dernières minutes, et pas de
// coupure demandée depuis. C'est le plus près de la vérité qu'on puisse être :
// rien ne signale au serveur qu'un onglet vient d'être fermé.
$requete = $pdo->prepare(
    'SELECT id, identifiant, nom, email, telephone, administrateur, actif,
            derniere_connexion, derniere_activite,
            (derniere_activite IS NOT NULL
              AND derniere_activite >= (NOW() - INTERVAL ? MINUTE)
              AND (deconnecte_le IS NULL OR derniere_activite > deconnecte_le)) AS en_ligne
       FROM adherents
      ORDER BY actif DESC, nom'
);
$requete->execute([DELAI_PRESENCE_MINUTES]);
$membres = $requete->fetchAll();

debut_page("Adhérents", 'adherents');
titre_page("Gestion des adhérents", "Créer les comptes, réinitialiser les mots de passe, activer ou désactiver un accès.");
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <details class="depot-bloc">
    <summary>Créer un compte adhérent</summary>
    <form method="post" class="form-card" style="margin-top:16px;">
      <?= champ_csrf() ?>
      <input type="hidden" name="action" value="creer">
      <div class="field">
        <label for="identifiant">Identifiant de connexion</label>
        <input type="text" id="identifiant" name="identifiant" required placeholder="claire">
      </div>
      <div class="field">
        <label for="nom">Nom complet</label>
        <input type="text" id="nom" name="nom" required placeholder="Claire Martin">
      </div>
      <div class="field">
        <label for="email">E-mail (facultatif)</label>
        <input type="email" id="email" name="email">
      </div>
      <div class="field">
        <label for="telephone">Téléphone (facultatif)</label>
        <input type="tel" id="telephone" name="telephone">
      </div>
      <label class="case-a-cocher">
        <input type="checkbox" name="administrateur" value="1">
        Responsable (peut gérer les comptes, les documents et l'agenda)
      </label>
      <p class="form-note" style="margin:14px 0;">
        Un mot de passe provisoire sera affiché une seule fois, juste après la création.
        Notez-le et transmettez-le à l'adhérent, qui pourra le changer depuis l'onglet Annuaire.
      </p>
      <button type="submit" class="btn btn-primary">Créer le compte</button>
    </form>
  </details>

  <div class="tableau-defilant">
    <table class="tableau-adherents">
      <thead>
        <tr>
          <th>Connecté</th>
          <th>Nom</th>
          <th>Pseudo</th>
          <th>E-mail</th>
          <th>Dernière connexion</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($membres as $membre): ?>
          <?php $en_ligne = (bool) $membre['en_ligne']; ?>
          <tr<?= $membre['actif'] ? '' : ' class="compte-inactif"' ?>>
            <td>
              <?php if ($en_ligne): ?>
                <span class="pastille-en-ligne" title="Actif il y a moins de <?= DELAI_PRESENCE_MINUTES ?> minutes"></span>
                <span class="etat-en-ligne">en ligne</span>
              <?php else: ?>
                <span class="pastille-hors-ligne"></span>
                <span class="etat-hors-ligne">hors ligne</span>
              <?php endif; ?>
            </td>
            <td>
              <?= e($membre['nom']) ?>
              <?= $membre['administrateur'] ? ' <span class="badge-admin">responsable</span>' : '' ?>
              <?= $membre['actif'] ? '' : ' <span class="badge-inactif">désactivé</span>' ?>
            </td>
            <td><?= e($membre['identifiant']) ?></td>
            <td>
              <?= $membre['email'] ? e($membre['email']) : '—' ?>
              <?php if ($membre['telephone']): ?>
                <br><span class="contact-secondaire"><?= e($membre['telephone']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= $membre['derniere_connexion'] ? e(date_en_francais($membre['derniere_connexion'], false)) : 'jamais' ?></td>
            <td class="cellule-actions">
              <?php if ($en_ligne): ?>
                <form method="post" onsubmit="return confirm(<?= $membre['id'] === $adherent['id'] ? "'Fermer votre propre session ? Vous devrez vous reconnecter.'" : "'Fermer la session de cet adhérent ?'" ?>);">
                  <?= champ_csrf() ?>
                  <input type="hidden" name="action" value="deconnecter">
                  <input type="hidden" name="id" value="<?= (int) $membre['id'] ?>">
                  <button type="submit" class="lien-action lien-deconnecter">Déconnecter</button>
                </form>
              <?php endif; ?>
              <form method="post" onsubmit="return confirm('Générer un nouveau mot de passe provisoire ?');">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="reinitialiser">
                <input type="hidden" name="id" value="<?= (int) $membre['id'] ?>">
                <button type="submit" class="lien-action">Réinitialiser le mot de passe</button>
              </form>
              <?php if ((int) $membre['id'] !== $adherent['id']): ?>
                <form method="post">
                  <?= champ_csrf() ?>
                  <input type="hidden" name="action" value="basculer_actif">
                  <input type="hidden" name="id" value="<?= (int) $membre['id'] ?>">
                  <button type="submit" class="lien-action"><?= $membre['actif'] ? 'Désactiver' : 'Réactiver' ?></button>
                </form>
                <form method="post">
                  <?= champ_csrf() ?>
                  <input type="hidden" name="action" value="basculer_admin">
                  <input type="hidden" name="id" value="<?= (int) $membre['id'] ?>">
                  <button type="submit" class="lien-action"><?= $membre['administrateur'] ? 'Retirer responsable' : 'Nommer responsable' ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></section>
<?php
fin_page();

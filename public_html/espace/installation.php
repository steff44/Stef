<?php
/*
 * Installation de l'espace adhérents, à lancer UNE SEULE FOIS :
 * crée les tables, puis le premier compte responsable.
 *
 * Dès qu'un compte existe, cette page se verrouille d'elle-même : elle ne
 * peut plus servir à créer un second administrateur. Une fois l'installation
 * faite, vous pouvez supprimer ce fichier depuis le Gestionnaire de fichiers.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

$pdo = base_de_donnees();

// Les tables existent-elles déjà, et contiennent-elles un compte ?
$installe = false;
try {
    $installe = (int) $pdo->query('SELECT COUNT(*) FROM adherents')->fetchColumn() > 0;
} catch (PDOException) {
    // Table absente : c'est bien une première installation.
}

if ($installe) {
    debut_page("Installation");
    titre_page("Installation déjà faite");
    ?>
    <section class="section"><div class="container">
      <div class="form-card" style="max-width:560px;margin:0 auto;">
        <p style="color:var(--text-muted);margin:0 0 20px;">
          L'espace adhérents est déjà installé et contient au moins un compte.
          Pour ajouter d'autres adhérents, connectez-vous puis ouvrez l'onglet
          <strong>Adhérents</strong>.
        </p>
        <p style="color:var(--text-muted);margin:0 0 20px;">
          Vous pouvez maintenant supprimer le fichier
          <code>espace/installation.php</code> depuis le Gestionnaire de fichiers.
        </p>
        <a class="btn btn-primary" href="connexion.php">Aller à la connexion</a>
      </div>
    </div></section>
    <?php
    fin_page();
    exit;
}

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $identifiant = trim((string) ($_POST['identifiant'] ?? ''));
    $nom         = trim((string) ($_POST['nom'] ?? ''));
    $email       = trim((string) ($_POST['email'] ?? ''));
    $mot1        = (string) ($_POST['mot_de_passe'] ?? '');
    $mot2        = (string) ($_POST['confirmation'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $identifiant)) {
        $erreurs[] = "L'identifiant doit faire 3 à 60 caractères, sans espace ni accent (lettres, chiffres, point, tiret).";
    }
    if ($nom === '') {
        $erreurs[] = "Le nom est obligatoire.";
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'adresse e-mail n'est pas valide.";
    }
    if (mb_strlen($mot1) < 10) {
        $erreurs[] = "Le mot de passe doit contenir au moins 10 caractères.";
    }
    if ($mot1 !== $mot2) {
        $erreurs[] = "Les deux mots de passe ne sont pas identiques.";
    }

    if (!$erreurs) {
        // Création des tables.
        $sql = file_get_contents(__DIR__ . '/inc/schema.sql');
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $instruction) {
            $pdo->exec($instruction);
        }

        // Deuxième vérification, au cas où deux personnes ouvriraient la page
        // en même temps.
        if ((int) $pdo->query('SELECT COUNT(*) FROM adherents')->fetchColumn() > 0) {
            header('Location: connexion.php');
            exit;
        }

        $creation = $pdo->prepare(
            'INSERT INTO adherents (identifiant, nom, email, mot_de_passe, administrateur, actif)
             VALUES (?, ?, ?, ?, 1, 1)'
        );
        $creation->execute([
            $identifiant,
            $nom,
            $email !== '' ? $email : null,
            password_hash($mot1, PASSWORD_DEFAULT),
        ]);

        definir_message('succes', "Installation terminée. Vous pouvez vous connecter.");
        header('Location: connexion.php');
        exit;
    }
}

debut_page("Installation");
titre_page(
    "Installation de l'espace adhérents",
    "Cette page ne s'affiche qu'une fois : elle crée les tables et le premier compte responsable."
);
?>
<section class="section"><div class="container">
  <div class="form-card" style="max-width:520px;margin:0 auto;">
    <?php foreach ($erreurs as $erreur): ?>
      <div class="alerte alerte-erreur"><?= e($erreur) ?></div>
    <?php endforeach; ?>

    <form method="post" autocomplete="off">
      <?= champ_csrf() ?>
      <div class="field">
        <label for="identifiant">Identifiant de connexion</label>
        <input type="text" id="identifiant" name="identifiant" required
               value="<?= e($_POST['identifiant'] ?? '') ?>" placeholder="stephane">
      </div>
      <div class="field">
        <label for="nom">Nom complet</label>
        <input type="text" id="nom" name="nom" required
               value="<?= e($_POST['nom'] ?? '') ?>" placeholder="Stéphane Lecomte">
      </div>
      <div class="field">
        <label for="email">E-mail (facultatif)</label>
        <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="mot_de_passe">Mot de passe (10 caractères minimum)</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" required minlength="10">
      </div>
      <div class="field">
        <label for="confirmation">Confirmer le mot de passe</label>
        <input type="password" id="confirmation" name="confirmation" required minlength="10">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Installer</button>
    </form>
  </div>
</div></section>
<?php
fin_page();

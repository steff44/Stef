<?php
/*
 * Une ligne de la liste des documents (documents.php) — extrait en partiel
 * pour être inclus une fois par sous-catégorie sans dupliquer le HTML.
 * Attend $document (une ligne de la requête sur `documents`) dans la portée
 * appelante. `data-titre` sert au filtrage JavaScript par la recherche.
 */
declare(strict_types=1);
?>
        <li class="document-ligne" data-titre="<?= e(mb_strtolower($document['titre'])) ?>">
          <div class="document-infos">
            <a class="document-titre" href="telecharger.php?type=document&amp;id=<?= (int) $document['id'] ?>">
              <?= e($document['titre']) ?>
            </a>
            <?php if ($document['description']): ?>
              <p class="document-description"><?= e($document['description']) ?></p>
            <?php endif; ?>
            <p class="document-meta">
              <?= e($document['nom_origine']) ?> — <?= e(taille_lisible((int) $document['taille'])) ?>
              — déposé le <?= e(date_en_francais($document['cree_le'], false)) ?>
              <?= $document['auteur'] ? ' par ' . e($document['auteur']) : '' ?>
            </p>
          </div>
          <div class="document-actions">
            <a class="btn btn-ghost" href="telecharger.php?type=document&amp;id=<?= (int) $document['id'] ?>">Télécharger</a>
            <?php if (est_gestionnaire()): ?>
              <form method="post" onsubmit="return confirm('Supprimer ce document ?');">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="id" value="<?= (int) $document['id'] ?>">
                <button type="submit" class="lien-danger">Supprimer</button>
              </form>
            <?php endif; ?>
          </div>
        </li>

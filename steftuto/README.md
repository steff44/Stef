# Steftuto

Steftuto est un petit logiciel de bureau qui vous permet de classer vos
tutoriels de photo (fichiers `.docx`, `.pdf`, `.xls`, `.txt`, `.exe`,
`.zip`, `.jpg`... peu importe l'extension) et de les retrouver facilement.

- Un tuto peut appartenir à **plusieurs rubriques** et à **plusieurs
  thèmes** en même temps.
- Vous pouvez rechercher par texte, par rubrique, par thème, ou
  combiner les trois.
- Steftuto ne déplace ni ne copie jamais vos fichiers : il se contente
  d'enregistrer où ils se trouvent sur votre ordinateur. Un double-clic
  sur un tuto l'ouvre avec le programme habituel (Word, Adobe Reader,
  votre visionneuse d'images, etc.).

## Installation

Il faut avoir Python 3 installé sur votre ordinateur (déjà présent sur
la plupart des Mac et Linux ; sur Windows, téléchargez-le sur
[python.org](https://www.python.org/downloads/) en cochant bien
« Add Python to PATH » à l'installation).

Aucune autre installation n'est nécessaire : Steftuto n'utilise que des
outils fournis avec Python.

## Lancer Steftuto

Depuis un terminal (ou une invite de commandes), dans le dossier
`steftuto` :

```
python3 steftuto.py
```

Sur Windows, `python` fonctionne aussi si `python3` n'est pas reconnu :

```
python steftuto.py
```

Vous pouvez aussi créer un raccourci vers cette commande sur votre
bureau pour lancer Steftuto en un double-clic.

## Utilisation

- **Ajouter un tuto** : bouton « Ajouter un tuto… », puis « Parcourir… »
  pour choisir le fichier sur votre disque. Le titre se remplit
  automatiquement avec le nom du fichier (modifiable). Cochez ensuite
  une ou plusieurs rubriques et un ou plusieurs thèmes (maintenez
  Ctrl enfoncé pour en sélectionner plusieurs). Vous pouvez créer une
  nouvelle rubrique ou un nouveau thème directement depuis ce
  formulaire, avec les boutons « + Nouvelle rubrique » / « + Nouveau
  thème ».
- **Rechercher** : en haut de la fenêtre, tapez du texte (cherché dans
  le titre et la description) et/ou sélectionnez une ou plusieurs
  rubriques et thèmes dans les listes. Un tuto apparaît dès qu'il
  correspond à *au moins une* des rubriques cochées et *au moins un*
  des thèmes cochés (en plus du texte, si renseigné). « Réinitialiser
  les filtres » efface tout.
- **Ouvrir un tuto** : double-clic dessus dans la liste, ou bouton
  « Ouvrir ». Si le fichier a été déplacé depuis, Steftuto vous propose
  de retrouver son nouvel emplacement.
- **Modifier / Supprimer** : sélectionnez un tuto dans la liste puis
  utilisez les boutons correspondants. Supprimer une fiche ne supprime
  jamais le fichier lui-même sur votre disque.
- **Gérer les rubriques / les thèmes** (menu « Gérer ») : renommer ou
  supprimer une rubrique ou un thème. Une suppression est refusée tant
  qu'au moins un tuto l'utilise encore, pour ne jamais perdre de
  classement par erreur.

## Où sont stockées vos données ?

Dans un fichier `steftuto.db` créé automatiquement à côté de
`steftuto.py`, la première fois que vous lancez le logiciel. C'est une
petite base de données autonome (SQLite) : pour sauvegarder votre
catalogue, il suffit de copier ce fichier ailleurs (clé USB, cloud...).
Il n'est jamais envoyé où que ce soit par le logiciel.

## Rubriques et thèmes par défaut

Steftuto est livré avec quelques rubriques et thèmes de départ, à
adapter librement (renommer, compléter, supprimer) selon votre propre
façon de classer :

- **Rubriques** : Prise de vue, Post-traitement, Matériel, Logiciel,
  Technique, Composition.
- **Thèmes** : Portrait, Paysage, Macro / Proxi, Architecture,
  Animalier, Sport, Night / Astro, Noir et blanc, Lightroom,
  Photoshop.

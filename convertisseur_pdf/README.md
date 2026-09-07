# Convertisseur PDF

Convertisseur PDF est un petit logiciel de bureau qui transforme en PDF tous
les fichiers Word (`.docx`, `.doc`) d'un dossier — sous-dossiers compris —
en une seule opération.

- Vous choisissez **un dossier** (par exemple un des dossiers de vos fiches
  de formation, avec un sous-dossier par module).
- Le logiciel crée **un nouveau dossier juste à côté**, portant le même nom
  suivi de « (PDF) » (un nom strictement identique étant impossible au même
  endroit), avec la version PDF de chaque fichier Word trouvé — en
  reproduisant exactement la même organisation en sous-dossiers.
- Vos fichiers Word d'origine ne sont jamais modifiés ni déplacés.

## Comment la conversion est faite

Le logiciel choisit automatiquement le meilleur outil disponible sur votre
ordinateur :

1. **Microsoft Word**, si Word est installé sur votre PC (avec le module
   `pywin32`, voir plus bas) : c'est la conversion la plus fidèle, celle qui
   reproduit exactement la mise en page, les couleurs et les images, comme
   si vous faisiez vous-même *Fichier > Exporter > Créer un PDF* depuis
   Word.
2. **LibreOffice**, à défaut de Word/pywin32 : gratuit, sur
   [fr.libreoffice.org](https://fr.libreoffice.org/telecharger/).

Si aucun des deux n'est trouvé, le logiciel vous l'indique clairement avec
un message d'erreur, plutôt que d'échouer en silence.

## Installation

Il faut avoir Python 3 installé sur votre ordinateur (déjà présent sur la
plupart des Mac et Linux ; sur Windows, téléchargez-le sur
[python.org](https://www.python.org/downloads/) en cochant bien « Add
Python to PATH » à l'installation).

Pour utiliser Microsoft Word (recommandé, si vous l'avez déjà) plutôt que
LibreOffice, installez en plus le module `pywin32` (Windows uniquement) :

```
pip install pywin32
```

Sans ce module (ou sous Mac/Linux), le logiciel utilise automatiquement
LibreOffice s'il est installé — aucune autre installation n'est requise
dans ce cas.

## Lancer Convertisseur PDF

Depuis un terminal (ou une invite de commandes), dans le dossier
`convertisseur_pdf` :

```
python3 convertisseur_pdf.py
```

Sur Windows, `python` fonctionne aussi si `python3` n'est pas reconnu :

```
python convertisseur_pdf.py
```

Vous pouvez aussi créer un raccourci vers cette commande sur votre bureau
pour lancer le logiciel en un double-clic.

## Fabriquer un exécutable Windows (.exe)

Si vous préférez un vrai `.exe`, à double-cliquer sans jamais taper de
commande (et à partager avec quelqu'un qui n'a pas Python), le logiciel
peut être transformé en exécutable autonome grâce à
[PyInstaller](https://pyinstaller.org/). Cette fabrication se fait **une
seule fois, sur un PC Windows** (Python doit y être installé au moment de
la fabrication seulement — le `.exe` obtenu, lui, n'en a plus besoin et
peut être copié sur n'importe quel autre PC Windows).

1. Ouvrez le dossier `convertisseur_pdf` sur ce PC Windows.
2. Double-cliquez sur `build_exe.bat`.
3. Une fenêtre noire s'ouvre, installe PyInstaller et pywin32 (pour utiliser
   Word si présent), puis fabrique l'exécutable (une minute ou deux). À la
   fin, vous obtenez :
   ```
   convertisseur_pdf\dist\ConvertisseurPDF.exe
   ```
4. Copiez ce fichier où vous voulez (Bureau, clé USB...). Un double-clic
   dessus suffit à lancer le logiciel.

Pour fabriquer l'exécutable à la main plutôt que via `build_exe.bat` :

```
pip install pyinstaller pywin32
pyinstaller --onefile --windowed --name ConvertisseurPDF --icon icone.ico convertisseur_pdf.py
```

## Utilisation

1. Cliquez sur **« Choisir un dossier à convertir… »** et sélectionnez le
   dossier contenant vos fichiers Word (par exemple un dossier de module
   avec ses fiches).
2. Le logiciel indique combien de fichiers Word il a trouvés (y compris dans
   les sous-dossiers).
3. Cliquez sur **« Convertir en PDF »**. La progression s'affiche fichier
   par fichier, avec une coche verte (✓) en cas de réussite ou une croix
   rouge (✗) suivie du message d'erreur en cas d'échec sur un fichier
   précis — les autres fichiers continuent d'être traités.
4. À la fin, un message récapitule le nombre de fichiers convertis. Le
   bouton **« Ouvrir le dossier obtenu »** ouvre directement le nouveau
   dossier PDF dans l'explorateur de fichiers.

Vous pouvez relancer une conversion sur le même dossier plus tard : un
nouveau dossier de résultat est alors créé (avec un numéro à la fin de son
nom) plutôt que d'écraser le précédent.

#!/usr/bin/env python3
"""Steftuto - catalogue personnel de tutoriels photo.

Application de bureau (Tkinter, sans dépendance externe) qui permet de
classer des fichiers de tutoriels (docx, pdf, xls, txt, exe, zip, jpg...)
selon deux axes indépendants - une ou plusieurs rubriques et un ou
plusieurs thèmes par tuto - puis de les retrouver par recherche libre,
par rubrique ou par thème.

Steftuto ne déplace ni ne copie jamais les fichiers : il enregistre
seulement leur emplacement sur le disque, comme un catalogue. Un
double-clic ouvre le fichier avec l'application par défaut du système.

Lancement :  python3 steftuto.py
Données :    steftuto.db (créé automatiquement à côté de ce fichier)
"""

import os
import sqlite3
import subprocess
import sys
import tkinter as tk
from datetime import datetime
from tkinter import filedialog, messagebox, simpledialog, ttk

APP_TITLE = "Steftuto"
DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "steftuto.db")

RUBRIQUES_PAR_DEFAUT = [
    "Prise de vue",
    "Post-traitement",
    "Matériel",
    "Logiciel",
    "Technique",
    "Composition",
]

THEMES_PAR_DEFAUT = [
    "Portrait",
    "Paysage",
    "Macro / Proxi",
    "Architecture",
    "Animalier",
    "Sport",
    "Night / Astro",
    "Noir et blanc",
    "Lightroom",
    "Photoshop",
]


class ErreurUtilisation(Exception):
    """Levée quand on tente de supprimer une rubrique/thème encore utilisé."""


class Base:
    """Accès aux données : un tuto peut avoir plusieurs rubriques et
    plusieurs thèmes (relations many-to-many séparées)."""

    def __init__(self, chemin):
        self.connexion = sqlite3.connect(chemin)
        self.connexion.execute("PRAGMA foreign_keys = ON")
        self.connexion.row_factory = sqlite3.Row
        self._creer_schema()
        self._semer_valeurs_par_defaut()

    def _creer_schema(self):
        self.connexion.executescript(
            """
            CREATE TABLE IF NOT EXISTS rubriques (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT UNIQUE NOT NULL
            );
            CREATE TABLE IF NOT EXISTS themes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT UNIQUE NOT NULL
            );
            CREATE TABLE IF NOT EXISTS tutos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                titre TEXT NOT NULL,
                chemin_fichier TEXT NOT NULL,
                description TEXT,
                date_ajout TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS tuto_rubriques (
                tuto_id INTEGER NOT NULL REFERENCES tutos(id) ON DELETE CASCADE,
                rubrique_id INTEGER NOT NULL REFERENCES rubriques(id) ON DELETE CASCADE,
                PRIMARY KEY (tuto_id, rubrique_id)
            );
            CREATE TABLE IF NOT EXISTS tuto_themes (
                tuto_id INTEGER NOT NULL REFERENCES tutos(id) ON DELETE CASCADE,
                theme_id INTEGER NOT NULL REFERENCES themes(id) ON DELETE CASCADE,
                PRIMARY KEY (tuto_id, theme_id)
            );
            """
        )
        self.connexion.commit()

    def _semer_valeurs_par_defaut(self):
        aucune_rubrique = self.connexion.execute(
            "SELECT COUNT(*) AS n FROM rubriques"
        ).fetchone()["n"] == 0
        aucun_theme = self.connexion.execute(
            "SELECT COUNT(*) AS n FROM themes"
        ).fetchone()["n"] == 0
        if aucune_rubrique:
            for nom in RUBRIQUES_PAR_DEFAUT:
                self.connexion.execute(
                    "INSERT OR IGNORE INTO rubriques (nom) VALUES (?)", (nom,)
                )
        if aucun_theme:
            for nom in THEMES_PAR_DEFAUT:
                self.connexion.execute(
                    "INSERT OR IGNORE INTO themes (nom) VALUES (?)", (nom,)
                )
        self.connexion.commit()

    # -- Rubriques / thèmes ------------------------------------------------

    def lister_rubriques(self):
        return self.connexion.execute(
            "SELECT id, nom FROM rubriques ORDER BY nom COLLATE NOCASE"
        ).fetchall()

    def lister_themes(self):
        return self.connexion.execute(
            "SELECT id, nom FROM themes ORDER BY nom COLLATE NOCASE"
        ).fetchall()

    def ajouter_rubrique(self, nom):
        nom = nom.strip()
        if not nom:
            raise ValueError("Le nom de la rubrique ne peut pas être vide.")
        curseur = self.connexion.execute(
            "INSERT OR IGNORE INTO rubriques (nom) VALUES (?)", (nom,)
        )
        self.connexion.commit()
        if curseur.lastrowid and curseur.rowcount:
            return curseur.lastrowid
        return self.connexion.execute(
            "SELECT id FROM rubriques WHERE nom = ?", (nom,)
        ).fetchone()["id"]

    def ajouter_theme(self, nom):
        nom = nom.strip()
        if not nom:
            raise ValueError("Le nom du thème ne peut pas être vide.")
        curseur = self.connexion.execute(
            "INSERT OR IGNORE INTO themes (nom) VALUES (?)", (nom,)
        )
        self.connexion.commit()
        if curseur.lastrowid and curseur.rowcount:
            return curseur.lastrowid
        return self.connexion.execute(
            "SELECT id FROM themes WHERE nom = ?", (nom,)
        ).fetchone()["id"]

    def renommer_rubrique(self, rubrique_id, nouveau_nom):
        nouveau_nom = nouveau_nom.strip()
        if not nouveau_nom:
            raise ValueError("Le nom de la rubrique ne peut pas être vide.")
        try:
            self.connexion.execute(
                "UPDATE rubriques SET nom = ? WHERE id = ?", (nouveau_nom, rubrique_id)
            )
            self.connexion.commit()
        except sqlite3.IntegrityError:
            raise ValueError("Une rubrique porte déjà ce nom.")

    def renommer_theme(self, theme_id, nouveau_nom):
        nouveau_nom = nouveau_nom.strip()
        if not nouveau_nom:
            raise ValueError("Le nom du thème ne peut pas être vide.")
        try:
            self.connexion.execute(
                "UPDATE themes SET nom = ? WHERE id = ?", (nouveau_nom, theme_id)
            )
            self.connexion.commit()
        except sqlite3.IntegrityError:
            raise ValueError("Un thème porte déjà ce nom.")

    def compter_utilisation_rubrique(self, rubrique_id):
        return self.connexion.execute(
            "SELECT COUNT(*) AS n FROM tuto_rubriques WHERE rubrique_id = ?",
            (rubrique_id,),
        ).fetchone()["n"]

    def compter_utilisation_theme(self, theme_id):
        return self.connexion.execute(
            "SELECT COUNT(*) AS n FROM tuto_themes WHERE theme_id = ?",
            (theme_id,),
        ).fetchone()["n"]

    def supprimer_rubrique(self, rubrique_id):
        if self.compter_utilisation_rubrique(rubrique_id) > 0:
            raise ErreurUtilisation(
                "Cette rubrique est encore utilisée par au moins un tuto."
            )
        self.connexion.execute("DELETE FROM rubriques WHERE id = ?", (rubrique_id,))
        self.connexion.commit()

    def supprimer_theme(self, theme_id):
        if self.compter_utilisation_theme(theme_id) > 0:
            raise ErreurUtilisation(
                "Ce thème est encore utilisé par au moins un tuto."
            )
        self.connexion.execute("DELETE FROM themes WHERE id = ?", (theme_id,))
        self.connexion.commit()

    # -- Tutos ---------------------------------------------------------

    def ajouter_tuto(self, titre, chemin_fichier, description, rubrique_ids, theme_ids):
        curseur = self.connexion.execute(
            "INSERT INTO tutos (titre, chemin_fichier, description, date_ajout) "
            "VALUES (?, ?, ?, ?)",
            (titre.strip(), chemin_fichier, description.strip(), datetime.now().isoformat()),
        )
        tuto_id = curseur.lastrowid
        self._definir_liens(tuto_id, rubrique_ids, theme_ids)
        self.connexion.commit()
        return tuto_id

    def modifier_tuto(self, tuto_id, titre, chemin_fichier, description, rubrique_ids, theme_ids):
        self.connexion.execute(
            "UPDATE tutos SET titre = ?, chemin_fichier = ?, description = ? WHERE id = ?",
            (titre.strip(), chemin_fichier, description.strip(), tuto_id),
        )
        self.connexion.execute("DELETE FROM tuto_rubriques WHERE tuto_id = ?", (tuto_id,))
        self.connexion.execute("DELETE FROM tuto_themes WHERE tuto_id = ?", (tuto_id,))
        self._definir_liens(tuto_id, rubrique_ids, theme_ids)
        self.connexion.commit()

    def _definir_liens(self, tuto_id, rubrique_ids, theme_ids):
        for rubrique_id in rubrique_ids:
            self.connexion.execute(
                "INSERT OR IGNORE INTO tuto_rubriques (tuto_id, rubrique_id) VALUES (?, ?)",
                (tuto_id, rubrique_id),
            )
        for theme_id in theme_ids:
            self.connexion.execute(
                "INSERT OR IGNORE INTO tuto_themes (tuto_id, theme_id) VALUES (?, ?)",
                (tuto_id, theme_id),
            )

    def supprimer_tuto(self, tuto_id):
        self.connexion.execute("DELETE FROM tutos WHERE id = ?", (tuto_id,))
        self.connexion.commit()

    def mettre_a_jour_chemin(self, tuto_id, nouveau_chemin):
        self.connexion.execute(
            "UPDATE tutos SET chemin_fichier = ? WHERE id = ?", (nouveau_chemin, tuto_id)
        )
        self.connexion.commit()

    def obtenir_tuto(self, tuto_id):
        tuto = self.connexion.execute(
            "SELECT * FROM tutos WHERE id = ?", (tuto_id,)
        ).fetchone()
        if tuto is None:
            return None
        rubrique_ids = [
            ligne["rubrique_id"]
            for ligne in self.connexion.execute(
                "SELECT rubrique_id FROM tuto_rubriques WHERE tuto_id = ?", (tuto_id,)
            )
        ]
        theme_ids = [
            ligne["theme_id"]
            for ligne in self.connexion.execute(
                "SELECT theme_id FROM tuto_themes WHERE tuto_id = ?", (tuto_id,)
            )
        ]
        return {
            "id": tuto["id"],
            "titre": tuto["titre"],
            "chemin_fichier": tuto["chemin_fichier"],
            "description": tuto["description"] or "",
            "rubrique_ids": rubrique_ids,
            "theme_ids": theme_ids,
        }

    def lister_tutos(self, texte=None, rubrique_ids=None, theme_ids=None):
        """Renvoie les tutos correspondant aux filtres.

        - texte : sous-chaîne cherchée (insensible à la casse) dans le
          titre ou la description.
        - rubrique_ids / theme_ids : un tuto est retenu s'il porte AU
          MOINS UNE des rubriques (resp. thèmes) demandés. Une liste
          vide ou None signifie "pas de filtre sur cet axe".
        """
        lignes = self.connexion.execute(
            "SELECT id, titre, chemin_fichier, description FROM tutos "
            "ORDER BY titre COLLATE NOCASE"
        ).fetchall()

        resultat = []
        aiguille = (texte or "").strip().lower()
        rubrique_ids = set(rubrique_ids or [])
        theme_ids = set(theme_ids or [])

        for ligne in lignes:
            tuto_id = ligne["id"]
            rubriques_du_tuto = self.connexion.execute(
                "SELECT r.id, r.nom FROM rubriques r "
                "JOIN tuto_rubriques tr ON tr.rubrique_id = r.id "
                "WHERE tr.tuto_id = ? ORDER BY r.nom COLLATE NOCASE",
                (tuto_id,),
            ).fetchall()
            themes_du_tuto = self.connexion.execute(
                "SELECT t.id, t.nom FROM themes t "
                "JOIN tuto_themes tt ON tt.theme_id = t.id "
                "WHERE tt.tuto_id = ? ORDER BY t.nom COLLATE NOCASE",
                (tuto_id,),
            ).fetchall()

            if aiguille:
                cible = (ligne["titre"] + " " + (ligne["description"] or "")).lower()
                if aiguille not in cible:
                    continue

            if rubrique_ids:
                if not ({r["id"] for r in rubriques_du_tuto} & rubrique_ids):
                    continue

            if theme_ids:
                if not ({t["id"] for t in themes_du_tuto} & theme_ids):
                    continue

            resultat.append(
                {
                    "id": tuto_id,
                    "titre": ligne["titre"],
                    "chemin_fichier": ligne["chemin_fichier"],
                    "description": ligne["description"] or "",
                    "rubriques": [r["nom"] for r in rubriques_du_tuto],
                    "themes": [t["nom"] for t in themes_du_tuto],
                }
            )
        return resultat


def extension_fichier(chemin):
    ext = os.path.splitext(chemin)[1].lstrip(".").upper()
    return ext or "?"


def ouvrir_fichier(chemin):
    if sys.platform.startswith("win"):
        os.startfile(chemin)  # type: ignore[attr-defined]
    elif sys.platform == "darwin":
        subprocess.run(["open", chemin], check=True)
    else:
        subprocess.run(["xdg-open", chemin], check=True)


class FormulaireTuto(tk.Toplevel):
    """Fenêtre d'ajout / modification d'un tuto."""

    def __init__(self, parent, base, tuto_existant=None, on_valide=None):
        super().__init__(parent)
        self.base = base
        self.tuto_existant = tuto_existant
        self.on_valide = on_valide

        self.title("Modifier un tuto" if tuto_existant else "Ajouter un tuto")
        self.resizable(False, False)
        self.transient(parent)
        self.grab_set()

        conteneur = ttk.Frame(self, padding=16)
        conteneur.grid(sticky="nsew")

        ttk.Label(conteneur, text="Fichier :").grid(row=0, column=0, sticky="w", pady=4)
        cadre_fichier = ttk.Frame(conteneur)
        cadre_fichier.grid(row=0, column=1, sticky="ew", pady=4)
        self.var_chemin = tk.StringVar(
            value=tuto_existant["chemin_fichier"] if tuto_existant else ""
        )
        self.entree_chemin = ttk.Entry(cadre_fichier, textvariable=self.var_chemin, width=48)
        self.entree_chemin.grid(row=0, column=0, sticky="ew")
        ttk.Button(cadre_fichier, text="Parcourir…", command=self._parcourir).grid(
            row=0, column=1, padx=(6, 0)
        )
        cadre_fichier.columnconfigure(0, weight=1)

        ttk.Label(conteneur, text="Titre :").grid(row=1, column=0, sticky="w", pady=4)
        self.var_titre = tk.StringVar(value=tuto_existant["titre"] if tuto_existant else "")
        ttk.Entry(conteneur, textvariable=self.var_titre, width=50).grid(
            row=1, column=1, sticky="ew", pady=4
        )

        ttk.Label(conteneur, text="Description\n(facultative) :").grid(
            row=2, column=0, sticky="nw", pady=4
        )
        self.texte_description = tk.Text(conteneur, width=48, height=4, wrap="word")
        self.texte_description.grid(row=2, column=1, sticky="ew", pady=4)
        if tuto_existant:
            self.texte_description.insert("1.0", tuto_existant["description"])

        ttk.Label(conteneur, text="Rubriques\n(Ctrl+clic pour\nplusieurs) :").grid(
            row=3, column=0, sticky="nw", pady=4
        )
        cadre_rubriques = ttk.Frame(conteneur)
        cadre_rubriques.grid(row=3, column=1, sticky="ew", pady=4)
        self.liste_rubriques = tk.Listbox(
            cadre_rubriques, selectmode="multiple", height=6, exportselection=False
        )
        self.liste_rubriques.grid(row=0, column=0, sticky="ew")
        ttk.Button(
            cadre_rubriques, text="+ Nouvelle rubrique", command=self._nouvelle_rubrique
        ).grid(row=1, column=0, sticky="w", pady=(4, 0))
        cadre_rubriques.columnconfigure(0, weight=1)

        ttk.Label(conteneur, text="Thèmes\n(Ctrl+clic pour\nplusieurs) :").grid(
            row=4, column=0, sticky="nw", pady=4
        )
        cadre_themes = ttk.Frame(conteneur)
        cadre_themes.grid(row=4, column=1, sticky="ew", pady=4)
        self.liste_themes = tk.Listbox(
            cadre_themes, selectmode="multiple", height=6, exportselection=False
        )
        self.liste_themes.grid(row=0, column=0, sticky="ew")
        ttk.Button(
            cadre_themes, text="+ Nouveau thème", command=self._nouveau_theme
        ).grid(row=1, column=0, sticky="w", pady=(4, 0))
        cadre_themes.columnconfigure(0, weight=1)

        cadre_boutons = ttk.Frame(conteneur)
        cadre_boutons.grid(row=5, column=0, columnspan=2, pady=(16, 0), sticky="e")
        ttk.Button(cadre_boutons, text="Annuler", command=self.destroy).grid(
            row=0, column=0, padx=(0, 8)
        )
        ttk.Button(cadre_boutons, text="Enregistrer", command=self._enregistrer).grid(
            row=0, column=1
        )

        self._rubriques_ids = []
        self._themes_ids = []
        self._recharger_rubriques(
            selection=tuto_existant["rubrique_ids"] if tuto_existant else []
        )
        self._recharger_themes(
            selection=tuto_existant["theme_ids"] if tuto_existant else []
        )

        self.entree_chemin.focus_set()

    def _parcourir(self):
        chemin = filedialog.askopenfilename(title="Choisir le fichier du tuto")
        if not chemin:
            return
        self.var_chemin.set(chemin)
        if not self.var_titre.get().strip():
            nom_sans_extension = os.path.splitext(os.path.basename(chemin))[0]
            self.var_titre.set(nom_sans_extension)

    def _recharger_rubriques(self, selection):
        self.liste_rubriques.delete(0, "end")
        self._rubriques_ids = []
        for rubrique in self.base.lister_rubriques():
            self.liste_rubriques.insert("end", rubrique["nom"])
            self._rubriques_ids.append(rubrique["id"])
        for index, rubrique_id in enumerate(self._rubriques_ids):
            if rubrique_id in selection:
                self.liste_rubriques.selection_set(index)

    def _recharger_themes(self, selection):
        self.liste_themes.delete(0, "end")
        self._themes_ids = []
        for theme in self.base.lister_themes():
            self.liste_themes.insert("end", theme["nom"])
            self._themes_ids.append(theme["id"])
        for index, theme_id in enumerate(self._themes_ids):
            if theme_id in selection:
                self.liste_themes.selection_set(index)

    def _nouvelle_rubrique(self):
        nom = simpledialog.askstring("Nouvelle rubrique", "Nom de la rubrique :", parent=self)
        if not nom or not nom.strip():
            return
        try:
            nouvel_id = self.base.ajouter_rubrique(nom)
        except ValueError as erreur:
            messagebox.showerror(APP_TITLE, str(erreur), parent=self)
            return
        selection_actuelle = [
            self._rubriques_ids[i] for i in self.liste_rubriques.curselection()
        ]
        selection_actuelle.append(nouvel_id)
        self._recharger_rubriques(selection=selection_actuelle)

    def _nouveau_theme(self):
        nom = simpledialog.askstring("Nouveau thème", "Nom du thème :", parent=self)
        if not nom or not nom.strip():
            return
        try:
            nouvel_id = self.base.ajouter_theme(nom)
        except ValueError as erreur:
            messagebox.showerror(APP_TITLE, str(erreur), parent=self)
            return
        selection_actuelle = [self._themes_ids[i] for i in self.liste_themes.curselection()]
        selection_actuelle.append(nouvel_id)
        self._recharger_themes(selection=selection_actuelle)

    def _enregistrer(self):
        chemin = self.var_chemin.get().strip()
        titre = self.var_titre.get().strip()
        description = self.texte_description.get("1.0", "end").strip()

        if not chemin:
            messagebox.showerror(APP_TITLE, "Veuillez choisir un fichier.", parent=self)
            return
        if not titre:
            messagebox.showerror(APP_TITLE, "Veuillez saisir un titre.", parent=self)
            return
        if not os.path.exists(chemin):
            if not messagebox.askyesno(
                APP_TITLE,
                "Ce fichier est introuvable à cet emplacement.\n"
                "Enregistrer quand même la fiche ?",
                parent=self,
            ):
                return

        rubrique_ids = [self._rubriques_ids[i] for i in self.liste_rubriques.curselection()]
        theme_ids = [self._themes_ids[i] for i in self.liste_themes.curselection()]

        if self.tuto_existant:
            self.base.modifier_tuto(
                self.tuto_existant["id"], titre, chemin, description, rubrique_ids, theme_ids
            )
        else:
            self.base.ajouter_tuto(titre, chemin, description, rubrique_ids, theme_ids)

        if self.on_valide:
            self.on_valide()
        self.destroy()


class FenetreGestion(tk.Toplevel):
    """Gestion générique d'une liste de valeurs (rubriques ou thèmes)."""

    def __init__(self, parent, titre, lister, ajouter, renommer, supprimer, compter_utilisation, on_modifie):
        super().__init__(parent)
        self.title(titre)
        self.resizable(False, False)
        self.transient(parent)
        self.grab_set()

        self.lister = lister
        self.ajouter = ajouter
        self.renommer = renommer
        self.supprimer = supprimer
        self.compter_utilisation = compter_utilisation
        self.on_modifie = on_modifie

        conteneur = ttk.Frame(self, padding=16)
        conteneur.grid(sticky="nsew")

        self.liste = tk.Listbox(conteneur, width=40, height=12)
        self.liste.grid(row=0, column=0, columnspan=3, sticky="nsew")

        ttk.Button(conteneur, text="Ajouter", command=self._ajouter).grid(
            row=1, column=0, pady=(8, 0), sticky="ew"
        )
        ttk.Button(conteneur, text="Renommer", command=self._renommer).grid(
            row=1, column=1, pady=(8, 0), sticky="ew"
        )
        ttk.Button(conteneur, text="Supprimer", command=self._supprimer).grid(
            row=1, column=2, pady=(8, 0), sticky="ew"
        )

        self._ids = []
        self._recharger()

    def _recharger(self):
        self.liste.delete(0, "end")
        self._ids = []
        for ligne in self.lister():
            nb = self.compter_utilisation(ligne["id"])
            self.liste.insert("end", f"{ligne['nom']}  ({nb} tuto(s))")
            self._ids.append(ligne["id"])

    def _selection_id(self):
        selection = self.liste.curselection()
        if not selection:
            messagebox.showinfo(APP_TITLE, "Sélectionnez d'abord une ligne.", parent=self)
            return None
        return self._ids[selection[0]]

    def _ajouter(self):
        nom = simpledialog.askstring("Ajouter", "Nom :", parent=self)
        if not nom or not nom.strip():
            return
        try:
            self.ajouter(nom)
        except ValueError as erreur:
            messagebox.showerror(APP_TITLE, str(erreur), parent=self)
            return
        self._recharger()
        self.on_modifie()

    def _renommer(self):
        element_id = self._selection_id()
        if element_id is None:
            return
        nom_actuel = self.lister()[self._ids.index(element_id)]["nom"]
        nouveau_nom = simpledialog.askstring(
            "Renommer", "Nouveau nom :", initialvalue=nom_actuel, parent=self
        )
        if not nouveau_nom or not nouveau_nom.strip():
            return
        try:
            self.renommer(element_id, nouveau_nom)
        except ValueError as erreur:
            messagebox.showerror(APP_TITLE, str(erreur), parent=self)
            return
        self._recharger()
        self.on_modifie()

    def _supprimer(self):
        element_id = self._selection_id()
        if element_id is None:
            return
        if not messagebox.askyesno(APP_TITLE, "Confirmer la suppression ?", parent=self):
            return
        try:
            self.supprimer(element_id)
        except ErreurUtilisation as erreur:
            messagebox.showerror(APP_TITLE, str(erreur), parent=self)
            return
        self._recharger()
        self.on_modifie()


class ApplicationSteftuto(tk.Tk):
    def __init__(self):
        super().__init__()
        self.base = Base(DB_PATH)

        self.title(APP_TITLE)
        self.geometry("980x560")
        self.minsize(760, 440)

        self._construire_menu()
        self._construire_interface()
        self.actualiser_liste()

    # -- Construction de l'interface ------------------------------------

    def _construire_menu(self):
        barre = tk.Menu(self)

        menu_fichier = tk.Menu(barre, tearoff=0)
        menu_fichier.add_command(label="Ajouter un tuto…", command=self.ajouter_tuto)
        menu_fichier.add_separator()
        menu_fichier.add_command(label="Quitter", command=self.destroy)
        barre.add_cascade(label="Fichier", menu=menu_fichier)

        menu_gerer = tk.Menu(barre, tearoff=0)
        menu_gerer.add_command(label="Rubriques…", command=self.gerer_rubriques)
        menu_gerer.add_command(label="Thèmes…", command=self.gerer_themes)
        barre.add_cascade(label="Gérer", menu=menu_gerer)

        menu_aide = tk.Menu(barre, tearoff=0)
        menu_aide.add_command(label="À propos", command=self._a_propos)
        barre.add_cascade(label="Aide", menu=menu_aide)

        self.config(menu=barre)

    def _construire_interface(self):
        cadre_recherche = ttk.LabelFrame(self, text="Recherche", padding=10)
        cadre_recherche.pack(fill="x", padx=10, pady=(10, 0))

        ttk.Label(cadre_recherche, text="Texte :").grid(row=0, column=0, sticky="w")
        self.var_recherche = tk.StringVar()
        entree_recherche = ttk.Entry(cadre_recherche, textvariable=self.var_recherche, width=40)
        entree_recherche.grid(row=0, column=1, sticky="w", padx=(4, 16))
        entree_recherche.bind("<KeyRelease>", lambda _evt: self.actualiser_liste())

        ttk.Label(cadre_recherche, text="Rubriques :").grid(row=0, column=2, sticky="w")
        self.liste_filtre_rubriques = tk.Listbox(
            cadre_recherche, selectmode="multiple", height=4, exportselection=False, width=22
        )
        self.liste_filtre_rubriques.grid(row=0, column=3, rowspan=2, sticky="w", padx=(4, 16))
        self.liste_filtre_rubriques.bind(
            "<<ListboxSelect>>", lambda _evt: self.actualiser_liste()
        )

        ttk.Label(cadre_recherche, text="Thèmes :").grid(row=0, column=4, sticky="w")
        self.liste_filtre_themes = tk.Listbox(
            cadre_recherche, selectmode="multiple", height=4, exportselection=False, width=22
        )
        self.liste_filtre_themes.grid(row=0, column=5, rowspan=2, sticky="w", padx=(4, 16))
        self.liste_filtre_themes.bind(
            "<<ListboxSelect>>", lambda _evt: self.actualiser_liste()
        )

        ttk.Button(
            cadre_recherche, text="Réinitialiser les filtres", command=self._reinitialiser_filtres
        ).grid(row=1, column=0, columnspan=2, sticky="w", pady=(6, 0))

        cadre_liste = ttk.Frame(self, padding=10)
        cadre_liste.pack(fill="both", expand=True)

        colonnes = ("titre", "extension", "rubriques", "themes")
        self.arbre = ttk.Treeview(cadre_liste, columns=colonnes, show="headings")
        self.arbre.heading("titre", text="Titre")
        self.arbre.heading("extension", text="Type")
        self.arbre.heading("rubriques", text="Rubriques")
        self.arbre.heading("themes", text="Thèmes")
        self.arbre.column("titre", width=260)
        self.arbre.column("extension", width=70, anchor="center")
        self.arbre.column("rubriques", width=260)
        self.arbre.column("themes", width=260)
        self.arbre.pack(side="left", fill="both", expand=True)
        self.arbre.bind("<Double-1>", lambda _evt: self.ouvrir_selection())

        defilement = ttk.Scrollbar(cadre_liste, orient="vertical", command=self.arbre.yview)
        defilement.pack(side="left", fill="y")
        self.arbre.configure(yscrollcommand=defilement.set)

        cadre_boutons = ttk.Frame(self, padding=(10, 0, 10, 10))
        cadre_boutons.pack(fill="x")
        ttk.Button(cadre_boutons, text="Ajouter un tuto…", command=self.ajouter_tuto).pack(
            side="left"
        )
        ttk.Button(cadre_boutons, text="Ouvrir", command=self.ouvrir_selection).pack(
            side="left", padx=6
        )
        ttk.Button(cadre_boutons, text="Modifier…", command=self.modifier_selection).pack(
            side="left"
        )
        ttk.Button(cadre_boutons, text="Supprimer", command=self.supprimer_selection).pack(
            side="left", padx=6
        )

        self.etiquette_compte = ttk.Label(cadre_boutons, text="")
        self.etiquette_compte.pack(side="right")

        self._tutos_ids_par_ligne = []
        self._recharger_filtres()

    def _a_propos(self):
        messagebox.showinfo(
            APP_TITLE,
            "Steftuto - catalogue personnel de tutoriels photo.\n\n"
            "Classez vos tutos par rubrique et par thème, "
            "retrouvez-les par recherche, ouvrez-les d'un double-clic.\n\n"
            f"Données stockées dans :\n{DB_PATH}",
        )

    # -- Filtres ---------------------------------------------------------

    def _recharger_filtres(self):
        self._rubriques_filtre_ids = []
        self.liste_filtre_rubriques.delete(0, "end")
        for rubrique in self.base.lister_rubriques():
            self.liste_filtre_rubriques.insert("end", rubrique["nom"])
            self._rubriques_filtre_ids.append(rubrique["id"])

        self._themes_filtre_ids = []
        self.liste_filtre_themes.delete(0, "end")
        for theme in self.base.lister_themes():
            self.liste_filtre_themes.insert("end", theme["nom"])
            self._themes_filtre_ids.append(theme["id"])

    def _reinitialiser_filtres(self):
        self.var_recherche.set("")
        self.liste_filtre_rubriques.selection_clear(0, "end")
        self.liste_filtre_themes.selection_clear(0, "end")
        self.actualiser_liste()

    # -- Actions -----------------------------------------------------------

    def actualiser_liste(self):
        rubrique_ids = [
            self._rubriques_filtre_ids[i] for i in self.liste_filtre_rubriques.curselection()
        ]
        theme_ids = [
            self._themes_filtre_ids[i] for i in self.liste_filtre_themes.curselection()
        ]
        tutos = self.base.lister_tutos(
            texte=self.var_recherche.get(), rubrique_ids=rubrique_ids, theme_ids=theme_ids
        )

        self.arbre.delete(*self.arbre.get_children())
        self._tutos_ids_par_ligne = []
        for tuto in tutos:
            ligne = self.arbre.insert(
                "",
                "end",
                values=(
                    tuto["titre"],
                    extension_fichier(tuto["chemin_fichier"]),
                    ", ".join(tuto["rubriques"]) or "—",
                    ", ".join(tuto["themes"]) or "—",
                ),
            )
            self._tutos_ids_par_ligne.append((ligne, tuto["id"]))

        self.etiquette_compte.config(text=f"{len(tutos)} tuto(s)")

    def _tuto_id_selectionne(self):
        selection = self.arbre.selection()
        if not selection:
            return None
        ligne = selection[0]
        for id_ligne, tuto_id in self._tutos_ids_par_ligne:
            if id_ligne == ligne:
                return tuto_id
        return None

    def ajouter_tuto(self):
        FormulaireTuto(self, self.base, on_valide=self._apres_modification_tutos)

    def modifier_selection(self):
        tuto_id = self._tuto_id_selectionne()
        if tuto_id is None:
            messagebox.showinfo(APP_TITLE, "Sélectionnez d'abord un tuto dans la liste.")
            return
        tuto = self.base.obtenir_tuto(tuto_id)
        FormulaireTuto(
            self, self.base, tuto_existant=tuto, on_valide=self._apres_modification_tutos
        )

    def supprimer_selection(self):
        tuto_id = self._tuto_id_selectionne()
        if tuto_id is None:
            messagebox.showinfo(APP_TITLE, "Sélectionnez d'abord un tuto dans la liste.")
            return
        tuto = self.base.obtenir_tuto(tuto_id)
        if not messagebox.askyesno(
            APP_TITLE, f"Supprimer la fiche « {tuto['titre']} » ?\n"
            "(Le fichier lui-même n'est pas touché.)"
        ):
            return
        self.base.supprimer_tuto(tuto_id)
        self._apres_modification_tutos()

    def ouvrir_selection(self):
        tuto_id = self._tuto_id_selectionne()
        if tuto_id is None:
            return
        tuto = self.base.obtenir_tuto(tuto_id)
        chemin = tuto["chemin_fichier"]
        if not os.path.exists(chemin):
            if messagebox.askyesno(
                APP_TITLE,
                f"Introuvable :\n{chemin}\n\nIndiquer son nouvel emplacement ?",
            ):
                nouveau_chemin = filedialog.askopenfilename(title="Retrouver le fichier")
                if nouveau_chemin:
                    self.base.mettre_a_jour_chemin(tuto_id, nouveau_chemin)
                    chemin = nouveau_chemin
                else:
                    return
            else:
                return
        try:
            ouvrir_fichier(chemin)
        except Exception as erreur:  # ouverture système, cause variable selon l'OS
            messagebox.showerror(APP_TITLE, f"Impossible d'ouvrir ce fichier :\n{erreur}")

    def _apres_modification_tutos(self):
        self._recharger_filtres()
        self.actualiser_liste()

    def gerer_rubriques(self):
        FenetreGestion(
            self,
            "Gérer les rubriques",
            self.base.lister_rubriques,
            self.base.ajouter_rubrique,
            self.base.renommer_rubrique,
            self.base.supprimer_rubrique,
            self.base.compter_utilisation_rubrique,
            on_modifie=self._apres_modification_tutos,
        )

    def gerer_themes(self):
        FenetreGestion(
            self,
            "Gérer les thèmes",
            self.base.lister_themes,
            self.base.ajouter_theme,
            self.base.renommer_theme,
            self.base.supprimer_theme,
            self.base.compter_utilisation_theme,
            on_modifie=self._apres_modification_tutos,
        )


def main():
    app = ApplicationSteftuto()
    app.mainloop()


if __name__ == "__main__":
    main()

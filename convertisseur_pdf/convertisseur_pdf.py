#!/usr/bin/env python3
"""Convertisseur PDF - transforme des fichiers Word en PDF, par dossier entier.

Application de bureau (Tkinter, sans dépendance obligatoire) : on choisit un
dossier contenant des fichiers .docx/.doc (éventuellement rangés dans des
sous-dossiers), et le logiciel crée un nouveau dossier portant le même nom
(suivi de « (PDF) », un même nom exact étant impossible juste à côté de
l'original) contenant la version PDF de chaque fichier, en conservant
l'organisation en sous-dossiers.

Deux façons de convertir, choisies automatiquement selon ce qui est
disponible sur l'ordinateur :
- Microsoft Word (via pywin32), la plus fidèle : reproduit exactement la
  mise en page, les couleurs et les images comme si on faisait
  Fichier > Exporter > Créer un PDF depuis Word lui-même.
- LibreOffice (soffice), utilisé si Word/pywin32 n'est pas disponible.

Lancement :  python3 convertisseur_pdf.py
"""

import os
import queue
import shutil
import subprocess
import sys
import threading
import tkinter as tk
from tkinter import filedialog, messagebox, ttk

APP_TITLE = "Convertisseur PDF"
SOUS_TITRE = "Transformez vos fichiers Word en PDF, dossier par dossier"

EXTENSIONS_ACCEPTEES = (".docx", ".doc")

# Icône de l'application (même appareil photo blanc sur fond dégradé bleu ->
# violet que Steftuto, pour une identité visuelle cohérente entre les deux
# logiciels), encodée en base64 pour rester dans ce seul fichier.
ICONE_FENETRE_BASE64 = """
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
""".strip().replace("\n", "")


def _definir_icone_fenetre(fenetre):
    try:
        image = tk.PhotoImage(data=ICONE_FENETRE_BASE64)
        fenetre.iconphoto(True, image)
        fenetre._icone_reference = image  # évite le ramasse-miettes de Tk
    except tk.TclError:
        pass


# -- Palette et style (identique à Steftuto, pour une cohérence visuelle) --
COULEUR_ACCENT = "#2563EB"
COULEUR_ACCENT_ACTIF = "#1D4ED8"
COULEUR_ACCENT_2 = "#7C3AED"
COULEUR_DANGER = "#DC2626"
COULEUR_DANGER_ACTIF = "#B91C1C"
COULEUR_SUCCES = "#16A34A"
COULEUR_FOND = "#F8FAFC"
COULEUR_CARTE = "#FFFFFF"
COULEUR_BORDURE = "#CBD5E1"
COULEUR_TEXTE = "#0F172A"
COULEUR_TEXTE_DOUX = "#64748B"
COULEUR_LIGNE_ALTERNEE = "#EEF2FF"
COULEUR_BANDEAU_SOUS_TITRE = "#E0E7FF"

POLICES_CANDIDATES = ["Segoe UI", "Helvetica Neue", "Helvetica", "Arial", "DejaVu Sans"]


def _hex_vers_rgb(couleur):
    couleur = couleur.lstrip("#")
    return tuple(int(couleur[i : i + 2], 16) for i in (0, 2, 4))


def _police(taille, gras=False):
    import tkinter.font as tkfont

    familles = set(tkfont.families())
    for nom in POLICES_CANDIDATES:
        if nom in familles:
            return (nom, taille, "bold" if gras else "normal")
    return ("TkDefaultFont", taille, "bold" if gras else "normal")


def _configurer_style(fenetre):
    style = ttk.Style(fenetre)
    try:
        style.theme_use("clam")
    except tk.TclError:
        pass

    police_base = _police(10)
    police_grasse = _police(10, gras=True)
    fenetre.option_add("*Font", police_base)

    style.configure(".", background=COULEUR_FOND, foreground=COULEUR_TEXTE, font=police_base)
    style.configure("TFrame", background=COULEUR_FOND)
    style.configure("TLabel", background=COULEUR_FOND, foreground=COULEUR_TEXTE)
    style.configure(
        "TLabelframe", background=COULEUR_FOND, bordercolor=COULEUR_BORDURE, relief="solid"
    )
    style.configure(
        "TLabelframe.Label", background=COULEUR_FOND, foreground=COULEUR_ACCENT, font=police_grasse
    )

    style.configure(
        "TButton",
        padding=(12, 7),
        font=police_base,
        background=COULEUR_CARTE,
        foreground=COULEUR_TEXTE,
        bordercolor=COULEUR_BORDURE,
        focusthickness=0,
    )
    style.map(
        "TButton",
        background=[("active", COULEUR_LIGNE_ALTERNEE)],
        bordercolor=[("focus", COULEUR_ACCENT)],
    )

    style.configure(
        "Accent.TButton",
        padding=(14, 8),
        font=police_grasse,
        background=COULEUR_ACCENT,
        foreground="white",
        bordercolor=COULEUR_ACCENT,
    )
    style.map(
        "Accent.TButton",
        background=[("active", COULEUR_ACCENT_ACTIF), ("disabled", COULEUR_BORDURE)],
        foreground=[("disabled", COULEUR_TEXTE_DOUX)],
    )

    style.configure(
        "TEntry",
        fieldbackground=COULEUR_CARTE,
        bordercolor=COULEUR_BORDURE,
        padding=6,
    )
    style.map("TEntry", bordercolor=[("focus", COULEUR_ACCENT)])

    style.configure(
        "TProgressbar",
        troughcolor=COULEUR_LIGNE_ALTERNEE,
        background=COULEUR_ACCENT,
        bordercolor=COULEUR_FOND,
        lightcolor=COULEUR_ACCENT,
        darkcolor=COULEUR_ACCENT,
    )

    style.configure(
        "TScrollbar",
        background=COULEUR_FOND,
        troughcolor=COULEUR_FOND,
        bordercolor=COULEUR_FOND,
        arrowcolor=COULEUR_TEXTE_DOUX,
    )

    return police_base, police_grasse


def _styliser_liste(liste):
    liste.configure(
        background=COULEUR_CARTE,
        foreground=COULEUR_TEXTE,
        selectbackground=COULEUR_ACCENT,
        selectforeground="white",
        relief="flat",
        highlightthickness=1,
        highlightbackground=COULEUR_BORDURE,
        highlightcolor=COULEUR_ACCENT,
        borderwidth=0,
    )


class BandeauTitre(tk.Canvas):
    """Bandeau dégradé bleu -> violet en haut de la fenêtre principale."""

    NB_BANDES = 120

    def __init__(self, parent, police_titre, police_sous_titre):
        super().__init__(parent, height=64, highlightthickness=0, bd=0)
        self._police_titre = police_titre
        self._police_sous_titre = police_sous_titre
        try:
            self._icone = tk.PhotoImage(data=ICONE_FENETRE_BASE64).subsample(4, 4)
        except tk.TclError:
            self._icone = None
        self.bind("<Configure>", self._redessiner)

    def _redessiner(self, _evenement=None):
        largeur = self.winfo_width()
        hauteur = self.winfo_height()
        if largeur <= 1:
            return
        self.delete("all")

        depart = _hex_vers_rgb(COULEUR_ACCENT)
        arrivee = _hex_vers_rgb(COULEUR_ACCENT_2)
        largeur_bande = largeur / self.NB_BANDES
        for i in range(self.NB_BANDES):
            t = i / (self.NB_BANDES - 1)
            r = round(depart[0] + (arrivee[0] - depart[0]) * t)
            g = round(depart[1] + (arrivee[1] - depart[1]) * t)
            b = round(depart[2] + (arrivee[2] - depart[2]) * t)
            couleur = f"#{r:02x}{g:02x}{b:02x}"
            x0 = i * largeur_bande
            x1 = (i + 1) * largeur_bande + 1
            self.create_rectangle(x0, 0, x1, hauteur, fill=couleur, outline=couleur)

        x_texte = 18
        if self._icone is not None:
            self.create_image(18, hauteur // 2, image=self._icone, anchor="w")
            x_texte = 18 + self._icone.width() + 12

        self.create_text(
            x_texte, hauteur // 2 - 11, text=APP_TITLE, anchor="w", fill="white",
            font=self._police_titre,
        )
        self.create_text(
            x_texte, hauteur // 2 + 13, text=SOUS_TITRE, anchor="w",
            fill=COULEUR_BANDEAU_SOUS_TITRE, font=self._police_sous_titre,
        )


# -- Recherche du moteur de conversion disponible ---------------------------


def _pywin32_disponible():
    """Word (via pywin32) n'existe que sous Windows, et seulement si le
    module est installé et Microsoft Word présent sur la machine."""
    if not sys.platform.startswith("win"):
        return False
    try:
        import win32com.client  # noqa: F401
    except ImportError:
        return False
    return True


def _chemin_soffice():
    """Cherche LibreOffice sur le système, solution de repli si Word/pywin32
    n'est pas disponible. Renvoie None si introuvable."""
    for nom in ("soffice", "soffice.exe"):
        trouve = shutil.which(nom)
        if trouve:
            return trouve
    for chemin in (
        r"C:\Program Files\LibreOffice\program\soffice.exe",
        r"C:\Program Files (x86)\LibreOffice\program\soffice.exe",
        "/Applications/LibreOffice.app/Contents/MacOS/soffice",
    ):
        if os.path.exists(chemin):
            return chemin
    return None


class ErreurConversion(Exception):
    """Levée quand ni Word ni LibreOffice ne sont disponibles, ou qu'un
    fichier précis échoue à se convertir."""


class MoteurWord:
    """Convertit via Microsoft Word (COM), la plus fidèle : reproduit
    exactement la mise en page, comme Word lui-même exportant en PDF."""

    WD_FORMAT_PDF = 17  # constante Word : wdExportFormatPDF

    def __init__(self):
        import pythoncom
        import win32com.client

        self._pythoncom = pythoncom
        pythoncom.CoInitialize()
        # DispatchEx : nouvelle instance de Word dédiée à cette conversion,
        # pour ne jamais interférer avec un Word déjà ouvert par la personne.
        self.word = win32com.client.DispatchEx("Word.Application")
        self.word.Visible = False
        self.word.DisplayAlerts = 0  # wdAlertsNone : pas de fenêtre de confirmation

    def convertir(self, chemin_source, chemin_pdf):
        doc = self.word.Documents.Open(
            os.path.abspath(chemin_source), ReadOnly=True, AddToRecentFiles=False
        )
        try:
            doc.ExportAsFixedFormat(
                OutputFileName=os.path.abspath(chemin_pdf), ExportFormat=self.WD_FORMAT_PDF
            )
        finally:
            doc.Close(False)

    def fermer(self):
        try:
            self.word.Quit()
        except Exception:
            pass
        self._pythoncom.CoUninitialize()


class MoteurLibreOffice:
    """Convertit via LibreOffice en ligne de commande, utilisé seulement si
    Word/pywin32 n'est pas disponible sur cet ordinateur."""

    def __init__(self, chemin_soffice):
        self.chemin_soffice = chemin_soffice

    def convertir(self, chemin_source, chemin_pdf):
        dossier_sortie = os.path.dirname(chemin_pdf)
        resultat = subprocess.run(
            [
                self.chemin_soffice, "--headless", "--norestore",
                "--convert-to", "pdf", "--outdir", dossier_sortie,
                os.path.abspath(chemin_source),
            ],
            capture_output=True, text=True, timeout=120,
        )
        # LibreOffice nomme toujours sa sortie d'après le fichier source :
        # si le nom voulu diffère (cas rare), on renomme pour rester cohérent.
        nom_genere = os.path.join(
            dossier_sortie, os.path.splitext(os.path.basename(chemin_source))[0] + ".pdf"
        )
        if resultat.returncode != 0 or not os.path.exists(nom_genere):
            raise ErreurConversion(resultat.stderr.strip() or "Échec de conversion LibreOffice.")
        if os.path.abspath(nom_genere) != os.path.abspath(chemin_pdf):
            os.replace(nom_genere, chemin_pdf)

    def fermer(self):
        pass


def creer_moteur():
    """Choisit automatiquement Word (le plus fidèle) si disponible, sinon
    LibreOffice. Lève ErreurConversion si aucun des deux n'est trouvé."""
    if _pywin32_disponible():
        try:
            return MoteurWord()
        except Exception:
            pass  # pywin32 présent mais Word absent/défaillant : on retente LibreOffice
    chemin = _chemin_soffice()
    if chemin:
        return MoteurLibreOffice(chemin)
    raise ErreurConversion(
        "Aucun outil de conversion trouvé sur cet ordinateur.\n\n"
        "Installez Microsoft Word, ou à défaut LibreOffice (gratuit,\n"
        "sur https://fr.libreoffice.org/telecharger/)."
    )


# -- Logique de conversion d'un dossier entier ------------------------------


def dossier_sortie_pour(dossier_source):
    """Nom du dossier de résultat : le même nom que le dossier de départ,
    placé juste à côté de lui. Un nom strictement identique étant impossible
    au même endroit, on ajoute « (PDF) », puis un numéro si ce nom existe
    déjà (conversions précédentes)."""
    dossier_source = os.path.normpath(dossier_source)
    parent = os.path.dirname(dossier_source)
    nom = os.path.basename(dossier_source)
    candidat = os.path.join(parent, f"{nom} (PDF)")
    base_candidat = candidat
    n = 2
    while os.path.exists(candidat):
        candidat = f"{base_candidat} ({n})"
        n += 1
    return candidat


def lister_fichiers_a_convertir(dossier_source):
    """Parcourt le dossier (et ses sous-dossiers) et renvoie la liste des
    chemins de fichiers Word trouvés, chemin relatif au dossier de départ
    inclus (pour reproduire la même arborescence dans le résultat)."""
    fichiers = []
    for racine, _sous_dossiers, noms in os.walk(dossier_source):
        for nom in sorted(noms, key=str.casefold):
            if nom.startswith("~$"):
                continue  # fichier verrou temporaire de Word, pas un vrai document
            if os.path.splitext(nom)[1].lower() in EXTENSIONS_ACCEPTEES:
                chemin_absolu = os.path.join(racine, nom)
                chemin_relatif = os.path.relpath(chemin_absolu, dossier_source)
                fichiers.append((chemin_absolu, chemin_relatif))
    return fichiers


def convertir_dossier(dossier_source, dossier_cible, fichiers, sur_progression, sur_erreur_globale):
    """Fait tout le travail de conversion. Pensé pour tourner dans un thread
    à part : ne touche à aucun widget Tkinter directement, seulement aux
    deux fonctions de rappel fournies (elles-mêmes thread-safe, voir la
    file d'attente utilisée par la fenêtre principale)."""
    try:
        moteur = creer_moteur()
    except ErreurConversion as erreur:
        sur_erreur_globale(str(erreur))
        return

    try:
        for chemin_absolu, chemin_relatif in fichiers:
            chemin_pdf = os.path.join(
                dossier_cible, os.path.splitext(chemin_relatif)[0] + ".pdf"
            )
            os.makedirs(os.path.dirname(chemin_pdf), exist_ok=True)
            try:
                moteur.convertir(chemin_absolu, chemin_pdf)
                sur_progression(chemin_relatif, None)
            except Exception as erreur:
                sur_progression(chemin_relatif, str(erreur))
    finally:
        moteur.fermer()


def ouvrir_dossier(chemin):
    if sys.platform.startswith("win"):
        os.startfile(chemin)  # type: ignore[attr-defined]
    elif sys.platform == "darwin":
        subprocess.run(["open", chemin], check=True)
    else:
        subprocess.run(["xdg-open", chemin], check=True)


# -- Interface ---------------------------------------------------------------


class ApplicationConvertisseur(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title(APP_TITLE)
        self.geometry("760x560")
        self.minsize(620, 460)
        self.configure(background=COULEUR_FOND)
        _definir_icone_fenetre(self)

        self._police_base, self._police_grasse = _configurer_style(self)

        self._dossier_source = None
        self._dossier_cible = None
        self._fichiers_a_convertir = []
        self._file_evenements = queue.Queue()
        self._conversion_en_cours = False

        self._construire_interface()
        self._verifier_file_evenements()

    def _construire_interface(self):
        police_bandeau_titre = (self._police_base[0], 20, "bold")
        police_bandeau_sous_titre = (self._police_base[0], 10, "normal")
        self.bandeau = BandeauTitre(self, police_bandeau_titre, police_bandeau_sous_titre)
        self.bandeau.pack(fill="x", side="top")

        cadre_haut = ttk.Frame(self, padding=16)
        cadre_haut.pack(fill="x")

        ttk.Label(
            cadre_haut,
            text=(
                "Choisissez un dossier contenant des fichiers Word (.docx) : le logiciel crée\n"
                "un nouveau dossier juste à côté, portant le même nom (suivi de « (PDF) »),\n"
                "avec la version PDF de chaque fichier — sous-dossiers compris."
            ),
            justify="left",
        ).pack(anchor="w")

        cadre_choix = ttk.Frame(cadre_haut)
        cadre_choix.pack(fill="x", pady=(12, 0))
        self.bouton_choisir = ttk.Button(
            cadre_choix,
            text="📂  Choisir un dossier à convertir…",
            style="Accent.TButton",
            command=self.choisir_dossier,
        )
        self.bouton_choisir.pack(side="left")

        self.var_dossier = tk.StringVar(value="Aucun dossier choisi pour l'instant.")
        ttk.Label(cadre_choix, textvariable=self.var_dossier, foreground=COULEUR_TEXTE_DOUX).pack(
            side="left", padx=(12, 0)
        )

        cadre_action = ttk.Frame(cadre_haut)
        cadre_action.pack(fill="x", pady=(14, 0))
        self.bouton_convertir = ttk.Button(
            cadre_action,
            text="▶  Convertir en PDF",
            style="Accent.TButton",
            command=self.lancer_conversion,
            state="disabled",
        )
        self.bouton_convertir.pack(side="left")

        self.bouton_ouvrir = ttk.Button(
            cadre_action, text="Ouvrir le dossier obtenu", command=self._ouvrir_dossier_resultat,
            state="disabled",
        )
        self.bouton_ouvrir.pack(side="left", padx=(8, 0))

        self.barre_progression = ttk.Progressbar(cadre_haut, mode="determinate")
        self.barre_progression.pack(fill="x", pady=(14, 0))

        self.var_statut = tk.StringVar(value="")
        ttk.Label(cadre_haut, textvariable=self.var_statut, foreground=COULEUR_TEXTE_DOUX).pack(
            anchor="w", pady=(6, 0)
        )

        cadre_journal = ttk.LabelFrame(self, text="Détail", padding=10)
        cadre_journal.pack(fill="both", expand=True, padx=16, pady=(4, 16))

        self.journal = tk.Listbox(cadre_journal)
        _styliser_liste(self.journal)
        self.journal.pack(side="left", fill="both", expand=True)

        defilement = ttk.Scrollbar(cadre_journal, orient="vertical", command=self.journal.yview)
        defilement.pack(side="left", fill="y")
        self.journal.configure(yscrollcommand=defilement.set)

    # -- Choix du dossier et lancement ---------------------------------

    def choisir_dossier(self):
        dossier = filedialog.askdirectory(title="Choisir le dossier à convertir")
        if not dossier:
            return

        fichiers = lister_fichiers_a_convertir(dossier)
        if not fichiers:
            messagebox.showinfo(
                APP_TITLE,
                "Aucun fichier Word (.docx ou .doc) trouvé dans ce dossier, "
                "ni dans ses sous-dossiers.",
            )
            return

        self._dossier_source = dossier
        self._fichiers_a_convertir = fichiers
        self.var_dossier.set(f"{dossier}  ({len(fichiers)} fichier(s) Word trouvé(s))")
        self.bouton_convertir.configure(state="normal")
        self.bouton_ouvrir.configure(state="disabled")
        self.journal.delete(0, "end")
        self.var_statut.set("")
        self.barre_progression.configure(value=0, maximum=max(len(fichiers), 1))

    def lancer_conversion(self):
        if self._conversion_en_cours or not self._dossier_source:
            return
        self._dossier_cible = dossier_sortie_pour(self._dossier_source)
        os.makedirs(self._dossier_cible, exist_ok=True)

        self._conversion_en_cours = True
        self.bouton_choisir.configure(state="disabled")
        self.bouton_convertir.configure(state="disabled")
        self.bouton_ouvrir.configure(state="disabled")
        self.journal.delete(0, "end")
        self.barre_progression.configure(value=0, maximum=len(self._fichiers_a_convertir))
        self.var_statut.set("Conversion en cours…")

        fil = threading.Thread(target=self._travail_conversion, daemon=True)
        fil.start()

    def _travail_conversion(self):
        """Tourne dans un thread séparé : ne fait que déposer des messages
        dans la file, jamais d'appel direct à un widget Tkinter."""

        def sur_progression(chemin_relatif, erreur):
            self._file_evenements.put(("progression", chemin_relatif, erreur))

        def sur_erreur_globale(message):
            self._file_evenements.put(("erreur_globale", message))

        convertir_dossier(
            self._dossier_source,
            self._dossier_cible,
            self._fichiers_a_convertir,
            sur_progression,
            sur_erreur_globale,
        )
        self._file_evenements.put(("termine", None))

    def _verifier_file_evenements(self):
        """Boucle appelée régulièrement par Tkinter (pas de threading direct
        sur l'interface) : relit les messages déposés par le thread de
        conversion et met la fenêtre à jour en conséquence."""
        try:
            while True:
                evenement = self._file_evenements.get_nowait()
                self._traiter_evenement(evenement)
        except queue.Empty:
            pass
        self.after(100, self._verifier_file_evenements)

    def _traiter_evenement(self, evenement):
        genre = evenement[0]

        if genre == "progression":
            _genre, chemin_relatif, erreur = evenement
            if erreur:
                self.journal.insert("end", f"✗  {chemin_relatif} — {erreur}")
                self.journal.itemconfig(self.journal.size() - 1, foreground=COULEUR_DANGER)
            else:
                self.journal.insert("end", f"✓  {chemin_relatif}")
                self.journal.itemconfig(self.journal.size() - 1, foreground=COULEUR_SUCCES)
            self.journal.see("end")
            self.barre_progression.configure(value=self.barre_progression["value"] + 1)
            fait = int(self.barre_progression["value"])
            total = len(self._fichiers_a_convertir)
            self.var_statut.set(f"{fait} / {total} fichier(s) traité(s)…")

        elif genre == "erreur_globale":
            _genre, message = evenement
            self._conversion_en_cours = False
            self.bouton_choisir.configure(state="normal")
            self.bouton_convertir.configure(state="normal")
            self.var_statut.set("")
            messagebox.showerror(APP_TITLE, message)

        elif genre == "termine":
            self._conversion_en_cours = False
            self.bouton_choisir.configure(state="normal")
            self.bouton_convertir.configure(state="normal")
            nb_echecs = sum(
                1 for i in range(self.journal.size()) if self.journal.get(i).startswith("✗")
            )
            nb_total = len(self._fichiers_a_convertir)
            nb_reussis = nb_total - nb_echecs
            self.var_statut.set(f"Terminé : {nb_reussis} / {nb_total} fichier(s) converti(s).")
            if nb_reussis > 0:
                self.bouton_ouvrir.configure(state="normal")
            if nb_echecs:
                messagebox.showwarning(
                    APP_TITLE,
                    f"{nb_reussis} fichier(s) converti(s), {nb_echecs} en échec.\n"
                    "Voir le détail dans la liste.",
                )
            else:
                messagebox.showinfo(
                    APP_TITLE, f"Les {nb_reussis} fichiers ont bien été convertis en PDF."
                )

    def _ouvrir_dossier_resultat(self):
        if self._dossier_cible and os.path.isdir(self._dossier_cible):
            try:
                ouvrir_dossier(self._dossier_cible)
            except Exception as erreur:
                messagebox.showerror(APP_TITLE, f"Impossible d'ouvrir ce dossier :\n{erreur}")


def main():
    app = ApplicationConvertisseur()
    app.mainloop()


if __name__ == "__main__":
    main()

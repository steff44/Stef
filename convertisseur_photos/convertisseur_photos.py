#!/usr/bin/env python3
"""Convertisseur Photos - prépare des photos pour l'écran (site, diaporama...).

Application de bureau (Tkinter), même présentation que Convertisseur PDF et
Steftuto. Elle transforme des photos RAW de tout constructeur (Nikon, Canon,
Sony, Fujifilm, Olympus/OM, Panasonic, Pentax, Leica, DNG...), des JPEG et la
plupart des autres formats d'image (TIFF, PNG, HEIC, WebP...) en JPEG ou en
WebP :
- plus grand côté ramené à 1920 pixels (réglable) — jamais agrandi,
- poids maximum de chaque photo (500 Ko par défaut, réglable),
- résolution 72 ppp,
- accentuation de la netteté adaptée à l'écran,
- conversion des couleurs en sRGB (l'espace des écrans et navigateurs),
- métadonnées EXIF conservées ou retirées, au choix (GPS retirable à part),
- renommage facultatif avec un numéro (Photo_001, Photo_002...).

On choisit une photo, plusieurs photos ou un dossier entier, puis le dossier
de destination. Les photos d'origine ne sont jamais modifiées.

Modules nécessaires :  pip install pillow rawpy exifread pillow-heif
(rawpy pour les RAW, exifread pour lire les métadonnées de certains RAW,
pillow-heif pour les photos HEIC d'iPhone — tous facultatifs sauf Pillow :
le logiciel signale clairement ce qui manque.)

Lancement :  python3 convertisseur_photos.py
"""

import io
import json
import os
import queue
import subprocess
import sys
import threading
import time
import tkinter as tk
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime
from tkinter import filedialog, messagebox, ttk

try:
    from PIL import Image, ImageCms, ImageFilter, ImageOps

    PILLOW_OK = True
except ImportError:  # signalé au démarrage, le logiciel ne peut rien faire sans
    PILLOW_OK = False

try:
    import rawpy

    RAWPY_OK = True
except ImportError:
    RAWPY_OK = False

try:
    import exifread

    EXIFREAD_OK = True
except ImportError:
    EXIFREAD_OK = False

HEIF_OK = False
if PILLOW_OK:
    try:
        import pillow_heif

        pillow_heif.register_heif_opener()
        HEIF_OK = True
        try:
            pillow_heif.register_avif_opener()
        except Exception:
            pass
    except ImportError:
        pass

APP_TITLE = "Convertisseur Photos"
SOUS_TITRE = "RAW, JPEG et autres formats → JPEG ou WebP prêts pour l'écran"

# Extensions RAW connues (LibRaw, via rawpy, les lit presque toutes).
EXTENSIONS_RAW = {
    ".3fr", ".ari", ".arw", ".bay", ".cap", ".cr2", ".cr3", ".crw", ".dcr", ".dcs",
    ".dng", ".drf", ".eip", ".erf", ".fff", ".gpr", ".iiq", ".k25", ".kdc", ".mdc",
    ".mef", ".mos", ".mrw", ".nef", ".nrw", ".obm", ".orf", ".ori", ".pef", ".ptx",
    ".pxn", ".r3d", ".raf", ".raw", ".rw2", ".rwl", ".rwz", ".sr2", ".srf", ".srw",
    ".x3f",
}
# Autres formats d'image ouverts par Pillow (et pillow-heif pour HEIC/AVIF).
EXTENSIONS_IMAGES = {
    ".jpg", ".jpeg", ".jpe", ".jfif", ".png", ".tif", ".tiff", ".bmp", ".gif",
    ".webp", ".heic", ".heif", ".avif", ".jp2", ".j2k", ".psd", ".tga", ".ppm",
    ".pgm", ".pbm", ".ico", ".dib", ".pcx",
}
EXTENSIONS_ACCEPTEES = EXTENSIONS_RAW | EXTENSIONS_IMAGES

NIVEAUX_NETTETE = {
    # libellé : (rayon, pourcentage, seuil) du masque flou (unsharp mask),
    # appliqué APRÈS la réduction à 1920 px : c'est à cette taille que la
    # photo sera vue, donc c'est là qu'il faut accentuer.
    "Aucune": None,
    "Légère": (0.6, 60, 2),
    "Normale (écran)": (0.8, 90, 2),
    "Forte": (1.0, 130, 2),
}

ORDRES = ("Nom de fichier", "Date de prise de vue", "Ordre de la liste")

POIDS_MAX_DEFAUT_KO = 500
# Pour tenir sous le poids maximum, on baisse d'abord la qualité, mais pas
# en dessous de QUALITE_MIN (au-delà, la compression se voit) ; si ça ne
# suffit pas, on réduit un peu les dimensions, jusqu'à COTE_MIN pixels.
QUALITE_MIN = 70
QUALITE_PLANCHER = 30
COTE_MIN = 480

FICHIER_REGLAGES = os.path.join(os.path.expanduser("~"), ".convertisseur_photos.json")

# Icône de l'application (même appareil photo blanc sur fond dégradé bleu ->
# violet que Steftuto et Convertisseur PDF), encodée en base64 pour rester
# dans ce seul fichier.
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


# -- Palette et style (identique à Steftuto / Convertisseur PDF) -------------
COULEUR_ACCENT = "#2563EB"
COULEUR_ACCENT_ACTIF = "#1D4ED8"
COULEUR_ACCENT_2 = "#7C3AED"
COULEUR_DANGER = "#DC2626"
COULEUR_DANGER_ACTIF = "#B91C1C"
COULEUR_SUCCES = "#16A34A"
COULEUR_AVERTISSEMENT = "#B45309"
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
    style.configure("Doux.TLabel", background=COULEUR_FOND, foreground=COULEUR_TEXTE_DOUX)
    style.configure("TCheckbutton", background=COULEUR_FOND, foreground=COULEUR_TEXTE)
    style.configure("TRadiobutton", background=COULEUR_FOND, foreground=COULEUR_TEXTE)
    style.map("TCheckbutton", background=[("active", COULEUR_FOND)])
    style.map("TRadiobutton", background=[("active", COULEUR_FOND)])
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
        "Danger.TButton",
        padding=(12, 7),
        font=police_base,
        background=COULEUR_CARTE,
        foreground=COULEUR_DANGER,
        bordercolor=COULEUR_BORDURE,
    )
    style.map(
        "Danger.TButton",
        background=[("active", "#FEE2E2")],
        foreground=[("disabled", COULEUR_TEXTE_DOUX)],
    )

    style.configure(
        "TEntry",
        fieldbackground=COULEUR_CARTE,
        bordercolor=COULEUR_BORDURE,
        padding=5,
    )
    style.map("TEntry", bordercolor=[("focus", COULEUR_ACCENT)])
    style.configure(
        "TSpinbox", fieldbackground=COULEUR_CARTE, bordercolor=COULEUR_BORDURE, padding=4
    )
    style.configure(
        "TCombobox", fieldbackground=COULEUR_CARTE, bordercolor=COULEUR_BORDURE, padding=4
    )

    style.configure(
        "Treeview",
        background=COULEUR_CARTE,
        fieldbackground=COULEUR_CARTE,
        foreground=COULEUR_TEXTE,
        bordercolor=COULEUR_BORDURE,
        rowheight=24,
    )
    style.map(
        "Treeview",
        background=[("selected", COULEUR_ACCENT)],
        foreground=[("selected", "white")],
    )
    style.configure(
        "Treeview.Heading",
        background=COULEUR_LIGNE_ALTERNEE,
        foreground=COULEUR_TEXTE,
        font=police_grasse,
        relief="flat",
        bordercolor=COULEUR_BORDURE,
    )
    style.map("Treeview.Heading", background=[("active", COULEUR_BANDEAU_SOUS_TITRE)])

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
        activestyle="none",
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


class CadreDefilant(ttk.Frame):
    """Colonne qui défile avec la molette quand l'écran est trop petit pour
    tout afficher (portable, affichage agrandi à 125 % ou 150 %...)."""

    def __init__(self, parent):
        super().__init__(parent)
        self._canvas = tk.Canvas(self, highlightthickness=0, bd=0, background=COULEUR_FOND)
        self._barre = ttk.Scrollbar(self, orient="vertical", command=self._canvas.yview)
        self.interieur = ttk.Frame(self._canvas)
        self._fenetre = self._canvas.create_window((0, 0), window=self.interieur, anchor="nw")
        self._canvas.configure(yscrollcommand=self._barre.set)
        self._canvas.pack(side="left", fill="both", expand=True)
        self._barre.pack(side="right", fill="y")
        self.interieur.bind("<Configure>", self._contenu_change)
        self._canvas.bind("<Configure>", self._cadre_change)
        self._canvas.bind("<Enter>", self._activer_molette)
        self._canvas.bind("<Leave>", self._desactiver_molette)

    def _contenu_change(self, _evenement=None):
        self._canvas.configure(
            scrollregion=self._canvas.bbox("all"), width=self.interieur.winfo_reqwidth()
        )

    def _cadre_change(self, evenement):
        self._canvas.itemconfigure(self._fenetre, width=evenement.width)

    def _activer_molette(self, _evenement=None):
        self._canvas.bind_all("<MouseWheel>", self._molette)
        self._canvas.bind_all("<Button-4>", lambda _e: self._canvas.yview_scroll(-1, "units"))
        self._canvas.bind_all("<Button-5>", lambda _e: self._canvas.yview_scroll(1, "units"))

    def _desactiver_molette(self, _evenement=None):
        for sequence in ("<MouseWheel>", "<Button-4>", "<Button-5>"):
            self._canvas.unbind_all(sequence)

    def _molette(self, evenement):
        if self._canvas.yview() == (0.0, 1.0):
            return  # tout est déjà visible
        pas = -1 if evenement.delta > 0 else 1
        self._canvas.yview_scroll(pas, "units")


# -- Lecture des photos ------------------------------------------------------

# Étiquettes EXIF (numéros standard) utilisées plus bas.
TAG_EXIF_IFD = 0x8769
TAG_GPS_IFD = 0x8825
TAG_INTEROP_IFD = 0xA005
TAG_ORIENTATION = 0x0112
TAG_X_RESOLUTION = 0x011A
TAG_Y_RESOLUTION = 0x011B
TAG_RESOLUTION_UNIT = 0x0128
TAG_DATETIME = 0x0132
TAG_DATETIME_ORIGINAL = 0x9003
TAG_MAKERNOTE = 0x927C
TAG_PIXEL_X = 0xA002
TAG_PIXEL_Y = 0xA003
TAG_COLOR_SPACE = 0xA001

# Étiquettes de l'IFD principal qui décrivent la photo (appareil, date,
# auteur...) et méritent d'être recopiées. Les autres (dimensions, offsets
# des données RAW, sous-IFD propres au constructeur...) décrivent le fichier
# d'origine et deviendraient fausses dans la nouvelle image.
TAGS_IFD0_CONSERVES = {
    0x010E,  # ImageDescription
    0x010F,  # Make
    0x0110,  # Model
    0x0131,  # Software
    0x0132,  # DateTime
    0x013B,  # Artist
    0x8298,  # Copyright
    0x9C9B, 0x9C9C, 0x9C9D, 0x9C9E, 0x9C9F,  # XPTitle, XPComment, XPAuthor, XPKeywords, XPSubject
}
TAGS_EXIF_EXCLUS = {TAG_MAKERNOTE, TAG_INTEROP_IFD, TAG_PIXEL_X, TAG_PIXEL_Y, 0xA420}


class ErreurPhoto(Exception):
    """Levée quand une photo précise ne peut pas être lue ou écrite."""


def est_raw(chemin):
    return os.path.splitext(chemin)[1].lower() in EXTENSIONS_RAW


def taille_lisible(octets):
    """1 234 567 octets -> « 1,2 Mo » ; 456 789 -> « 446 Ko »."""
    if octets is None:
        return ""
    if octets < 1024 * 1024:
        return f"{max(1, round(octets / 1024))} Ko"
    return f"{octets / (1024 * 1024):.1f} Mo".replace(".", ",")


def lire_dimensions(chemin):
    """Dimensions (largeur, hauteur) de la photo d'origine, dans le sens où
    elle s'affiche (portrait/paysage), ou None si illisible."""
    try:
        if est_raw(chemin) and RAWPY_OK:
            with rawpy.imread(chemin) as raw:
                largeur, hauteur = raw.sizes.width, raw.sizes.height
                if raw.sizes.flip in (5, 6):
                    largeur, hauteur = hauteur, largeur
                return largeur, hauteur
        with Image.open(chemin) as image:
            largeur, hauteur = image.size
            try:
                orientation = image.getexif().get(TAG_ORIENTATION)
            except Exception:
                orientation = None
            if orientation in (5, 6, 7, 8):
                largeur, hauteur = hauteur, largeur
            return largeur, hauteur
    except Exception:
        return None


def _exif_depuis_pillow(chemin):
    """Essaie de lire l'EXIF avec Pillow (JPEG, TIFF, HEIC, PNG, WebP, et
    beaucoup de RAW construits sur le format TIFF : NEF, CR2, ARW, DNG...)."""
    try:
        with Image.open(chemin) as image:
            exif = image.getexif()
            if exif and (exif.get(0x010F) or exif.get(TAG_EXIF_IFD)):
                return exif
    except Exception:
        pass
    return None


def _sous_ifd(exif, tag):
    """Renvoie un sous-bloc EXIF (réglages de prise de vue, GPS) sous forme de
    dictionnaire, qu'il vienne d'un fichier lu par Pillow ou qu'il ait été
    reconstruit à la main (dictionnaire rangé directement sous l'étiquette)."""
    if exif is None:
        return {}
    valeur = exif.get(tag)
    if isinstance(valeur, dict):
        return valeur
    if valeur is None:
        return {}
    try:
        return dict(exif.get_ifd(tag))
    except Exception:
        return {}


def _valeur_exifread(tag):
    """Convertit une valeur lue par exifread en valeur acceptée par Pillow."""
    from PIL.TiffImagePlugin import IFDRational

    valeurs = tag.values
    if isinstance(valeurs, (str, bytes)):
        return valeurs.strip() if isinstance(valeurs, str) else valeurs
    if getattr(tag, "field_type", None) == 7:  # type « UNDEFINED » : octets bruts
        try:
            return bytes(valeurs)
        except (TypeError, ValueError):
            pass
    convertis = []
    for v in valeurs:
        num = getattr(v, "num", getattr(v, "numerator", None))
        den = getattr(v, "den", getattr(v, "denominator", None))
        if num is not None and den is not None and not isinstance(v, int):
            convertis.append(IFDRational(num, den or 1))
        else:
            convertis.append(v)
    if len(convertis) == 1:
        return convertis[0]
    return tuple(convertis)


def _exif_depuis_exifread(chemin):
    """Repli pour les RAW que Pillow ne sait pas lire (CR3, RAF, ORF, RW2...) :
    exifread les décode, et on reconstruit un EXIF Pillow à partir des
    étiquettes utiles."""
    if not EXIFREAD_OK:
        return None
    try:
        with open(chemin, "rb") as f:
            tags = exifread.process_file(f, details=False)
    except Exception:
        return None
    if not tags:
        return None

    exif = Image.Exif()
    ifd_exif = {}
    ifd_gps = {}
    for cle, tag in tags.items():
        try:
            numero = tag.tag
            valeur = _valeur_exifread(tag)
        except Exception:
            continue
        if cle.startswith("Image ") and numero in TAGS_IFD0_CONSERVES:
            exif[numero] = valeur
        elif cle.startswith("EXIF ") and numero not in TAGS_EXIF_EXCLUS:
            ifd_exif[numero] = valeur
        elif cle.startswith("GPS "):
            ifd_gps[numero] = valeur
    if ifd_exif:
        exif[TAG_EXIF_IFD] = ifd_exif
    if ifd_gps:
        exif[TAG_GPS_IFD] = ifd_gps
    if not exif.get(0x010F) and not ifd_exif:
        return None
    return exif


def lire_exif(chemin):
    exif = _exif_depuis_pillow(chemin)
    if exif is None and est_raw(chemin):
        exif = _exif_depuis_exifread(chemin)
    return exif


def date_prise_de_vue(chemin):
    """Date de prise de vue (EXIF), sinon date de modification du fichier.
    Sert uniquement à trier les photos avant de les numéroter."""
    exif = lire_exif(chemin)
    if exif is not None:
        texte = _sous_ifd(exif, TAG_EXIF_IFD).get(TAG_DATETIME_ORIGINAL) or exif.get(TAG_DATETIME)
        if isinstance(texte, bytes):
            texte = texte.decode("ascii", "ignore")
        if texte:
            try:
                return datetime.strptime(str(texte).strip()[:19], "%Y:%m:%d %H:%M:%S").timestamp()
            except ValueError:
                pass
    try:
        return os.path.getmtime(chemin)
    except OSError:
        return 0.0


def _ouvrir_raw(chemin, taille_max):
    """Développe un RAW avec LibRaw (via rawpy) : balance des blancs de
    l'appareil, couleurs sRGB, rotation appliquée. Quand le capteur est
    largement plus grand que la taille voulue, on développe en demi-taille
    (bien plus rapide, et sans perte visible une fois réduit à 1920 px)."""
    if not RAWPY_OK:
        raise ErreurPhoto(
            "fichier RAW, mais le module « rawpy » n'est pas installé "
            "(pip install rawpy)"
        )
    try:
        with rawpy.imread(chemin) as raw:
            try:
                largeur, hauteur = raw.sizes.width, raw.sizes.height
                demi = taille_max > 0 and max(largeur, hauteur) / 2 >= taille_max
            except Exception:
                demi = False
            try:
                rgb = raw.postprocess(
                    use_camera_wb=True,
                    half_size=demi,
                    output_bps=8,
                    output_color=rawpy.ColorSpace.sRGB,
                )
                return Image.fromarray(rgb), None
            except Exception as erreur_dev:
                # Certains RAW très récents ou exotiques ne se développent pas
                # encore : on se rabat sur l'aperçu JPEG intégré au fichier,
                # souvent en pleine définition.
                try:
                    vignette = raw.extract_thumb()
                except Exception:
                    raise ErreurPhoto(f"RAW illisible ({erreur_dev})") from erreur_dev
                if vignette.format == rawpy.ThumbFormat.JPEG:
                    image = Image.open(io.BytesIO(vignette.data))
                    image.load()
                    image = ImageOps.exif_transpose(image)
                else:
                    image = Image.fromarray(vignette.data)
                return image, "développement RAW impossible, aperçu intégré utilisé"
    except ErreurPhoto:
        raise
    except Exception as erreur:
        raise ErreurPhoto(f"RAW illisible ({erreur})") from erreur


_PROFIL_SRGB = None


def _profil_srgb():
    global _PROFIL_SRGB
    if _PROFIL_SRGB is None:
        _PROFIL_SRGB = ImageCms.createProfile("sRGB")
    return _PROFIL_SRGB


def _vers_srgb(image):
    """Ramène l'image en RGB 8 bits dans l'espace sRGB. Une photo en Adobe
    RGB ou ProPhoto affichée telle quelle sur le web paraît terne : on
    convertit donc à partir de son profil ICC s'il y en a un."""
    icc = image.info.get("icc_profile")
    if image.mode in ("I;16", "I;16B", "I;16L", "I"):
        try:  # niveaux de gris 16 bits : ramenés sur 8 bits
            image = image.point(lambda v: v * (1 / 256)).convert("L")
        except Exception:
            image = image.convert("L")
    if image.mode in ("RGBA", "LA", "P", "PA"):
        # Les transparences deviennent blanches (JPEG ne sait pas les garder).
        rgba = image.convert("RGBA")
        fond = Image.new("RGB", rgba.size, (255, 255, 255))
        fond.paste(rgba, mask=rgba.getchannel("A"))
        image = fond
    if icc:
        try:
            source = ImageCms.ImageCmsProfile(io.BytesIO(icc))
            if image.mode not in ("RGB", "CMYK", "L"):
                image = image.convert("RGB")
            image = ImageCms.profileToProfile(
                image, source, _profil_srgb(), renderingIntent=0, outputMode="RGB"
            )
        except Exception:
            pass
    if image.mode != "RGB":
        image = image.convert("RGB")
    return image


def ouvrir_photo(chemin, taille_max):
    """Renvoie (image RGB sRGB déjà redressée, remarque éventuelle)."""
    if est_raw(chemin):
        image, remarque = _ouvrir_raw(chemin, taille_max)
        return _vers_srgb(image), remarque
    try:
        image = Image.open(chemin)
        if getattr(image, "is_animated", False):
            image.seek(0)  # GIF/WebP animé : on garde la première image
        image.load()
    except Exception as erreur:
        # Extension d'image inconnue de Pillow mais peut-être un RAW renommé.
        if RAWPY_OK:
            try:
                image, remarque = _ouvrir_raw(chemin, taille_max)
                return _vers_srgb(image), remarque
            except ErreurPhoto:
                pass
        extension = os.path.splitext(chemin)[1].lower()
        if extension in (".heic", ".heif", ".avif") and not HEIF_OK:
            raise ErreurPhoto(
                "photo HEIC/AVIF, mais le module « pillow-heif » n'est pas installé "
                "(pip install pillow-heif)"
            ) from erreur
        raise ErreurPhoto(f"format non reconnu ({erreur})") from erreur
    image = ImageOps.exif_transpose(image)  # applique la rotation portrait/paysage
    return _vers_srgb(image), None


def redimensionner(image, taille_max):
    """Ramène le plus grand côté à taille_max. Une photo déjà plus petite
    n'est JAMAIS agrandie (elle deviendrait floue sans rien gagner)."""
    largeur, hauteur = image.size
    grand_cote = max(largeur, hauteur)
    if taille_max <= 0 or grand_cote <= taille_max:
        return image
    echelle = taille_max / grand_cote
    nouvelle = (max(1, round(largeur * echelle)), max(1, round(hauteur * echelle)))
    return image.resize(nouvelle, Image.LANCZOS, reducing_gap=3.0)


def accentuer(image, niveau):
    reglage = NIVEAUX_NETTETE.get(niveau)
    if not reglage:
        return image
    rayon, pourcentage, seuil = reglage
    return image.filter(ImageFilter.UnsharpMask(radius=rayon, percent=pourcentage, threshold=seuil))


def preparer_exif(exif_source, largeur, hauteur, garder_gps):
    """Construit l'EXIF de la photo convertie à partir de celui d'origine :
    on garde appareil, objectif, réglages, date, auteur, copyright ; on met à
    jour ce qui a changé (orientation déjà appliquée, dimensions, 72 ppp,
    espace sRGB) ; on retire la note constructeur (souvent très lourde et
    illisible ailleurs) et, si demandé, la position GPS."""
    from PIL.TiffImagePlugin import IFDRational

    exif = Image.Exif()
    if exif_source is not None:
        for tag in TAGS_IFD0_CONSERVES:
            valeur = exif_source.get(tag)
            if valeur not in (None, "", b""):
                exif[tag] = valeur
        ifd_exif = {
            tag: valeur
            for tag, valeur in _sous_ifd(exif_source, TAG_EXIF_IFD).items()
            if tag not in TAGS_EXIF_EXCLUS and not isinstance(valeur, dict)
        }
        ifd_gps = dict(_sous_ifd(exif_source, TAG_GPS_IFD)) if garder_gps else {}
    else:
        ifd_exif, ifd_gps = {}, {}

    exif[TAG_ORIENTATION] = 1
    exif[TAG_X_RESOLUTION] = IFDRational(72, 1)
    exif[TAG_Y_RESOLUTION] = IFDRational(72, 1)
    exif[TAG_RESOLUTION_UNIT] = 2  # pouces
    ifd_exif[TAG_PIXEL_X] = largeur
    ifd_exif[TAG_PIXEL_Y] = hauteur
    ifd_exif[TAG_COLOR_SPACE] = 1  # sRGB

    exif[TAG_EXIF_IFD] = ifd_exif
    if ifd_gps:
        exif[TAG_GPS_IFD] = ifd_gps
    return exif


def _exif_en_octets(exif):
    """tobytes() peut échouer sur une valeur exotique venue d'un RAW : on
    retire alors les étiquettes fautives une à une plutôt que de tout perdre."""
    try:
        return exif.tobytes()
    except Exception:
        pass
    ifd_exif = exif[TAG_EXIF_IFD]
    for tag in list(ifd_exif):
        if tag in (TAG_PIXEL_X, TAG_PIXEL_Y, TAG_COLOR_SPACE):
            continue
        valeur = ifd_exif.pop(tag)
        try:
            exif.tobytes()
            ifd_exif[tag] = valeur  # celle-ci n'était pas en cause
        except Exception:
            pass
    try:
        return exif.tobytes()
    except Exception:
        exif.pop(TAG_GPS_IFD, None)
        return exif.tobytes()


_ICC_SRGB_OCTETS = None


def _profil_icc_srgb_octets():
    """Profil sRGB joint à chaque photo (quelques centaines d'octets) : il
    garantit un rendu de couleurs identique dans tous les navigateurs."""
    global _ICC_SRGB_OCTETS
    if _ICC_SRGB_OCTETS is None:
        try:
            _ICC_SRGB_OCTETS = ImageCms.ImageCmsProfile(_profil_srgb()).tobytes()
        except Exception:
            _ICC_SRGB_OCTETS = b""
    return _ICC_SRGB_OCTETS


def encoder(image, format_sortie, qualite, exif_octets):
    """Encode la photo en mémoire et renvoie les octets du fichier final :
    on peut ainsi mesurer son poids exact avant de l'écrire sur le disque."""
    tampon = io.BytesIO()
    parametres = {"quality": int(qualite)}
    icc = _profil_icc_srgb_octets()
    if icc:
        parametres["icc_profile"] = icc
    if exif_octets:
        parametres["exif"] = exif_octets
    if format_sortie == "JPEG":
        parametres.update(dpi=(72, 72), optimize=True, progressive=True, subsampling="4:2:0")
        image.save(tampon, "JPEG", **parametres)
    else:
        parametres.update(method=4)
        image.save(tampon, "WEBP", **parametres)
    return tampon.getvalue()


def _meilleure_qualite_sous_limite(image, format_sortie, qualite_max, qualite_min,
                                   exif_octets, limite):
    """Recherche par dichotomie la plus haute qualité entre qualite_min et
    qualite_max qui tient sous la limite. Renvoie (qualité, octets) ou None."""
    bas, haut = qualite_min, qualite_max
    meilleur = None
    while bas <= haut:
        milieu = (bas + haut) // 2
        donnees = encoder(image, format_sortie, milieu, exif_octets)
        if len(donnees) <= limite:
            meilleur = (milieu, donnees)
            bas = milieu + 1
        else:
            haut = milieu - 1
    return meilleur


def convertir_une_photo(chemin, chemin_sortie, reglages):
    """Tout le traitement d'une photo. Renvoie un dictionnaire décrivant le
    résultat : dimensions, poids, remarques éventuelles."""
    taille = reglages["taille"]
    format_sortie = reglages["format"]
    qualite = reglages["qualite"]
    limite = reglages["poids_max_ko"] * 1024

    source, remarque_ouverture = ouvrir_photo(chemin, taille)
    exif_source = lire_exif(chemin) if reglages["garder_metadonnees"] else None

    def preparer(cote):
        image = accentuer(redimensionner(source, cote), reglages["nettete"])
        exif_octets = None
        if reglages["garder_metadonnees"]:
            exif = preparer_exif(exif_source, image.width, image.height, reglages["garder_gps"])
            exif_octets = _exif_en_octets(exif)
        return image, exif_octets

    cote = min(taille, max(source.size)) if taille > 0 else max(source.size)
    cote_depart = cote
    image, exif_octets = preparer(cote)
    qualite_finale = qualite
    donnees = encoder(image, format_sortie, qualite, exif_octets)
    trop_lourde = False

    if limite and len(donnees) > limite:
        # 1) baisser un peu la qualité ; 2) sinon réduire les dimensions de
        # 10 % et recommencer ; 3) au plus petit, accepter une qualité plus
        # basse plutôt que d'échouer.
        while True:
            trouve = _meilleure_qualite_sous_limite(
                image, format_sortie, qualite - 1, min(QUALITE_MIN, qualite - 1),
                exif_octets, limite,
            )
            if trouve:
                qualite_finale, donnees = trouve
                break
            if cote <= COTE_MIN:
                trouve = _meilleure_qualite_sous_limite(
                    image, format_sortie, QUALITE_MIN - 1, QUALITE_PLANCHER, exif_octets, limite
                )
                if trouve:
                    qualite_finale, donnees = trouve
                else:
                    qualite_finale = QUALITE_PLANCHER
                    donnees = encoder(image, format_sortie, QUALITE_PLANCHER, exif_octets)
                    trop_lourde = True
                break
            cote = max(COTE_MIN, int(cote * 0.9))
            image, exif_octets = preparer(cote)

    fichier_temporaire = chemin_sortie + ".partiel"
    try:
        with open(fichier_temporaire, "wb") as f:
            f.write(donnees)
        os.replace(fichier_temporaire, chemin_sortie)
    except Exception as erreur:
        try:
            os.remove(fichier_temporaire)
        except OSError:
            pass
        raise ErreurPhoto(f"écriture impossible ({erreur})") from erreur

    remarques = []
    if qualite_finale != qualite:
        remarques.append(f"qualité {qualite_finale}")
    if cote < cote_depart:
        remarques.append("dimensions réduites pour tenir le poids")
    if trop_lourde:
        remarques.append("poids maximum impossible à atteindre")
    if remarque_ouverture:
        remarques.append(remarque_ouverture)
    return {
        "largeur": image.width,
        "hauteur": image.height,
        "poids": len(donnees),
        "remarques": remarques,
        "alerte": bool(remarque_ouverture or trop_lourde),
    }


# -- Liste des photos, noms de sortie ----------------------------------------


def lister_photos_dossier(dossier, sous_dossiers):
    trouves = []
    if sous_dossiers:
        for racine, dossiers, noms in os.walk(dossier):
            dossiers.sort(key=str.casefold)
            for nom in sorted(noms, key=str.casefold):
                if _est_photo(nom):
                    trouves.append(os.path.join(racine, nom))
    else:
        try:
            noms = sorted(os.listdir(dossier), key=str.casefold)
        except OSError:
            return []
        for nom in noms:
            chemin = os.path.join(dossier, nom)
            if os.path.isfile(chemin) and _est_photo(nom):
                trouves.append(chemin)
    return trouves


def _est_photo(nom):
    if nom.startswith(".") or nom.startswith("~$"):
        return False
    return os.path.splitext(nom)[1].lower() in EXTENSIONS_ACCEPTEES


def nom_numerote(prefixe, numero, chiffres, extension):
    return f"{prefixe}{numero:0{chiffres}d}{extension}"


def extension_sortie(format_sortie):
    return ".jpg" if format_sortie == "JPEG" else ".webp"


def calculer_noms_sortie(photos, dossier_cible, reglages):
    """Associe à chaque photo son chemin de sortie. Deux photos ne peuvent
    jamais recevoir le même nom (ex. IMG_001.NEF et IMG_001.JPG du même
    déclenchement) ; un fichier déjà présent dans la destination n'est
    jamais écrasé : on ajoute « (2) », « (3) »... au nom."""
    extension = extension_sortie(reglages["format"])
    deja_pris = set()
    resultat = []
    numero = reglages["numero_depart"]
    for chemin in photos:
        if reglages["renommer"]:
            base = nom_numerote(reglages["prefixe"], numero, reglages["chiffres"], "")
            numero += 1
        else:
            base = os.path.splitext(os.path.basename(chemin))[0]
        candidat = base
        n = 2
        while (
            candidat.casefold() in deja_pris
            or os.path.exists(os.path.join(dossier_cible, candidat + extension))
        ):
            candidat = f"{base} ({n})"
            n += 1
        deja_pris.add(candidat.casefold())
        resultat.append((chemin, os.path.join(dossier_cible, candidat + extension)))
    return resultat


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
        # Fenêtre adaptée à l'écran : sur un portable, une fenêtre plus haute
        # que l'écran cacherait le bas (dont le dossier de destination).
        largeur = min(1120, self.winfo_screenwidth() - 40)
        hauteur = min(800, self.winfo_screenheight() - 90)
        self.geometry(f"{largeur}x{hauteur}+20+10")
        self.minsize(820, 540)
        self.configure(background=COULEUR_FOND)
        _definir_icone_fenetre(self)

        self._police_base, self._police_grasse = _configurer_style(self)

        self._photos = []  # chemins absolus, dans l'ordre de la liste
        self._iid_par_chemin = {}
        self._chemin_par_iid = {}
        self._poids = {}  # chemin -> poids du fichier d'origine
        self._compteur_iid = 0
        self._dossier_cible = None
        self._file_evenements = queue.Queue()
        self._file_dimensions = queue.Queue()
        self._conversion_en_cours = False
        self._annulation = threading.Event()
        self._nb_total = 0
        self._nb_reussis = 0
        self._nb_echecs = 0
        self._poids_total_sortie = 0

        self._creer_variables()
        self._charger_reglages()
        self._construire_interface()
        self._mettre_a_jour_exemple()
        self._mettre_a_jour_etats()
        self._verifier_file_evenements()
        threading.Thread(target=self._lecteur_dimensions, daemon=True).start()
        self.protocol("WM_DELETE_WINDOW", self._fermer)
        self.after(300, self._signaler_modules_manquants)

    # -- Réglages mémorisés d'une fois sur l'autre ------------------------

    def _creer_variables(self):
        self.var_format = tk.StringVar(value="JPEG")
        self.var_qualite = tk.IntVar(value=90)
        self.var_taille = tk.IntVar(value=1920)
        self.var_poids_max = tk.IntVar(value=POIDS_MAX_DEFAUT_KO)
        self.var_nettete = tk.StringVar(value="Normale (écran)")
        self.var_metadonnees = tk.BooleanVar(value=True)
        self.var_gps = tk.BooleanVar(value=False)
        self.var_renommer = tk.BooleanVar(value=False)
        self.var_prefixe = tk.StringVar(value="Photo_")
        self.var_numero = tk.IntVar(value=1)
        self.var_chiffres = tk.IntVar(value=3)
        self.var_ordre = tk.StringVar(value=ORDRES[0])
        self.var_sous_dossiers = tk.BooleanVar(value=True)
        self.var_destination = tk.StringVar(value="")

    VARIABLES_MEMORISEES = (
        "format", "qualite", "taille", "poids_max", "nettete", "metadonnees", "gps",
        "renommer", "prefixe", "chiffres", "ordre", "sous_dossiers", "destination",
    )

    def _charger_reglages(self):
        try:
            with open(FICHIER_REGLAGES, encoding="utf-8") as f:
                donnees = json.load(f)
        except (OSError, ValueError):
            return
        for nom in self.VARIABLES_MEMORISEES:
            if nom in donnees:
                try:
                    getattr(self, f"var_{nom}").set(donnees[nom])
                except (tk.TclError, ValueError):
                    pass
        if self.var_nettete.get() not in NIVEAUX_NETTETE:
            self.var_nettete.set("Normale (écran)")
        if self.var_ordre.get() not in ORDRES:
            self.var_ordre.set(ORDRES[0])
        if self.var_format.get() not in ("JPEG", "WEBP"):
            self.var_format.set("JPEG")
        if self.var_destination.get() and not os.path.isdir(self.var_destination.get()):
            self.var_destination.set("")

    def _enregistrer_reglages(self):
        donnees = {}
        for nom in self.VARIABLES_MEMORISEES:
            try:
                donnees[nom] = getattr(self, f"var_{nom}").get()
            except (tk.TclError, ValueError):
                pass
        try:
            with open(FICHIER_REGLAGES, "w", encoding="utf-8") as f:
                json.dump(donnees, f, ensure_ascii=False, indent=2)
        except OSError:
            pass

    def _fermer(self):
        if self._conversion_en_cours and not messagebox.askyesno(
            APP_TITLE, "Une conversion est en cours. Voulez-vous vraiment quitter ?"
        ):
            return
        self._annulation.set()
        self._enregistrer_reglages()
        self.destroy()

    def _signaler_modules_manquants(self):
        manquants = []
        if not RAWPY_OK:
            manquants.append("• rawpy — indispensable pour les photos RAW")
        if not HEIF_OK:
            manquants.append("• pillow-heif — pour les photos HEIC (iPhone)")
        if not EXIFREAD_OK:
            manquants.append("• exifread — métadonnées de certains RAW (CR3, RAF, ORF...)")
        if manquants:
            self._journaliser(
                "ℹ  Modules facultatifs absents : " + ", ".join(
                    m.split(" — ")[0].lstrip("• ") for m in manquants
                ),
                COULEUR_AVERTISSEMENT,
            )
        if not RAWPY_OK:
            messagebox.showwarning(
                APP_TITLE,
                "Certains modules ne sont pas installés :\n\n" + "\n".join(manquants)
                + "\n\nPour les installer, tapez dans une invite de commandes :\n"
                "pip install rawpy pillow-heif exifread\n\n"
                "Le logiciel fonctionne quand même pour les autres formats.",
            )

    # -- Construction de la fenêtre -----------------------------------------

    def _construire_interface(self):
        police_bandeau_titre = (self._police_base[0], 20, "bold")
        police_bandeau_sous_titre = (self._police_base[0], 10, "normal")
        self.bandeau = BandeauTitre(self, police_bandeau_titre, police_bandeau_sous_titre)
        self.bandeau.pack(fill="x", side="top")

        # Bas de fenêtre (destination, actions, progression, détail), posé
        # avant le centre pour qu'il reste toujours visible, même sur un
        # petit écran.
        cadre_bas = ttk.Frame(self, padding=(16, 0, 16, 12))
        cadre_bas.pack(fill="x", side="bottom")
        self._construire_bas(cadre_bas)

        centre = ttk.Frame(self, padding=(16, 12, 16, 8))
        centre.pack(fill="both", expand=True)
        centre.columnconfigure(0, weight=1)
        centre.columnconfigure(1, weight=0)
        centre.rowconfigure(0, weight=1)

        self._construire_liste_photos(centre)
        colonne_droite = CadreDefilant(centre)
        colonne_droite.grid(row=0, column=1, sticky="nsew", padx=(14, 0))
        self._construire_reglages(colonne_droite.interieur)
        self._construire_noms(colonne_droite.interieur)

    def _construire_liste_photos(self, parent):
        cadre = ttk.LabelFrame(parent, text="1. Photos à convertir", padding=10)
        cadre.grid(row=0, column=0, sticky="nsew")
        cadre.columnconfigure(0, weight=1)
        cadre.rowconfigure(2, weight=1)

        boutons = ttk.Frame(cadre)
        boutons.grid(row=0, column=0, columnspan=2, sticky="w")
        self.bouton_ajout_photos = ttk.Button(
            boutons, text="🖼  Ajouter des photos…", style="Accent.TButton",
            command=self.ajouter_photos,
        )
        self.bouton_ajout_photos.pack(side="left")
        self.bouton_ajout_dossier = ttk.Button(
            boutons, text="📂  Ajouter un dossier…", style="Accent.TButton",
            command=self.ajouter_dossier,
        )
        self.bouton_ajout_dossier.pack(side="left", padx=(8, 0))

        options = ttk.Frame(cadre)
        options.grid(row=1, column=0, columnspan=2, sticky="ew", pady=(8, 6))
        ttk.Checkbutton(
            options, text="Inclure les sous-dossiers", variable=self.var_sous_dossiers
        ).pack(side="left")
        self.bouton_vider = ttk.Button(
            options, text="Tout retirer", style="Danger.TButton", command=self.vider_liste
        )
        self.bouton_vider.pack(side="right")
        self.bouton_retirer = ttk.Button(
            options, text="Retirer la sélection", command=self.retirer_selection
        )
        self.bouton_retirer.pack(side="right", padx=(0, 8))

        colonnes = ("nom", "dimensions", "poids", "dossier")
        self.tableau = ttk.Treeview(
            cadre, columns=colonnes, show="headings", selectmode="extended"
        )
        self.tableau.heading("nom", text="Photo", anchor="w")
        self.tableau.heading("dimensions", text="Dimensions")
        self.tableau.heading("poids", text="Poids")
        self.tableau.heading("dossier", text="Dossier", anchor="w")
        self.tableau.column("nom", width=220, minwidth=120, anchor="w")
        self.tableau.column("dimensions", width=120, minwidth=90, anchor="center", stretch=False)
        self.tableau.column("poids", width=80, minwidth=60, anchor="e", stretch=False)
        self.tableau.column("dossier", width=150, minwidth=80, anchor="w")
        self.tableau.grid(row=2, column=0, sticky="nsew")
        defilement = ttk.Scrollbar(cadre, orient="vertical", command=self.tableau.yview)
        defilement.grid(row=2, column=1, sticky="ns")
        self.tableau.configure(yscrollcommand=defilement.set)
        self.tableau.bind("<Delete>", lambda _e: self.retirer_selection())
        self.tableau.bind("<BackSpace>", lambda _e: self.retirer_selection())
        self.tableau.bind("<<TreeviewSelect>>", lambda _e: self._mettre_a_jour_compte())

        self.var_compte = tk.StringVar()
        ttk.Label(cadre, textvariable=self.var_compte, style="Doux.TLabel").grid(
            row=3, column=0, columnspan=2, sticky="w", pady=(6, 0)
        )

    def _construire_reglages(self, parent):
        cadre = ttk.LabelFrame(parent, text="2. Réglages", padding=10)
        cadre.pack(fill="x", padx=(0, 4))
        cadre.columnconfigure(1, weight=1)

        ttk.Label(cadre, text="Format :").grid(row=0, column=0, sticky="w")
        ligne = ttk.Frame(cadre)
        ligne.grid(row=0, column=1, sticky="w")
        ttk.Radiobutton(
            ligne, text="JPEG", value="JPEG", variable=self.var_format,
            command=self._format_change,
        ).pack(side="left")
        ttk.Radiobutton(
            ligne, text="WebP (plus léger)", value="WEBP", variable=self.var_format,
            command=self._format_change,
        ).pack(side="left", padx=(12, 0))

        ttk.Label(cadre, text="Plus grand côté :").grid(row=1, column=0, sticky="w", pady=(8, 0))
        ligne = ttk.Frame(cadre)
        ligne.grid(row=1, column=1, sticky="w", pady=(8, 0))
        ttk.Spinbox(
            ligne, from_=320, to=8000, increment=10, width=7, textvariable=self.var_taille
        ).pack(side="left")
        ttk.Label(ligne, text="pixels  —  72 ppp", style="Doux.TLabel").pack(
            side="left", padx=(6, 0)
        )

        ttk.Label(cadre, text="Poids maximum :").grid(row=2, column=0, sticky="w", pady=(8, 0))
        ligne = ttk.Frame(cadre)
        ligne.grid(row=2, column=1, sticky="w", pady=(8, 0))
        ttk.Spinbox(
            ligne, from_=0, to=20000, increment=50, width=7, textvariable=self.var_poids_max
        ).pack(side="left")
        ttk.Label(ligne, text="Ko  (0 = sans limite)", style="Doux.TLabel").pack(
            side="left", padx=(6, 0)
        )

        ttk.Label(cadre, text="Qualité :").grid(row=3, column=0, sticky="w", pady=(8, 0))
        ligne = ttk.Frame(cadre)
        ligne.grid(row=3, column=1, sticky="w", pady=(8, 0))
        ttk.Spinbox(
            ligne, from_=50, to=100, increment=1, width=5, textvariable=self.var_qualite
        ).pack(side="left")
        ttk.Label(ligne, text="(90 conseillé)", style="Doux.TLabel").pack(side="left", padx=(6, 0))

        ttk.Label(cadre, text="Netteté écran :").grid(row=4, column=0, sticky="w", pady=(8, 0))
        ttk.Combobox(
            cadre, values=list(NIVEAUX_NETTETE), textvariable=self.var_nettete,
            state="readonly", width=17,
        ).grid(row=4, column=1, sticky="w", pady=(8, 0))

        ttk.Label(
            cadre,
            text="Les photos plus petites gardent leur taille (jamais agrandies).",
            style="Doux.TLabel",
        ).grid(row=5, column=0, columnspan=2, sticky="w", pady=(8, 0))
        ttk.Checkbutton(
            cadre, text="Conserver les métadonnées (appareil, objectif, date…)",
            variable=self.var_metadonnees, command=self._mettre_a_jour_etats,
        ).grid(row=6, column=0, columnspan=2, sticky="w", pady=(6, 0))
        self.case_gps = ttk.Checkbutton(
            cadre, text="…y compris la position GPS", variable=self.var_gps,
        )
        self.case_gps.grid(row=7, column=0, columnspan=2, sticky="w", padx=(24, 0))

    def _construire_noms(self, parent):
        cadre = ttk.LabelFrame(parent, text="3. Nom des photos", padding=10)
        cadre.pack(fill="x", pady=(10, 0), padx=(0, 4))
        cadre.columnconfigure(1, weight=1)

        ttk.Radiobutton(
            cadre, text="Garder le nom d'origine", value=False, variable=self.var_renommer,
            command=self._mettre_a_jour_etats,
        ).grid(row=0, column=0, columnspan=2, sticky="w")
        ttk.Radiobutton(
            cadre, text="Renommer avec un numéro", value=True, variable=self.var_renommer,
            command=self._mettre_a_jour_etats,
        ).grid(row=1, column=0, columnspan=2, sticky="w", pady=(2, 0))

        self._widgets_renommage = []
        ttk.Label(cadre, text="Début du nom :").grid(row=2, column=0, sticky="w", pady=(8, 0))
        champ = ttk.Entry(cadre, textvariable=self.var_prefixe, width=22)
        champ.grid(row=2, column=1, sticky="w", pady=(8, 0))
        self._widgets_renommage.append(champ)

        ttk.Label(cadre, text="Premier numéro :").grid(row=3, column=0, sticky="w", pady=(6, 0))
        ligne = ttk.Frame(cadre)
        ligne.grid(row=3, column=1, sticky="w", pady=(6, 0))
        champ = ttk.Spinbox(ligne, from_=0, to=999999, width=7, textvariable=self.var_numero)
        champ.pack(side="left")
        self._widgets_renommage.append(champ)
        ttk.Label(ligne, text="chiffres :").pack(side="left", padx=(10, 4))
        champ = ttk.Spinbox(ligne, from_=1, to=6, width=3, textvariable=self.var_chiffres)
        champ.pack(side="left")
        self._widgets_renommage.append(champ)

        ttk.Label(cadre, text="Ordre :").grid(row=4, column=0, sticky="w", pady=(6, 0))
        champ = ttk.Combobox(
            cadre, values=ORDRES, textvariable=self.var_ordre, state="readonly", width=20
        )
        champ.grid(row=4, column=1, sticky="w", pady=(6, 0))
        self._widgets_renommage.append(champ)

        self.var_exemple = tk.StringVar()
        ttk.Label(cadre, textvariable=self.var_exemple, style="Doux.TLabel").grid(
            row=5, column=0, columnspan=2, sticky="w", pady=(8, 0)
        )
        for variable in (self.var_prefixe, self.var_numero, self.var_chiffres, self.var_format):
            variable.trace_add("write", lambda *_a: self._mettre_a_jour_exemple())

    def _construire_bas(self, parent):
        # Dossier de destination : dans le bas de la fenêtre, toujours visible.
        cadre_dest = ttk.LabelFrame(parent, text="4. Dossier de destination", padding=(10, 8))
        cadre_dest.pack(fill="x", pady=(0, 8))
        self.bouton_destination = ttk.Button(
            cadre_dest, text="📁  Choisir le dossier…", command=self.choisir_destination
        )
        self.bouton_destination.pack(side="left")
        self.label_destination = ttk.Label(cadre_dest, style="Doux.TLabel", anchor="w")
        self.label_destination.pack(side="left", fill="x", expand=True, padx=(12, 0))
        self.var_destination.trace_add("write", lambda *_a: self._mettre_a_jour_etats())

        actions = ttk.Frame(parent)
        actions.pack(fill="x")
        self.bouton_convertir = ttk.Button(
            actions, text="▶  Convertir les photos", style="Accent.TButton",
            command=self.lancer_conversion,
        )
        self.bouton_convertir.pack(side="left")
        self.bouton_annuler = ttk.Button(
            actions, text="Arrêter", style="Danger.TButton", command=self.annuler_conversion
        )
        self.bouton_annuler.pack(side="left", padx=(8, 0))
        self.bouton_ouvrir = ttk.Button(
            actions, text="Ouvrir le dossier de destination",
            command=self._ouvrir_dossier_resultat,
        )
        self.bouton_ouvrir.pack(side="left", padx=(8, 0))

        self.barre_progression = ttk.Progressbar(parent, mode="determinate")
        self.barre_progression.pack(fill="x", pady=(10, 0))
        self.var_statut = tk.StringVar(value="")
        ttk.Label(parent, textvariable=self.var_statut, style="Doux.TLabel").pack(
            anchor="w", pady=(4, 0)
        )

        cadre_journal = ttk.LabelFrame(parent, text="Détail", padding=8)
        cadre_journal.pack(fill="x", pady=(6, 0))
        self.journal = tk.Listbox(cadre_journal, height=4)
        _styliser_liste(self.journal)
        self.journal.pack(side="left", fill="both", expand=True)
        defilement = ttk.Scrollbar(cadre_journal, orient="vertical", command=self.journal.yview)
        defilement.pack(side="left", fill="y")
        self.journal.configure(yscrollcommand=defilement.set)

    # -- Mises à jour de l'affichage ------------------------------------------

    def _format_change(self):
        # Qualité conseillée différente selon le format : on ne la change que
        # si la valeur actuelle est celle conseillée pour l'autre format.
        try:
            qualite = self.var_qualite.get()
        except tk.TclError:
            qualite = None
        if self.var_format.get() == "WEBP" and qualite in (None, 90):
            self.var_qualite.set(85)
        elif self.var_format.get() == "JPEG" and qualite in (None, 85):
            self.var_qualite.set(90)

    def _entier(self, variable, defaut, minimum, maximum):
        try:
            valeur = int(variable.get())
        except (tk.TclError, ValueError):
            return defaut
        return max(minimum, min(maximum, valeur))

    def _mettre_a_jour_exemple(self):
        prefixe = self.var_prefixe.get()
        numero = self._entier(self.var_numero, 1, 0, 999999)
        chiffres = self._entier(self.var_chiffres, 3, 1, 6)
        extension = extension_sortie(self.var_format.get())
        self.var_exemple.set(
            "Exemple : "
            + nom_numerote(prefixe, numero, chiffres, extension)
            + ", "
            + nom_numerote(prefixe, numero + 1, chiffres, extension)
            + "…"
        )

    def _mettre_a_jour_compte(self):
        nb = len(self._photos)
        if nb == 0:
            self.var_compte.set(
                "Aucune photo. Ajoutez une ou plusieurs photos, ou un dossier entier."
            )
            return
        nb_raw = sum(1 for p in self._photos if est_raw(p))
        total = sum(self._poids.get(p) or 0 for p in self._photos)
        texte = f"{nb} photo(s)"
        if nb_raw:
            texte += f" dont {nb_raw} RAW"
        texte += f" — {taille_lisible(total)} au total."
        selection = [self._chemin_par_iid[i] for i in self.tableau.selection()
                     if i in self._chemin_par_iid]
        if selection:
            poids_selection = sum(self._poids.get(p) or 0 for p in selection)
            texte += f"   Sélection : {len(selection)} photo(s), {taille_lisible(poids_selection)}."
        self.var_compte.set(texte)

    def _mettre_a_jour_etats(self):
        occupe = self._conversion_en_cours
        etat_normal = "disabled" if occupe else "normal"

        for widget in self._widgets_renommage:
            actif = self.var_renommer.get() and not occupe
            if isinstance(widget, ttk.Combobox):
                widget.configure(state="readonly" if actif else "disabled")
            else:
                widget.configure(state="normal" if actif else "disabled")
        self.case_gps.configure(
            state="normal" if self.var_metadonnees.get() and not occupe else "disabled"
        )

        for bouton in (
            self.bouton_ajout_photos, self.bouton_ajout_dossier, self.bouton_destination,
            self.bouton_retirer, self.bouton_vider,
        ):
            bouton.configure(state=etat_normal)

        destination = self.var_destination.get()
        if destination:
            self.label_destination.configure(text=destination, foreground=COULEUR_TEXTE)
        else:
            self.label_destination.configure(
                text="Aucun dossier choisi pour l'instant.", foreground=COULEUR_TEXTE_DOUX
            )

        pret = bool(self._photos) and bool(destination) and not occupe
        self.bouton_convertir.configure(state="normal" if pret else "disabled")
        self.bouton_annuler.configure(state="normal" if occupe else "disabled")
        self.bouton_ouvrir.configure(
            state="normal" if destination and os.path.isdir(destination) else "disabled"
        )
        self._mettre_a_jour_compte()

    # -- Liste des photos ----------------------------------------------------

    def _ajouter(self, chemins):
        ajoutes = 0
        for chemin in chemins:
            absolu = os.path.abspath(chemin)
            if absolu in self._iid_par_chemin:
                continue
            try:
                poids = os.path.getsize(absolu)
            except OSError:
                poids = None
            self._compteur_iid += 1
            iid = f"photo{self._compteur_iid}"
            self._photos.append(absolu)
            self._iid_par_chemin[absolu] = iid
            self._chemin_par_iid[iid] = absolu
            self._poids[absolu] = poids
            self.tableau.insert(
                "", "end", iid=iid,
                values=(
                    os.path.basename(absolu),
                    "…",
                    taille_lisible(poids),
                    os.path.basename(os.path.dirname(absolu)),
                ),
            )
            self._file_dimensions.put(absolu)
            ajoutes += 1
        if not self.var_destination.get() and self._photos:
            # Proposition par défaut : un dossier « Photos converties » à côté
            # des photos ; le bouton permet d'en choisir un autre.
            dossier = os.path.dirname(self._photos[0])
            self.var_destination.set(os.path.join(dossier, "Photos converties"))
        self._mettre_a_jour_etats()
        return ajoutes

    def _lecteur_dimensions(self):
        """Tourne en arrière-plan : lit les dimensions des photos ajoutées
        (un RAW doit être ouvert pour cela) sans figer la fenêtre."""
        while True:
            chemin = self._file_dimensions.get()
            self._file_evenements.put(("dimensions", chemin, lire_dimensions(chemin)))

    def ajouter_photos(self):
        types = [
            ("Toutes les photos", " ".join(f"*{e} *{e.upper()}" for e in sorted(EXTENSIONS_ACCEPTEES))),
            ("Photos RAW", " ".join(f"*{e} *{e.upper()}" for e in sorted(EXTENSIONS_RAW))),
            ("JPEG", "*.jpg *.jpeg *.JPG *.JPEG"),
            ("Tous les fichiers", "*"),
        ]
        chemins = filedialog.askopenfilenames(
            parent=self, title="Choisir une ou plusieurs photos", filetypes=types
        )
        if chemins:
            self._ajouter(self.tk.splitlist(chemins))

    def ajouter_dossier(self):
        dossier = filedialog.askdirectory(parent=self, title="Choisir un dossier de photos")
        if not dossier:
            return
        trouves = lister_photos_dossier(dossier, self.var_sous_dossiers.get())
        if not trouves:
            messagebox.showinfo(
                APP_TITLE,
                "Aucune photo trouvée dans ce dossier"
                + (" ni dans ses sous-dossiers." if self.var_sous_dossiers.get() else "."),
            )
            return
        ajoutes = self._ajouter(trouves)
        self._journaliser(f"📂  {ajoutes} photo(s) ajoutée(s) depuis {dossier}", COULEUR_TEXTE)

    def _oublier(self, chemin):
        iid = self._iid_par_chemin.pop(chemin, None)
        if iid:
            self._chemin_par_iid.pop(iid, None)
        self._poids.pop(chemin, None)

    def retirer_selection(self):
        if self._conversion_en_cours:
            return
        for iid in self.tableau.selection():
            chemin = self._chemin_par_iid.get(iid)
            self.tableau.delete(iid)
            if chemin:
                self._photos.remove(chemin)
                self._oublier(chemin)
        self._mettre_a_jour_etats()

    def vider_liste(self):
        if self._conversion_en_cours:
            return
        self.tableau.delete(*self.tableau.get_children())
        for chemin in list(self._photos):
            self._oublier(chemin)
        self._photos.clear()
        self._mettre_a_jour_etats()

    def choisir_destination(self):
        initial = self.var_destination.get()
        while initial and not os.path.isdir(initial):
            parent = os.path.dirname(initial)
            if parent == initial:
                break
            initial = parent
        options = {"parent": self, "title": "Choisir le dossier où enregistrer les photos converties"}
        if initial and os.path.isdir(initial):
            options["initialdir"] = initial
        dossier = filedialog.askdirectory(**options)
        if dossier:
            self.var_destination.set(os.path.normpath(dossier))

    # -- Conversion -----------------------------------------------------------

    def _lire_reglages(self):
        prefixe = self.var_prefixe.get()
        for interdit in '\\/:*?"<>|':
            prefixe = prefixe.replace(interdit, "_")
        return {
            "format": self.var_format.get(),
            "qualite": self._entier(self.var_qualite, 90, 50, 100),
            "taille": self._entier(self.var_taille, 1920, 100, 20000),
            "poids_max_ko": self._entier(self.var_poids_max, POIDS_MAX_DEFAUT_KO, 0, 100000),
            "nettete": self.var_nettete.get(),
            "garder_metadonnees": self.var_metadonnees.get(),
            "garder_gps": self.var_metadonnees.get() and self.var_gps.get(),
            "renommer": self.var_renommer.get(),
            "prefixe": prefixe,
            "numero_depart": self._entier(self.var_numero, 1, 0, 999999),
            "chiffres": self._entier(self.var_chiffres, 3, 1, 6),
            "ordre": self.var_ordre.get(),
        }

    def lancer_conversion(self):
        if self._conversion_en_cours or not self._photos:
            return
        destination = self.var_destination.get()
        if not destination:
            messagebox.showwarning(APP_TITLE, "Choisissez d'abord le dossier de destination.")
            return
        try:
            os.makedirs(destination, exist_ok=True)
        except OSError as erreur:
            messagebox.showerror(APP_TITLE, f"Impossible de créer ce dossier :\n{erreur}")
            return

        reglages = self._lire_reglages()
        self._dossier_cible = destination
        self._enregistrer_reglages()

        self._conversion_en_cours = True
        self._annulation.clear()
        self._nb_total = len(self._photos)
        self._nb_reussis = 0
        self._nb_echecs = 0
        self._poids_total_sortie = 0
        self._mettre_a_jour_etats()
        self.journal.delete(0, "end")
        self.barre_progression.configure(value=0, maximum=self._nb_total)
        self.var_statut.set("Préparation…")

        photos = list(self._photos)
        fil = threading.Thread(
            target=self._travail_conversion, args=(photos, destination, reglages), daemon=True
        )
        fil.start()

    def annuler_conversion(self):
        if self._conversion_en_cours:
            self._annulation.set()
            self.var_statut.set("Arrêt demandé : on termine les photos en cours…")
            self.bouton_annuler.configure(state="disabled")

    def _travail_conversion(self, photos, destination, reglages):
        """Tourne dans un thread séparé : ne fait que déposer des messages
        dans la file, jamais d'appel direct à un widget Tkinter."""
        evenements = self._file_evenements
        debut = time.monotonic()

        if reglages["renommer"]:
            if reglages["ordre"] == "Nom de fichier":
                photos.sort(key=lambda p: (os.path.basename(p).casefold(), p.casefold()))
            elif reglages["ordre"] == "Date de prise de vue":
                evenements.put(("statut", "Lecture des dates de prise de vue…"))
                dates = {p: date_prise_de_vue(p) for p in photos}
                photos.sort(key=lambda p: (dates[p], os.path.basename(p).casefold()))

        taches = calculer_noms_sortie(photos, destination, reglages)

        def traiter(tache):
            chemin, chemin_sortie = tache
            if self._annulation.is_set():
                return
            try:
                resultat = convertir_une_photo(chemin, chemin_sortie, reglages)
                evenements.put(("progression", chemin, chemin_sortie, None, resultat))
            except ErreurPhoto as erreur:
                evenements.put(("progression", chemin, chemin_sortie, str(erreur), None))
            except Exception as erreur:  # jamais bloquer tout le lot sur une photo
                evenements.put(("progression", chemin, chemin_sortie, f"erreur ({erreur})", None))

        # Plusieurs photos traitées en parallèle (les processeurs actuels ont
        # plusieurs cœurs) ; limité pour ne pas saturer la mémoire avec des
        # RAW de 50 Mpx développés en même temps.
        nb_fils = max(1, min(4, (os.cpu_count() or 2) - 1))
        with ThreadPoolExecutor(max_workers=nb_fils) as executeur:
            list(executeur.map(traiter, taches))

        evenements.put(("termine", time.monotonic() - debut, self._annulation.is_set()))

    def _verifier_file_evenements(self):
        """Boucle appelée régulièrement par Tkinter : relit les messages
        déposés par les threads de travail et met la fenêtre à jour."""
        try:
            while True:
                self._traiter_evenement(self._file_evenements.get_nowait())
        except queue.Empty:
            pass
        self.after(100, self._verifier_file_evenements)

    def _journaliser(self, texte, couleur):
        self.journal.insert("end", texte)
        self.journal.itemconfig(self.journal.size() - 1, foreground=couleur)
        self.journal.see("end")

    def _traiter_evenement(self, evenement):
        genre = evenement[0]

        if genre == "dimensions":
            _genre, chemin, dimensions = evenement
            iid = self._iid_par_chemin.get(chemin)
            if iid and self.tableau.exists(iid):
                texte = f"{dimensions[0]} × {dimensions[1]}" if dimensions else "?"
                self.tableau.set(iid, "dimensions", texte)

        elif genre == "statut":
            self.var_statut.set(evenement[1])

        elif genre == "progression":
            _genre, chemin, chemin_sortie, erreur, resultat = evenement
            nom = os.path.basename(chemin)
            if erreur:
                self._nb_echecs += 1
                self._journaliser(f"✗  {nom} — {erreur}", COULEUR_DANGER)
            else:
                self._nb_reussis += 1
                self._poids_total_sortie += resultat["poids"]
                texte = (
                    f"✓  {nom}  →  {os.path.basename(chemin_sortie)}   "
                    f"{resultat['largeur']} × {resultat['hauteur']} px, "
                    f"{taille_lisible(resultat['poids'])}"
                )
                if resultat["remarques"]:
                    texte += "   (" + ", ".join(resultat["remarques"]) + ")"
                couleur = COULEUR_AVERTISSEMENT if resultat["alerte"] else COULEUR_SUCCES
                self._journaliser(texte, couleur)
            fait = self._nb_reussis + self._nb_echecs
            self.barre_progression.configure(value=fait)
            self.var_statut.set(f"{fait} / {self._nb_total} photo(s) traitée(s)…")

        elif genre == "termine":
            _genre, duree, annule = evenement
            self._conversion_en_cours = False
            self._mettre_a_jour_etats()
            minutes, secondes = divmod(int(duree), 60)
            temps = f"{minutes} min {secondes:02d} s" if minutes else f"{secondes} s"
            if annule:
                self.var_statut.set(
                    f"Arrêté : {self._nb_reussis} photo(s) convertie(s) sur {self._nb_total}."
                )
                return
            self.var_statut.set(
                f"Terminé en {temps} : {self._nb_reussis} / {self._nb_total} photo(s) "
                f"convertie(s), {taille_lisible(self._poids_total_sortie)} au total."
            )
            if self._nb_echecs:
                messagebox.showwarning(
                    APP_TITLE,
                    f"{self._nb_reussis} photo(s) convertie(s), {self._nb_echecs} en échec.\n"
                    "Voir le détail en bas de la fenêtre.",
                )
            else:
                if messagebox.askyesno(
                    APP_TITLE,
                    f"Les {self._nb_reussis} photo(s) ont bien été converties.\n\n"
                    "Ouvrir le dossier de destination ?",
                ):
                    self._ouvrir_dossier_resultat()

    def _ouvrir_dossier_resultat(self):
        dossier = self._dossier_cible or self.var_destination.get()
        if dossier and os.path.isdir(dossier):
            try:
                ouvrir_dossier(dossier)
            except Exception as erreur:
                messagebox.showerror(APP_TITLE, f"Impossible d'ouvrir ce dossier :\n{erreur}")


def main():
    if not PILLOW_OK:
        racine = tk.Tk()
        racine.withdraw()
        messagebox.showerror(
            APP_TITLE,
            "Le module « Pillow » n'est pas installé.\n\n"
            "Tapez dans une invite de commandes :\n"
            "pip install pillow rawpy pillow-heif exifread",
        )
        return
    app = ApplicationConvertisseur()
    app.mainloop()


if __name__ == "__main__":
    main()

(function () {
  "use strict";

  /* ---------- Navigation mobile ---------- */
  const toggle = document.querySelector(".nav-toggle");
  const links = document.querySelector(".nav-links");
  if (toggle && links) {
    toggle.addEventListener("click", function () {
      const open = links.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", String(open));
    });
    links.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        links.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  /* ---------- Menu déroulant "Espace Adhérent" ---------- */
  const dropdowns = document.querySelectorAll(".nav-dropdown");
  function closeDropdown(dropdown) {
    dropdown.classList.remove("is-open");
    dropdown.querySelector(".nav-dropdown-trigger").setAttribute("aria-expanded", "false");
  }
  dropdowns.forEach(function (dropdown) {
    const trigger = dropdown.querySelector(".nav-dropdown-trigger");
    trigger.addEventListener("click", function () {
      const willOpen = !dropdown.classList.contains("is-open");
      dropdowns.forEach(closeDropdown);
      if (willOpen) {
        dropdown.classList.add("is-open");
        trigger.setAttribute("aria-expanded", "true");
      }
    });
    dropdown.querySelectorAll(".nav-dropdown-menu a").forEach(function (a) {
      a.addEventListener("click", function () {
        closeDropdown(dropdown);
      });
    });
  });
  document.addEventListener("click", function (e) {
    dropdowns.forEach(function (dropdown) {
      if (!dropdown.contains(e.target)) closeDropdown(dropdown);
    });
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") dropdowns.forEach(closeDropdown);
  });

  /* ---------- Contenu dynamique (coordonnées, présentation) ----------
     Un responsable peut modifier ces textes depuis l'espace adhérents
     (« Réglages du site »). Comme le reste du site est du HTML statique, ils
     sont chargés ici depuis infos-club.php et viennent remplacer le texte
     déjà présent dans la page. En cas d'échec — hors ligne, ou préversion
     GitHub Pages qui ne peut pas exécuter PHP — le texte figé dans le HTML
     reste affiché : c'est le repli voulu, pas une erreur à corriger. */
  (function () {
    const cibles = document.querySelectorAll("[data-contenu]");
    if (!cibles.length) return;

    fetch("infos-club.php")
      .then(function (reponse) {
        return reponse.ok ? reponse.json() : Promise.reject();
      })
      .then(function (donnees) {
        cibles.forEach(function (el) {
          const cle = el.dataset.contenu;
          const valeur = donnees[cle];
          if (typeof valeur !== "string" || valeur === "") return;
          el.textContent = valeur;
          if (el.tagName === "A" && cle === "telephone") {
            el.href = "tel:" + valeur.replace(/\s+/g, "");
          }
          if (el.tagName === "A" && cle === "email") {
            el.href = "mailto:" + valeur;
          }
        });

        const detailsAdresse = document.querySelector("[data-contenu-adresse-details]");
        if (detailsAdresse && donnees.adresse_rue && donnees.adresse_code_postal && donnees.adresse_ville) {
          detailsAdresse.textContent =
            donnees.adresse_rue + ", " + donnees.adresse_code_postal + " " + donnees.adresse_ville;
        }

        const lienMaps = document.querySelector("[data-contenu-maps]");
        if (lienMaps) {
          const requete = [donnees.nom_lieu, donnees.adresse_rue, donnees.adresse_code_postal, donnees.adresse_ville]
            .filter(Boolean)
            .join(" ");
          if (requete) {
            lienMaps.href = "https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent(requete);
          }
        }

        const formulaire = document.querySelector("[data-contenu-mailto-action]");
        if (formulaire && donnees.email) {
          formulaire.action = "mailto:" + donnees.email;
        }
      })
      .catch(function () {});
  })();

  /* ---------- État de connexion sur les pages statiques ----------
     Les pages statiques ne savent jamais, au moment du déploiement, si le
     visiteur est connecté : sans ce correctif, le menu « {pseudo} connecté »
     revenait afficher « Espace Adhérent » dès qu'on quittait une page de
     l'espace adhérents pour une page publique, alors que la session, elle,
     restait bien active. On ne touche à rien si la page a déjà rendu l'état
     connecté côté serveur (page.php) — reconnaissable à .nav-dropdown-label,
     absente du rendu non connecté. */
  (function () {
    let dropdown = null;
    dropdowns.forEach(function (d) {
      if (
        !d.querySelector(".nav-dropdown-label") &&
        d.querySelector('a[href$="connexion.php"]')
      ) {
        dropdown = d;
      }
    });
    if (!dropdown) return;

    function echapper(texte) {
      const span = document.createElement("span");
      span.textContent = texte;
      return span.innerHTML;
    }

    fetch("espace/statut-connexion.php")
      .then(function (reponse) {
        return reponse.ok ? reponse.json() : Promise.reject();
      })
      .then(function (donnees) {
        if (!donnees.connecte) return;

        const trigger = dropdown.querySelector(".nav-dropdown-trigger");
        const menu = dropdown.querySelector(".nav-dropdown-menu");
        if (!trigger || !menu) return;

        Array.from(trigger.childNodes).forEach(function (noeud) {
          if (noeud.nodeType === Node.TEXT_NODE) noeud.remove();
        });
        trigger.classList.add("nav-dropdown-trigger--icone");
        trigger.setAttribute("aria-label", "Ouvrir le menu Espace Adhérent");

        const lien = document.createElement("a");
        lien.className = "nav-dropdown-label";
        lien.href = "espace/index.php";
        lien.textContent = donnees.identifiant + " connecté";
        dropdown.insertBefore(lien, trigger);

        const badge = donnees.administrateur
          ? ' <span class="badge-admin">responsable</span>'
          : "";
        const pagesAdmin = donnees.administrateur
          ? '<li><a href="espace/adherents.php">Adhérents</a></li>' +
            '<li><a href="espace/parametres.php">Réglages du site</a></li>'
          : "";
        menu.innerHTML =
          '<li class="nav-dropdown-heading">Bonjour <strong>' + echapper(donnees.nom) + "</strong>" + badge + "</li>" +
          '<li><a href="espace/deconnexion.php">Se déconnecter</a></li>' +
          '<li class="nav-dropdown-divider"></li>' +
          '<li><a href="espace/index.php">Tableau de bord</a></li>' +
          '<li><a href="espace/galerie.php">Galerie privée</a></li>' +
          '<li><a href="espace/documents.php">Documents</a></li>' +
          '<li><a href="espace/agenda.php">Agenda des sorties</a></li>' +
          '<li><a href="espace/sorties-a-venir.php">Sorties à venir</a></li>' +
          '<li><a href="espace/annuaire.php">Annuaire</a></li>' +
          '<li><a href="espace/le-club.php">Le Club</a></li>' +
          pagesAdmin;

        // Les liens ajoutés après coup n'ont pas les écouteurs posés plus
        // haut (fermeture du menu déroulant et du menu mobile au clic).
        const fermer = function () {
          closeDropdown(dropdown);
          if (links) links.classList.remove("is-open");
          if (toggle) toggle.setAttribute("aria-expanded", "false");
        };
        lien.addEventListener("click", fermer);
        menu.querySelectorAll("a").forEach(function (a) {
          a.addEventListener("click", fermer);
        });
      })
      .catch(function () {});
  })();

  /* ---------- Utilitaires ---------- */
  function initials(nom) {
    return nom
      .split(" ")
      .map(function (p) {
        return p.charAt(0);
      })
      .join("")
      .toUpperCase();
  }

  function photoGradient(hue, index) {
    const h1 = (hue + index * 11) % 360;
    const h2 = (hue + 40 + index * 7) % 360;
    return "linear-gradient(150deg, hsl(" + h1 + " 60% 42%), hsl(" + h2 + " 45% 18%))";
  }

  function avatarGradient(hue) {
    return "linear-gradient(135deg, hsl(" + hue + " 70% 62%), hsl(" + ((hue + 50) % 360) + " 55% 42%))";
  }

  /* ---------- Lightbox ---------- */
  const lightbox = document.querySelector("[data-lightbox]");
  let activePhotos = [];
  let activeIndex = 0;
  let diaporamaTimer = null;

  function stopDiaporama() {
    if (diaporamaTimer) {
      clearInterval(diaporamaTimer);
      diaporamaTimer = null;
    }
  }

  function startDiaporama() {
    stopDiaporama();
    diaporamaTimer = setInterval(function () {
      activeIndex = (activeIndex + 1) % activePhotos.length;
      renderLightbox();
    }, 3500);
  }

  function openLightbox(photos, index) {
    if (!lightbox) return;
    activePhotos = photos;
    activeIndex = index;
    renderLightbox();
    lightbox.classList.add("is-open");
    document.body.style.overflow = "hidden";
    lightbox.querySelector(".lightbox-close").focus();
  }

  function closeLightbox() {
    if (!lightbox) return;
    stopDiaporama();
    lightbox.classList.remove("is-open");
    document.body.style.overflow = "";
  }

  function renderLightbox() {
    const photo = activePhotos[activeIndex];
    const frame = lightbox.querySelector(".lightbox-frame");
    frame.style.background = photoGradient(photo.hue, photo.index);
    lightbox.querySelector(".lightbox-title").textContent = photo.titre;
    lightbox.querySelector(".lightbox-meta").textContent =
      photo.membreNom + " — " + photo.theme;
  }

  if (lightbox) {
    lightbox.querySelector(".lightbox-close").addEventListener("click", closeLightbox);
    lightbox.querySelector(".lightbox-prev").addEventListener("click", function () {
      stopDiaporama();
      activeIndex = (activeIndex - 1 + activePhotos.length) % activePhotos.length;
      renderLightbox();
    });
    lightbox.querySelector(".lightbox-next").addEventListener("click", function () {
      stopDiaporama();
      activeIndex = (activeIndex + 1) % activePhotos.length;
      renderLightbox();
    });
    lightbox.addEventListener("click", function (e) {
      if (e.target === lightbox) closeLightbox();
    });
    document.addEventListener("keydown", function (e) {
      if (!lightbox.classList.contains("is-open")) return;
      if (e.key === "Escape") closeLightbox();
      if (e.key === "ArrowLeft") lightbox.querySelector(".lightbox-prev").click();
      if (e.key === "ArrowRight") lightbox.querySelector(".lightbox-next").click();
    });
  }

  function buildPhotoCard(photo, hue, membreNom, index, photosForLightbox) {
    const card = document.createElement("button");
    card.type = "button";
    card.className = "photo-card";
    card.setAttribute("aria-label", "Voir la photo : " + photo.titre);
    card.innerHTML =
      '<span class="photo-frame" style="display:block;aspect-ratio:4/3;background:' +
      photoGradient(hue, index) +
      '"></span>' +
      '<span class="photo-caption">' +
      '<span class="title">' + photo.titre + "</span>" +
      '<span class="meta">' + membreNom + " · " + photo.theme + "</span>" +
      "</span>";
    card.addEventListener("click", function () {
      const idx = photosForLightbox.findIndex(function (p) {
        return p.titre === photo.titre && p.membreNom === membreNom;
      });
      openLightbox(photosForLightbox, idx);
    });
    return card;
  }

  /* ---------- Page d'accueil : sélection de photos ---------- */
  const highlightGrid = document.querySelector("[data-highlights]");
  if (highlightGrid) {
    const pool = [];
    CLUB_DATA.membres.forEach(function (m) {
      m.photos.forEach(function (p, i) {
        pool.push(Object.assign({}, p, { hue: m.hue, membreNom: m.nom, index: i }));
      });
    });
    const picked = pool.filter(function (_, i) {
      return i % 3 === 0;
    }).slice(0, 8);
    picked.forEach(function (photo) {
      highlightGrid.appendChild(
        buildPhotoCard(photo, photo.hue, photo.membreNom, photo.index, picked)
      );
    });
  }

  /* ---------- Page adhérents ---------- */
  const membersGrid = document.querySelector("[data-members]");
  if (membersGrid) {
    CLUB_DATA.membres.forEach(function (m) {
      const card = document.createElement("a");
      card.className = "member-card";
      card.href = "galerie.html";
      card.innerHTML =
        '<span class="member-cover" style="display:block;background:' +
        photoGradient(m.hue, 2) +
        '"></span>' +
        '<span class="member-body">' +
        '<span class="member-avatar" style="background:' + avatarGradient(m.hue) + '">' +
        initials(m.nom) +
        "</span>" +
        "<h3>" + m.nom + "</h3>" +
        '<span class="member-role">' + m.role + "</span>" +
        '<span class="member-bio">' + m.bio + "</span>" +
        '<span class="member-link">Voir la galerie</span>' +
        "</span>";
      membersGrid.appendChild(card);
    });
  }

  /* ---------- Page galerie : toutes les photos, filtrées par thème ---------- */
  const galleryRoot = document.querySelector("[data-gallery-page]");
  if (galleryRoot) {
    const pool = [];
    CLUB_DATA.membres.forEach(function (m) {
      m.photos.forEach(function (p, i) {
        pool.push(Object.assign({}, p, { hue: m.hue, membreNom: m.nom, index: i }));
      });
    });

    const grid = document.querySelector("[data-photos]");
    const emptyMessage = document.querySelector("[data-gallery-empty]");
    const filtersRoot = document.querySelector("[data-theme-filters]");
    let currentTheme = "";

    function photosForCurrentTheme() {
      return currentTheme ? pool.filter(function (p) { return p.theme === currentTheme; }) : pool;
    }

    function renderGrid() {
      const filtered = photosForCurrentTheme();
      grid.innerHTML = "";
      filtered.forEach(function (photo) {
        grid.appendChild(buildPhotoCard(photo, photo.hue, photo.membreNom, photo.index, filtered));
      });
      if (emptyMessage) emptyMessage.hidden = filtered.length > 0;
    }

    const toutesLesPhotos = "Toutes";
    [toutesLesPhotos].concat(CLUB_DATA.themes).forEach(function (theme) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "theme-filter";
      btn.textContent = theme;
      if (theme === toutesLesPhotos) btn.classList.add("is-active");
      btn.addEventListener("click", function () {
        filtersRoot.querySelectorAll(".theme-filter").forEach(function (b) {
          b.classList.remove("is-active");
        });
        btn.classList.add("is-active");
        currentTheme = theme === toutesLesPhotos ? "" : theme;
        renderGrid();
      });
      filtersRoot.appendChild(btn);
    });

    renderGrid();

    const diaporamaBtn = document.querySelector("[data-start-diaporama]");
    if (diaporamaBtn) {
      diaporamaBtn.addEventListener("click", function () {
        const filtered = photosForCurrentTheme();
        if (!filtered.length) return;
        openLightbox(filtered, 0);
        startDiaporama();
      });
    }
  }

})();

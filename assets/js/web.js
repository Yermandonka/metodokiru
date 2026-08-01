document.addEventListener("DOMContentLoaded", () => {

  // ---------- Menú lateral ----------
  const menuBtn = document.querySelector(".menu-btn");
  const closeBtn = document.querySelector(".close-menu");
  const overlay = document.querySelector(".menu-overlay");

  if (menuBtn && overlay) {
    menuBtn.addEventListener("click", () => {
      overlay.classList.add("active");
      menuBtn.classList.add("abierto");
      menuBtn.setAttribute("aria-expanded", "true");
      // Bloquea el scroll de la página mientras el menú está abierto.
      document.body.classList.add("menu-abierto");
    });
  }
  if (closeBtn && overlay) {
    const cerrar = () => {
      overlay.classList.remove("active");
      if (menuBtn) {
        menuBtn.classList.remove("abierto");
        menuBtn.setAttribute("aria-expanded", "false");
      }
      document.body.classList.remove("menu-abierto");
    };
    closeBtn.addEventListener("click", cerrar);
    // También se cierra pulsando fuera del panel o con Escape.
    overlay.addEventListener("click", (ev) => {
      if (ev.target === overlay) cerrar();
    });
    document.addEventListener("keydown", (ev) => {
      if (ev.key === "Escape") cerrar();
    });
  }

  // ---------- FAQ desplegable ----------
  document.querySelectorAll(".faq-item").forEach(item => {
    const question = item.querySelector(".faq-question");
    question.addEventListener("click", () => {
      item.classList.toggle("active");
    });
  });

  // ---------- Animaciones de aparición al hacer scroll ----------
  const animados = document.querySelectorAll(
    ".hero-content, .subpage-content, .quote h2, .quote a, .testimonials h2, blockquote, " +
    ".programa-card, .tarifa-card, .faq-item, .family-card, .program-note, .foto-ancha img"
  );

  // En la portada las animaciones son reversibles: se repiten al subir y bajar.
  const reversible = document.body.classList.contains("pagina-inicio");

  if ("IntersectionObserver" in window && animados.length) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          if (!reversible) observer.unobserve(entry.target);
        } else if (reversible) {
          entry.target.classList.remove("visible");
        }
      });
    }, { threshold: 0.15, rootMargin: "0px 0px -40px 0px" });

    let orden = 0;
    animados.forEach(el => {
      el.classList.add("reveal");
      // Pequeño escalonado entre elementos cercanos.
      el.style.transitionDelay = (orden % 4) * 90 + "ms";
      orden++;
      observer.observe(el);
    });
  }

});

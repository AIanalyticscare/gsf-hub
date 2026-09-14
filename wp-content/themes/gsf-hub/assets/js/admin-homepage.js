document.addEventListener("DOMContentLoaded", () => {
  const sectionLinks = Array.from(
    document.querySelectorAll(".gsf-hub-section-nav__link")
  );
  const sections = Array.from(
    document.querySelectorAll(".gsf-hub-fields__section")
  );

  const setActiveSection = (sectionId) => {
    sectionLinks.forEach((link) => {
      link.classList.toggle(
        "is-active",
        link.getAttribute("data-section") === sectionId
      );
    });
  };

  if (sections.length > 0) {
    setActiveSection(sections[0].getAttribute("data-section"));
  }

  sectionLinks.forEach((link) => {
    link.addEventListener("click", () => {
      setActiveSection(link.getAttribute("data-section"));
    });
  });

  sections.forEach((section) => {
    section.addEventListener("focusin", () => {
      setActiveSection(section.getAttribute("data-section"));
    });
  });

  if ("IntersectionObserver" in window && sections.length > 0) {
    const observer = new IntersectionObserver(
      (entries) => {
        const visibleEntry = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

        if (visibleEntry) {
          setActiveSection(visibleEntry.target.getAttribute("data-section"));
        }
      },
      {
        rootMargin: "-20% 0px -60% 0px",
        threshold: [0.1, 0.25, 0.5],
      }
    );

    sections.forEach((section) => observer.observe(section));
  }

  const bindEditorFocus = (editor) => {
    if (!editor || !editor.getElement) {
      return;
    }

    editor.on("focus", () => {
      const textarea = editor.getElement();
      const section = textarea
        ? textarea.closest(".gsf-hub-fields__section")
        : null;

      if (section) {
        setActiveSection(section.getAttribute("data-section"));
      }
    });
  };

  if (window.tinymce) {
    tinymce.on("AddEditor", (event) => bindEditorFocus(event.editor));
    tinymce.editors.forEach(bindEditorFocus);
  }

  document.querySelectorAll(".gsf-hub-image-button").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();

      const targetId = button.getAttribute("data-target");
      const input = document.getElementById(targetId);

      if (!input || !window.wp || !wp.media) {
        return;
      }

      const frame = wp.media({
        title: "Select homepage image",
        multiple: false,
        library: { type: "image" },
        button: { text: "Use this image" },
      });

      frame.on("select", () => {
        const attachment = frame.state().get("selection").first().toJSON();
        input.value = attachment.url || "";
      });

      frame.open();
    });
  });
});

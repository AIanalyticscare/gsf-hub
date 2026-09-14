document.addEventListener("DOMContentLoaded", () => {
  const toggle = document.querySelector("[data-nav-toggle]");
  const nav = document.getElementById("primary-navigation");
  const actions = document.querySelector(".site-actions");

  if (!toggle || !nav || !actions) {
    return;
  }

  if (!actions.id) {
    actions.id = "site-actions";
  }

  const controlledIds = new Set(
    (toggle.getAttribute("aria-controls") || "")
      .split(/\s+/)
      .filter(Boolean)
  );

  controlledIds.add(nav.id);
  controlledIds.add(actions.id);
  toggle.setAttribute("aria-controls", Array.from(controlledIds).join(" "));

  const syncActions = () => {
    const isOpen = toggle.getAttribute("aria-expanded") === "true";
    actions.classList.toggle("gsf-mobile-actions-open", isOpen);
  };

  toggle.addEventListener("click", syncActions);
  syncActions();
});

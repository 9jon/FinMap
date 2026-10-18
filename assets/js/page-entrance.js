(() => {
  const selectors = [
    "body > header",
    "body > section",
    "body > main",
    "main > *",
    ".overview-header",
    ".overview-cards > *",
    ".main-actions__grid > *",
    ".dashboard-panels > *",
    ".floating-transaction-btn",
    ".savings-dashboard > *",
    ".budget-dashboard > *",
    ".goals-dashboard > *",
    ".alerts-dashboard > *",
    ".review-dashboard > *",
    ".login-wrapper > *",
    ".register-wrapper > *",
    ".income-shell > *",
    ".data-source-shell > *"
  ];

  function getPageElements() {
    const candidates = Array.from(document.querySelectorAll(selectors.join(",")))
      .filter((element) => {
        if (!element.getClientRects().length) return false;
        if (element.closest("[hidden], [aria-hidden='true'], .notif-panel, .notif-overlay, .ai-modal, .settings-modal, .modal, [role='dialog']")) return false;
        return true;
      });

    return candidates.filter((element) =>
      !candidates.some((other) => other !== element && element.contains(other))
    );
  }

  function playEntrance() {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

    const elements = getPageElements();
    if (!elements.length) return;

    elements.forEach((element, index) => {
      element.classList.remove("page-enter-item");
      element.style.setProperty("--page-enter-delay", `${Math.min(index * 90, 630)}ms`);
    });

    // Flush the reset so pages restored from browser history replay the entrance too.
    void document.documentElement.offsetWidth;
    requestAnimationFrame(() => elements.forEach((element) => element.classList.add("page-enter-item")));
  }

  window.addEventListener("pageshow", playEntrance);
})();

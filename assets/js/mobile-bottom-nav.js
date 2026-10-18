(() => {
  const nav = document.querySelector('.finmap-app-nav');
  if (!nav || !nav.parentNode) return;

  const originalParent = nav.parentNode;
  const placeholder = document.createComment('FinMap primary navigation');
  originalParent.insertBefore(placeholder, nav);

  const mobileLayout = window.matchMedia('(max-width: 840px)');
  const syncNavigationPosition = () => {
    if (mobileLayout.matches && nav.parentNode !== document.body) {
      document.body.appendChild(nav);
    } else if (!mobileLayout.matches && nav.parentNode !== originalParent) {
      originalParent.insertBefore(nav, placeholder.nextSibling);
    }
  };

  syncNavigationPosition();
  mobileLayout.addEventListener('change', syncNavigationPosition);
})();

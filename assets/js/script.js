const header = document.getElementById("header");

window.addEventListener("scroll", () => {

if (window.scrollY > 40) {

header.classList.add("scrolled");

} else {

header.classList.remove("scrolled");

}

});

const heroSection = document.querySelector(".hero-section");
const heroOrbs = document.querySelectorAll(".hero-orb");

if (heroSection && heroOrbs.length) {
  heroSection.addEventListener("mousemove", (e) => {
    const rect = heroSection.getBoundingClientRect();

    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    const centerX = rect.width / 20;
    const centerY = rect.height / 20;

    const moveX = x - centerX;
    const moveY = y - centerY;

    heroOrbs.forEach((orb) => {
      const speed = Number(orb.dataset.speed) || 20;

      const offsetX = moveX / speed;
      const offsetY = moveY / speed;

      orb.style.transform = `translate(${offsetX}px, ${offsetY}px)`;
    });
  });

  heroSection.addEventListener("mouseleave", () => {
    heroOrbs.forEach((orb) => {
      orb.style.transform = "translate(0px, 0px)";
    });
  });
}

const revealElements = document.querySelectorAll(".reveal");

const revealOnScroll = () => {
  revealElements.forEach((element) => {
    const elementTop = element.getBoundingClientRect().top;
    const triggerPoint = window.innerHeight - 80;

    if (elementTop < triggerPoint) {
      element.classList.add("active");
    }
  });
};

window.addEventListener("scroll", revealOnScroll);
window.addEventListener("load", revealOnScroll);


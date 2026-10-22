const menuButton = document.querySelector(".menu-toggle");
const navigation = document.querySelector("#site-navigation");
const progressBar = document.querySelector(".scroll-progress span");
const hero = document.querySelector(".hero");
const heroImage = document.querySelector("[data-parallax]");
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
const desktopLayout = window.matchMedia("(min-width: 681px)");

document.documentElement.classList.add("js");

if ("IntersectionObserver" in window) {
    const revealObserver = new IntersectionObserver((entries, observer) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                entry.target.classList.add("is-visible");
                observer.unobserve(entry.target);
            }
        }
    }, { threshold: 0.12 });

    document.querySelectorAll(".reveal").forEach((element) => revealObserver.observe(element));
} else {
    document.querySelectorAll(".reveal").forEach((element) => element.classList.add("is-visible"));
}

if (menuButton && navigation) {
    const closeMenu = () => {
        menuButton.setAttribute("aria-expanded", "false");
        navigation.classList.remove("is-open");
        navigation.querySelectorAll("details[open]").forEach((group) => group.removeAttribute("open"));
    };

    menuButton.addEventListener("click", () => {
        const isOpen = menuButton.getAttribute("aria-expanded") === "true";
        menuButton.setAttribute("aria-expanded", String(!isOpen));
        navigation.classList.toggle("is-open", !isOpen);
    });

    navigation.querySelectorAll("a").forEach((link) => link.addEventListener("click", closeMenu));
    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") return;

        if (menuButton.getAttribute("aria-expanded") === "true") {
            closeMenu();
            menuButton.focus();
            return;
        }

        const openGroup = navigation.querySelector("details[open]");
        if (openGroup) {
            const summary = openGroup.querySelector("summary");
            openGroup.removeAttribute("open");
            summary?.focus();
        }
    });

    document.addEventListener("click", (event) => {
        if (menuButton.getAttribute("aria-expanded") === "true"
            && event.target instanceof Node
            && !navigation.contains(event.target)
            && !menuButton.contains(event.target)
        ) {
            closeMenu();
        }
    });
}

let scrollScheduled = false;
let heroVisible = true;

const updateScrollEffects = () => {
    scrollScheduled = false;
    const scrollableHeight = document.documentElement.scrollHeight - window.innerHeight;
    const progress = scrollableHeight > 0 ? window.scrollY / scrollableHeight : 0;
    progressBar?.style.setProperty("transform", `scaleX(${Math.min(1, Math.max(0, progress))})`);

    if (heroVisible && desktopLayout.matches && !reduceMotion.matches && hero && heroImage) {
        const heroBottom = hero.getBoundingClientRect().bottom;
        const offset = Math.min(70, Math.max(0, window.scrollY * 0.12));
        if (heroBottom > 0) heroImage.style.transform = `translate3d(0, ${offset}px, 0) scale(1.08)`;
    }
};

const scheduleScrollEffects = () => {
    if (!scrollScheduled) {
        scrollScheduled = true;
        window.requestAnimationFrame(updateScrollEffects);
    }
};

if ("IntersectionObserver" in window && hero) {
    const heroObserver = new IntersectionObserver(([entry]) => {
        heroVisible = entry.isIntersecting;
    });
    heroObserver.observe(hero);
}

window.addEventListener("scroll", scheduleScrollEffects, { passive: true });
window.addEventListener("resize", scheduleScrollEffects, { passive: true });
window.addEventListener("load", scheduleScrollEffects, { once: true });

/*
 * Smooth Scroll – Lenis-artig, ohne Library (~1 KB).
 *
 * Prinzip: Nur Mausrad/Trackpad-Events werden abgefangen und per Lerp
 * auf das native Window-Scrolling gelegt. Die Seite scrollt weiterhin
 * nativ (kein Transform-Container) → GSAP ScrollTrigger, Pins,
 * position: sticky, Find-in-Page und Accessibility funktionieren unverändert.
 *
 * Performance:
 *  - rAF-Loop läuft nur während einer Bewegung, danach komplett idle.
 *  - Touch-Geräte senden keine wheel-Events → dort bleibt alles 100 % nativ.
 *  - Keine Layout-Writes, pro Frame genau ein scrollTo().
 *  - Tastatur, Scrollbar-Drag, Anker-Tweens (GSAP) übernehmen sofort:
 *    externe Scroll-Änderungen werden erkannt und die Animation stoppt.
 *  - prefers-reduced-motion → deaktiviert.
 *
 * Opt-out für einzelne Bereiche: Attribut data-smooth-prevent.
 */
(() => {
  // Nur Maus/Trackpad-Geräte (wird auf Handys/Tablets gar nicht erst geladen – doppelt abgesichert)
  if (!window.matchMedia("(hover: hover) and (pointer: fine)").matches) return;

  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)");
  const root = document.documentElement;

  const LERP = 0.1; // Anteil pro 60-fps-Frame (wie Lenis-Default), framerate-unabhängig
  const LINE = 40; // px pro Zeile (deltaMode 1, z. B. Firefox/Windows)
  const EPS = 0.5; // ab hier gilt die Bewegung als beendet
  const DRIFT = 2; // Toleranz für Subpixel-Rundung beim Erkennen externer Scrolls

  let target = 0;
  let current = 0;
  let lastSet = 0;
  let lastTime = 0;
  let raf = 0;
  let prepared = false;

  const maxScroll = () => root.scrollHeight - root.clientHeight;

  function stop() {
    if (raf) cancelAnimationFrame(raf);
    raf = 0;
  }

  function frame(now) {
    // Jemand anderes hat gescrollt (Tastatur, Scrollbar, GSAP-ScrollTo, Resize) → abgeben.
    if (Math.abs(window.scrollY - lastSet) > DRIFT) {
      stop();
      return;
    }

    const dt = Math.min(now - lastTime, 64);
    lastTime = now;

    current += (target - current) * (1 - Math.pow(1 - LERP, dt / 16.667));
    if (Math.abs(target - current) < EPS) current = target;

    window.scrollTo(0, current);
    lastSet = current;

    raf = current === target ? 0 : requestAnimationFrame(frame);
  }

  // Liegt unter dem Cursor ein eigener Scroll-Container, der in diese Richtung noch scrollen kann?
  function nativeScrollerFor(el, dy) {
    for (; el && el !== root && el !== document.body; el = el.parentElement) {
      if (el.hasAttribute("data-smooth-prevent") || el.tagName === "DIALOG") return true;
      if (el.scrollHeight <= el.clientHeight + 1) continue;
      const oy = getComputedStyle(el).overflowY;
      if (oy !== "auto" && oy !== "scroll" && oy !== "overlay") continue;
      if (dy > 0 ? el.scrollTop + el.clientHeight < el.scrollHeight - 1 : el.scrollTop > 0) return true;
    }
    return false;
  }

  function onWheel(e) {
    if (reduce.matches || e.defaultPrevented || e.ctrlKey) return; // ctrlKey = Pinch-Zoom

    // deltaY vor deltaMode lesen (Firefox liefert sonst Zeilen statt Pixel)
    let dy = e.deltaY;
    const dx = e.deltaX;
    if (e.deltaMode === 1) dy *= LINE;
    else if (e.deltaMode === 2) dy *= root.clientHeight;

    if (!dy || Math.abs(dx) > Math.abs(dy)) return; // horizontales Scrollen nativ lassen
    if (nativeScrollerFor(e.target, dy)) return;
    if (getComputedStyle(root).overflowY === "hidden") return; // Menü offen / Preloader

    e.preventDefault();

    if (!prepared) {
      // Globales CSS-smooth würde jedes scrollTo() selbst animieren.
      root.style.scrollBehavior = "auto";
      prepared = true;
    }

    if (!raf) {
      current = target = lastSet = window.scrollY;
      lastTime = performance.now();
    }

    target = Math.max(0, Math.min(maxScroll(), target + dy));

    if (!raf) raf = requestAnimationFrame(frame);
  }

  window.addEventListener("wheel", onWheel, { passive: false });
  // Bei Tab-Wechsel o. Ä. nicht weiterlaufen lassen
  document.addEventListener("visibilitychange", stop);
  reduce.addEventListener?.("change", stop);
})();

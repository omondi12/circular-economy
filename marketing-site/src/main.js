document.addEventListener('DOMContentLoaded', () => {
  initScrollProgress();
  initIntersectionObserver();
  initCounters();
  initMagneticButtons();
});

/* Scroll progress bar, header elevation, active nav-link highlighting. */
function initScrollProgress() {
  const bar = document.getElementById('scrollProgress');
  const nav = document.getElementById('mainNav');
  const navLinks = document.querySelectorAll('.nav-link');
  const sections = document.querySelectorAll('section[id]');

  window.addEventListener('scroll', () => {
    const scrollTop = window.scrollY;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
    if (bar) bar.style.width = `${progress}%`;

    if (nav) {
      nav.classList.toggle('scrolled', scrollTop > 20);
    }

    let current = '';
    sections.forEach((sec) => {
      const top = sec.offsetTop - 120;
      if (scrollTop >= top) current = sec.getAttribute('id');
    });

    navLinks.forEach((link) => {
      link.classList.toggle('active', link.getAttribute('href') === `#${current}`);
    });
  }, { passive: true });
}

/* Fade+rise elements into view as the page scrolls past them. */
function initIntersectionObserver() {
  const reveals = document.querySelectorAll('.reveal');
  const io = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) entry.target.classList.add('active');
    });
  }, { threshold: 0.12 });

  reveals.forEach((el) => io.observe(el));
}

/* Count up to each stat's real, sourced value once its section scrolls into view. */
function initCounters() {
  const counters = document.querySelectorAll('.counter');
  if (!counters.length) return;

  let animated = false;
  const heroSection = document.querySelector('.hero-light');
  const countObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting && !animated) {
        counters.forEach(runCounter);
        animated = true;
      }
    });
  }, { threshold: 0.2 });

  if (heroSection) countObserver.observe(heroSection);

  function runCounter(el) {
    const target = parseInt(el.getAttribute('data-target') || '0', 10);
    const duration = 1400;
    const startTime = performance.now();

    function step(now) {
      const progress = Math.min((now - startTime) / duration, 1);
      const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
      el.textContent = Math.floor(eased * target).toLocaleString();
      if (progress < 1) requestAnimationFrame(step);
      else el.textContent = target.toLocaleString();
    }
    requestAnimationFrame(step);
  }
}

/* Subtle cursor-following pull on primary CTA buttons. */
function initMagneticButtons() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  document.querySelectorAll('.btn-magnetic').forEach((btn) => {
    btn.addEventListener('mousemove', (e) => {
      const rect = btn.getBoundingClientRect();
      const x = e.clientX - rect.left - rect.width / 2;
      const y = e.clientY - rect.top - rect.height / 2;
      btn.style.transform = `translate(${x * 0.18}px, ${y * 0.18}px)`;
    });
    btn.addEventListener('mouseleave', () => {
      btn.style.transform = 'translate(0px, 0px)';
    });
  });
}

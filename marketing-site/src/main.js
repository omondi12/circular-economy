import confetti from 'canvas-confetti';

document.addEventListener('DOMContentLoaded', () => {
  initBootScreen();
  initAmbientBackground();
  initCursorGlow();
  initScrollProgress();
  initIntersectionObserver();
  initCounters();
  initMagneticButtons();
});

/* Cinematic first-load transition inspired by premium global infrastructure sites. */
function initBootScreen() {
  const screen = document.getElementById('bootScreen');
  const bar = document.getElementById('bootProgress');
  const percent = document.getElementById('bootPercent');
  if (!screen) return;

  document.body.classList.add('booting');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const duration = reduced ? 250 : 1250;
  const started = performance.now();

  function tick(now) {
    const progress = Math.min((now - started) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    const value = Math.round(eased * 100);
    if (bar) bar.style.width = `${value}%`;
    if (percent) percent.textContent = `${String(value).padStart(2, '0')}%`;
    if (progress < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);

  window.setTimeout(() => {
    screen.classList.add('is-done');
    document.body.classList.remove('booting');
    window.setTimeout(() => screen.remove(), reduced ? 260 : 850);
  }, duration + 120);
}

/* 1. Ambient Background Particles and Flow Lines */
function initAmbientBackground() {
  const canvas = document.getElementById('ambientCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let width, height;
  let particles = [];
  const particleCount = 28;

  function resize() {
    width = canvas.width = window.innerWidth;
    height = canvas.height = window.innerHeight;
  }
  window.addEventListener('resize', resize);
  resize();

  class Particle {
    constructor() {
      this.reset();
    }
    reset() {
      this.x = Math.random() * width;
      this.y = Math.random() * height;
      this.size = Math.random() * 2.2 + 0.8;
      this.speedX = (Math.random() - 0.5) * 0.45;
      this.speedY = (Math.random() - 0.5) * 0.45;
      this.color = Math.random() > 0.4 ? 'rgba(31, 140, 82, ' : 'rgba(181, 129, 10, ';
      this.alpha = Math.random() * 0.45 + 0.1;
    }
    update() {
      this.x += this.speedX;
      this.y += this.speedY;
      if (this.x < 0 || this.x > width || this.y < 0 || this.y > height) {
        this.reset();
      }
    }
    draw() {
      ctx.fillStyle = `${this.color}${this.alpha})`;
      ctx.beginPath();
      ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
      ctx.fill();
    }
  }

  for (let i = 0; i < particleCount; i++) {
    particles.push(new Particle());
  }

  function animate() {
    ctx.clearRect(0, 0, width, height);

    // Subtle connecting lines between close particles
    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const dx = particles[i].x - particles[j].x;
        const dy = particles[i].y - particles[j].y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < 120) {
          ctx.strokeStyle = `rgba(20, 112, 65, ${0.12 * (1 - dist / 120)})`;
          ctx.lineWidth = 0.8;
          ctx.beginPath();
          ctx.moveTo(particles[i].x, particles[i].y);
          ctx.lineTo(particles[j].x, particles[j].y);
          ctx.stroke();
        }
      }
    }

    particles.forEach(p => {
      p.update();
      p.draw();
    });

    requestAnimationFrame(animate);
  }
  animate();
}

/* 2. Ambient Cursor Glow */
function initCursorGlow() {
  const glow = document.getElementById('cursorGlow');
  if (!glow || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  window.addEventListener('pointermove', (e) => {
    glow.style.left = `${e.clientX}px`;
    glow.style.top = `${e.clientY}px`;
  }, { passive: true });
}

/* 3. Scroll Progress and Header Elevation */
function initScrollProgress() {
  const bar = document.getElementById('scrollProgress');
  const nav = document.getElementById('mainNav');
  const navLinks = document.querySelectorAll('.nav-link');
  const sections = document.querySelectorAll('section[id]');

  window.addEventListener('scroll', () => {
    const scrollTop = window.scrollY;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const progress = (scrollTop / docHeight) * 100;
    if (bar) bar.style.width = `${progress}%`;

    if (nav) {
      if (scrollTop > 20) {
        nav.classList.add('scrolled');
      } else {
        nav.classList.remove('scrolled');
      }
    }

    // Active link highlighting
    let current = '';
    sections.forEach(sec => {
      const top = sec.offsetTop - 120;
      if (scrollTop >= top) {
        current = sec.getAttribute('id');
      }
    });

    navLinks.forEach(link => {
      link.classList.remove('active');
      if (link.getAttribute('href') === `#${current}`) {
        link.classList.add('active');
      }
    });
  }, { passive: true });
}

/* 4. Intersection Observer for Scroll Reveals */
function initIntersectionObserver() {
  const reveals = document.querySelectorAll('.reveal');
  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('active');
      }
    });
  }, { threshold: 0.12 });

  reveals.forEach(el => io.observe(el));
}

/* 5. Smooth Number Counters */
function initCounters() {
  const counters = document.querySelectorAll('.counter, #heroMainCounter');
  let animated = false;

  const countObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting && !animated) {
        counters.forEach(c => runCounter(c));
        animated = true;
      }
    });
  }, { threshold: 0.2 });

  const heroSection = document.querySelector('.hero');
  if (heroSection) countObserver.observe(heroSection);

  function runCounter(el) {
    const target = parseInt(el.getAttribute('data-target') || '0', 10);
    const duration = 1800; // ms
    const startTime = performance.now();

    function step(now) {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // Ease out expo
      const ease = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
      const current = Math.floor(ease * target);
      el.textContent = current.toLocaleString();

      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = target.toLocaleString();
      }
    }
    requestAnimationFrame(step);
  }
}

/* 6. Four-Step Interactive Pipeline */
/* 10. Magnetic Physics on CTA Buttons */
function initMagneticButtons() {
  const btns = document.querySelectorAll('.btn-magnetic');
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  btns.forEach(btn => {
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

  const partnerBtn = document.getElementById('btnPartnerAction');
  if (partnerBtn) {
    partnerBtn.addEventListener('click', () => {
      confetti({
        particleCount: 50,
        spread: 70,
        origin: { y: 0.8 },
        colors: ['#147041', '#b5810a', '#3aa96c', '#e3ad3f']
      });
    });
  }
}

import confetti from 'canvas-confetti';

document.addEventListener('DOMContentLoaded', () => {
  initBootScreen();
  initAmbientBackground();
  initCursorGlow();
  initScrollProgress();
  initIntersectionObserver();
  initCounters();
  initPipeline();
  initMaterialsShowcase();
  initCoverageMatrix();
  initSimulator();
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
function initPipeline() {
  const steps = document.querySelectorAll('.pipeline-card');
  const titleEl = document.getElementById('previewStepTitle');
  const logEl = document.getElementById('consoleOutput');

  const stepData = {
    '1': {
      title: 'Stage 01: Institutional Mapping In-Progress',
      logs: [
        '> Initiating Government Structure Cross-Reference...',
        '> Scanning 24 Ministries... [VERIFIED]',
        '> Mapping 47 County Executive Offices... [SYNCHRONIZED]',
        '> 668 Target Institutional Nodes Registered.'
      ]
    },
    '2': {
      title: 'Stage 02: Relationship Manager Field Deployment',
      logs: [
        '> Matching Field RM Officers by Regional District...',
        '> 24 Senior Relationship Managers dispatched on-site.',
        '> Establishing physical liaison protocol with departmental heads.',
        '> Active engagement sessions recorded: 42 this week.'
      ]
    },
    '3': {
      title: 'Stage 03: Physical Stream Categorisation & Weigh-In',
      logs: [
        '> In-situ sorting: Paper (confidential) vs Metal vs E-Waste.',
        '> Digital custody certificate generated #WIC-STRM-882.',
        '> Sorting yield classification: 6 distinct recovered streams.',
        '> Scheduled offload logistics routing authorized.'
      ]
    },
    '4': {
      title: 'Stage 04: Public Value Recovery & Real-time Audit',
      logs: [
        '> Public Procurement & Disposal Act (PPADA) verification checked.',
        '> Recovery ledger updated across national dashboard.',
        '> Commercial remanufacturing yield credited to public ledger.',
        '> 100% auditable chain of custody sealed.'
      ]
    }
  };

  steps.forEach(card => {
    card.addEventListener('click', () => {
      steps.forEach(s => s.classList.remove('active'));
      card.classList.add('active');

      const stepNum = card.getAttribute('data-step') || '1';
      const data = stepData[stepNum];

      if (titleEl && data) titleEl.textContent = data.title;
      if (logEl && data) {
        logEl.innerHTML = data.logs.map((line, idx) => {
          const isHighlight = idx === data.logs.length - 1;
          return `<div class="log-line ${isHighlight ? 'highlight' : ''}">${line}</div>`;
        }).join('');
      }
    });
  });
}

/* 7. Interactive Material Streams Showcase */
function initMaterialsShowcase() {
  const chips = document.querySelectorAll('.chip-interactive');
  const titleEl = document.getElementById('matTitle');
  const descEl = document.getElementById('matDesc');
  const stat1 = document.getElementById('matStat1');
  const stat2 = document.getElementById('matStat2');
  const stat3 = document.getElementById('matStat3');
  const codeEl = document.getElementById('matCode');
  const statusBadge = document.getElementById('matStatusBadge');
  const symbolEl = document.getElementById('matVizSymbol');

  const streamInfo = {
    paper: {
      type: 'sale',
      badge: 'Value Stream: Sale',
      code: 'PROTOCOL ID: #MAT-PPR-01',
      title: 'Institutional Paper & Document Archives',
      desc: 'Confidential shredding, grading, and secondary pulp conversion for decommissioned government documents, archives, stationery, and publications. Converted into packaging grade liner board and domestic cellulose materials.',
      s1: 'High Volume',
      s2: '100% Chain-of-Custody',
      s3: 'Commercial Reclaim',
      symbol: '📄'
    },
    metal: {
      type: 'sale',
      badge: 'Value Stream: Sale',
      code: 'PROTOCOL ID: #MAT-MTL-02',
      title: 'Ferrous & Non-Ferrous Heavy Scrap',
      desc: 'Decommissioned vehicles, structural scrap, machinery, institutional fencing, and mechanical spares cataloged and melted down for rebar fabrication and industrial infrastructure manufacturing.',
      s1: 'High Density',
      s2: 'Certified Foundry Melt',
      s3: 'Infrastructure Re-use',
      symbol: '⚙️'
    },
    plastic: {
      type: 'sale',
      badge: 'Value Stream: Sale',
      code: 'PROTOCOL ID: #MAT-PLS-03',
      title: 'Clean Polymers & Rigids Reprocessing',
      desc: 'Rigid containers, bulk crates, institutional drums, and packaging cleaned, flaked, and pelletized into recycled feedstock for domestic manufacturing in accordance with NEMA guidelines.',
      s1: 'Circularity Certified',
      s2: 'Zero Landfill Target',
      s3: 'Extrusion Quality',
      symbol: '♻️'
    },
    furniture: {
      type: 'disposal',
      badge: 'Value Stream: Disposal',
      code: 'PROTOCOL ID: #MAT-FUR-04',
      title: 'Decommissioned Office Furniture & Fixtures',
      desc: 'Wood, steel, and composite desks, cabinetry, and executive seating assessed for public refurbishment programs or staged for safe eco-disposal under county asset boards.',
      s1: 'Refurbish Priority',
      s2: 'Public Re-Allocation',
      s3: 'Eco-Safe Dismantling',
      symbol: '🪑'
    },
    ewaste: {
      type: 'disposal',
      badge: 'Value Stream: Disposal',
      code: 'PROTOCOL ID: #MAT-EWT-05',
      title: 'Certified Hazardous E-Waste & IT Assets',
      desc: 'Obsolete computers, servers, printers, displays, and wiring safely depolluted. Heavy metals (lead, mercury, cadmium) neutralized with precious trace elements salvaged through specialized recyclers.',
      s1: 'Hazardous Regulated',
      s2: 'NEMA Compliant',
      s3: 'Rare Element Salvage',
      symbol: '💻'
    },
    other: {
      type: 'disposal',
      badge: 'Value Stream: Disposal',
      code: 'PROTOCOL ID: #MAT-OTH-06',
      title: 'Specialty Institutional Streams',
      desc: 'Textiles, uniforms, automotive tires, and non-standard decommissioned inventory evaluated case-by-case for industrial thermal energy recovery or county safe disposal.',
      s1: 'Custom Audit',
      s2: 'Thermal Energy Off-take',
      s3: 'Safe Environmental Isolation',
      symbol: '📦'
    }
  };

  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      chips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');

      const stream = chip.getAttribute('data-stream');
      const data = streamInfo[stream];
      if (!data) return;

      if (titleEl) titleEl.textContent = data.title;
      if (descEl) descEl.textContent = data.desc;
      if (stat1) stat1.textContent = data.s1;
      if (stat2) stat2.textContent = data.s2;
      if (stat3) stat3.textContent = data.s3;
      if (codeEl) codeEl.textContent = data.code;
      if (symbolEl) symbolEl.textContent = data.symbol;

      if (statusBadge) {
        statusBadge.textContent = data.badge;
        statusBadge.className = `stream-pill ${data.type}`;
      }
    });
  });
}

/* 8. Interactive Coverage Matrix (168 Nodes) */
function initCoverageMatrix() {
  const field = document.getElementById('dotfield');
  const infoEl = document.getElementById('matrixHoverInfo');
  const filterBtns = document.querySelectorAll('.matrix-btn');
  if (!field) return;

  const total = 168; // 7 rows x 24 cols
  const states = new Array(total).fill('');
  const idx = Array.from({ length: total }, (_, i) => i);

  // Shuffle indexes for natural distribution
  for (let i = idx.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [idx[i], idx[j]] = [idx[j], idx[i]];
  }

  // 24 national ministries, 47 county headquarters, rest are mapped field entities
  idx.slice(0, 24).forEach(i => { states[i] = 'ministry'; });
  idx.slice(24, 24 + 47).forEach(i => { states[i] = 'county'; });

  const frag = document.createDocumentFragment();
  states.forEach((type, index) => {
    const d = document.createElement('div');
    d.className = `dot ${type}`;
    d.setAttribute('data-index', index);
    d.setAttribute('data-type', type || 'institution');

    d.addEventListener('mouseenter', () => {
      if (infoEl) {
        if (type === 'ministry') {
          infoEl.innerHTML = `<strong>National Ministry Node #${index + 1}:</strong> Executive Cabinet Agency, Nairobi HQ. Direct Relationship Manager assigned.`;
        } else if (type === 'county') {
          infoEl.innerHTML = `<strong>County Government Node #${index + 1}:</strong> County Executive Department, Decentralized Field Office. Active stream tracking.`;
        } else {
          infoEl.innerHTML = `<strong>Institutional Facility Node #${index + 1}:</strong> Registered Public Body in national circular network.`;
        }
      }
    });

    frag.appendChild(d);
  });
  field.appendChild(frag);

  // Filtering
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const filter = btn.getAttribute('data-filter');
      const dots = field.querySelectorAll('.dot');

      dots.forEach(dot => {
        const type = dot.getAttribute('data-type');
        dot.classList.remove('dimmed');

        if (filter === 'ministries' && type !== 'ministry') {
          dot.classList.add('dimmed');
        } else if (filter === 'counties' && type !== 'county') {
          dot.classList.add('dimmed');
        }
      });
    });
  });
}

/* 9. Impact Simulator */
function initSimulator() {
  const choices = document.querySelectorAll('.sim-choice');
  const slider = document.getElementById('officeSizeSlider');
  const sliderVal = document.getElementById('sliderValDisplay');
  const paperYield = document.getElementById('simPaperYield');
  const ewasteYield = document.getElementById('simEwasteYield');
  const valueYield = document.getElementById('simValueYield');

  let currentTier = 'ministry';
  let currentScale = 3;

  const scales = {
    1: 'Small Bureau / 200 Officers',
    2: 'Medium Department / 600 Officers',
    3: 'Large Ministry / 1,200 Officers (3 Facilities)',
    4: 'Multi-Directorate / 3,000 Officers (8 Facilities)',
    5: 'Enterprise Agency / 6,500+ Officers Nationwide'
  };

  const multipliers = {
    ministry: { paper: 9.5, ewaste: 110, value: 1.25 },
    county: { paper: 6.8, ewaste: 85, value: 0.95 },
    parastatal: { paper: 8.2, ewaste: 140, value: 1.6 }
  };

  function update() {
    if (sliderVal) sliderVal.textContent = scales[currentScale];
    const m = multipliers[currentTier];

    const paper = (currentScale * m.paper).toFixed(1);
    const ewaste = Math.floor(currentScale * m.ewaste);
    const val = (currentScale * m.value).toFixed(1);

    if (paperYield) paperYield.textContent = `${paper} MT / yr`;
    if (ewasteYield) ewasteYield.textContent = `${ewaste}+ Units / yr`;
    if (valueYield) valueYield.textContent = `KES ${val}M+`;
  }

  choices.forEach(btn => {
    btn.addEventListener('click', () => {
      choices.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentTier = btn.getAttribute('data-tier') || 'ministry';
      update();
    });
  });

  if (slider) {
    slider.addEventListener('input', (e) => {
      currentScale = parseInt(e.target.value, 10);
      update();
    });
  }
}

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

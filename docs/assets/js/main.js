/* ============================================================
   BangronDB Documentation — main.js
   ============================================================ */

// ===== Navbar scroll effect =====
(function () {
  const nav = document.getElementById('navbar');
  if (!nav) return;
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 20);
  }, { passive: true });
})();

// ===== Mobile menu toggle =====
(function () {
  const btn = document.getElementById('menuBtn');
  const links = document.getElementById('navLinks');
  if (!btn || !links) return;
  btn.addEventListener('click', () => links.classList.toggle('open'));
})();

// ===== Auto-generate TOC from H2/H3 in main content =====
(function () {
  const tocNav = document.getElementById('toc');
  if (!tocNav) return;

  const content = document.querySelector('.doc-content');
  if (!content) return;

  const headings = content.querySelectorAll('h2, h3');
  if (headings.length === 0) {
    tocNav.parentElement.style.display = 'none';
    return;
  }

  const ul = document.createElement('ul');
  let currentH2 = null;

  headings.forEach(h => {
    // Skip headings that are inside the TOC nav itself or are hidden
    if (h.closest('.doc-nav')) return;

    // Generate ID if not present
    if (!h.id) {
      h.id = h.textContent
        .toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    }

    const li = document.createElement('li');
    const a = document.createElement('a');
    a.href = '#' + h.id;
    a.textContent = h.textContent.replace(/^#+\s*/, '').substring(0, 60);
    a.setAttribute('data-target', h.id);

    if (h.tagName === 'H3') {
      a.classList.add('h3');
      if (!currentH2) {
        // H3 without preceding H2 — just append to main list
        li.appendChild(a);
        ul.appendChild(li);
      } else {
        // Append to nested ul under current H2
        let nestedUl = currentH2.querySelector('ul');
        if (!nestedUl) {
          nestedUl = document.createElement('ul');
          currentH2.appendChild(nestedUl);
        }
        const nestedLi = document.createElement('li');
        nestedLi.appendChild(a);
        nestedUl.appendChild(nestedLi);
      }
    } else {
      // H2
      li.appendChild(a);
      ul.appendChild(li);
      currentH2 = li;
    }

    // Add anchor link icon
    const anchor = document.createElement('a');
    anchor.href = '#' + h.id;
    anchor.className = 'anchor';
    anchor.textContent = '#';
    anchor.setAttribute('aria-label', 'Anchor link');
    h.appendChild(anchor);
  });

  tocNav.appendChild(ul);

  // ===== Scrollspy: highlight current TOC item =====
  const tocLinks = tocNav.querySelectorAll('a[data-target]');
  const headingEls = Array.from(headings).map(h => document.getElementById(h.id)).filter(Boolean);

  if (headingEls.length === 0) return;

  let scrollTimeout;
  function updateActiveToc() {
    const scrollPos = window.scrollY + 120; // offset for navbar
    let activeIdx = 0;
    for (let i = 0; i < headingEls.length; i++) {
      if (headingEls[i].offsetTop <= scrollPos) {
        activeIdx = i;
      }
    }
    tocLinks.forEach((link, idx) => {
      link.classList.toggle('active', idx === activeIdx);
    });
  }

  window.addEventListener('scroll', () => {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(updateActiveToc, 50);
  }, { passive: true });
  updateActiveToc();
})();

// ===== Active sidebar nav link (highlight current page) =====
(function () {
  const sidebarLinks = document.querySelectorAll('.doc-sidebar nav a');
  if (sidebarLinks.length === 0) return;

  const currentPath = window.location.pathname.replace(/\.html$/, '').replace(/\/$/, '');
  sidebarLinks.forEach(link => {
    const linkPath = link.getAttribute('href').replace(/\.html$/, '').replace(/\/$/, '');
    // Strip leading slash and base for comparison
    const cleanCurrent = currentPath.split('/').pop();
    const cleanLink = linkPath.split('/').pop();
    if (cleanCurrent === cleanLink && cleanCurrent !== '') {
      link.classList.add('active');
    }
  });
})();

// ===== Copy button on code blocks =====
(function () {
  const preBlocks = document.querySelectorAll('.doc-content pre');
  preBlocks.forEach(pre => {
    // Skip if already has a copy button
    if (pre.querySelector('.copy-code-btn')) return;

    const btn = document.createElement('button');
    btn.className = 'copy-code-btn';
    btn.textContent = 'Copy';
    btn.setAttribute('aria-label', 'Copy code to clipboard');

    btn.addEventListener('click', () => {
      const code = pre.querySelector('code');
      const text = code ? code.textContent : pre.textContent;
      navigator.clipboard.writeText(text).then(() => {
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(() => {
          btn.textContent = 'Copy';
          btn.classList.remove('copied');
        }, 2000);
      }).catch(() => {
        // Fallback for older browsers
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(() => {
          btn.textContent = 'Copy';
          btn.classList.remove('copied');
        }, 2000);
      });
    });

    pre.appendChild(btn);
  });
})();

// ===== External links: open in new tab =====
(function () {
  const links = document.querySelectorAll('.doc-content a[href^="http"]');
  links.forEach(link => {
    if (link.hostname !== window.location.hostname) {
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
    }
  });
})();

// ===== Smooth scroll for anchor links (with navbar offset) =====
(function () {
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (!target) return;
      e.preventDefault();
      const offset = 80; // navbar height
      const top = target.getBoundingClientRect().top + window.pageYOffset - offset;
      window.scrollTo({ top, behavior: 'smooth' });
      // Update URL hash without jumping
      history.replaceState(null, '', this.getAttribute('href'));
    });
  });
})();

// ===== Scroll reveal (IntersectionObserver) =====
(function () {
  const obs = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        obs.unobserve(e.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
})();

// ===== Copy install command (landing page only) =====
function copyInstall() {
  const cmd = 'composer require herdianrony/bangrondb';
  navigator.clipboard.writeText(cmd).then(() => {
    const btn = document.getElementById('copyBtn');
    if (btn) {
      btn.textContent = 'Copied!';
      setTimeout(() => btn.textContent = 'Copy', 2000);
    }
  }).catch(() => {
    const ta = document.createElement('textarea');
    ta.value = cmd;
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
    const btn = document.getElementById('copyBtn');
    if (btn) {
      btn.textContent = 'Copied!';
      setTimeout(() => btn.textContent = 'Copy', 2000);
    }
  });
}

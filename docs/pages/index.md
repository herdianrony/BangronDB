---
layout: doc
title: "Documentation"
description: "Daftar lengkap dokumentasi BangronDB — Getting Started, Features, Query Operators, Schema, Hooks, Security, API Reference, Integrations, Project Scenarios, dan Modular Architecture."
permalink: /docs/
toc: true
edit_on_github: true
---

# Dokumentasi BangronDB

Halo, saya Rony — author BangronDB. Saya bikin database ini karena di project-project saya (ERP, CRM, POS, dll) saya berulang kali butuh hal yang sama: penyimpanan dokumen yang fleksibel, bisa di-deploy tanpa setup database server, dan punya API yang enak dipakai. MongoDB-style API di atas SQLite adalah jawaban saya.

Dokumentasi di bawah ini saya tulis dari pengalaman actual pakai BangronDB di production. Bukan teori — semua pattern, anti-pattern, dan tips sudah diuji di project nyata.

> Halaman ini auto-generate dari front-matter setiap file `.md`. Kalau saya tambah docs baru dengan `category` di front-matter, list di bawah otomatis update.

{% assign doc_pages = site.pages | where: "layout", "doc" | sort: "title" %}

## Daftar Lengkap Dokumentasi

### Dasar

Dokumen fundamental — mulai dari sini kalau baru kenal BangronDB.

{% assign cat_pages = doc_pages | where: "category", "dasar" %}
{% if cat_pages.size > 0 %}
<div class="doc-list">
{% for p in cat_pages %}
  <a href="{{ p.url | relative_url }}" class="doc-list-card">
    <h3>
      <span class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      </span>
      {{ p.title }}
    </h3>
    <div class="card-desc">{{ p.description | default: "—" }}</div>
    <span class="card-link">{{ p.url }}</span>
  </a>
{% endfor %}
</div>
{% else %}
<p style="color:var(--text-muted);font-style:italic">Belum ada dokumen di kategori ini.</p>
{% endif %}

### Keamanan & API

Security, encryption, dan referensi API lengkap.

{% assign cat_pages = doc_pages | where: "category", "keamanan-api" %}
{% if cat_pages.size > 0 %}
<div class="doc-list">
{% for p in cat_pages %}
  <a href="{{ p.url | relative_url }}" class="doc-list-card">
    <h3>
      <span class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </span>
      {{ p.title }}
    </h3>
    <div class="card-desc">{{ p.description | default: "—" }}</div>
    <span class="card-link">{{ p.url }}</span>
  </a>
{% endfor %}
</div>
{% else %}
<p style="color:var(--text-muted);font-style:italic">Belum ada dokumen di kategori ini.</p>
{% endif %}

### Integrasi

Micro-framework yang cocok untuk BangronDB.

Lihat halaman index: [/docs/integrations/](/docs/integrations/)

{% assign cat_pages = doc_pages | where: "category", "integrations" %}
{% if cat_pages.size > 0 %}
<div class="doc-list">
{% for p in cat_pages %}
  <a href="{{ p.url | relative_url }}" class="doc-list-card">
    <h3>
      <span class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
      </span>
      {{ p.title }}
    </h3>
    <div class="card-desc">{{ p.description | default: "—" }}</div>
    <span class="card-link">{{ p.url }}</span>
  </a>
{% endfor %}
</div>
{% else %}
<p style="color:var(--text-muted);font-style:italic">Belum ada dokumen di kategori ini.</p>
{% endif %}

### Skenario Project

Implementasi di project nyata: ERP, CRM, SCM, HRIS, POS.

Lihat halaman index: [/docs/scenarios/](/docs/scenarios/)

{% assign cat_pages = doc_pages | where: "category", "scenarios" %}
{% if cat_pages.size > 0 %}
<div class="doc-list">
{% for p in cat_pages %}
  <a href="{{ p.url | relative_url }}" class="doc-list-card">
    <h3>
      <span class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      </span>
      {{ p.title }}
    </h3>
    <div class="card-desc">{{ p.description | default: "—" }}</div>
    <span class="card-link">{{ p.url }}</span>
  </a>
{% endfor %}
</div>
{% else %}
<p style="color:var(--text-muted);font-style:italic">Belum ada dokumen di kategori ini.</p>
{% endif %}

### Arsitektur

Strategi multi-database dan modular architecture.

{% assign cat_pages = doc_pages | where: "category", "arsitektur" %}
{% if cat_pages.size > 0 %}
<div class="doc-list">
{% for p in cat_pages %}
  <a href="{{ p.url | relative_url }}" class="doc-list-card">
    <h3>
      <span class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
      </span>
      {{ p.title }}
    </h3>
    <div class="card-desc">{{ p.description | default: "—" }}</div>
    <span class="card-link">{{ p.url }}</span>
  </a>
{% endfor %}
</div>
{% else %}
<p style="color:var(--text-muted);font-style:italic">Belum ada dokumen di kategori ini.</p>
{% endif %}

### Lainnya

Roadmap dan dokumen tambahan.

{% assign cat_pages = doc_pages | where: "category", "lainnya" %}
{% if cat_pages.size > 0 %}
<div class="doc-list">
{% for p in cat_pages %}
  <a href="{{ p.url | relative_url }}" class="doc-list-card">
    <h3>
      <span class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      </span>
      {{ p.title }}
    </h3>
    <div class="card-desc">{{ p.description | default: "—" }}</div>
    <span class="card-link">{{ p.url }}</span>
  </a>
{% endfor %}
</div>
{% else %}
<p style="color:var(--text-muted);font-style:italic">Belum ada dokumen di kategori ini.</p>
{% endif %}

## Mulai Dari Mana?

### Pemula

Kalau baru kenal BangronDB, saya sarankan mulai dari sini:

1. [Getting Started](/docs/getting-started/) — instalasi dan konsep dasar. 10 menit baca, langsung bisa cobain.
2. [Features](/docs/features/) — gambaran kemampuan. Skip kalau sudah excited mau cobain.
3. [Query Operators](/docs/query-operators/) — operator query mirip MongoDB. Cek kalau butuh query kompleks.

### Sudah paham dasar, mau implementasi

1. Pilih [skenario project](/docs/scenarios/) yang mirip dengan yang Anda bangun (ERP, CRM, SCM, dll). Saya tulis dari pengalaman actual.
2. Pilih [micro-framework](/docs/integrations/) untuk integrasi. Saya pakai Flight PHP di docs, tapi Slim/Lumen juga cocok.
3. Baca [Modular Architecture](/docs/modular-architecture/) kalau aplikasi Anda butuh multi-modul (mis. ERP + CRM + POS).

### Mau deploy ke production

1. [Security](/docs/security/) — baca dulu sebelum simpan data sensitif. Encryption, blind index, key rotation.
2. [Hook Patterns](/docs/hook-patterns/) — pattern hook untuk business logic (audit log, auto-timestamp, ACL).
3. [API Reference](/docs/api-reference/) — referensi method lengkap kalau butuh lookup cepat.

## Stack yang Saya Pakai

```
BangronDB (embedded database)
    +
Flight PHP (micro-framework)  
    +
PHP 8.1+
```

Saya pakai kombinasi ini di project-project saya. Alasannya:
- **Embedded** — tidak butuh server database atau web server tambahan. Cukup PHP + folder data.
- **Ringan** — total footprint < 5MB (BangronDB + Flight + dependencies).
- **Zero config** — deploy dengan copy folder, jalan.
- **Production-ready** — schema validation, encryption, hooks, transactions. Bukan toy project.

## Lihat Juga

- [GitHub Repository](https://github.com/herdianrony/BangronDB) — source code & issues
- [Packagist](https://packagist.org/packages/herdianrony/bangrondb) — install via Composer
- [CONTRIBUTING.md](https://github.com/herdianrony/BangronDB/blob/master/CONTRIBUTING.md) — kontribusi

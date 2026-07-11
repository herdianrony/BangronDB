---
layout: doc
title: "Documentation"
description: "Daftar lengkap dokumentasi BangronDB — Getting Started, Features, Query Operators, Schema, Hooks, Security, API Reference, Integrations, Project Scenarios, dan Modular Architecture."
permalink: /docs/
toc: true
edit_on_github: true
---

# Dokumentasi BangronDB

Selamat datang di dokumentasi BangronDB — embedded document database untuk PHP. MongoDB-style API, SQLite backend, zero server, zero config.

> Halaman ini di-generate otomatis dari front-matter setiap file `.md`. Tambah file baru dengan `category` di front-matter, dan halaman ini akan otomatis ter-update.

## Daftar Lengkap Dokumentasi

{% comment %}
  Auto-generate dari site.pages yang punya layout: doc dan category.
  Exclude index pages (yang punya permalink ending with /docs/, /docs/integrations/, /docs/scenarios/).
  Group by category: dasar, keamanan-api, integrations, scenarios, arsitektur, lainnya.
{% endcomment %}

{% assign doc_pages = site.pages | where: "layout", "doc" | sort: "title" %}

### Dasar

<table>
<thead>
<tr><th>Dokumen</th><th>Isi</th><th>Link</th></tr>
</thead>
<tbody>
{% for p in doc_pages %}
  {% if p.category == "dasar" %}
  <tr>
    <td><strong>{{ p.title }}</strong></td>
    <td>{{ p.description | default: "—" }}</td>
    <td><a href="{{ p.url }}">/docs{{ p.url | remove: "/docs" }}/</a></td>
  </tr>
  {% endif %}
{% endfor %}
</tbody>
</table>

### Keamanan & API

<table>
<thead>
<tr><th>Dokumen</th><th>Isi</th><th>Link</th></tr>
</thead>
<tbody>
{% for p in doc_pages %}
  {% if p.category == "keamanan-api" %}
  <tr>
    <td><strong>{{ p.title }}</strong></td>
    <td>{{ p.description | default: "—" }}</td>
    <td><a href="{{ p.url }}">{{ p.url }}</a></td>
  </tr>
  {% endif %}
{% endfor %}
</tbody>
</table>

### Integrasi

Lihat halaman index: [/docs/integrations/](/docs/integrations/)

<table>
<thead>
<tr><th>Dokumen</th><th>Isi</th><th>Link</th></tr>
</thead>
<tbody>
{% for p in doc_pages %}
  {% if p.category == "integrations" %}
  <tr>
    <td><strong>{{ p.title }}</strong></td>
    <td>{{ p.description | default: "—" }}</td>
    <td><a href="{{ p.url }}">{{ p.url }}</a></td>
  </tr>
  {% endif %}
{% endfor %}
</tbody>
</table>

### Skenario Project

Lihat halaman index: [/docs/scenarios/](/docs/scenarios/)

<table>
<thead>
<tr><th>Dokumen</th><th>Isi</th><th>Link</th></tr>
</thead>
<tbody>
{% for p in doc_pages %}
  {% if p.category == "scenarios" %}
  <tr>
    <td><strong>{{ p.title }}</strong></td>
    <td>{{ p.description | default: "—" }}</td>
    <td><a href="{{ p.url }}">{{ p.url }}</a></td>
  </tr>
  {% endif %}
{% endfor %}
</tbody>
</table>

### Arsitektur

<table>
<thead>
<tr><th>Dokumen</th><th>Isi</th><th>Link</th></tr>
</thead>
<tbody>
{% for p in doc_pages %}
  {% if p.category == "arsitektur" %}
  <tr>
    <td><strong>{{ p.title }}</strong></td>
    <td>{{ p.description | default: "—" }}</td>
    <td><a href="{{ p.url }}">{{ p.url }}</a></td>
  </tr>
  {% endif %}
{% endfor %}
</tbody>
</table>

### Lainnya

<table>
<thead>
<tr><th>Dokumen</th><th>Isi</th><th>Link</th></tr>
</thead>
<tbody>
{% for p in doc_pages %}
  {% if p.category == "lainnya" %}
  <tr>
    <td><strong>{{ p.title }}</strong></td>
    <td>{{ p.description | default: "—" }}</td>
    <td><a href="{{ p.url }}">{{ p.url }}</a></td>
  </tr>
  {% endif %}
{% endfor %}
</tbody>
</table>

## Mulai Dari Mana?

### Pemula

1. Baca [Getting Started](/docs/getting-started/) — instalasi dan konsep dasar.
2. Lihat [Features](/docs/features/) — gambaran kemampuan BangronDB.
3. Coba [Query Operators](/docs/query-operators/) — operator query mirip MongoDB.

### Sudah paham dasar & mau implementasi

1. Pilih [skenario project](/docs/scenarios/) yang sesuai (ERP, CRM, dll).
2. Pilih [micro-framework](/docs/integrations/) untuk integrasi.
3. Baca [Modular Architecture](/docs/modular-architecture/) kalau butuh multi-modul.

### Production deployment

1. Baca [Security](/docs/security/) — encryption, key rotation, audit.
2. Setup [Hook Patterns](/docs/hook-patterns/) untuk business logic.
3. Pelajari [API Reference](/docs/api-reference/) untuk method lengkap.

## Stack yang Direkomendasikan

```
BangronDB (embedded database)
    +
Flight PHP (micro-framework)  
    +
PHP 8.1+
```

Kombinasi ini memberikan:
- **Embedded** — tidak butuh server database atau web server tambahan
- **Ringan** — total footprint < 5MB (BangronDB + Flight + dependencies)
- **Zero config** — deploy dengan copy folder
- **Production-ready** — schema validation, encryption, hooks, transactions

## Lihat Juga

- [GitHub Repository](https://github.com/herdianrony/BangronDB) — source code & issues
- [Packagist](https://packagist.org/packages/herdianrony/bangrondb) — install via Composer
- [CONTRIBUTING.md](https://github.com/herdianrony/BangronDB/blob/master/CONTRIBUTING.md) — kontribusi

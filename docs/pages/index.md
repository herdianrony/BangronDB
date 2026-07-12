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

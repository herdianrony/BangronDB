---
layout: doc
title: "Project Scenarios"
description: "Tips & trick implementasi BangronDB pada skenario project nyata: ERP, CRM, SCM, HRIS, POS, Auth & ACL, dan Modular Architecture."
permalink: /docs/scenarios/
toc: true
edit_on_github: true
---

# Project Scenarios

Saya tulis docs skenario ini dari pengalaman actual — bukan teori. Setiap skenario (ERP, CRM, SCM, HRIS, POS, Auth) saya tulis berdasarkan project yang pernah saya kerjakan. Termasuk jebakan-jebakan yang pernah saya jatuhin, supaya Anda tidak perlu repeat.

Setiap skenario membahas: schema design, query patterns, hooks untuk business logic, performance tips, security, dan anti-pattern yang harus dihindari. Plus bagian Transaction Safety yang saya tambahkan setelah sadar banyak operasi multi-step yang WAJIB atomic — ini pelajaran mahal dari production bug.

## Daftar Skenario

> Halaman ini di-generate otomatis dari file di `pages/scenarios/`. Tambah file `.md` baru dengan `category: scenarios`, dan halaman ini akan otomatis ter-update.

{% assign scenario_pages = site.pages | where: "category", "scenarios" | sort: "title" %}

<div class="doc-list">
{% for p in scenario_pages %}{% include doc-list-card.html p=p icon="book-open" %}{% endfor %}
</div>

## Pola Umum di Semua Skenario

Setiap dokumen skenario mengikuti struktur konsisten:

1. **Pendahuluan** — kapan BangronDB cocok untuk skenario ini, kapan tidak.
2. **Schema Design** — contoh `setSchema()` lengkap dengan type, validation, unique, regex.
3. **Query Patterns** — aggregation pipeline khas untuk skenario (e.g. stock card, sales pipeline, AR aging, P&L).
4. **Hooks & Events** — auto business logic via `beforeInsert`/`afterUpdate` hooks.
5. **Performance & Indexing** — searchable fields, cursor streaming, EXPLAIN, bulk insert.
6. **Security** — encryption field sensitif, blind index PII, RBAC, audit log.
7. **Relasi & Populate** — cross-collection populate, foreign key emulation via hooks.
8. **Transaction Safety** — `$conn->beginTransaction()` untuk operasi multi-step yang WAJIB atomic.
9. **Anti-Pattern** — jebakan umum yang harus dihindari.
10. **Referensi** — cross-link ke dokumen lain.

## Stack yang Dipakai

Semua skenario menggunakan kombinasi:

- **BangronDB** — embedded document database (SQLite backend, MongoDB-style API).
- **Flight PHP** — micro-framework paling cocok untuk BangronDB karena keduanya embedded.
- **PHP 8.1+** — sesuai requirement `composer.json` BangronDB.

Untuk integrasi dengan framework lain (Slim, Lumen, Vanilla PHP), lihat [Integrations](/docs/integrations/).

## Kombinasi Skenario

Skenario-skenario ini dirancang untuk saling terintegrasi. Contoh kombinasi:

- **ERP + CRM** — sales team kerja di CRM, transaksi terekam di ERP.
- **ERP + SCM** — purchasing di SCM, inventory & accounting di ERP.
- **ERP + HRIS** — payroll di HRIS otomatis generate journal entry di ERP finance.
- **ERP + POS** — outlet POS sync transaksi ke ERP sebagai sales orders.
- **All + Auth & ACL** — modul auth terpisah melayani semua modul lain.

Untuk panduan integrasi multi-modul, lihat [Modular Architecture](/docs/modular-architecture/).

## Pilih Skenario Mana?

Tergantung project apa yang sedang Anda bangun:

- **Bangun aplikasi bisnis terintegrasi**: mulai dari [ERP](/docs/scenarios/erp/). Ini paling lengkap, jadi foundation-nya bagus.
- **Fokus ke sales & marketing**: [CRM](/docs/scenarios/crm/). Leads, opportunities, sales pipeline.
- **Manajemen rantai pasok & logistics**: [SCM](/docs/scenarios/scm/). Purchase orders, goods receipt, stock movements.
- **Aplikasi HR & payroll**: [HRIS](/docs/scenarios/hris/). Paling banyak encryption stuff (gaji, KTP, NPWP wajib encrypt).
- **Retail / F&B dengan multi-outlet**: [POS](/docs/scenarios/pos/). Offline-first, sync ke central.
- **Setup otentikasi & authorization**: [Auth & ACL](/docs/scenarios/auth-acl/). Modul terpisah, pakai setCustomConfig untuk ACL.
- **Sudah punya multiple modul & mau integrasikan**: [Modular Architecture](/docs/modular-architecture/). Strategi cross-database + hooks.

## Lihat Juga

- [Getting Started](/docs/getting-started/) — instalasi dan konsep dasar.
- [Integrations](/docs/integrations/) — integrasi per framework PHP.
- [Hook Patterns](/docs/hook-patterns/) — 8 pola hook lanjutan.
- [Security](/docs/security/) — encryption, blind index, key rotation.

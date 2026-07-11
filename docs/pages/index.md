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

## Daftar Lengkap Dokumentasi

### Dasar

| Dokumen | Isi | Link |
|---------|-----|------|
| **Getting Started** | Instalasi, konsep dasar, quick start CRUD | [/docs/getting-started/](/docs/getting-started/) |
| **Features** | Sorotan fitur: API MongoDB-style, schema, encryption, hooks, aggregation | [/docs/features/](/docs/features/) |
| **Query Operators** | Daftar lengkap operator: `$gt`, `$in`, `$regex`, fuzzy search, dll | [/docs/query-operators/](/docs/query-operators/) |
| **Schema & Metadata** | Flat schema format, validasi, type aliases, metadata UI | [/docs/schema-metadata-guide/](/docs/schema-metadata-guide/) |
| **Hook Patterns** | 8 pola praktis hook: auto-timestamp, audit log, ACL, cascade delete | [/docs/hook-patterns/](/docs/hook-patterns/) |

### Keamanan & API

| Dokumen | Isi | Link |
|---------|-----|------|
| **Security** | Encryption AES-256-GCM, blind index, key rotation, security auditor | [/docs/security/](/docs/security/) |
| **API Reference** | Referensi lengkap method Client, Database, Collection, Cursor | [/docs/api-reference/](/docs/api-reference/) |

### Integrasi

| Dokumen | Isi | Link |
|---------|-----|------|
| **Integrations** | Daftar integrasi micro-framework: Flight, Slim, Lumen, Vanilla PHP | [/docs/integrations/](/docs/integrations/) |

### Skenario Project

| Dokumen | Isi | Link |
|---------|-----|------|
| **Scenarios Index** | Daftar skenario implementasi: ERP, CRM, SCM, HRIS, POS, Auth | [/docs/scenarios/](/docs/scenarios/) |
| **ERP** | Inventory, sales, accounting, journal entries | [/docs/scenarios/erp/](/docs/scenarios/erp/) |
| **CRM** | Leads, opportunities, sales pipeline, activities | [/docs/scenarios/crm/](/docs/scenarios/crm/) |
| **SCM** | Purchase orders, goods receipt, shipments, stock movements | [/docs/scenarios/scm/](/docs/scenarios/scm/) |
| **HRIS** | Employees, attendance, leave, payroll, PII encryption | [/docs/scenarios/hris/](/docs/scenarios/hris/) |
| **POS** | Cash drawer, transactions, multi-outlet sync, offline-first | [/docs/scenarios/pos/](/docs/scenarios/pos/) |
| **Auth & ACL** | Login, register, JWT, ACL per-collection via setCustomConfig | [/docs/scenarios/auth-acl/](/docs/scenarios/auth-acl/) |

### Arsitektur

| Dokumen | Isi | Link |
|---------|-----|------|
| **Modular Architecture** | Integrasi ERP + CRM + SCM + HRIS + POS dengan cross-database + hooks | [/docs/modular-architecture/](/docs/modular-architecture/) |

### Lainnya

| Dokumen | Isi | Link |
|---------|-----|------|
| **Roadmap** | Roadmap pengembangan BangronDB — fitur yang sudah ada & akan datang | [/docs/roadmap/](/docs/roadmap/) |

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

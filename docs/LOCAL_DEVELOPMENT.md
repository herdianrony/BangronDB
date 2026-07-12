# Local Development

Panduan setup environment untuk edit dokumentasi BangronDB di komputer local. Setelah setup, Anda bisa preview perubahan sebelum push ke GitHub.

## Prerequisites

### 1. Install Ruby

BangronDB docs pakai Jekyll (static site generator dari Ruby). Anda butuh Ruby 3.x.

**macOS** (pakai Homebrew):
```bash
brew install ruby
# Tambah ke PATH (tambah ke ~/.zshrc atau ~/.bashrc):
echo 'export PATH="/opt/homebrew/opt/ruby/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

**Windows** (pakai RubyInstaller):
- Download dari https://rubyinstaller.org/
- Pilih versi dengan DevKit (mis. "Ruby 3.3.x-1 with Devkit")
- Saat install, centang "Add Ruby executables to your PATH"

**Linux** (Ubuntu/Debian):
```bash
sudo apt update
sudo apt install ruby-full build-essential zlib1g-dev
```

Verifikasi:
```bash
ruby --version    # harusnya 3.x
gem --version
```

### 2. Install Bundler

Bundler adalah package manager untuk Ruby (seperti Composer untuk PHP).

```bash
gem install bundler
```

## Setup Project

### 1. Clone Repository

```bash
git clone https://github.com/herdianrony/BangronDB.git
cd BangronDB/docs
```

### 2. Install Dependencies

```bash
bundle install
```

Perintah ini baca `Gemfile` dan install Jekyll + semua plugins. Cukup jalankan sekali.

### 3. Jalankan Local Server

```bash
bundle exec jekyll serve
```

Output akan menunjukkan URL local (biasanya `http://127.0.0.1:4000/BangronDB/`):

```
Configuration file: /path/to/BangronDB/docs/_config.yml
            Source: /path/to/BangronDB/docs
       Destination: /path/to/BangronDB/docs/_site
      Incremental: disabled. Enable with --incremental
     Generating... 
                    done in 2.1 seconds.
 Auto-regeneration: enabled for '/path/to/BangronDB/docs'
    Server address: http://127.0.0.1:4000/BangronDB/
  Server running... press ctrl-c to stop.
```

### 4. Preview di Browser

Buka `http://127.0.0.1:4000/BangronDB/` di browser. Anda akan lihat site yang sama dengan live, tapi jalan di local.

## Edit Dokumentasi

### Struktur Folder

```
docs/
├── _config.yml              # Konfigurasi Jekyll (rarely edit)
├── _layouts/
│   ├── home.html            # Layout landing page
│   └── doc.html             # Layout doc pages (sidebar + TOC)
├── _includes/
│   ├── head.html            # <head> dengan SEO + fonts + CSS
│   ├── navbar.html          # Navbar reusable
│   ├── footer.html          # Footer reusable
│   ├── sidebar.html         # Sidebar navigasi (grouped by category)
│   └── doc-list-card.html   # Card component untuk list pages
├── assets/
│   ├── css/style.css        # CSS utama (dark theme + doc styles)
│   └── js/main.js           # JS untuk navbar, TOC, copy button, dll
├── index.html               # Landing page (root)
└── pages/
    ├── index.html           # /docs/ — index semua dokumentasi
    ├── getting-started.md   # /docs/getting-started/
    ├── features.md          # /docs/features/
    ├── ...                  # (8 doc pages di root)
    ├── integrations/
    │   ├── index.html       # /docs/integrations/
    │   ├── flight.md        # /docs/integrations/flight/
    │   ├── slim.md
    │   ├── lumen.md
    │   └── vanilla-php.md
    ├── scenarios/
    │   ├── index.html       # /docs/scenarios/
    │   ├── erp.md           # /docs/scenarios/erp/
    │   ├── crm.md
    │   └── ...              # (6 scenario files)
    └── modular-architecture.md
```

### Edit Halaman Existing

Cukup edit file `.md` atau `.html` di `pages/`. Jekyll auto-reload — setelah save, browser refresh otomatis menunjukkan perubahan.

Contoh: edit `pages/getting-started.md`, save, dan browser akan reload otomatis.

### Tambah Halaman Baru

#### 1. Buat file `.md` baru di folder yang sesuai:

```bash
# Contoh: tambah doc di kategori Dasar
touch pages/new-feature.md
```

#### 2. Tambah front-matter:

```yaml
---
layout: doc
title: "New Feature"
description: "Deskripsi singkat tentang feature baru."
permalink: /docs/new-feature/
toc: true
edit_on_github: true
category: dasar          # dasar | keamanan-api | integrations | scenarios | arsitektur | lainnya
---

# New Feature

Konten di sini...
```

#### 3. Save file. Jekyll auto-reload.

#### 4. Halaman otomatis muncul di:
- `/docs/new-feature/` — page baru
- `/docs/` — index auto-update (di section sesuai category)
- `/docs/integrations/` atau `/docs/scenarios/` — kalau category cocok

**Tidak perlu edit index page manual** — semua auto-generate dari front-matter.

#### 5. Tambah ke sidebar (manual):

Edit `_includes/sidebar.html`, tambah `<li>` di section yang sesuai:

```html
<li><a href="{{ '/docs/new-feature/' | relative_url }}" class="{% if page.url contains '/docs/new-feature/' %}active{% endif %}">New Feature</a></li>
```

> **Catatan:** Sidebar masih manual karena Jekyll Liquid tidak bisa generate sidebar dinamis. Index pages sudah auto, tapi sidebar perlu tambah manual.

### Edit CSS

Edit `assets/css/style.css`. Jekyll auto-reload setelah save.

### Edit Layout

Edit file di `_layouts/`. Perubahan apply ke semua pages yang pakai layout tersebut.

### Edit Sidebar / Navbar / Footer

Edit file di `_includes/`. Perubahan apply ke semua pages.

## Troubleshooting

### Error: `bundle: command not found`

Install Bundler dulu:
```bash
gem install bundler
```

### Error: `jekyll: command not found`

Jalankan via bundler:
```bash
bundle exec jekyll serve
```

Bukan `jekyll serve` langsung.

### Error: `Permission denied - bind(2)`

Port 4000 sudah dipakai. Pakai port lain:
```bash
bundle exec jekyll serve --port 4001
```

### Error: `Liquid Exception: ...`

Syntax error di Liquid template. Cek log output — biasanya menunjukkan file dan baris yang error.

### Perubahan tidak muncul

- Hard refresh browser (Ctrl+Shift+R / Cmd+Shift+R)
- Cek apakah Jekyll masih running
- Restart Jekyll: Ctrl+C, lalu `bundle exec jekyll serve` lagi

### Layout / styling hancur

- Cek apakah CSS file di-load: buka DevTools → Network → cari `style.css`
- Cek apakah `_layouts/` dan `_includes/` ada di folder yang benar
- Cek apakah front-matter `layout:` benar (`doc` atau `home`)

## Tips Development

### 1. Pakai `--livereload` untuk auto-refresh browser

```bash
bundle exec jekyll serve --livereload
```

Browser akan auto-refresh setiap kali file di-edit. Tidak perlu manual refresh.

### 2. Pakai `--incremental` untuk build lebih cepat

```bash
bundle exec jekyll serve --incremental --livereload
```

Hanya re-build file yang berubah. Berguna untuk site besar.

### 3. Cek build production sebelum push

```bash
bundle exec jekyll build
```

Build ke `_site/` folder. Cek apakah ada error atau warning.

### 4. Cek broken links

```bash
bundle exec htmlproofer ./_site --disable-external
```

Cek semua internal links. Install dulu: `gem install html-proofer`.

## Push ke GitHub

Setelah puas dengan perubahan:

```bash
# Dari root repo (bukan docs/)
cd /path/to/BangronDB

# Stage perubahan
git add docs/

# Commit
git commit -m "docs: update <apa yang diubah>"

# Push
git push origin master
```

GitHub Actions otomatis build dan deploy ke GitHub Pages. Cek di:
https://github.com/herdianrony/BangronDB/actions

Setelah workflow selesai (~1-2 menit), perubahan live di:
https://herdianrony.github.io/BangronDB/

## Quick Reference

```bash
# Setup (sekali saja)
cd BangronDB/docs
bundle install

# Run local server (setiap kali mau edit)
bundle exec jekyll serve --livereload

# Buka browser
open http://127.0.0.1:4000/BangronDB/

# Push ke GitHub
cd ..
git add docs/
git commit -m "docs: ..."
git push origin master
```

import { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useTheme } from '../context/ThemeContext';
import { GithubIcon } from '../components/icons';
import { navigation, findNavItem, getAdjacentPages } from '../data/sidebar';
import pages from '../data/pages';
import {
  Sun, Moon, Database, Search, Menu, X, FileQuestion,
  Copy, Check, Info, AlertTriangle, Lightbulb, Pin,
  ChevronRight, ChevronLeft, ArrowLeft,
} from 'lucide-react';

/* ════════════════════════════════════════════════════
   Lightweight Markdown-ish renderer
   Supports: h2, h3, p, code blocks, tables, blockquotes, lists, inline code/bold/links
   ════════════════════════════════════════════════════ */

interface TocEntry { id: string; text: string; level: number }

function slugify(s: string) {
  return s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}

function parseBody(md: string) {
  const lines = md.split('\n');
  const elements: React.ReactNode[] = [];
  const toc: TocEntry[] = [];
  let i = 0;
  let key = 0;

  const renderInline = (text: string): React.ReactNode => {
    const parts: React.ReactNode[] = [];
    const regex = /(\*\*(.*?)\*\*)|(`([^`]+)`)|(\[([^\]]+)\]\(([^)]+)\))/g;
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = regex.exec(text)) !== null) {
      if (match.index > lastIndex) {
        parts.push(text.slice(lastIndex, match.index));
      }
      if (match[1]) {
        parts.push(<strong key={`b${match.index}`} className="font-semibold text-inherit">{match[2]}</strong>);
      } else if (match[3]) {
        parts.push(<code key={`c${match.index}`} className="px-1.5 py-0.5 rounded text-[13px] font-mono bg-gray-100 text-brand-700 dark:bg-gray-800 dark:text-brand-400">{match[4]}</code>);
      } else if (match[5]) {
        parts.push(<a key={`a${match.index}`} href={match[7]} target="_blank" rel="noopener noreferrer" className="text-brand-600 dark:text-brand-400 hover:underline">{match[6]}</a>);
      }
      lastIndex = match.index + match[0].length;
    }
    if (lastIndex < text.length) parts.push(text.slice(lastIndex));
    return parts.length === 1 && typeof parts[0] === 'string' ? parts[0] : <>{parts}</>;
  };

  while (i < lines.length) {
    const line = lines[i];

    // Blank line
    if (line.trim() === '') { i++; continue; }

    // Headings
    if (line.startsWith('## ')) {
      const text = line.slice(3).trim();
      const id = slugify(text);
      toc.push({ id, text, level: 2 });
      elements.push(
        <h2 key={key++} id={id} className="text-xl sm:text-2xl font-bold mt-10 mb-4 pb-2 border-b border-gray-200 dark:border-gray-800 scroll-mt-20">
          {renderInline(text)}
        </h2>
      );
      i++; continue;
    }
    if (line.startsWith('### ')) {
      const text = line.slice(4).trim();
      const id = slugify(text);
      toc.push({ id, text, level: 3 });
      elements.push(
        <h3 key={key++} id={id} className="text-lg font-semibold mt-8 mb-3 scroll-mt-20">{renderInline(text)}</h3>
      );
      i++; continue;
    }

    // Code block
    if (line.trim().startsWith('```')) {
      const lang = line.trim().slice(3);
      const codeLines: string[] = [];
      i++;
      while (i < lines.length && !lines[i].trim().startsWith('```')) {
        codeLines.push(lines[i]);
        i++;
      }
      i++; // skip closing ```
      elements.push(
        <div key={key++} className="my-4 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-800 shadow-sm">
          {lang && (
            <div className="flex items-center justify-between px-4 py-2 text-xs font-mono bg-gray-100 dark:bg-gray-800/80 text-gray-500 dark:text-gray-500 border-b border-gray-200 dark:border-gray-800">
              <span>{lang}</span>
              <CopyButton text={codeLines.join('\n')} />
            </div>
          )}
          <pre className="p-4 overflow-x-auto bg-gray-50 dark:bg-gray-900 text-[13px] leading-relaxed">
            <code className="font-mono text-gray-800 dark:text-gray-300">{codeLines.join('\n')}</code>
          </pre>
        </div>
      );
      continue;
    }

    // Table
    if (line.includes('|') && lines[i + 1]?.includes('---')) {
      const headers = line.split('|').map(s => s.trim()).filter(Boolean);
      i += 2; // skip header + separator
      const rows: string[][] = [];
      while (i < lines.length && lines[i].includes('|')) {
        rows.push(lines[i].split('|').map(s => s.trim()).filter(Boolean));
        i++;
      }
      elements.push(
        <div key={key++} className="my-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-gray-50 dark:bg-gray-800/50">
                {headers.map((h, hi) => (
                  <th key={hi} className="text-left px-4 py-2.5 font-semibold border-b border-gray-200 dark:border-gray-800">
                    {renderInline(h)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, ri) => (
                <tr key={ri} className="border-b last:border-0 border-gray-100 dark:border-gray-800/50 hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                  {row.map((cell, ci) => (
                    <td key={ci} className="px-4 py-2 text-gray-600 dark:text-gray-400">
                      {renderInline(cell)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      );
      continue;
    }

    // Blockquote
    if (line.startsWith('> ')) {
      const bqLines: string[] = [];
      while (i < lines.length && lines[i].startsWith('> ')) {
        bqLines.push(lines[i].slice(2));
        i++;
      }
      const text = bqLines.join(' ');
      // Detect type
      let variant: 'info' | 'warning' | 'tip' = 'info';
      let IconComp = Info;
      if (text.startsWith('**Warning:**') || text.startsWith('**warning:**')) { variant = 'warning'; IconComp = AlertTriangle; }
      else if (text.startsWith('**Tip:**') || text.startsWith('**tip:**')) { variant = 'tip'; IconComp = Lightbulb; }
      else if (text.startsWith('**Catatan:**') || text.startsWith('**Penting:**')) { variant = 'warning'; IconComp = Pin; }

      const colors = {
        info: 'border-blue-300 bg-blue-50 dark:border-blue-500/30 dark:bg-blue-500/5 text-blue-900 dark:text-blue-300',
        warning: 'border-amber-300 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/5 text-amber-900 dark:text-amber-300',
        tip: 'border-emerald-300 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/5 text-emerald-900 dark:text-emerald-300',
      };

      const iconColors = {
        info: 'text-blue-500 dark:text-blue-400',
        warning: 'text-amber-500 dark:text-amber-400',
        tip: 'text-emerald-500 dark:text-emerald-400',
      };

      elements.push(
        <div key={key++} className={`my-4 px-4 py-3 rounded-lg border-l-4 text-sm leading-relaxed flex items-start gap-2.5 ${colors[variant]}`}>
          <IconComp className={`w-4 h-4 mt-0.5 shrink-0 ${iconColors[variant]}`} />
          <div>{renderInline(text)}</div>
        </div>
      );
      continue;
    }

    // Unordered list
    if (line.startsWith('- ')) {
      const items: string[] = [];
      while (i < lines.length && lines[i].startsWith('- ')) {
        items.push(lines[i].slice(2));
        i++;
      }
      elements.push(
        <ul key={key++} className="my-3 space-y-1.5 list-disc list-inside text-gray-700 dark:text-gray-300">
          {items.map((item, ii) => <li key={ii} className="text-sm leading-relaxed">{renderInline(item)}</li>)}
        </ul>
      );
      continue;
    }

    // Paragraph
    elements.push(
      <p key={key++} className="my-3 text-[15px] leading-relaxed text-gray-700 dark:text-gray-300">
        {renderInline(line)}
      </p>
    );
    i++;
  }

  return { elements, toc };
}

/* Copy button */
function CopyButton({ text }: { text: string }) {
  const [copied, setCopied] = useState(false);
  const handleCopy = () => {
    navigator.clipboard.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };
  return (
    <button onClick={handleCopy} className="hover:text-gray-700 dark:hover:text-gray-300 transition text-xs inline-flex items-center gap-1">
      {copied
        ? <><Check className="w-3 h-3 text-emerald-500" /> <span className="text-emerald-500">Copied</span></>
        : <><Copy className="w-3 h-3" /> Copy</>
      }
    </button>
  );
}

/* ════════════════════════════════════════════════════
   Docs Page Component
   ════════════════════════════════════════════════════ */
export default function Docs() {
  const { slug = 'getting-started' } = useParams<{ slug: string }>();
  const navigate = useNavigate();
  const { theme, toggle } = useTheme();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [search, setSearch] = useState('');
  const mainRef = useRef<HTMLDivElement>(null);

  const page = pages[slug];
  const navItem = findNavItem(slug);
  const { prev, next } = getAdjacentPages(slug);

  const { elements, toc } = useMemo(() => {
    if (!page) return { elements: [], toc: [] as TocEntry[] };
    return parseBody(page.body);
  }, [page]);

  // Scroll to top on page change
  useEffect(() => {
    mainRef.current?.scrollTo(0, 0);
    window.scrollTo(0, 0);
    setSidebarOpen(false);
  }, [slug]);

  // Search results
  const searchResults = useMemo(() => {
    if (!search.trim()) return [];
    const q = search.toLowerCase();
    return Object.entries(pages)
      .filter(([, p]) => p.title.toLowerCase().includes(q) || p.body.toLowerCase().includes(q))
      .map(([s, p]) => ({ slug: s, title: p.title }))
      .slice(0, 8);
  }, [search]);

  const closeSidebar = useCallback(() => setSidebarOpen(false), []);

  const dark = theme === 'dark';

  if (!page) {
    return (
      <div className={`min-h-screen flex items-center justify-center ${dark ? 'bg-gray-950 text-gray-100' : 'bg-white text-gray-900'}`}>
        <div className="text-center">
          <FileQuestion className="w-16 h-16 mx-auto mb-4 text-gray-400" />
          <h1 className="text-2xl font-bold mb-2">Halaman tidak ditemukan</h1>
          <Link to="/docs/getting-started" className="text-brand-600 dark:text-brand-400 hover:underline inline-flex items-center gap-1.5">
            <ArrowLeft className="w-4 h-4" /> Kembali ke Getting Started
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className={`min-h-screen ${dark ? 'bg-gray-950 text-gray-100' : 'bg-white text-gray-900'}`}>
      {/* ── Header ── */}
      <header className={`fixed top-0 inset-x-0 z-50 h-14 border-b backdrop-blur-sm ${dark ? 'bg-gray-950/90 border-gray-800' : 'bg-white/90 border-gray-200'}`}>
        <div className="flex items-center h-full px-4 gap-4">
          {/* Mobile hamburger */}
          <button onClick={() => setSidebarOpen(o => !o)} className={`lg:hidden w-8 h-8 flex items-center justify-center rounded-md ${dark ? 'hover:bg-gray-800' : 'hover:bg-gray-100'}`}>
            {sidebarOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
          </button>

          {/* Logo */}
          <Link to="/" className="flex items-center gap-2 font-bold text-base shrink-0">
            <Database className="w-5 h-5 text-brand-600 dark:text-brand-400" />
            <span className="hidden sm:inline">Bangron<span className="text-brand-600 dark:text-brand-400">DB</span></span>
          </Link>

          {/* Search */}
          <div className="relative flex-1 max-w-md mx-auto">
            <input
              type="text"
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Cari dokumentasi…"
              className={`w-full h-9 pl-9 pr-4 rounded-lg text-sm outline-none transition ${dark
                ? 'bg-gray-800 border border-gray-700 focus:border-brand-500 text-gray-200 placeholder-gray-500'
                : 'bg-gray-100 border border-gray-200 focus:border-brand-500 text-gray-700 placeholder-gray-400'
              }`}
            />
            <Search className={`absolute left-3 top-2.5 w-4 h-4 ${dark ? 'text-gray-500' : 'text-gray-400'}`} />

            {/* Search results dropdown */}
            {searchResults.length > 0 && (
              <div className={`absolute top-11 inset-x-0 rounded-lg shadow-xl border overflow-hidden z-50 ${dark ? 'bg-gray-900 border-gray-700' : 'bg-white border-gray-200'}`}>
                {searchResults.map(r => (
                  <button
                    key={r.slug}
                    onClick={() => { navigate(`/docs/${r.slug}`); setSearch(''); }}
                    className={`w-full text-left px-4 py-2.5 text-sm transition ${dark ? 'hover:bg-gray-800' : 'hover:bg-gray-50'}`}
                  >
                    {r.title}
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Right actions */}
          <div className="flex items-center gap-2 shrink-0">
            <a href="https://github.com/herdianrony/BangronDB" target="_blank" rel="noopener noreferrer" className={`w-8 h-8 flex items-center justify-center rounded-md transition ${dark ? 'hover:bg-gray-800 text-gray-400' : 'hover:bg-gray-100 text-gray-500'}`}>
              <GithubIcon className="w-4 h-4" />
            </a>
            <button onClick={toggle} className={`w-8 h-8 flex items-center justify-center rounded-md transition ${dark ? 'hover:bg-gray-800' : 'hover:bg-gray-100'}`}>
              {dark ? <Sun className="w-4 h-4 text-yellow-400" /> : <Moon className="w-4 h-4 text-gray-500" />}
            </button>
          </div>
        </div>
      </header>

      {/* ── Mobile overlay ── */}
      {sidebarOpen && <div className="fixed inset-0 z-30 bg-black/40 lg:hidden" onClick={closeSidebar} />}

      {/* ── Sidebar ── */}
      <aside className={`fixed top-14 bottom-0 left-0 z-40 w-64 overflow-y-auto transition-transform lg:translate-x-0 border-r ${
        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
      } ${dark ? 'bg-gray-950 border-gray-800' : 'bg-white border-gray-200'}`}>
        <nav className="p-4 pb-20">
          {navigation.map(group => (
            <div key={group.label} className="mb-6">
              <h3 className={`text-[11px] font-bold uppercase tracking-wider mb-2 px-2 ${dark ? 'text-gray-500' : 'text-gray-400'}`}>
                {group.label}
              </h3>
              <ul className="space-y-0.5">
                {group.items.map(item => {
                  const active = item.slug === slug;
                  return (
                    <li key={item.slug}>
                      <Link
                        to={`/docs/${item.slug}`}
                        onClick={closeSidebar}
                        className={`block px-3 py-1.5 rounded-md text-sm transition-colors ${
                          active
                            ? `font-semibold ${dark ? 'text-brand-400 bg-brand-500/10' : 'text-brand-700 bg-brand-50'}`
                            : `${dark ? 'text-gray-400 hover:text-gray-200 hover:bg-gray-800/50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'}`
                        }`}
                      >
                        {item.title}
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          ))}
        </nav>
      </aside>

      {/* ── Main content ── */}
      <main ref={mainRef} className="lg:ml-64 pt-14 min-h-screen">
        <div className="flex">
          {/* Article */}
          <article className="flex-1 min-w-0 px-6 sm:px-10 py-10 max-w-3xl animate-fadein">
            {/* Breadcrumb */}
            <div className={`flex items-center gap-1.5 text-xs mb-6 ${dark ? 'text-gray-500' : 'text-gray-400'}`}>
              <Link to="/" className="hover:text-brand-600 dark:hover:text-brand-400">Home</Link>
              <ChevronRight className="w-3 h-3" />
              <span>{navItem?.group}</span>
              <ChevronRight className="w-3 h-3" />
              <span className={dark ? 'text-gray-300' : 'text-gray-700'}>{page.title}</span>
            </div>

            {/* Title */}
            <h1 className="text-3xl sm:text-4xl font-extrabold tracking-tight mb-2">{page.title}</h1>
            <p className={`text-base mb-8 ${dark ? 'text-gray-400' : 'text-gray-500'}`}>{page.description}</p>
            <hr className={`mb-8 ${dark ? 'border-gray-800' : 'border-gray-200'}`} />

            {/* Rendered content */}
            <div className="prose-docs">
              {elements}
            </div>

            {/* Prev / Next */}
            <div className={`flex justify-between items-stretch mt-16 pt-8 border-t gap-4 ${dark ? 'border-gray-800' : 'border-gray-200'}`}>
              {prev ? (
                <Link to={`/docs/${prev.slug}`} className={`flex-1 p-4 rounded-lg border text-left transition group ${dark ? 'border-gray-800 hover:border-gray-700 hover:bg-gray-900/50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'}`}>
                  <div className={`text-xs mb-1 flex items-center gap-1 ${dark ? 'text-gray-500' : 'text-gray-400'}`}>
                    <ChevronLeft className="w-3 h-3" /> Sebelumnya
                  </div>
                  <div className="text-sm font-semibold group-hover:text-brand-600 dark:group-hover:text-brand-400 transition">{prev.title}</div>
                </Link>
              ) : <div className="flex-1" />}
              {next ? (
                <Link to={`/docs/${next.slug}`} className={`flex-1 p-4 rounded-lg border text-right transition group ${dark ? 'border-gray-800 hover:border-gray-700 hover:bg-gray-900/50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'}`}>
                  <div className={`text-xs mb-1 flex items-center justify-end gap-1 ${dark ? 'text-gray-500' : 'text-gray-400'}`}>
                    Selanjutnya <ChevronRight className="w-3 h-3" />
                  </div>
                  <div className="text-sm font-semibold group-hover:text-brand-600 dark:group-hover:text-brand-400 transition">{next.title}</div>
                </Link>
              ) : <div className="flex-1" />}
            </div>

            {/* Edit on GitHub */}
            <div className="mt-6 text-center">
              <a href="https://github.com/herdianrony/BangronDB" target="_blank" rel="noopener noreferrer" className={`inline-flex items-center gap-1.5 text-xs transition ${dark ? 'text-gray-600 hover:text-gray-400' : 'text-gray-400 hover:text-gray-600'}`}>
                <GithubIcon className="w-3.5 h-3.5" /> Edit halaman ini di GitHub
              </a>
            </div>

            {/* Spacer */}
            <div className="h-20" />
          </article>

          {/* ── Table of Contents (desktop) ── */}
          {toc.length > 0 && (
            <aside className="hidden xl:block w-56 shrink-0 py-10 pr-6">
              <div className="sticky top-24">
                <h4 className={`text-[11px] font-bold uppercase tracking-wider mb-3 ${dark ? 'text-gray-500' : 'text-gray-400'}`}>
                  Daftar Isi
                </h4>
                <ul className={`space-y-1 border-l ${dark ? 'border-gray-800' : 'border-gray-200'}`}>
                  {toc.map(entry => (
                    <li key={entry.id}>
                      <a
                        href={`#${entry.id}`}
                        className={`block text-xs leading-relaxed transition py-0.5 ${
                          entry.level === 3 ? 'pl-6' : 'pl-3'
                        } ${dark
                          ? 'text-gray-500 hover:text-gray-300'
                          : 'text-gray-400 hover:text-gray-700'
                        }`}
                      >
                        {entry.text}
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            </aside>
          )}
        </div>
      </main>
    </div>
  );
}

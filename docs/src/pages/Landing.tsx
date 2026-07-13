import { Link } from 'react-router-dom';
import { useTheme } from '../context/ThemeContext';
import { GithubIcon } from '../components/icons';
import {
  Sun, Moon, Database, FileText, Shield, Zap, CheckCircle,
  BarChart3, Workflow, Trash2, Clock, Link2, Cpu, Search,
  ShieldCheck, Heart, CheckCheck, XCircle
} from 'lucide-react';

export default function Landing() {
  const { theme, toggle } = useTheme();
  const dark = theme === 'dark';

  const features = [
    { icon: FileText, title: 'MongoDB-style API', desc: 'find(), insert(), update(), remove(), aggregate() — API familiar.', color: 'text-blue-500' },
    { icon: Shield, title: 'AES-256-GCM', desc: 'Enkripsi dokumen + key rotation + searchable fields via blind index.', color: 'text-emerald-500' },
    { icon: Zap, title: 'Dual Query Strategy', desc: 'SQL-first via json_extract, fallback ke PHP-side untuk query kompleks.', color: 'text-amber-500' },
    { icon: CheckCircle, title: 'Schema Validation', desc: 'type, enum, regex, min/max, unique constraint — menjaga integritas data.', color: 'text-violet-500' },
    { icon: BarChart3, title: 'Aggregation', desc: '$match, $group, $sort, $limit, $skip, $project, $count, $unset.', color: 'text-rose-500' },
    { icon: Workflow, title: 'Hooks Lifecycle', desc: 'before/after insert, update, remove — bangun ACL, audit, timestamp.', color: 'text-teal-500' },
    { icon: Trash2, title: 'Soft Deletes', desc: 'Hapus aman dengan restore, withTrashed(), onlyTrashed().', color: 'text-orange-500' },
    { icon: Clock, title: 'TTL Expiration', desc: 'Dokumen auto-expire. Sempurna untuk session dan cache.', color: 'text-cyan-500' },
    { icon: Link2, title: 'Relationships', desc: 'Populate relasi antar-collection dan antar-database.', color: 'text-indigo-500' },
    { icon: Cpu, title: 'Cursor Streaming', desc: 'PHP Generator untuk dataset besar — memori tetap konstan.', color: 'text-fuchsia-500' },
    { icon: Search, title: 'Explain Query', desc: 'Query plan analysis, optimization suggestions, health metrics.', color: 'text-lime-500' },
    { icon: ShieldCheck, title: 'Security Hardened', desc: 'Closure-only, field validation, ReDoS protection, strict types.', color: 'text-red-500' },
  ];

  return (
    <div className={`min-h-screen ${dark ? 'bg-gray-950 text-gray-100' : 'bg-white text-gray-900'}`}>
      {/* ── Top Nav ── */}
      <header className={`border-b ${dark ? 'border-gray-800 bg-gray-950/80' : 'border-gray-200 bg-white/80'} backdrop-blur-sm sticky top-0 z-50`}>
        <div className="max-w-6xl mx-auto flex items-center justify-between px-6 h-16">
          <Link to="/" className="flex items-center gap-2.5 font-bold text-lg">
            <Database className="w-6 h-6 text-brand-600 dark:text-brand-400" />
            <span>Bangron<span className="text-brand-600 dark:text-brand-400">DB</span></span>
          </Link>
          <nav className="hidden sm:flex items-center gap-6 text-sm">
            <Link to="/docs/getting-started" className={`hover:text-brand-600 dark:hover:text-brand-400 transition ${dark ? 'text-gray-400' : 'text-gray-600'}`}>Docs</Link>
            <a href="https://github.com/herdianrony/BangronDB" target="_blank" rel="noopener noreferrer" className={`hover:text-brand-600 dark:hover:text-brand-400 transition flex items-center gap-1.5 ${dark ? 'text-gray-400' : 'text-gray-600'}`}>
              <GithubIcon className="w-4 h-4" /> GitHub
            </a>
            <button onClick={toggle} className={`w-8 h-8 rounded-lg flex items-center justify-center transition ${dark ? 'bg-gray-800 hover:bg-gray-700 text-yellow-400' : 'bg-gray-100 hover:bg-gray-200 text-gray-600'}`}>
              {dark ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
            </button>
          </nav>
          <button onClick={toggle} className={`sm:hidden w-8 h-8 rounded-lg flex items-center justify-center ${dark ? 'bg-gray-800 text-yellow-400' : 'bg-gray-100 text-gray-600'}`}>
            {dark ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
          </button>
        </div>
      </header>

      {/* ── Hero ── */}
      <section className="relative overflow-hidden">
        <div className={`absolute inset-0 ${dark ? 'bg-gradient-to-b from-brand-900/20 to-transparent' : 'bg-gradient-to-b from-brand-50 to-transparent'}`} />
        <div className="relative max-w-4xl mx-auto px-6 pt-20 pb-16 text-center">
          {/* Badges */}
          <div className="flex flex-wrap justify-center gap-2 mb-8">
            <span className={`inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full ${dark ? 'bg-brand-500/10 text-brand-400 ring-1 ring-brand-500/20' : 'bg-brand-50 text-brand-700 ring-1 ring-brand-200'}`}>
              <span className="w-2 h-2 rounded-full bg-emerald-500 inline-block" /> MIT License
            </span>
            <span className={`inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full ${dark ? 'bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/20' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'}`}>
              <CheckCheck className="w-3.5 h-3.5" /> 376 Tests Passed
            </span>
            <span className={`inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full ${dark ? 'bg-blue-500/10 text-blue-400 ring-1 ring-blue-500/20' : 'bg-blue-50 text-blue-700 ring-1 ring-blue-200'}`}>
              PHP 8.1+
            </span>
          </div>

          <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.1] mb-6">
            MongoDB-style API.{' '}
            <br className="hidden sm:block" />
            <span className="bg-gradient-to-r from-brand-600 to-brand-400 bg-clip-text text-transparent">
              SQLite simplicity.
            </span>
          </h1>

          <p className={`text-lg sm:text-xl max-w-2xl mx-auto leading-relaxed mb-10 ${dark ? 'text-gray-400' : 'text-gray-600'}`}>
            Document database fleksibel untuk PHP — tanpa setup server, tanpa konfigurasi rumit.
            Cukup <code className={`px-1.5 py-0.5 rounded text-sm font-mono ${dark ? 'bg-gray-800 text-brand-400' : 'bg-gray-100 text-brand-600'}`}>composer&nbsp;require</code> dan mulai coding.
          </p>

          {/* CTA */}
          <div className="flex flex-wrap justify-center gap-3 mb-10">
            <Link to="/docs/getting-started" className="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm shadow-lg shadow-brand-600/20 transition hover:shadow-brand-600/30">
              Baca Dokumentasi
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
            </Link>
            <a href="https://github.com/herdianrony/BangronDB" target="_blank" rel="noopener noreferrer" className={`inline-flex items-center gap-2 px-6 py-3 rounded-lg font-semibold text-sm border transition ${dark ? 'border-gray-700 hover:bg-gray-800 text-gray-300' : 'border-gray-300 hover:bg-gray-50 text-gray-700'}`}>
              <GithubIcon className="w-4 h-4" /> GitHub
            </a>
          </div>

          {/* Install */}
          <div className={`inline-flex items-center gap-3 px-5 py-3 rounded-xl font-mono text-sm ${dark ? 'bg-gray-900 border border-gray-800 text-gray-300' : 'bg-gray-50 border border-gray-200 text-gray-700'}`}>
            <span className={dark ? 'text-gray-500' : 'text-gray-400'}>$</span>
            composer require <span className="text-brand-600 dark:text-brand-400 font-semibold">herdianrony/bangrondb</span>
          </div>
        </div>
      </section>

      {/* ── Code Preview ── */}
      <section className="max-w-4xl mx-auto px-6 pb-20">
        <div className={`rounded-xl overflow-hidden border ${dark ? 'border-gray-800 bg-gray-900' : 'border-gray-200 bg-gray-950'} shadow-2xl`}>
          <div className="flex items-center gap-2 px-4 py-3 border-b border-gray-800">
            <div className="flex gap-1.5">
              <div className="w-3 h-3 rounded-full bg-red-500/80" />
              <div className="w-3 h-3 rounded-full bg-yellow-500/80" />
              <div className="w-3 h-3 rounded-full bg-green-500/80" />
            </div>
            <span className="text-xs text-gray-500 font-mono ml-2">quick-start.php</span>
          </div>
          <pre className="p-5 text-sm leading-relaxed overflow-x-auto">
            <code className="text-gray-300 font-mono">{`use BangronDB\\Client;

$client = new Client(__DIR__ . '/data');
$client->createDB('app');
$client->createCollection('app', 'users');
$users = $client->selectCollection('app', 'users');

`}<span className="text-gray-500">// Insert</span>{`
$userId = $users->`}<span className="text-green-400">insert</span>{`([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
]);

`}<span className="text-gray-500">// Find</span>{`
$user = $users->`}<span className="text-blue-400">findOne</span>{`(['_id' => $userId]);

`}<span className="text-gray-500">// Update with operators</span>{`
$users->`}<span className="text-amber-400">update</span>{`(['_id' => $userId], [
    '$set' => ['role' => 'admin'],
]);

`}<span className="text-gray-500">// Aggregation</span>{`
$stats = $users->`}<span className="text-purple-400">aggregate</span>{`([
    ['$match' => ['status' => 'active']],
    ['$group' => ['_id' => '$role', 'count' => ['$sum' => 1]]],
]);`}</code>
          </pre>
        </div>
      </section>

      {/* ── Features Grid ── */}
      <section className={`py-20 ${dark ? 'bg-gray-900/50' : 'bg-gray-50'}`}>
        <div className="max-w-6xl mx-auto px-6">
          <h2 className="text-2xl sm:text-3xl font-bold text-center mb-4">Sorotan Fitur</h2>
          <p className={`text-center mb-12 max-w-xl mx-auto ${dark ? 'text-gray-400' : 'text-gray-600'}`}>
            Semua yang Anda butuhkan untuk document database yang powerful dan aman.
          </p>

          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {features.map((f, i) => (
              <div key={i} className={`p-5 rounded-xl border transition hover:shadow-md ${dark ? 'bg-gray-900 border-gray-800 hover:border-gray-700' : 'bg-white border-gray-200 hover:border-gray-300'}`}>
                <f.icon className={`w-6 h-6 mb-3 ${f.color}`} />
                <h3 className="font-semibold mb-1">{f.title}</h3>
                <p className={`text-sm leading-relaxed ${dark ? 'text-gray-400' : 'text-gray-600'}`}>{f.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Architecture ── */}
      <section className="py-20">
        <div className="max-w-4xl mx-auto px-6">
          <h2 className="text-2xl sm:text-3xl font-bold text-center mb-4">Arsitektur</h2>
          <p className={`text-center mb-12 max-w-xl mx-auto ${dark ? 'text-gray-400' : 'text-gray-600'}`}>
            Desain modular dengan trait-based architecture.
          </p>

          <div className={`rounded-xl border p-6 sm:p-8 font-mono text-sm ${dark ? 'bg-gray-900 border-gray-800' : 'bg-gray-50 border-gray-200'}`}>
            <pre className={`whitespace-pre-wrap leading-relaxed ${dark ? 'text-gray-300' : 'text-gray-700'}`}>{`src/
├── Client.php              ← Entry point: mengelola banyak database
├── Database.php            ← Satu file .bangron / :memory:
├── Collection.php          ← Tabel dokumen (menggunakan traits)
├── Cursor.php              ← Lazy query result iterator
├── QueryExecutor.php       ← Eksekusi SQL via PDO prepared statements
├── UtilArrayQuery.php      ← PHP-side query engine (fallback)
├── Config.php              ← Global configuration
├── Enums/                  ← Enum: HookEvent, IdMode
├── Exceptions/             ← Typed exceptions
├── Security/               ← SecurityAuditor (utilitas opsional)
└── Traits/                 ← Fitur-fitur collection
    ├── QueryBuilderTrait.php
    ├── EncryptionTrait.php
    ├── SchemaValidationTrait.php
    ├── HooksTrait.php
    ├── SearchableFieldsTrait.php
    ├── IdGeneratorTrait.php
    ├── SoftDeleteTrait.php
    ├── TtlTrait.php
    ├── ChangeTrackingTrait.php
    └── ConfigurationPersistenceTrait.php`}</pre>
          </div>
        </div>
      </section>

      {/* ── Use Cases ── */}
      <section className={`py-20 ${dark ? 'bg-gray-900/50' : 'bg-gray-50'}`}>
        <div className="max-w-4xl mx-auto px-6 text-center">
          <h2 className="text-2xl sm:text-3xl font-bold mb-4">Kapan Menggunakan BangronDB?</h2>
          <p className={`mb-10 max-w-xl mx-auto ${dark ? 'text-gray-400' : 'text-gray-600'}`}>
            Dirancang khusus untuk skenario tertentu.
          </p>

          <div className="grid sm:grid-cols-2 gap-4 text-left">
            <div className={`p-5 rounded-xl border ${dark ? 'bg-emerald-500/5 border-emerald-500/20' : 'bg-emerald-50 border-emerald-200'}`}>
              <h3 className={`font-semibold mb-3 flex items-center gap-2 ${dark ? 'text-emerald-400' : 'text-emerald-700'}`}>
                <CheckCircle className="w-5 h-5" /> Cocok untuk
              </h3>
              <ul className={`space-y-2 text-sm ${dark ? 'text-gray-300' : 'text-gray-700'}`}>
                <li className="flex items-start gap-2"><CheckCheck className="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" /> Deploy per-customer (appliance model)</li>
                <li className="flex items-start gap-2"><CheckCheck className="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" /> Embedded di sistem existing</li>
                <li className="flex items-start gap-2"><CheckCheck className="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" /> Project SMB (ERP, CRM, POS, HRIS)</li>
                <li className="flex items-start gap-2"><CheckCheck className="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" /> Prototyping dan MVP</li>
                <li className="flex items-start gap-2"><CheckCheck className="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" /> Desktop / CLI application</li>
              </ul>
            </div>
            <div className={`p-5 rounded-xl border ${dark ? 'bg-red-500/5 border-red-500/20' : 'bg-red-50 border-red-200'}`}>
              <h3 className={`font-semibold mb-3 flex items-center gap-2 ${dark ? 'text-red-400' : 'text-red-700'}`}>
                <XCircle className="w-5 h-5" /> Bukan untuk
              </h3>
              <ul className={`space-y-2 text-sm ${dark ? 'text-gray-300' : 'text-gray-700'}`}>
                <li className="flex items-start gap-2"><XCircle className="w-4 h-4 text-red-500 mt-0.5 shrink-0" /> SaaS multi-tenant skala besar</li>
                <li className="flex items-start gap-2"><XCircle className="w-4 h-4 text-red-500 mt-0.5 shrink-0" /> Concurrent write-heavy workloads</li>
                <li className="flex items-start gap-2"><XCircle className="w-4 h-4 text-red-500 mt-0.5 shrink-0" /> Cluster / replication scenario</li>
                <li className={`text-xs pl-6 ${dark ? 'text-gray-500' : 'text-gray-400'}`}>Gunakan PostgreSQL + RLS untuk ini</li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      {/* ── CTA ── */}
      <section className="py-20">
        <div className="max-w-3xl mx-auto px-6 text-center">
          <h2 className="text-2xl sm:text-3xl font-bold mb-4">Siap memulai?</h2>
          <p className={`mb-8 ${dark ? 'text-gray-400' : 'text-gray-600'}`}>
            Install dalam hitungan detik, baca dokumentasi, dan mulai bangun aplikasi.
          </p>
          <div className="flex flex-wrap justify-center gap-3">
            <Link to="/docs/getting-started" className="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm transition">
              Mulai Sekarang
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
            </Link>
            <Link to="/docs/examples" className={`inline-flex items-center gap-2 px-6 py-3 rounded-lg font-semibold text-sm border transition ${dark ? 'border-gray-700 hover:bg-gray-800 text-gray-300' : 'border-gray-300 hover:bg-gray-50 text-gray-700'}`}>
              Lihat 24 Contoh
            </Link>
          </div>
        </div>
      </section>

      {/* ── Footer ── */}
      <footer className={`border-t py-10 ${dark ? 'border-gray-800' : 'border-gray-200'}`}>
        <div className="max-w-6xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
          <p className={`text-sm flex items-center gap-1.5 ${dark ? 'text-gray-500' : 'text-gray-400'}`}>
            &copy; {new Date().getFullYear()} BangronDB — MIT License.
            Made with <Heart className="w-3.5 h-3.5 text-red-500 fill-red-500" /> by{' '}
            <a href="https://github.com/herdianrony" target="_blank" rel="noopener noreferrer" className="text-brand-600 dark:text-brand-400 hover:underline">Herdian Rony</a>
          </p>
          <div className="flex items-center gap-4">
            <a href="https://github.com/herdianrony/BangronDB" target="_blank" rel="noopener noreferrer" className={`text-sm hover:text-brand-600 dark:hover:text-brand-400 transition ${dark ? 'text-gray-500' : 'text-gray-400'}`}>
              GitHub
            </a>
            <a href="https://packagist.org/packages/herdianrony/bangrondb" target="_blank" rel="noopener noreferrer" className={`text-sm hover:text-brand-600 dark:hover:text-brand-400 transition ${dark ? 'text-gray-500' : 'text-gray-400'}`}>
              Packagist
            </a>
          </div>
        </div>
      </footer>
    </div>
  );
}

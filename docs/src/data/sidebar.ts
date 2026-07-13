export interface NavItem {
  slug: string;
  title: string;
  group: string;
}

export interface NavGroup {
  label: string;
  items: NavItem[];
}

export const navigation: NavGroup[] = [
  {
    label: 'Memulai',
    items: [
      { slug: 'getting-started', title: 'Getting Started', group: 'Memulai' },
      { slug: 'installation', title: 'Instalasi', group: 'Memulai' },
      { slug: 'concepts', title: 'Konsep Dasar', group: 'Memulai' },
    ],
  },
  {
    label: 'Panduan',
    items: [
      { slug: 'crud', title: 'CRUD Operations', group: 'Panduan' },
      { slug: 'query-operators', title: 'Query Operators', group: 'Panduan' },
      { slug: 'aggregation', title: 'Aggregation Pipeline', group: 'Panduan' },
      { slug: 'pagination', title: 'Pagination & Sorting', group: 'Panduan' },
    ],
  },
  {
    label: 'Fitur',
    items: [
      { slug: 'encryption', title: 'Encryption', group: 'Fitur' },
      { slug: 'schema', title: 'Schema Validation', group: 'Fitur' },
      { slug: 'hooks', title: 'Hooks', group: 'Fitur' },
      { slug: 'soft-deletes', title: 'Soft Deletes', group: 'Fitur' },
      { slug: 'ttl', title: 'TTL Expiration', group: 'Fitur' },
      { slug: 'relationships', title: 'Relationships', group: 'Fitur' },
      { slug: 'indexing', title: 'Indexing & Performance', group: 'Fitur' },
      { slug: 'streaming', title: 'Cursor Streaming', group: 'Fitur' },
      { slug: 'transactions', title: 'Transactions', group: 'Fitur' },
      { slug: 'configuration', title: 'Configuration', group: 'Fitur' },
    ],
  },
  {
    label: 'Referensi',
    items: [
      { slug: 'api-client', title: 'Client API', group: 'Referensi' },
      { slug: 'api-database', title: 'Database API', group: 'Referensi' },
      { slug: 'api-collection', title: 'Collection API', group: 'Referensi' },
      { slug: 'api-cursor', title: 'Cursor API', group: 'Referensi' },
      { slug: 'security', title: 'Security', group: 'Referensi' },
      { slug: 'examples', title: 'Contoh Lengkap', group: 'Referensi' },
    ],
  },
];

export function findNavItem(slug: string): NavItem | undefined {
  for (const g of navigation) {
    const found = g.items.find(i => i.slug === slug);
    if (found) return found;
  }
  return undefined;
}

export function getAdjacentPages(slug: string) {
  const flat = navigation.flatMap(g => g.items);
  const idx = flat.findIndex(i => i.slug === slug);
  return {
    prev: idx > 0 ? flat[idx - 1] : null,
    next: idx < flat.length - 1 ? flat[idx + 1] : null,
  };
}

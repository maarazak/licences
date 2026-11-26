import {
  siApplemusic,
  siAppletv,
  siAvira,
  siClaude,
  siCoursera,
  siCrunchyroll,
  siCursor,
  siDeepseek,
  siDuolingo,
  siElevenlabs,
  siExpressvpn,
  siFigma,
  siFramer,
  siGmail,
  siGooglegemini,
  siGrammarly,
  siHeadspace,
  siInstagram,
  siJetbrains,
  siLinear,
  siMiro,
  siNetflix,
  siNordvpn,
  siNotion,
  siPerplexity,
  siPicsart,
  siPosthog,
  siProtonvpn,
  siRailway,
  siReplit,
  siSpotify,
  siYoutube,
  siX,
} from 'simple-icons';
import type { LogoSpec } from '@/Types/logo';

type Icon = { path: string; hex: string };
type Brand = { key: string; label: string; test: RegExp; icon?: Icon; hex?: string; mark?: string };

const BRANDS: Brand[] = [
  { key: 'claude', label: 'Claude', test: /claude/i, icon: siClaude },
  { key: 'chatgpt', label: 'ChatGPT', test: /chatgpt|openai|codex/i, hex: '10A37F', mark: 'G' },
  { key: 'gemini', label: 'Gemini', test: /gemini|google ai|pixel/i, icon: siGooglegemini },
  { key: 'deepseek', label: 'DeepSeek', test: /deepseek|kimi/i, icon: siDeepseek },
  { key: 'grok', label: 'Grok', test: /grok/i, icon: siX },
  { key: 'elevenlabs', label: 'ElevenLabs', test: /eleven/i, icon: siElevenlabs },
  { key: 'perplexity', label: 'Perplexity', test: /perplexity/i, icon: siPerplexity },
  { key: 'cursor', label: 'Cursor', test: /cursor/i, icon: siCursor },
  { key: 'canva', label: 'Canva', test: /canva/i, hex: '00B4C8', mark: 'C' },
  { key: 'capcut', label: 'CapCut', test: /capcut/i, hex: '15181F', mark: 'C' },
  { key: 'adobe', label: 'Adobe', test: /adobe/i, hex: 'E8281E', mark: 'A' },
  { key: 'figma', label: 'Figma', test: /figma/i, icon: siFigma },
  { key: 'framer', label: 'Framer', test: /framer/i, icon: siFramer },
  { key: 'picsart', label: 'Picsart', test: /picsart/i, icon: siPicsart },
  { key: 'miro', label: 'Miro', test: /miro/i, icon: siMiro },
  { key: 'lovable', label: 'Lovable', test: /lovable/i, hex: 'F0457E', mark: 'L' },
  { key: 'replit', label: 'Replit', test: /replit/i, icon: siReplit },
  { key: 'railway', label: 'Railway', test: /railway/i, icon: siRailway },
  { key: 'jetbrains', label: 'JetBrains', test: /jetbrains/i, icon: siJetbrains },
  { key: 'linear', label: 'Linear', test: /linear/i, icon: siLinear },
  { key: 'posthog', label: 'PostHog', test: /posthog/i, icon: siPosthog },
  { key: 'arize', label: 'Arize', test: /arize/i, hex: '7B5CF0', mark: 'A' },
  { key: 'netflix', label: 'Netflix', test: /netflix/i, icon: siNetflix },
  { key: 'primevideo', label: 'Prime Video', test: /prime video/i, hex: '0F79AF', mark: 'P' },
  { key: 'applemusic', label: 'Apple Music', test: /apple music/i, icon: siApplemusic },
  { key: 'appletv', label: 'Apple TV', test: /apple tv/i, icon: siAppletv },
  { key: 'crunchyroll', label: 'Crunchyroll', test: /crunchyroll/i, icon: siCrunchyroll },
  { key: 'shahid', label: 'Shahid', test: /shahid/i, hex: '0B9B6B', mark: 'S' },
  { key: 'nordvpn', label: 'NordVPN', test: /nord ?vpn/i, icon: siNordvpn },
  { key: 'expressvpn', label: 'ExpressVPN', test: /express ?vpn/i, icon: siExpressvpn },
  { key: 'protonvpn', label: 'Proton VPN', test: /proton/i, icon: siProtonvpn },
  { key: 'avira', label: 'Avira', test: /avira/i, icon: siAvira },
  { key: 'hma', label: 'HMA', test: /\bhma\b/i, hex: 'E5A500', mark: 'H' },
  { key: 'notion', label: 'Notion', test: /notion/i, icon: siNotion },
  { key: 'grammarly', label: 'Grammarly', test: /grammarly/i, icon: siGrammarly },
  { key: 'office', label: 'Microsoft', test: /office|outlook|hotmail|windows/i, hex: 'D83B01', mark: 'O' },
  { key: 'granola', label: 'Granola', test: /granola/i, hex: '3F9D4B', mark: 'G' },
  { key: 'coursera', label: 'Coursera', test: /coursera/i, icon: siCoursera },
  { key: 'duolingo', label: 'Duolingo', test: /duolingo/i, icon: siDuolingo },
  { key: 'headspace', label: 'Headspace', test: /headspace/i, icon: siHeadspace },
  { key: 'scribd', label: 'Scribd', test: /scribd/i, hex: '1B7C86', mark: 'S' },
  { key: 'linkedin', label: 'LinkedIn', test: /linkedin/i, hex: '0A66C2', mark: 'in' },
  { key: 'gmail', label: 'Gmail', test: /gmail/i, icon: siGmail },
  { key: 'instagram', label: 'Instagram', test: /instagram/i, icon: siInstagram },
  { key: 'gamma', label: 'Gamma', test: /gamma/i, hex: '6D5BFF', mark: 'G' },
  { key: 'pangram', label: 'Pangram', test: /pangram/i, hex: '1F2937', mark: 'P' },
  { key: 'spotify', label: 'Spotify', test: /spotify/i, icon: siSpotify },
  { key: 'youtube', label: 'YouTube', test: /youtube/i, icon: siYoutube },
];

const LOCAL_BRAND_FILES = new Set([
  'adobe.png',
  'arize.png',
  'canva.jpg',
  'capcut.png',
  'chatgpt.png',
  'gamma.jpg',
  'granola.png',
  'linkedin.png',
  'lovable.jpg',
  'office.png',
  'pangram.png',
  'primevideo.png',
  'scribd.png',
  'shahid.png',
]);

const FALLBACK_HEX = ['0D5C4A', '2563EB', '7C3AED', 'DB2777', 'EA580C', '0891B2', '4F46E5', '65A30D'];

function hash(value: string): number {
  let h = 0;
  for (let i = 0; i < value.length; i++) h = (h * 31 + value.charCodeAt(i)) | 0;
  return Math.abs(h);
}

function initials(name: string): string {
  const words = name.replace(/[^\p{L}\p{N} ]/gu, ' ').trim().split(/\s+/);
  return ((words[0]?.[0] ?? '?') + (words.length > 1 ? (words[1]?.[0] ?? '') : '')).slice(0, 2).toUpperCase();
}

/** Logo d'un produit : l'image personnalisée d'un produit manuel si elle existe, sinon le logo de marque d'après son nom. */
export function productLogo(p: { custom_image?: string | null; customImage?: string | null; name?: string | null; display_name?: string | null; title?: string | null }): LogoSpec {
  const image = p.custom_image || p.customImage;
  if (image) {
    return { kind: 'image', src: image, label: p.title || p.display_name || p.name || '', tint: '#0D5C4A', known: true };
  }
  return logoFor(p.name, p.display_name);
}

export function logoFor(...names: Array<string | null | undefined>): LogoSpec {
  const name = names.filter(Boolean).join(' ');
  const brand = BRANDS.find((b) => b.test.test(name));
  if (!brand) {
    const hex = FALLBACK_HEX[hash(name) % FALLBACK_HEX.length];
    return {
      kind: 'mono',
      letters: initials(names.find(Boolean) ?? '?'),
      hex: `#${hex}`,
      label: name,
      tint: `#${hex}`,
      known: false,
    };
  }

  const file = ['svg', 'png', 'webp', 'jpg']
    .map((ext) => `${brand.key}.${ext}`)
    .find((f) => LOCAL_BRAND_FILES.has(f));

  const hex = `#${brand.icon?.hex ?? brand.hex ?? '0D5C4A'}`;

  if (file) {
    return { kind: 'image', src: `/brands/${file}`, label: brand.label, tint: hex, known: true };
  }
  if (brand.icon) {
    return { kind: 'icon', path: brand.icon.path, hex, label: brand.label, tint: hex, known: true };
  }
  return {
    kind: 'mono',
    letters: brand.mark ?? brand.label.charAt(0).toUpperCase(),
    hex,
    label: brand.label,
    tint: hex,
    known: true,
  };
}

export function distinctBrands(logos: LogoSpec[], limit = 16): LogoSpec[] {
  const counts = new Map<string, { logo: LogoSpec; n: number }>();
  for (const logo of logos) {
    if (!logo || !logo.known) continue;
    const entry = counts.get(logo.label);
    if (entry) entry.n++;
    else counts.set(logo.label, { logo, n: 1 });
  }
  return [...counts.values()]
    .sort((a, b) => b.n - a.n)
    .slice(0, limit)
    .map((e) => e.logo);
}

export function diversify<T extends { logo: LogoSpec }>(items: T[], limit: number): T[] {
  const seen = new Set<string>();
  const picked: T[] = [];
  for (const item of items) {
    if (picked.length >= limit) break;
    if (seen.has(item.logo.label)) continue;
    seen.add(item.logo.label);
    picked.push(item);
  }
  for (const item of items) {
    if (picked.length >= limit) break;
    if (!picked.includes(item)) picked.push(item);
  }
  return picked;
}

export interface BrandInfo {
  label: string;
  image?: string;
  hex: string;
  tint: string;
  mark?: string;
}

const KNOWN_BRANDS: Array<{
  test: RegExp;
  label: string;
  image?: string;
  hex: string;
  tint: string;
  mark?: string;
}> = [
  { test: /chatgpt|openai|codex/i, label: 'ChatGPT', image: '/brands/chatgpt.png', hex: '#10a37f', tint: '#10a37f' },
  { test: /canva/i, label: 'Canva', image: '/brands/canva.jpg', hex: '#00c4cc', tint: '#00c4cc' },
  { test: /capcut/i, label: 'CapCut', image: '/brands/capcut.png', hex: '#15181f', tint: '#15181f' },
  { test: /claude/i, label: 'Claude', hex: '#d97706', tint: '#d97706', mark: 'C' },
  { test: /adobe/i, label: 'Adobe', image: '/brands/adobe.png', hex: '#e8281e', tint: '#e8281e' },
  { test: /prime video|amazon/i, label: 'Prime Video', image: '/brands/primevideo.png', hex: '#00a8e1', tint: '#00a8e1' },
  { test: /netflix/i, label: 'Netflix', hex: '#e50914', tint: '#e50914', mark: 'N' },
  { test: /linkedin/i, label: 'LinkedIn', image: '/brands/linkedin.png', hex: '#0a66c2', tint: '#0a66c2' },
  { test: /office|microsoft/i, label: 'Office 365', image: '/brands/office.png', hex: '#d83b01', tint: '#d83b01' },
  { test: /gamma/i, label: 'Gamma', image: '/brands/gamma.jpg', hex: '#6d5bff', tint: '#6d5bff' },
  { test: /lovable/i, label: 'Lovable', image: '/brands/lovable.jpg', hex: '#f0457e', tint: '#f0457e' },
  { test: /granola/i, label: 'Granola', image: '/brands/granola.png', hex: '#3f9d4b', tint: '#3f9d4b' },
  { test: /shahid/i, label: 'Shahid', image: '/brands/shahid.png', hex: '#0b9b6b', tint: '#0b9b6b' },
  { test: /scribd/i, label: 'Scribd', image: '/brands/scribd.png', hex: '#1b7c86', tint: '#1b7c86' },
  { test: /pangram/i, label: 'Pangram', image: '/brands/pangram.png', hex: '#1f2937', tint: '#1f2937' },
  { test: /arize/i, label: 'Arize', image: '/brands/arize.png', hex: '#7b5cf0', tint: '#7b5cf0' },
  { test: /gemini|google/i, label: 'Google Gemini', hex: '#4285f4', tint: '#4285f4', mark: 'G' },
  { test: /cursor/i, label: 'Cursor', hex: '#1e293b', tint: '#1e293b', mark: 'C' },
  { test: /deepseek/i, label: 'DeepSeek', hex: '#1e40af', tint: '#1e40af', mark: 'D' },
  { test: /nordvpn/i, label: 'NordVPN', hex: '#4687ff', tint: '#4687ff', mark: 'N' },
  { test: /expressvpn/i, label: 'ExpressVPN', hex: '#da3940', tint: '#da3940', mark: 'E' },
  { test: /proton/i, label: 'Proton', hex: '#6d4aff', tint: '#6d4aff', mark: 'P' },
  { test: /spotify/i, label: 'Spotify', hex: '#1ed760', tint: '#1ed760', mark: 'S' },
  { test: /youtube/i, label: 'YouTube', hex: '#ff0000', tint: '#ff0000', mark: 'Y' },
  { test: /figma/i, label: 'Figma', hex: '#f24e1e', tint: '#f24e1e', mark: 'F' },
  { test: /notion/i, label: 'Notion', hex: '#000000', tint: '#000000', mark: 'N' },
];

export function resolveBrand(productName: string): BrandInfo {
  for (const b of KNOWN_BRANDS) {
    if (b.test.test(productName)) {
      return {
        label: b.label,
        image: b.image,
        hex: b.hex,
        tint: b.tint,
        mark: b.mark || b.label.charAt(0),
      };
    }
  }

  // Fallback monogram
  const clean = productName.replace(/[^a-zA-Z0-9 ]/g, '').trim();
  const letters = clean.slice(0, 2).toUpperCase() || 'LC';
  return {
    label: productName,
    hex: '#0d5c4a',
    tint: '#0d5c4a',
    mark: letters,
  };
}

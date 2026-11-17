export type LogoSpec =
  | { kind: 'icon'; path: string; hex: string; label: string; tint: string; known: boolean }
  | { kind: 'image'; src: string; label: string; tint: string; known: boolean }
  | { kind: 'mono'; letters: string; hex: string; label: string; tint: string; known: boolean };

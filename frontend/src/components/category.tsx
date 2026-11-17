import { BookOpen, Briefcase, CodeXml, Palette, Play, ShieldCheck, Sparkles, Tag, UserRound, type LucideIcon } from "lucide-react";

/** Icône et dégradé propres à chaque rayon de la boutique. */
const STYLES: Record<string, { gradient: string; Icon: LucideIcon }> = {
  "IA et assistants": { gradient: "from-violet-500 to-fuchsia-500", Icon: Sparkles },
  "Création et design": { gradient: "from-pink-500 to-orange-400", Icon: Palette },
  "Développement": { gradient: "from-sky-500 to-indigo-500", Icon: CodeXml },
  "Bureautique": { gradient: "from-emerald-500 to-teal-500", Icon: Briefcase },
  "Apprentissage": { gradient: "from-amber-400 to-orange-500", Icon: BookOpen },
  "Divertissement": { gradient: "from-rose-500 to-red-500", Icon: Play },
  "VPN et sécurité": { gradient: "from-slate-600 to-slate-800", Icon: ShieldCheck },
  "Comptes et réseaux": { gradient: "from-cyan-500 to-blue-500", Icon: UserRound },
};

export function categoryStyle(name: string) {
  return STYLES[name] ?? { gradient: "from-brand to-brand-bright", Icon: Tag };
}

export function CategoryBubble({ name, size = "md" }: { name: string; size?: "sm" | "md" }) {
  const { gradient, Icon } = categoryStyle(name);
  return (
    <span className={`flex shrink-0 items-center justify-center bg-gradient-to-br text-white shadow-[0_10px_20px_-10px_rgb(11_18_32/0.5)] ${gradient} ${size === "sm" ? "size-8 rounded-lg" : "size-12 rounded-2xl"}`}>
      <Icon className={size === "sm" ? "size-4" : "size-6"} aria-hidden="true" />
    </span>
  );
}

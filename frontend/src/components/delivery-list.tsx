import { CopyButton } from "./forms";

/** Accès livrés d'une commande. Ne rendre ce composant qu'au propriétaire (ou à un administrateur). */
export function DeliveryList({ items }: { items: string[] }) {
  return (
    <ul className="space-y-2">
      {items.map((item, index) => (
        <li key={index} className="flex items-start justify-between gap-3 rounded-lg border border-line bg-canvas p-3">
          <code className="min-w-0 flex-1 font-mono text-sm leading-relaxed whitespace-pre-wrap [overflow-wrap:anywhere]">{item}</code>
          <CopyButton value={item} />
        </li>
      ))}
    </ul>
  );
}

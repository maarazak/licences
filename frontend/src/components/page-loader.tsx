/** Cercle de chargement plein bloc, affiché pendant le changement de page (fichiers loading.tsx). */
export function PageLoader({ label }: { label: string }) {
  return (
    <div role="status" className="flex min-h-[50vh] flex-col items-center justify-center gap-4 py-16 text-muted">
      <span aria-hidden="true" className="spinner spinner-lg" />
      <span className="text-sm font-semibold">{label}</span>
    </div>
  );
}

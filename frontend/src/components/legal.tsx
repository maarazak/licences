/** Mise en page commune des pages légales. */
export function LegalPage({ title, updated, updatedLabel, sections }: { title: string; updated: string; updatedLabel: string; sections: Array<{ title: string; paragraphs: string[] }> }) {
  return (
    <div className="container-page max-w-3xl py-10">
      <h1 className="h1">{title}</h1>
      <p className="mt-2 text-xs text-muted">
        {updatedLabel} : {updated}
      </p>
      <div className="mt-8 space-y-8">
        {sections.map((section) => (
          <section key={section.title}>
            <h2 className="h3 text-lg">{section.title}</h2>
            {section.paragraphs.map((p) => (
              <p key={p} className="mt-2 text-sm leading-relaxed text-ink/90">
                {p}
              </p>
            ))}
          </section>
        ))}
      </div>
    </div>
  );
}

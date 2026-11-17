#!/usr/bin/env python3
"""Recense les textes français à traduire (clés de `t()` et messages renvoyés par le serveur)."""
import re, pathlib, json, sys
root = pathlib.Path("src")
LIT = r'"((?:[^"\\\n]|\\.)*)"'
keys = {}

def add(text, where):
    text = text.replace('\\"', '"').replace("\\'", "'").replace("\\n", "\n")
    if text.strip():
        keys.setdefault(text, where)

files = [p for p in root.rglob("*") if p.suffix in (".ts", ".tsx") and "i18n-en" not in p.name and "/admin/" not in str(p) and "server/actions/admin" not in str(p) and "services/admin" not in str(p)]
for p in files:
    src = p.read_text()
    for m in re.finditer(r'\bt\(\s*' + LIT, src):
        add(m.group(1), str(p))
    for m in re.finditer(r"\bt\(\s*'((?:[^'\\\n]|\\.)*)'", src):
        add(m.group(1), str(p))

# messages renvoyés au client par le serveur (affichés via FormMessage / ActionButton)
server = ["src/server/actions/shop.ts", "src/server/actions/auth.ts", "src/server/validators.ts", "src/server/security/rate-limit.ts"]
for f in server:
    src = pathlib.Path(f).read_text()
    for m in re.finditer(LIT, src):
        s = m.group(1)
        if re.search(r"[A-Za-zÀ-ÿ]{3,} [A-Za-zÀ-ÿ']", s) and not s.startswith("@/") and not s.startswith("use "):
            add(s, f)
src = pathlib.Path("src/server/services/orders.ts").read_text()
for m in re.finditer(r'CheckoutError\(\s*(?:`([^`]*)`|"((?:[^"\\\n]|\\.)*)")', src):
    add(m.group(1) or m.group(2), "orders.ts")
for f in ["src/server/services/tickets.ts", "src/server/services/wallet.ts"]:
    pass

# constantes d'affichage
def lits(path, start=None, end=None):
    s = pathlib.Path(path).read_text()
    if start: s = s[s.index(start):]
    if end: s = s[:s.index(end)]
    for m in re.finditer(LIT, s):
        v = m.group(1)
        if re.search(r"[A-Za-zÀ-ÿ]{3,}", v): add(v, path)
lits("src/lib/enums.ts", "export const ORDER_STATUS_LABELS", "export const ORDER_STATUS_ADMIN_LABELS")
lits("src/lib/enums.ts", "export const TICKET_STATUS_LABELS", "export const TICKET_STATUS_TONES")
for m in re.finditer(r'name:\s*"([^"]+)"', pathlib.Path("src/lib/countries.ts").read_text()):
    add(m.group(1), "src/lib/countries.ts")
lits("src/lib/enums.ts", "export function deliveryKindLabel", None)
lits("src/lib/site.ts", "export const NAV_LINKS", None)
lits("src/lib/format.ts", "export function formatDuration", None)
lits("src/app/(site)/paiement/retour/page.tsx", "const MESSAGES", "const TONES")
lits("src/app/(site)/compte/achats/[id]/page.tsx", "const NOTES", "export default")
lits("src/app/(site)/compte/achats/page.tsx", "const TABS", "export default")
lits("src/app/(site)/boutique/page.tsx", "const SORTS", "export default")
lits("src/components/category.tsx", "const STYLES", "export function categoryStyle")
json.dump(keys, open("scripts/.i18n-keys.json", "w"), ensure_ascii=False, indent=0)
print(len(keys), "textes")

# Afdal — Mémoire projet

Plateforme B2B commandes textile · Symfony 7 + PostgreSQL (o2switch)

---

## Design System

### Style
**Trust & Authority** — badges, crédibilité, WCAG AAA
- Light ✓ Full · Dark ✓ Full
- Performance : excellent

### Couleurs

| Rôle | Hex | Variable CSS |
|------|-----|--------------|
| Primary | `#0F172A` | `--color-primary` |
| On Primary | `#FFFFFF` | `--color-on-primary` |
| Secondary | `#334155` | `--color-secondary` |
| Accent / CTA | `#0369A1` | `--color-accent` |
| Background | `#F8FAFC` | `--color-background` |
| Foreground | `#020617` | `--color-foreground` |
| Muted | `#E8ECF1` | `--color-muted` |
| Border | `#E2E8F0` | `--color-border` |
| Destructive | `#DC2626` | `--color-destructive` |
| Ring | `#0F172A` | `--color-ring` |

### Typographie
- **Titres** : Lexend (300–700)
- **Corps** : Source Sans 3 (300–700)
- Mood : corporate, trustworthy, accessible, readable

```css
@import url('https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&family=Source+Sans+3:wght@300;400;500;600;700&display=swap');
```

### À éviter
- Design playful / gradients purple-pink (AI vibes)
- Credentials / badges cachés
- Emojis en icônes (utiliser SVG : Heroicons, Lucide)

---

## Pages à concevoir

- [ ] Landing / login commerçants
- [ ] Catalogue produits textile (grille + filtres)
- [ ] Page produit + configuration commande
- [ ] Panier / checkout B2B
- [ ] Dashboard commandes (suivi, historique)
- [ ] Admin (stocks, clients)

---

## Décisions prises

<!-- On remplit au fur et à mesure -->

---

## Composants réutilisables

<!-- À lister quand on les crée -->

---

## Pré-livraison (checklist)

- [ ] Pas d'emojis comme icônes (SVG uniquement)
- [ ] `cursor-pointer` sur tous les éléments cliquables
- [ ] Transitions hover 150–300ms
- [ ] Contraste texte ≥ 4.5:1
- [ ] Focus visible (clavier)
- [ ] `prefers-reduced-motion` respecté
- [ ] Responsive : 375px · 768px · 1024px · 1440px

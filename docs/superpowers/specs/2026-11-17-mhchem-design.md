# Chemistry (`\ce`, `\pu`) in TeX input

## Why

Chemistry written in TeX uses mhchem: `\ce{2H2 + O2 -> 2H2O}`, `\ce{SO4^2-}`, `\pu{25 kJ/mol}`. KaTeX and MathJax
both ship it as an extension, so any tool that previews TeX with them writes chemistry this way. Generated teaching
material does it without being asked. The TeX reader does not know `\ce`: it draws the command name as text, then the
argument as italic letters with unsubscripted digits and `->` split into a minus and a greater-than, and notes
`\ce` as an unknown command. The TeX writer then writes `\text{\textbackslash{}ce} {H 2 O}`, so the source is lost too.

The first report came from a real document on the first day it met chemistry. Its formulas:

- `\ce{CH4 + 2O2 -> CO2 + 2H2O}`, `\ce{CaCO3 + 2HCl -> CaCl2 + CO2 + H2O}`, `\ce{4Fe + 3O2 -> 2Fe2O3}`
- `\ce{SO4^2-}`, `\ce{NH4+}`, `\ce{Ca^2+}`, `\ce{Fe(OH)3}`
- `\ce{N2 + 3H2 <=> 2NH3}`
- `\ce{NaCl(aq)}`

Success: chemistry that previewed correctly in KaTeX draws the same way in PDF, SVG and PNG, and reaches Word and
MathML as real equations.

Two pieces of work overlap and ship with it:

- **Source kept on output.** Writing `\ce{…}` back out needs the tree to remember the source. The same mechanism
  fixes a loss the TeX work left: `\boxed`, `\fbox`, `\color`, `\textcolor`, `\cancel`, `\bcancel`, `\xcancel` and
  `\tag` are read and not drawn, and the writer then drops them, so TeX in, TeX out silently loses them.
- **Prescripts.** Isotopes (`\ce{^{14}_{6}C}`) need scripts on the left of the base. The tree has none: the TeX reader
  notes `\prescript` and `\sideset` as unsupported, and the MathML reader draws `mmultiscripts` as its contents.
  Word has native prescripts (`m:sPre`).

## Decisions taken with the user

- The proper subset now, not a stopgap that only hides the command name.
- The bar is what generated chemistry actually contains, which is the common core of mhchem, not all of it.
- Source preservation and the prescript node are in this round. `\pu` is in.
- The TeX writer writes `\ce{…}` and `\pu{…}` back as read, not expanded. The same source mechanism carries
  `\boxed`, `\color` and the other not-drawn constructs.
- siunitx (`\SI`, `\si`, `\qty`, `\num`) is out. It is a candidate if it turns up in real input.

## Goals

1. The TeX reader reads `\ce{…}` and `\pu{…}` into ordinary nodes: upright element symbols, subscript counts,
   superscript charges, stretchy reaction arrows. No chemistry node types; Word, MathML, linear text and layout get
   chemistry through the nodes they already handle.
2. A construct outside the subset never breaks the formula: that piece is drawn as its source text, upright, with an
   `approximated` note naming the construct. The rest of the argument is still read.
3. A prescript node (or prescripts on `Scripts`; builder's call), drawn as IUPAC and MathML set them: right-aligned
   against the base. Read from `\prescript`, `\sideset`, MathML `mmultiscripts`/`mprescripts`, and mhchem isotopes.
   Written as MathML `mmultiscripts`, OMML `m:sPre`, TeX (see below), linear text.
4. The tree keeps the TeX source of the constructs it reads but does not draw (the list above) and of each `\ce`/`\pu`
   group. The TeX writer writes that source back. Other writers ignore it.
5. Builder: a way to make prescripts. Chemistry through the builder is `Math::tex('\ce{…}')`; no chemistry builder
   methods.
6. Docs: a chemistry section in the TeX input page of the math site, the gallery, README, changelog.

## Non-goals

- All of mhchem. Out: bond diagrams beyond `-`, `=`, `#` in a line; arrows with mhchem-only shapes beyond the list
  below; `\bond{…}`; the `\ce` "operator" forms for nuclear decay and orbital diagrams; oxidation states written as
  `^{II}` over the element (`\ce{Fe^{II}}` puts them as a superscript, as KaTeX does; that is fine); mhchem's font
  tweaks; `\cee` and `\cf` (the old aliases are cheap and may come along if trivial: builder's call).
- siunitx.
- Drawing color, boxes, strike-throughs, row and column rules, or equation numbers. They stay not drawn, with notes
  as today. Only their round trip through the TeX writer changes.
- Finding chemistry in plain text or HTML. As for TeX, the caller passes TeX.
- A chemistry linear-text or speech mode. Linear text writes the nodes as it does today (`H₂O`).

## Anti-goals

- A throw or unbounded work on any `\ce` argument. Input is untrusted; the reader's existing limits (input length,
  nesting, cell count) cover `\ce` and `\pu`, including math inside them (`\ce{$x$ H2O}`), and the fuzz run gains
  chemistry token soup.
- Hacks standing in for the prescript node (negative spaces, phantoms).
- Chemistry that previews one way in KaTeX and draws another way here without a note.
- Consumer-specific fixtures. The corpus is ordinary school and first-year university chemistry.

## The `\ce` subset

The argument is read as raw text (the cursor's raw group) by a small chemistry parser, separate from `TexReader`,
which hands `$…$` and `{…}` math back to the reader.

"Same as KaTeX" means the same drawing, not the same tree. mhchem's TeX expansion is drawing scaffolding
(`{\mathrm{H}{\vphantom{A}}_{\smash[t]{2}}\mathrm{O}}`, `{}+{}`, letter runs across elements such as `\mathrm{CaCO}`),
and the TeX reader is not taught those idioms. The tree is semantic instead: one upright identifier per element symbol
(`Ca`, `C`, `O`), counts as `Scripts` on the element or group, charges as superscripts, reactions as relations. Word,
MathML and linear text then get real element symbols, not letter runs. *(My call.)*

Rules come from the maintained upstream `mhchemParser` (4.x, Apache-2.0, used by MathJax). KaTeX bundles an older
port (3.3); where the two disagree on a corpus formula, the corpus records it and the KaTeX drawing is the one to
match, since that is the bar above. *(My call.)*

In the subset:

| Input | Meaning | Drawn as |
|---|---|---|
| `H2O`, `Fe2O3`, `CaCl2` | element symbols, counts | upright symbols, counts as subscripts |
| `Fe(OH)3`, `[Cu(NH3)4]^2+` | groups with counts | brackets upright, count as subscript after the group |
| `2H2O`, `1/2O2`, `$n$ H2O` | stoichiometric coefficients | upright number, fraction, or math; no gap after a number |
| `SO4^2-`, `Ca^{2+}`, `NH4+`, `Cl-`, `e-` | charges | superscript; `-` drawn as the minus sign U+2212 |
| `+` between species | plus | binary operator with spacing |
| `->`, `<-`, `<->`, `<-->`, `<=>`, `<=>>`, `<<=>`, `=` (in equations) | reaction arrows | stretchy arrows (`→`, `←`, `↔`, `⇄`, `⇌`, and the unequal pairs), as relations |
| `->[\Delta]`, `->[H2O][heat]` | arrow labels above and below | over/under labels on the stretchy arrow; labels are read as `\ce` text, `$…$` as math |
| `(aq)`, `(s)`, `(l)`, `(g)` | state symbols | upright, on the baseline |
| `^` and `v` as separate words | gas, precipitate | `↑`, `↓` |
| `*` or `.` between parts | adducts, hydrates (`CuSO4*5H2O`) | centered dot `·` |
| `-`, `=`, `#` between atoms | single, double, triple bonds | `−`, `=`, `≡` |
| `^{14}_{6}C`, `^{14}C` | isotopes | prescripts, right-aligned |
| `\alpha` and other commands, `$…$` | math inside chemistry | read by the TeX reader |

The charge and bond rules for `-` follow mhchem: after a symbol, count or group and before a space or the end it is a
charge; between two atoms it is a bond. Where mhchem's own parser disagrees with this table on a corpus formula,
mhchem wins and the table is wrong.

The unequal equilibrium arrows (`<=>>`, `<<=>`) have no single Unicode character. They are two stacked harpoons of
unequal length; how they are built from the font (a small grid, an over/under pair, or an operator variant) is the
builder's call, as long as they stretch with their labels. If they cannot be drawn well, they fall back to `⇌` with an
`approximated` note.

## `\pu`

Physical units, mhchem's other command. In the subset:

- A number then units: `\pu{25 kJ/mol}`, `\pu{1.2e3 kJ}` (drawn 1.2·10³), `\pu{-273.15 °C}`.
- Units with exponents and products: `\pu{kJ mol-1}` (mol⁻¹), `\pu{m/s^2}`, `\pu{kg*m/s2}`; products as a thin space
  or `·` as mhchem draws them, `/` as a slash.
- Units upright, numbers upright, a thin space between number and unit.

Anything else falls back as for `\ce`.

## Source preservation

- The tree records the TeX source for: each `\ce` and `\pu` group, and each construct read but not drawn
  (`\boxed`, `\fbox`, `\color`, `\textcolor`, `\cancel`, `\bcancel`, `\xcancel`, `\sout`, `\tag`). How it is held (a
  field on `Fallback`, or a small wrapper node all writers but TeX see through) is the builder's call. It must be
  bounded by the input, so no copies per nesting level.
- `\color{red}` switches the rest of a group, so its source covers what it colors, not just the command.
- The writers other than TeX ignore it: SVG, PDF, PNG, MathML, OMML and linear text draw the content as today.
- The TeX writer writes the recorded source instead of the content. For `\boxed` and the rest this is standard
  KaTeX TeX. For `\ce` and `\pu` it is the group as read, which needs mhchem wherever the TeX goes next (KaTeX and
  MathJax both offer it). *(Decided with the user.)*
- Round trip: the read-back tree equals the first tree (the existing round-trip test, with the source compared too).
- A tree built in code has no source, and writes as today.

## Prescripts

- Pre-subscript and pre-superscript on a base, each optional. Set right-aligned against the base, with the font's
  script shifts as for ordinary scripts. Post-scripts on the same base remain possible (`^{14}_{6}C^{4+}`).
- Readers: mhchem isotopes; `\prescript{a}{b}{X}` (mathtools: sup, sub, base) and `\sideset{_a^b}{_c^d}X` (amsmath;
  post-scripts through ordinary scripts); the TeX idiom `{}^{a}_{b}X` (an empty group with scripts directly before a
  base) also reads as prescripts; MathML `mmultiscripts` with `mprescripts`, including post-scripts. Multiple
  script pairs in `mmultiscripts` (tensor indices) are read as far as the node can hold them and noted beyond that.
- Writers: MathML `mmultiscripts`/`mprescripts`; OMML `m:sPre`, wrapped in `m:sSubSup` or similar when there are
  post-scripts too (checked by hand in Word); TeX `{}^{a}_{b}X`, which KaTeX accepts and the reader reads back as
  prescripts; linear text with Unicode scripts before the base where they exist.
- Builder: a `Math::` method for a base with prescripts. *(Name and signature are the builder's call.)*

## Testing

- **Corpus:** the report's formulas above, plus ordinary school and first-year university chemistry: combustion,
  neutralization, precipitation with `v`, gas with `^`, ionic equations, complex ions, hydrates, isotopes and nuclear
  equations, equilibria with conditions on the arrow, half-equations with `e-`, and `\pu` quantities.
- **Reference:** each corpus formula has an expected tree written as plain KaTeX-core TeX
  (`\mathrm{Fe}(\mathrm{O}\mathrm{H})_{3}`), and the test asserts `\ce{x}` reads to the same tree as that TeX,
  ignoring the recorded source. The expected TeX is checked once by eye against KaTeX's mhchem rendering on a contact
  sheet (as `bin/tex-contact-sheet.php` does for TeX), and the sheet is regenerated when the corpus grows. mhchem's
  expansion is not a test fixture; no JavaScript at test time.
- **Round trip:** every corpus formula, and every not-drawn construct with source, goes TeX → nodes → TeX → nodes.
- **Writers:** MathML and OMML snapshots for a representative subset, prescripts included. A labelled .docx and a
  checklist for a manual Word check of `m:sPre`, reaction arrows with labels, and charges.
- **Layout:** SVG snapshots for the report's formulas, isotopes, a labelled arrow and the unequal equilibrium arrows.
- **Fallback:** each out-of-subset construct draws its source with one note, and the rest of the formula is read.
- **Hostile input:** chemistry token soup in the fuzz run (unbalanced brackets, long chains of charges and arrows,
  deep `$…$` nesting), no throw, bounded memory.
- Suites on PHP 8.4 and 8.5.

## Left to the builder

- Class names; where the chemistry parser lives; how the source is held; whether prescripts are a node or fields on
  `Scripts`.
- How the unequal equilibrium arrows are built.
- The exact mhchem rules for edge cases, taken from mhchem's parser.
- Whether `\cee` and `\cf` come along.
- The order of work.

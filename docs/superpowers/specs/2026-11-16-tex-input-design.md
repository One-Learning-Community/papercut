# TeX input and output for papercut/math

## Why

`papercut/math` lays formulas out from an OpenType MATH font and writes them as SVG, MathML, Word OMML, linear text
and, through core, PDF. Formulas come in through the PHP builder or MathML. The input most people actually write is
TeX: `\frac{a}{b}`, `\sum_{n=1}^\infty`, `\begin{aligned}…`. Without it, the package is a Papercut add-on. With it,
it stands alone as a PHP math library, with the node tree as the hub between formats:

- in: builder, MathML, **TeX**
- out: SVG, MathML, OMML, linear text, PDF layout, **TeX**

So TeX → MathML, TeX → Word, MathML → TeX and TeX → SVG all fall out of one reader and one writer. A server-side
alternative to MathJax (TeX in, path-based SVG out) is something users can put together from these parts; it is not
a feature of its own here.

A throwaway spike (local branch `spike/tex`, commit `ab0cbaa`) read 87 realistic formulas into the existing nodes.
None crashed, 76 needed no notes, and about 40 of the most used command families needed no new node types. It also
showed where the node tree and layout engine fall short. This design takes the parser to a shipped standard and
closes the three engine gaps that real TeX hits most.

## Decisions taken with the user

- `Math::tex(string): Node` returns the package's node tree, the same tree the builder and MathML reader make. No
  hop through MathML strings.
- Scope is the reader and writer, not a MathJax-style page filter. No scanning of HTML or text for `$…$`.
- A **TeX writer** ships with the reader. TeX → nodes → TeX → nodes must give the same tree; this round trip is the
  main validation, and where it fails it names the node-tree gaps that remain.
- No OMML reader.
- Engine gaps in this round: **alignment points** (with column spacing on grids), **`\middle`**, **minimum width
  for extensible arrows**, and **fixed-size delimiters** (`\big`…`\Bigg`). Deferred: colour, boxes and strike-outs, table rules, `\tag`, script-style switches.
- **Standard TeX wins over a perfect round trip.** The writer always writes TeX that KaTeX accepts. A tree with no
  standard spelling (MathML-only details such as a non-stretchy operator override, top-aligned grid rows, an accent
  mark with no command) is written as the nearest TeX, and those details are lost. The round-trip guarantee covers
  trees that came from TeX or the builder.
- The misplaced integral limits (see below) are a bug in the released package and are fixed first, on their own.

## Goals

1. A TeX reader in `papercut/math` (`Papercut\Math\Tex\TexReader`, result with node, display flag and notes, like
   `MathmlReader`) and `Math::tex()`.
2. A TeX writer (`Papercut\Math\Render\TexWriter` or similar) that writes any node tree as standard TeX. Trees made
   from TeX or the builder read back to the same tree.
3. Alignment points, `\middle`, arrow minimum width and fixed-size delimiters in the node tree and layout, and in every writer: SVG/PDF,
   MathML, OMML, TeX and linear text.
4. Builder methods for each new construct (e.g. `Math::aligned(rows)`, a middle delimiter inside `Math::fenced`).
5. Core: wherever a MathML string is accepted for a formula, TeX can be given too. In Blade that is an explicit
   `tex` attribute (or equivalent) on the `equation` and `math` components, because a plain string is already read
   as builder input and TeX can't be told from it reliably. *(My call.)*
6. Docs: README section "Reading TeX", the guide and gallery pages, changelog.

## Non-goals

- Finding TeX in HTML or Markdown (`$…$`, `\(…\)`, `<span class="math-tex">`), and the `application/x-tex`
  annotation in MathML `semantics`. MathML stays authoritative when both are present.
- Colour (`\color`, `\textcolor`): content drawn in the surrounding colour, with an `approximated` note. The MathML
  spec's decision that formulas take the surrounding colour stands for now.
- `\boxed`, `\fbox`, `\cancel`, `\xcancel`, `\hline` and `array` column rules, `\tag`, `\label`, `\scriptstyle` and
  `\scriptscriptstyle`, `\smash`, separate-axis phantoms: approximated or dropped, with notes. A frame node is the
  strongest candidate for the next round, because it would also draw MathML's `menclose`.
- `\newenvironment`, delimited macro parameters, `\begin{CD}`, `\mathchoice`, `\sideset`, `\prescript`/`\tensor`:
  noted as unsupported.
- Full TeX (catcodes, `\catcode`, `\expandafter` games). This is math-mode TeX as KaTeX and MathJax read it.
- Line breaking inside a formula.

## Anti-goals

- An exception for input the reader doesn't understand. Like the MathML reader, it never throws; unknown commands
  are drawn as their names with a `dropped` note, as KaTeX shows its error text.
- Hacks in the reader standing in for missing nodes. The spike faked alignment with a negative space; a gap is
  closed in the node tree so every reader and writer gets it, or it is noted.
- A TeX writer that writes something the reader can't read back.
- Consumer-specific fixtures. Corpus formulas come from ordinary school and university maths.

## The reader

- **Bar:** KaTeX's list of supported functions, minus the non-goals above. Greek, symbols, relations, arrows,
  big operators with `\limits`/`\nolimits`, `\frac \dfrac \tfrac \cfrac \binom \dbinom \tbinom`, `\sqrt[n]`,
  `\left \middle \right`, `\big`…`\Bigg` with l/r/m variants, font commands (`\mathbb` etc. through the existing
  variants), `\text` with nested `$…$`, `\operatorname(*)`, `\DeclareMathOperator`, accents and wide accents,
  `\overbrace`/`\underbrace` with labels, `\overset \underset \stackrel \substack`, `\not`, `\pmod \bmod`, primes,
  spacing commands, `\displaystyle \textstyle`, environments `matrix pmatrix bmatrix Bmatrix vmatrix Vmatrix
  smallmatrix cases dcases rcases aligned align align* gathered gather* split array`, macros (`\newcommand`,
  `\renewcommand`, `\def` with `#1`…`#9`, optional first argument with a default).
- **Display flag:** `\displaystyle` at the top, or display environments (`align`, `gather`, …), set it; otherwise
  the caller decides. `$$…$$`, `\[…\]` and `\(…\)` wrappers around the whole input are stripped and set the flag.
  *(My call.)*
- **Operator classes:** `\mathrel`, `\mathbin`, `\mathop`, `\mathopen`, `\mathclose`, `\mathpunct`, `\mathord`
  give the operator an explicit class (via `Operator`'s `form` and flags, or a small extension of them) so spacing
  matches TeX and survives the round trip. The spike passed them through.
- **Notes** use `MathNote`/`NoteLevel` and live in the tree (fallback nodes), as for MathML. One note per command
  name and reason per formula. `element` holds the command or environment name (`\color`, `aligned`).
- **Untrusted input.** TeX often comes from end users (comments, answers), unlike the trusted-input features, so
  hostile input is in scope: limits on input length, group and environment nesting depth (256, as the MathML
  reader), macro expansion (count and output size; the spike hit exponential growth with
  `\def\a#1{\a{#1#1}}\a{x}`), and grid size. Hitting a limit gives an `approximated` note and the rest as text,
  never a throw or a memory blow-up. A fuzz run is part of the tests.
- The spike's code (`src/Tex/` on `spike/tex`) may be used as a starting point. It is not the design; it was
  written fast and has no real tests.

## The writer

- Writes every node type the package has, as plain TeX that KaTeX and the reader both accept (no package-specific
  macros). Identifiers, numbers and operators map back to their commands where one exists (`α` → `\alpha`), and
  otherwise stay as Unicode characters, which the reader accepts.
- Fallback nodes write their content; their notes are not written. Nodes from the MathML reader convert the same
  way, so MathML → TeX works.
- Where a tree has no standard TeX spelling, the writer writes the nearest one and drops the detail silently. It
  never invents macros or spellings only our reader understands. Writers have no notes API. *(Decided with the
  user.)*
- Output is normalised (one canonical spelling per construct) so the round trip compares trees, not strings.

## Engine gaps

### Alignment points

`aligned`, `align`, `split` and `gathered` set rows in column pairs: right-aligned, then left-aligned, with no gap
inside a pair and a gap between pairs. A relation at the start of a left cell keeps its spacing (amsmath inserts an
empty group before it).

- `Grid` gains column spacing: a list of gaps in em between columns, the last repeating, defaulting to today's 1em.
  The MathML reader then honours `columnspacing` (it is noted as approximated today), and `array` uses TeX's
  2 × `\arraycolsep`.
- The tree records that a grid is an alignment, not a matrix, so writers can use the native form. *(Whether that is
  a flag on `Grid` or its own node is the builder's call.)*
- The reader makes a relation at the start of a cell infix (explicit form) rather than inserting a negative space.
- Writers: MathML as `mtable` with alternating `columnalign="right left"` and `columnspacing`; OMML as `m:eqArr` with
  alignment marks, falling back to a matrix only if Word can't hold the pairs; TeX as `aligned`; linear text as
  lines. *(The OMML form is checked by hand in Word.)*
- Builder: `Math::aligned(rows)`, each row a list of cells.

### `\middle`

- A delimiter inside `Fenced` content that stretches to the fence's size: in TeX `\left\{ x \middle| x > 0 \right\}`.
  *(Representation, e.g. a stretchy `Operator` directly inside the fenced row, is the builder's call.)*
- Writers: MathML `<mo stretchy="true">`; OMML `m:d` with `m:sepChr` and one `m:e` per part (Word's native
  separators), or the closest Word accepts; TeX `\middle`.
- Builder: a way to put a middle delimiter in `Math::fenced`.

### Extensible arrows

- `\xrightarrow`, `\xleftarrow`, `\xleftrightarrow`, `\xLeftarrow`, `\xRightarrow`, `\xlongequal` and similar: the
  arrow stretches to its labels' width plus padding on each side, and is never shorter than a minimum (about 1.5em,
  as KaTeX). Today it stretches only to the label width.
- Writers keep what they already do for stretchy over/under arrows; MathML may add `minsize`. TeX writes the `\x…`
  command back.

### Fixed-size delimiters

- `\big`, `\Big`, `\bigg`, `\Bigg` and their `l`/`r`/`m` variants set a delimiter at a fixed size (TeX's 1.2, 1.8,
  2.4 and 3.0 times the base size, as KaTeX), whatever the content's height. The spike's invisible strut is not
  used.
- An operator gains a fixed or minimum size, drawn with the font's size variants and assembly as stretchy
  delimiters are today. The MathML reader then honours `minsize` and `maxsize` on `mo` (ignored silently today).
- Writers: MathML `minsize`/`maxsize`; OMML at the natural size (Word has no fixed-size delimiter); TeX as the
  nearest `\big…` command; SVG/PDF drawn at the size.
- Builder: a way to give a delimiter its size (e.g. `Math::big('(', 2)` or a size argument). *(Builder's call.)*

## Integral limits (separate fix, first)

`\int_0^1` and `Math::integral('0', '1')` place both limits far to the right of the integral sign in the released
0.14.1. They should sit as TeX sets them: the superscript just past the top of the sign and the subscript tucked in
under it, using the glyph's italic correction (OpenType MATH: subscript shifted left by the italic correction). The
cause has not been traced. This ships as a patch release before the TeX work.

## Testing

- **Round trip:** every corpus formula goes TeX → nodes → TeX → nodes, and the two trees must be equal (a structural
  comparison, ignoring notes). Every node type the builder can make also goes nodes → TeX → nodes.
- **MathML → TeX:** MathML fixtures written as TeX must read back without notes, but need not give the same tree.
- **Corpus:** the spike's 87 formulas, extended toward KaTeX's function list, plus layout snapshots for a
  representative subset. If fixtures are taken from KaTeX's own tests, keep its MIT notice.
- **Writers on TeX input:** MathML and OMML output for TeX-sourced formulas, not only SVG (the spike checked only
  SVG). A labelled .docx and a checklist for a manual Word check of `m:eqArr` and `m:sepChr`.
- **Hostile input:** fuzz run (random token soup and hand-written breakage), each limit hit once, none throws,
  memory stays bounded.
- Suites on PHP 8.4 and 8.5.

## Left to the builder

- Class names, the alignment representation (flag or node), the `\middle` representation.
- The exact symbol tables (source them from KaTeX's or unicode-math's lists).
- The tree-equality helper for round-trip tests.
- The order of work, except that the integral fix ships first.

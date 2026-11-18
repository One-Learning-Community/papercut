<?php

declare(strict_types=1);

/**
 * Ordinary school and first-year chemistry for the `\ce` reader. name => [`\ce` argument, the same formula in plain
 * KaTeX TeX, expected notes as [element, level] pairs; [] for none]. The expected TeX spells the tree the reader builds
 * (one upright identifier per element), not the TeX mhchem expands to. A label-less arrow has no spelling in plain TeX
 * (`\\xrightarrow{}` reads as a plain arrow), so `⟦→⟧` stands for the stretchy arrow node the reader builds. Differences from KaTeX 0.16 that are kept are commented on their entries. An entry whose source is written `\\pu{…}` is a unit formula and is read as it stands. Used by tests/Tex/Chem/ChemCorpusTest.php.
 *
 * @return array<string, array{string, string, list<array{string, string}>}>
 */
return [
    'water' => ['H2O', '\mathrm{H}_{2}\mathrm{O}', []],
    // KaTeX 0.16 (mhchem 3.3) offsets a charge to the right of a subscript; the charge is stacked over the subscript here, as chemistry texts set it.
    'sulfate' => ['SO4^2-', '\mathrm{S}\mathrm{O}_{4}^{2-}', []],
    'ammonium' => ['NH4+', '\mathrm{N}\mathrm{H}_{4}^{+}', []],
    'calcium-ion' => ['Ca^2+', '\mathrm{Ca}^{2+}', []],
    'iron-hydroxide' => ['Fe(OH)3', '\mathrm{Fe}(\mathrm{O}\mathrm{H})_{3}', []],
    'salt-aqueous' => ['NaCl(aq)', '\mathrm{Na}\mathrm{Cl}\mkern2mu(\mathrm{aq})', []],
    'methane' => ['CH4', '\mathrm{C}\mathrm{H}_{4}', []],
    'oxygen-coefficient' => ['2O2', '2\,\mathrm{O}_{2}', []],
    'limestone' => ['CaCO3', '\mathrm{Ca}\mathrm{C}\mathrm{O}_{3}', []],
    'hydrochloric-acid' => ['2HCl', '2\,\mathrm{H}\mathrm{Cl}', []],
    'rust' => ['Fe2O3', '\mathrm{Fe}_{2}\mathrm{O}_{3}', []],
    'sum-of-species' => ['CaCl2 + CO2 + H2O', '\mathrm{Ca}\mathrm{Cl}_{2} + \mathrm{C}\mathrm{O}_{2} + \mathrm{H}_{2}\mathrm{O}', []],
    'complex-ion' => ['[Cu(NH3)4]^2+', '[\mathrm{Cu}(\mathrm{N}\mathrm{H}_{3})_{4}]^{2+}', []],
    'chloride' => ['Cl-', '\mathrm{Cl}^{-}', []],
    'electron' => ['e-', '\mathrm{e}^{-}', []],
    'hydrate-star' => ['CuSO4*5H2O', '\mathrm{Cu}\mathrm{S}\mathrm{O}_{4}\cdot 5\,\mathrm{H}_{2}\mathrm{O}', []],
    'hydrate-dot' => ['CuSO4.5H2O', '\mathrm{Cu}\mathrm{S}\mathrm{O}_{4}\cdot 5\,\mathrm{H}_{2}\mathrm{O}', []],
    'ethane-bond' => ['CH3-CH3', '\mathrm{C}\mathrm{H}_{3}{-}\mathrm{C}\mathrm{H}_{3}', []],
    'hydrogen-chloride-bond' => ['H-Cl', '\mathrm{H}{-}\mathrm{Cl}', []],
    'double-bond' => ['C=O', '\mathrm{C}{=}\mathrm{O}', []],
    'triple-bond' => ['HC#CH', '\mathrm{H}\mathrm{C}{\equiv}\mathrm{C}\mathrm{H}', []],
    'carbon-14' => ['^{14}_{6}C', '{}^{14}_{6}\mathrm{C}', []],
    'carbon-14-mass' => ['^{14}C', '{}^{14}\mathrm{C}', []],
    'uranium-235' => ['^{235}_{92}U', '{}^{235}_{92}\mathrm{U}', []],
    'half-oxygen' => ['1/2O2', '\frac{1}{2}\,\mathrm{O}_{2}', []],
    'math-amount' => ['$n$ H2O', 'n\,\mathrm{H}_{2}\mathrm{O}', []],
    'delta-enthalpy' => ['\Delta H', '\Delta\mathrm{H}', []],
    'greek-amount' => ['\alpha H2O', '\alpha\mathrm{H}_{2}\mathrm{O}', []],
    'water-liquid' => ['H2O(l)', '\mathrm{H}_{2}\mathrm{O}\mkern2mu(\mathrm{l})', []],
    'carbon-dioxide-gas' => ['CO2(g)', '\mathrm{C}\mathrm{O}_{2}\mkern2mu(\mathrm{g})', []],
    'magnesium-solid' => ['Mg(s)', '\mathrm{Mg}\mkern2mu(\mathrm{s})', []],
    'iron-braced-charge' => ['Fe^{3+}', '\mathrm{Fe}^{3+}', []],
    'permanganate' => ['MnO4-', '\mathrm{Mn}\mathrm{O}_{4}^{-}', []],
    'dichromate' => ['Cr2O7^2-', '\mathrm{Cr}_{2}\mathrm{O}_{7}^{2-}', []],
    'acetic-acid' => ['CH3COOH', '\mathrm{C}\mathrm{H}_{3}\mathrm{C}\mathrm{O}\mathrm{O}\mathrm{H}', []],
    'glucose' => ['C6H12O6', '\mathrm{C}_{6}\mathrm{H}_{12}\mathrm{O}_{6}', []],
    'gas-evolved' => ['CO2 ^', '\mathrm{C}\mathrm{O}_{2}\uparrow', []],
    'precipitate' => ['AgCl v', '\mathrm{Ag}\mathrm{Cl}\downarrow', []],
    'unsupported-bar' => ['A|B', '\mathrm{A}\text{|}\mathrm{B}', [['\ce', 'Approximated']]],
    'isotope-with-charge' => ['^{14}_{6}C^{4+}', '{}^{14}_{6}\mathrm{C}^{4+}', []],
    'combustion' => ['CH4 + 2O2 -> CO2 + 2H2O', '\mathrm{C}\mathrm{H}_{4} + 2\,\mathrm{O}_{2} ⟦→⟧ \mathrm{C}\mathrm{O}_{2} + 2\,\mathrm{H}_{2}\mathrm{O}', []],
    'acid-on-limestone' => ['CaCO3 + 2HCl -> CaCl2 + CO2 + H2O', '\mathrm{Ca}\mathrm{C}\mathrm{O}_{3} + 2\,\mathrm{H}\mathrm{Cl} ⟦→⟧ \mathrm{Ca}\mathrm{Cl}_{2} + \mathrm{C}\mathrm{O}_{2} + \mathrm{H}_{2}\mathrm{O}', []],
    'rusting' => ['4Fe + 3O2 -> 2Fe2O3', '4\,\mathrm{Fe} + 3\,\mathrm{O}_{2} ⟦→⟧ 2\,\mathrm{Fe}_{2}\mathrm{O}_{3}', []],
    'haber-process' => ['N2 + 3H2 <=> 2NH3', '\mathrm{N}_{2} + 3\,\mathrm{H}_{2} ⟦⇌⟧ 2\,\mathrm{N}\mathrm{H}_{3}', []],
    'precipitation' => ['Ag+ + Cl- -> AgCl v', '\mathrm{Ag}^{+} + \mathrm{Cl}^{-} ⟦→⟧ \mathrm{Ag}\mathrm{Cl}\downarrow', []],
    'decomposition-heat' => ['CaCO3 ->[\Delta] CaO + CO2 ^', '\mathrm{Ca}\mathrm{C}\mathrm{O}_{3}\xrightarrow{\Delta}\mathrm{Ca}\mathrm{O} + \mathrm{C}\mathrm{O}_{2}\uparrow', []],
    'haber-conditions' => ['N2 + 3H2 <=>[Fe][450 °C] 2NH3', '\mathrm{N}_{2} + 3\,\mathrm{H}_{2}\xrightleftharpoons[450\ °\mathrm{C}]{\mathrm{Fe}}2\,\mathrm{N}\mathrm{H}_{3}', []],
    'label-words' => ['A ->[long label] B', '\mathrm{A}\xrightarrow{\mathrm{long}\ \mathrm{label}}\mathrm{B}', []],
    'unequal-with-labels' => ['A <=>>[x][y] B', '\mathrm{A}\xrightleftharpoons[\mathrm{y}]{\mathrm{x}}\mathrm{B}', [['\ce', 'Approximated']]],
    // Braces in an arrow label are literal text in mhchem 3.x and 4.x, so the label is drawn as text with no subscript.
    'braced-label' => ['A ->[{H2O}] B', '\mathrm{A}\xrightarrow{\text{H2O}}\mathrm{B}', []],
    'arrow-left' => ['A <- B', '\mathrm{A} ⟦←⟧ \mathrm{B}', []],
    'arrow-both' => ['A <-> B', '\mathrm{A} ⟦↔⟧ \mathrm{B}', []],
    'arrow-to-and-fro' => ['A <--> B', '\mathrm{A} ⟦⇄⟧ \mathrm{B}', []],
    'electron-transfer' => ['Fe^3+ + e- -> Fe^2+', '\mathrm{Fe}^{3+} + \mathrm{e}^{-} ⟦→⟧ \mathrm{Fe}^{2+}', []],
    // KaTeX 0.16 draws a longer forward harpoon; the arrow is drawn as equal-length harpoons here, with a note.
    'unequal-forward' => ['A <=>> B', '\mathrm{A} ⟦⇌⟧ \mathrm{B}', [['\ce', 'Approximated']]],
    // KaTeX 0.16 draws a longer backward harpoon; the arrow is drawn as equal-length harpoons here, with a note.
    'unequal-backward' => ['A <<=> B', '\mathrm{A} ⟦⇌⟧ \mathrm{B}', [['\ce', 'Approximated']]],
    'equals-between-species' => ['A = B', '\mathrm{A} = \mathrm{B}', []],
    'unit-ratio' => ['\pu{25 kJ/mol}', '25~\mathrm{kJ}/\mathrm{mol}', []],
    'unit-power-of-ten' => ['\pu{1.2e3 kJ}', '1.2\cdot {10}^{3}~\mathrm{kJ}', []],
    'unit-celsius' => ['\pu{-273.15 °C}', '-273.15~\mathrm{°}\mathrm{C}', []],
    'unit-negative-exponent' => ['\pu{kJ mol-1}', '\mathrm{kJ}\,\mathrm{mol}^{-1}', []],
    'unit-caret-exponent' => ['\pu{m/s^2}', '\mathrm{m}/\mathrm{s}^{2}', []],
    'unit-product' => ['\pu{kg*m/s2}', '\mathrm{kg}\mkern1mu{\cdot}\mkern1mu\mathrm{m}\,/\,\mathrm{s}^{2}', []],
    'unit-dot-product' => ['\pu{m.s-1}', '\mathrm{m}\mkern1mu{\cdot}\mkern1mu\mathrm{s}^{-1}', []],
    'unit-braced-exponent' => ['\pu{mol^{-1}}', '\mathrm{mol}^{-1}', []],
    'unit-micro' => ['\pu{5 µm}', '5~\mathrm{µm}', []],
    'unit-ohm' => ['\pu{5 Ω}', '5~\mathrm{Ω}', []],
    'unit-plus-sign' => ['\pu{+5 m}', '+5~\mathrm{m}', []],
    'unit-true-minus' => ['\pu{−5 m}', '-5~\mathrm{m}', []],
    'unit-power-signed' => ['\pu{10^-3 m}', '{10}^{-3}~\mathrm{m}', []],
    'unit-power-braced' => ['\pu{10^{-3} m}', '{10}^{-3}~\mathrm{m}', []],
    'unit-degree-exponent' => ['\pu{°C2}', '\mathrm{°}\mathrm{C}^{2}', []],
    'unit-degree-caret' => ['\pu{°C^2}', '\mathrm{°}\mathrm{C}^{2}', []],
    'unit-degree-space' => ['\pu{° C}', '\mathrm{°}\,\mathrm{C}', []],
    // KaTeX 0.16 (mhchem 3.3) draws a dangling dot as a centered dot after the number; it is kept as text here, with a note.
    'unit-dangling-dot' => ['\pu{25.}', '\text{25.}', [['\pu', 'Approximated']]],
];

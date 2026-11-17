<?php

declare(strict_types=1);

namespace Quoin\Math\Tex;

use Quoin\Math\Styles;

/**
 * LaTeX command to Unicode tables, shared by the TeX writer and the reader (the common few hundred symbols, not all of
 * unicode-math), and the reverse lookups built from them. Where several commands share a character the writer uses
 * one canonical command (see PREFER).
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class TexSymbols
{
    public const GREEK = [
        'alpha' => 'α', 'beta' => 'β', 'gamma' => 'γ', 'delta' => 'δ', 'epsilon' => 'ϵ', 'varepsilon' => 'ε', 'zeta' => 'ζ',
        'eta' => 'η', 'theta' => 'θ', 'vartheta' => 'ϑ', 'iota' => 'ι', 'kappa' => 'κ', 'varkappa' => 'ϰ', 'lambda' => 'λ',
        'mu' => 'μ', 'nu' => 'ν', 'xi' => 'ξ', 'omicron' => 'ο', 'pi' => 'π', 'varpi' => 'ϖ', 'rho' => 'ρ', 'varrho' => 'ϱ',
        'sigma' => 'σ', 'varsigma' => 'ς', 'tau' => 'τ', 'upsilon' => 'υ', 'phi' => 'ϕ', 'varphi' => 'φ', 'chi' => 'χ',
        'psi' => 'ψ', 'omega' => 'ω', 'digamma' => 'ϝ',
    ];

    public const GREEK_UP = [
        'Gamma' => 'Γ', 'Delta' => 'Δ', 'Theta' => 'Θ', 'Lambda' => 'Λ', 'Xi' => 'Ξ', 'Pi' => 'Π', 'Sigma' => 'Σ',
        'Upsilon' => 'Υ', 'Phi' => 'Φ', 'Psi' => 'Ψ', 'Omega' => 'Ω', 'varGamma' => 'Γ', 'varDelta' => 'Δ', 'varTheta' => 'Θ',
        'varLambda' => 'Λ', 'varXi' => 'Ξ', 'varPi' => 'Π', 'varSigma' => 'Σ', 'varUpsilon' => 'Υ', 'varPhi' => 'Φ',
        'varPsi' => 'Ψ', 'varOmega' => 'Ω',
    ];

    /** Ordinary symbols (operands): drawn upright, no operator spacing. */
    public const ORD = [
        'infty' => '∞', 'partial' => '∂', 'nabla' => '∇', 'hbar' => 'ℏ', 'hslash' => 'ℏ', 'ell' => 'ℓ', 'emptyset' => '∅',
        'varnothing' => '∅', 'Re' => 'ℜ', 'Im' => 'ℑ', 'wp' => '℘', 'imath' => 'ı', 'jmath' => 'ȷ', 'prime' => '′',
        'backprime' => '‵', 'angle' => '∠', 'measuredangle' => '∡', 'sphericalangle' => '∢', 'triangle' => '△',
        'square' => '□', 'Box' => '□', 'blacksquare' => '■', 'blacktriangle' => '▲', 'surd' => '√', 'top' => '⊤', 'bot' => '⊥',
        'flat' => '♭', 'natural' => '♮', 'sharp' => '♯', 'clubsuit' => '♣', 'diamondsuit' => '♢', 'heartsuit' => '♡',
        'spadesuit' => '♠', 'degree' => '°', 'dag' => '†', 'ddag' => '‡', 'S' => '§', 'P' => '¶', 'copyright' => '©',
        'pounds' => '£', 'checkmark' => '✓', 'aleph' => 'ℵ', 'beth' => 'ℶ', 'gimel' => 'ℷ', 'daleth' => 'ℸ',
        'mho' => '℧', 'eth' => 'ð', 'Finv' => 'Ⅎ', 'Game' => '⅁', 'complement' => '∁', 'lozenge' => '◊',
        'ldots' => '…', 'dots' => '…', 'dotsc' => '…', 'dotsb' => '⋯', 'dotsm' => '⋯', 'dotsi' => '⋯', 'dotso' => '…',
        'cdots' => '⋯', 'vdots' => '⋮', 'ddots' => '⋱', 'mathellipsis' => '…', 'lbrace' => '{', 'rbrace' => '}',
        'backslash' => '\\', 'textbackslash' => '\\', 'bigstar' => '★', 'maltese' => '✠', 'circledR' => '®',
        'yen' => '¥', 'lq' => '‘', 'rq' => '’', 'ss' => 'ß', 'aa' => 'å', 'o' => 'ø',
    ];

    /** Binary operators, relations, quantifiers, logic and arrows: Operator nodes, spacing from the dictionary. */
    public const OPS = [
        // binary
        'pm' => '±', 'mp' => '∓', 'times' => '×', 'div' => '÷', 'cdot' => '⋅', 'ast' => '∗', 'star' => '⋆', 'circ' => '∘',
        'bullet' => '∙', 'oplus' => '⊕', 'ominus' => '⊖', 'otimes' => '⊗', 'oslash' => '⊘', 'odot' => '⊙', 'bigcirc' => '◯',
        'dagger' => '†', 'ddagger' => '‡', 'cap' => '∩', 'cup' => '∪', 'uplus' => '⊎', 'sqcap' => '⊓', 'sqcup' => '⊔',
        'vee' => '∨', 'lor' => '∨', 'wedge' => '∧', 'land' => '∧', 'setminus' => '∖', 'smallsetminus' => '∖', 'wr' => '≀',
        'diamond' => '⋄', 'bigtriangleup' => '△', 'bigtriangledown' => '▽', 'triangleleft' => '◃', 'triangleright' => '▹',
        'lhd' => '⊲', 'rhd' => '⊳', 'unlhd' => '⊴', 'unrhd' => '⊵', 'amalg' => '⨿', 'barwedge' => '⊼', 'veebar' => '⊻',
        'curlywedge' => '⋏', 'curlyvee' => '⋎', 'boxplus' => '⊞', 'boxminus' => '⊟', 'boxtimes' => '⊠', 'boxdot' => '⊡',
        'dotplus' => '∔', 'divideontimes' => '⋇', 'ltimes' => '⋉', 'rtimes' => '⋊', 'leftthreetimes' => '⋋',
        'rightthreetimes' => '⋌', 'intercal' => '⊺', 'circledcirc' => '⊚', 'circledast' => '⊛', 'circleddash' => '⊝',
        'centerdot' => '⋅', 'Cap' => '⋒', 'Cup' => '⋓', 'doublecap' => '⋒', 'doublecup' => '⋓',
        // relations
        'lt' => '<', 'gt' => '>', 'ne' => '≠', 'neq' => '≠', 'equiv' => '≡', 'approx' => '≈', 'sim' => '∼', 'simeq' => '≃', 'cong' => '≅',
        'propto' => '∝', 'varpropto' => '∝', 'le' => '≤', 'leq' => '≤', 'ge' => '≥', 'geq' => '≥', 'll' => '≪', 'gg' => '≫',
        'lll' => '⋘', 'ggg' => '⋙', 'prec' => '≺', 'succ' => '≻', 'preceq' => '⪯', 'succeq' => '⪰', 'subset' => '⊂',
        'supset' => '⊃', 'subseteq' => '⊆', 'supseteq' => '⊇', 'sqsubset' => '⊏', 'sqsupset' => '⊐', 'sqsubseteq' => '⊑',
        'sqsupseteq' => '⊒', 'in' => '∈', 'isin' => '∈', 'ni' => '∋', 'owns' => '∋', 'notin' => '∉', 'vdash' => '⊢',
        'dashv' => '⊣', 'models' => '⊨', 'perp' => '⊥', 'parallel' => '∥', 'nparallel' => '∦', 'mid' => '∣', 'nmid' => '∤',
        'asymp' => '≍', 'doteq' => '≐', 'bowtie' => '⋈', 'Join' => '⋈', 'smile' => '⌣', 'frown' => '⌢', 'nleq' => '≰',
        'ngeq' => '≱', 'nless' => '≮', 'ngtr' => '≯', 'nsubseteq' => '⊈', 'nsupseteq' => '⊉', 'subsetneq' => '⊊',
        'supsetneq' => '⊋', 'subsetneqq' => '⫋', 'supsetneqq' => '⫌', 'leqslant' => '⩽', 'geqslant' => '⩾',
        'lesssim' => '≲', 'gtrsim' => '≳', 'lessapprox' => '⪅', 'gtrapprox' => '⪆', 'approxeq' => '≊', 'backsim' => '∽',
        'thicksim' => '∼', 'thickapprox' => '≈', 'triangleq' => '≜', 'coloneqq' => '≔', 'eqqcolon' => '≕', 'doteqdot' => '≑',
        'risingdotseq' => '≓', 'fallingdotseq' => '≒', 'eqcirc' => '≖', 'circeq' => '≗', 'ncong' => '≇', 'nsim' => '≁',
        'nprec' => '⊀', 'nsucc' => '⊁', 'precsim' => '≾', 'succsim' => '≿', 'vDash' => '⊨', 'Vdash' => '⊩', 'Vvdash' => '⊪',
        'nvdash' => '⊬', 'nvDash' => '⊭', 'between' => '≬', 'pitchfork' => '⋔', 'therefore' => '∴', 'because' => '∵',
        'lesseqgtr' => '⋚', 'gtreqless' => '⋛', 'trianglelefteq' => '⊴', 'trianglerighteq' => '⊵', 'vartriangleleft' => '⊲',
        'vartriangleright' => '⊳', 'Subset' => '⋐', 'Supset' => '⋑', 'neg' => '¬', 'lnot' => '¬', 'forall' => '∀',
        'exists' => '∃', 'nexists' => '∄', 'lneq' => '⪇', 'gneq' => '⪈', 'lnsim' => '⋦', 'gnsim' => '⋧',
        'eqsim' => '≂', 'curlyeqprec' => '⋞', 'curlyeqsucc' => '⋟', 'Bumpeq' => '≎', 'bumpeq' => '≏', 'smallfrown' => '⌢',
        'smallsmile' => '⌣', 'shortmid' => '∣', 'shortparallel' => '∥', 'varsubsetneq' => '⊊', 'varsupsetneq' => '⊋',
        // arrows
        'to' => '→', 'rightarrow' => '→', 'leftarrow' => '←', 'gets' => '←', 'leftrightarrow' => '↔', 'Rightarrow' => '⇒',
        'Leftarrow' => '⇐', 'Leftrightarrow' => '⇔', 'implies' => '⟹', 'impliedby' => '⟸', 'iff' => '⟺', 'mapsto' => '↦',
        'longmapsto' => '⟼', 'longrightarrow' => '⟶', 'longleftarrow' => '⟵', 'longleftrightarrow' => '⟷',
        'Longrightarrow' => '⟹', 'Longleftarrow' => '⟸', 'Longleftrightarrow' => '⟺', 'uparrow' => '↑', 'downarrow' => '↓',
        'updownarrow' => '↕', 'Uparrow' => '⇑', 'Downarrow' => '⇓', 'Updownarrow' => '⇕', 'nearrow' => '↗', 'searrow' => '↘',
        'swarrow' => '↙', 'nwarrow' => '↖', 'hookrightarrow' => '↪', 'hookleftarrow' => '↩', 'leftharpoonup' => '↼',
        'leftharpoondown' => '↽', 'rightharpoonup' => '⇀', 'rightharpoondown' => '⇁', 'rightleftharpoons' => '⇌',
        'leftrightharpoons' => '⇋', 'rightsquigarrow' => '⇝', 'leadsto' => '⇝', 'twoheadrightarrow' => '↠',
        'twoheadleftarrow' => '↞', 'rightrightarrows' => '⇉', 'leftleftarrows' => '⇇', 'rightleftarrows' => '⇄',
        'leftrightarrows' => '⇆', 'Rrightarrow' => '⇛', 'Lleftarrow' => '⇚', 'curvearrowright' => '↷', 'curvearrowleft' => '↶',
        'circlearrowright' => '↻', 'circlearrowleft' => '↺', 'nrightarrow' => '↛', 'nleftarrow' => '↚', 'nRightarrow' => '⇏',
        'nLeftarrow' => '⇍', 'nleftrightarrow' => '↮', 'nLeftrightarrow' => '⇎', 'rightarrowtail' => '↣',
        'leftarrowtail' => '↢', 'looparrowright' => '↬', 'looparrowleft' => '↫', 'upuparrows' => '⇈', 'downdownarrows' => '⇊',
        'Lsh' => '↰', 'Rsh' => '↱', 'dashrightarrow' => '⇢', 'dashleftarrow' => '⇠', 'upharpoonleft' => '↿',
        'upharpoonright' => '↾', 'downharpoonleft' => '⇃', 'downharpoonright' => '⇂', 'multimap' => '⊸', 'mapsfrom' => '↤',
        'longmapsfrom' => '⟻', 'Mapsto' => '⤇', 'leftrightsquigarrow' => '↭',
    ];

    /** Large operators that take limits above/below in display style. */
    public const LARGE = [
        'sum' => '∑', 'prod' => '∏', 'coprod' => '∐', 'bigcup' => '⋃', 'bigcap' => '⋂', 'bigvee' => '⋁', 'bigwedge' => '⋀',
        'bigoplus' => '⨁', 'bigotimes' => '⨂', 'bigodot' => '⨀', 'bigsqcup' => '⨆', 'biguplus' => '⨄',
    ];

    /** Integrals: limits beside by default. */
    public const INTS = [
        'int' => '∫', 'iint' => '∬', 'iiint' => '∭', 'iiiint' => '⨌', 'oint' => '∮', 'oiint' => '∯', 'oiiint' => '∰',
        'smallint' => '∫', 'intop' => '∫', 'ointctrclockwise' => '∲', 'varointclockwise' => '∲', 'fint' => '⨏',
    ];

    public const FUNCS = \Quoin\Math\OperatorDictionary::PLAIN_FUNCTION_NAMES;
    public const FUNCS_LIM = \Quoin\Math\OperatorDictionary::LIMIT_NAMES;
    public const FUNCS_TEXT = ['injlim' => 'inj lim', 'projlim' => 'proj lim', 'varinjlim' => 'lim', 'varprojlim' => 'lim', 'varliminf' => 'lim', 'varlimsup' => 'lim'];

    /** \left / \right / \big delimiter names. */
    public const DELIMS = [
        '(' => '(', ')' => ')', '[' => '[', ']' => ']', '|' => '|', '/' => '/', '<' => '⟨', '>' => '⟩', '.' => '',
        '{' => '{', '}' => '}', 'lbrace' => '{', 'rbrace' => '}', 'lbrack' => '[', 'rbrack' => ']', 'langle' => '⟨',
        'rangle' => '⟩', 'lvert' => '|', 'rvert' => '|', 'vert' => '|', 'lVert' => '‖', 'rVert' => '‖', 'Vert' => '‖',
        'lfloor' => '⌊', 'rfloor' => '⌋', 'lceil' => '⌈', 'rceil' => '⌉', 'backslash' => '\\',
        'uparrow' => '↑', 'downarrow' => '↓', 'updownarrow' => '↕', 'Uparrow' => '⇑', 'Downarrow' => '⇓',
        'Updownarrow' => '⇕', 'ulcorner' => '⌜', 'urcorner' => '⌝', 'llcorner' => '⌞', 'lrcorner' => '⌟',
        'lgroup' => '⟮', 'rgroup' => '⟯', 'lmoustache' => '⎰', 'rmoustache' => '⎱',
    ];

    /** Delimiter names that are plain symbols elsewhere: \left\lt is an angle bracket, as in KaTeX. */
    private const DELIM_ALIASES = ['lt' => '⟨', 'gt' => '⟩'];

    /** The delimiter a name after \left, \right, \middle or \big stands for (`(`, `langle`, `lt`), or null when it is none. */
    public static function delimiterChar(string $name): ?string
    {
        return self::DELIMS[$name] ?? self::DELIM_ALIASES[$name] ?? null;
    }

    /** \hat etc.: command to combining mark. */
    public const ACCENTS = [
        'hat' => "\u{0302}", 'widehat' => "\u{0302}", 'tilde' => "\u{0303}", 'widetilde' => "\u{0303}", 'bar' => "\u{0304}",
        'check' => "\u{030C}", 'breve' => "\u{0306}", 'acute' => "\u{0301}", 'grave' => "\u{0300}", 'dot' => "\u{0307}",
        'ddot' => "\u{0308}", 'dddot' => "\u{20DB}", 'ddddot' => "\u{20DC}", 'mathring' => "\u{030A}", 'vec' => "\u{20D7}",
        'overrightarrow' => "\u{20D7}", 'overleftarrow' => "\u{20D6}", 'overleftrightarrow' => "\u{20E1}",
    ];

    /** Spacing commands, in em. */
    public const SPACES = [
        ',' => 3 / 18, 'thinspace' => 3 / 18, ':' => 4 / 18, '>' => 4 / 18, 'medspace' => 4 / 18, ';' => 5 / 18,
        'thickspace' => 5 / 18, '!' => -3 / 18, 'negthinspace' => -3 / 18, 'negmedspace' => -4 / 18,
        'negthickspace' => -5 / 18, ' ' => 1 / 3, 'quad' => 1.0, 'qquad' => 2.0, 'enspace' => 0.5, 'nobreakspace' => 1 / 3,
    ];

    /** Font commands to the style they map to: a Styles::VARIANTS name, or 'rm' (upright) / 'it'. */
    public const FONTS = [
        'mathrm' => 'rm', 'mathbf' => 'bold', 'mathit' => 'it', 'mathbb' => 'double-struck', 'mathcal' => 'script',
        'mathscr' => 'script', 'mathfrak' => 'fraktur', 'mathsf' => 'sans-serif', 'mathtt' => 'monospace',
        'boldsymbol' => 'bold-italic', 'bm' => 'bold-italic', 'pmb' => 'bold-italic', 'mathbfit' => 'bold-italic',
        'mathnormal' => 'it', 'textrm' => 'rm', 'mathsfit' => 'sans-serif-italic', 'mathbfsf' => 'bold-sans-serif',
        'bold' => 'bold', 'frak' => 'fraktur', 'Bbb' => 'double-struck', 'mit' => 'it',
    ];

    /** Old-style font switches ({\bf x}). */
    public const FONT_SWITCHES = ['rm' => 'rm', 'bf' => 'bold', 'it' => 'it', 'cal' => 'script', 'sf' => 'sans-serif', 'tt' => 'monospace', 'bfseries' => 'bold', 'itshape' => 'it', 'rmfamily' => 'rm', 'sffamily' => 'sans-serif', 'ttfamily' => 'monospace', 'normalfont' => 'rm', 'mdseries' => 'rm'];

    /** \not followed by this gives the negated symbol. */
    public const NEGATED = [
        '=' => '≠', '<' => '≮', '>' => '≯', 'in' => '∉', 'subset' => '⊄', 'supset' => '⊅', 'subseteq' => '⊈', 'supseteq' => '⊉',
        'equiv' => '≢', 'leq' => '≰', 'le' => '≰', 'geq' => '≱', 'ge' => '≱', 'sim' => '≁', 'simeq' => '≄', 'cong' => '≇',
        'approx' => '≉', 'ni' => '∌', 'exists' => '∄', 'mid' => '∤', 'parallel' => '∦', 'prec' => '⊀', 'succ' => '⊁',
        'vdash' => '⊬', 'models' => '⊭', 'rightarrow' => '↛', 'leftarrow' => '↚', 'Rightarrow' => '⇏', 'Leftarrow' => '⇍',
        'leftrightarrow' => '↮', 'Leftrightarrow' => '⇎', 'to' => '↛', 'sqsubseteq' => '⋢', 'sqsupseteq' => '⋣',
    ];

    /** What follows \not to give a precomposed negated symbol (`\subset`, `=`), or null when the character is not one. */
    public static function negation(string $char): ?string
    {
        $key = array_search($char, self::NEGATED, true);
        if ($key === false) {
            return null;
        }
        $key = (string) $key;

        return ctype_alpha($key) ? '\\' . $key : $key;
    }

    /** Text-mode escapes inside \text{}. */
    public const TEXT_ESC = [
        '&' => '&', '%' => '%', '$' => '$', '#' => '#', '_' => '_', '{' => '{', '}' => '}', ' ' => ' ', ',' => "\u{2009}",
        ';' => "\u{2005}", ':' => "\u{2005}", '!' => '', 'textbackslash' => '\\', 'ldots' => '…', 'dots' => '…',
        'quad' => "\u{2003}", 'qquad' => "\u{2003}\u{2003}", 'textasciitilde' => '~', 'textbar' => '|', 'textendash' => '–',
        'textemdash' => '—', 'textquoteleft' => '‘', 'textquoteright' => '’', 'textdegree' => '°', 'textminus' => '−',
        'textbullet' => '•', 'texttimes' => '×', 'textdollar' => '$', 'textpercent' => '%', 'copyright' => '©', 'S' => '§',
        'P' => '¶', 'dag' => '†', 'ddag' => '‡', 'ss' => 'ß', 'ae' => 'æ', 'AE' => 'Æ', 'oe' => 'œ', 'o' => 'ø', 'aa' => 'å',
        'i' => 'ı', 'j' => 'ȷ', 'LaTeX' => 'LaTeX', 'TeX' => 'TeX', '\\' => ' ', '-' => '', '/' => '', '@' => '',
        'textless' => '<', 'textgreater' => '>', 'textasciicircum' => '^', 'textunderscore' => '_',
    ];

    /** Commands the writer picks when several share a character (first listed table entry wins otherwise). */
    private const array PREFER = [
        'neq', 'leq', 'geq', 'perp', 'cdots', 'ldots', 'vdots', 'ddots', 'Vert', 'vartriangleleft', 'vartriangleright',
        'trianglelefteq', 'trianglerighteq', 'emptyset', 'Longrightarrow', 'Longleftarrow', 'Longleftrightarrow',
    ];

    /** Text-mode commands the reader knows but the writer never writes for a math symbol (the character itself is written). */
    private const array NO_WRITE = [
        'ss', 'aa', 'o', 'lq', 'rq', 'mathellipsis',
        // KaTeX 0.16 does not define these, so the writer leaves the character as Unicode.
        'mapsfrom', 'longmapsfrom', 'Mapsto', 'iiiint', 'ointctrclockwise', 'fint', 'varointclockwise',
    ];

    /** Symbols KaTeX accepts on their own but not after \left, \right, \middle or \big. */
    private const array NOT_DELIMITERS = ['⌜', '⌝', '⌞', '⌟'];

    /** Combining mark to [short command, wide command] (null where TeX has none). */
    public const array ACCENT_WRITE = [
        "\u{0302}" => ['hat', 'widehat'], "\u{0303}" => ['tilde', 'widetilde'], "\u{20D7}" => ['vec', 'overrightarrow'],
        "\u{20D6}" => [null, 'overleftarrow'], "\u{20E1}" => [null, 'overleftrightarrow'], "\u{0304}" => ['bar', null],
        "\u{030C}" => ['check', null], "\u{0306}" => ['breve', null], "\u{0301}" => ['acute', null], "\u{0300}" => ['grave', null],
        "\u{0307}" => ['dot', null], "\u{0308}" => ['ddot', null], "\u{20DB}" => ['dddot', null], "\u{20DC}" => ['ddddot', null],
        "\u{030A}" => ['mathring', null],
    ];

    /** Arrow to its extensible command (\xrightarrow and friends). */
    public const array XARROWS = [
        '→' => 'xrightarrow', '←' => 'xleftarrow', '↔' => 'xleftrightarrow', '⇒' => 'xRightarrow', '⇐' => 'xLeftarrow',
        '⇔' => 'xLeftrightarrow', '=' => 'xlongequal', '↦' => 'xmapsto', '↩' => 'xhookleftarrow', '↪' => 'xhookrightarrow',
        '⇀' => 'xrightharpoonup', '⇁' => 'xrightharpoondown', '↼' => 'xleftharpoonup', '↽' => 'xleftharpoondown',
        '⇌' => 'xrightleftharpoons', '⇋' => 'xleftrightharpoons', '↠' => 'xtwoheadrightarrow', '↞' => 'xtwoheadleftarrow',
        '⇄' => 'xtofrom', '⟶' => 'xrightarrow', '⟵' => 'xleftarrow', '⟷' => 'xleftrightarrow', '⟹' => 'xRightarrow',
        '⟸' => 'xLeftarrow', '⟺' => 'xLeftrightarrow',
    ];

    /** Math variant to the font command the writer uses (nearest standard TeX where the variant has none). */
    public const array VARIANT_WRITE = [
        'bold' => 'mathbf', 'italic' => 'mathit', 'bold-italic' => 'boldsymbol', 'double-struck' => 'mathbb',
        'script' => 'mathcal', 'bold-script' => 'mathcal', 'fraktur' => 'mathfrak', 'bold-fraktur' => 'mathfrak',
        'sans-serif' => 'mathsf', 'bold-sans-serif' => 'mathsf', 'sans-serif-italic' => 'mathsf',
        'sans-serif-bold-italic' => 'mathsf', 'monospace' => 'mathtt',
    ];

    /** The size commands of \big to \Bigg, indexed like Math::BIG_SIZES. */
    public const array BIG_COMMANDS = ['big', 'Big', 'bigg', 'Bigg'];

    /** @var array<string, string>|null command name (no backslash) to character */
    private static ?array $toChar = null;

    /** @var array<string, string>|null character to command name (no backslash) */
    private static ?array $toCommand = null;

    /** @var array<int, array{string, int}>|null styled code point to [variant, plain code point] */
    private static ?array $styled = null;

    /** @var array<string, true>|null delimiter characters */
    private static ?array $delims = null;

    /** The extensible arrow a command (`xrightarrow`, no backslash) stands for: the first arrow listed for it in XARROWS; null when it is none. */
    public static function xarrowChar(string $command): ?string
    {
        static $chars = null;
        if ($chars === null) {
            $chars = [];
            foreach (self::XARROWS as $arrow => $cmd) {
                $chars[$cmd] ??= $arrow;
            }
        }

        return $chars[$command] ?? null;
    }

    /** Whether TeX has a command for this function name (`sin`, `lim`): the names the reader reads as functions, less `sgn`, which KaTeX has no command for. */
    public static function isFunctionCommand(string $name): bool
    {
        return $name !== 'sgn' && (in_array($name, self::FUNCS, true) || in_array($name, self::FUNCS_LIM, true));
    }

    /** The command for a character (`α` to `\alpha`), or null when it has none or is plain ASCII. */
    public static function command(string $char): ?string
    {
        if (strlen($char) <= 1) {
            return null;
        }
        self::build();

        $name = self::$toCommand[$char] ?? null;

        return $name === null ? null : '\\' . $name;
    }

    /** The character a command stands for (`\alpha`, with or without its backslash, to `α`), or null when unknown. */
    public static function char(string $command): ?string
    {
        self::build();

        return self::$toChar[ltrim($command, '\\')] ?? null;
    }

    /** The command a delimiter is written with after \left, \right or \big: `(`, `\{`, `\langle`, `.` for none; null when it is no delimiter. */
    public static function delimiter(string $char): ?string
    {
        if ($char === '') {
            return '.';
        }
        self::build();
        if (strlen($char) === 1) {
            return match ($char) {
                '(', ')', '[', ']', '|', '/', '<', '>' => $char,
                '{' => '\{',
                '}' => '\}',
                '\\' => '\backslash',
                default => null,
            };
        }

        return isset(self::$delims[$char]) && ! in_array($char, self::NOT_DELIMITERS, true) ? self::command($char) : null;
    }

    /**
     * A styled letter or digit (a Mathematical Alphanumeric) as [variant, plain character], or null for any other character.
     *
     * @return array{string, string}|null
     */
    public static function unstyle(string $char): ?array
    {
        if (self::$styled === null) {
            self::$styled = [];
            $plain = [...range(0x41, 0x5A), ...range(0x61, 0x7A), ...range(0x30, 0x39), ...range(0x391, 0x3A9), ...range(0x3B1, 0x3C9), 0x3F5, 0x3D1, 0x3F0, 0x3D5, 0x3F1, 0x3D6];
            foreach (Styles::VARIANTS as $variant) {
                if ($variant === 'normal') {
                    continue;
                }
                foreach ($plain as $cp) {
                    $styled = Styles::variant($cp, $variant);
                    if ($styled !== $cp && ! isset(self::$styled[$styled])) {
                        self::$styled[$styled] = [$variant, $cp];
                    }
                }
            }
        }
        if ($char === '' || mb_strlen($char) !== 1) {
            return null;
        }
        $hit = self::$styled[mb_ord($char)] ?? null;

        return $hit === null ? null : [$hit[0], mb_chr($hit[1])];
    }

    private static function build(): void
    {
        if (self::$toChar !== null) {
            return;
        }
        $toChar = [];
        $toCommand = [];
        $tables = [self::GREEK, self::GREEK_UP, self::ORD, self::OPS, self::LARGE, self::INTS, self::DELIMS];
        foreach ($tables as $table) {
            foreach ($table as $name => $char) {
                $name = (string) $name;
                if (preg_match('/^[a-zA-Z]+$/', $name) !== 1 || $char === '') {
                    continue;
                }
                $toChar[$name] = $char;
            }
        }
        foreach (self::PREFER as $name) {
            if (isset($toChar[$name])) {
                $toCommand[$toChar[$name]] = $name;
            }
        }
        foreach ($toChar as $name => $char) {
            if (strlen($char) > 1 && ! isset($toCommand[$char]) && ! in_array($name, self::NO_WRITE, true)) {
                $toCommand[$char] = $name;
            }
        }
        $delims = [];
        foreach (self::DELIMS as $char) {
            if ($char !== '') {
                $delims[$char] = true;
            }
        }
        self::$toChar = $toChar;
        self::$toCommand = $toCommand;
        self::$delims = $delims;
    }
}

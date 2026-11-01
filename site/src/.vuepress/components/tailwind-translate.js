// Spike: turns Tailwind-styled HTML (or Blade) into Papercut Blade tags.
// It runs once, in the browser: whatever it can't translate is left as a Blade comment for the developer to fix.

// ---------- Parsing ----------

const VOID = new Set(['img', 'br', 'hr', 'input', 'meta', 'link', 'source', 'wbr'])

/** A forgiving HTML parser that keeps Blade echoes, comments and directives as text. */
export function parse(source) {
  const root = { type: 'el', tag: '#root', attrs: [], children: [] }
  const stack = [root]
  const top = () => stack[stack.length - 1]
  let i = 0
  let text = ''
  const flush = () => {
    if (text !== '') top().children.push({ type: 'text', text })
    text = ''
  }
  while (i < source.length) {
    const rest = source.slice(i)
    // Blade comments and echoes stay as text, even when they hold "<".
    const blade = rest.match(/^(\{\{--[\s\S]*?--\}\}|\{!![\s\S]*?!!\}|\{\{[\s\S]*?\}\})/)
    if (blade) {
      text += blade[0]
      i += blade[0].length
      continue
    }
    if (rest.startsWith('<!--')) {
      const end = source.indexOf('-->', i)
      i = end < 0 ? source.length : end + 3
      continue
    }
    const close = rest.match(/^<\/([A-Za-z][\w:.-]*)\s*>/)
    if (close) {
      flush()
      const tag = close[1].toLowerCase()
      const at = stack.map((n) => n.tag).lastIndexOf(tag)
      if (at > 0) stack.length = at
      i += close[0].length
      continue
    }
    const open = rest.match(/^<([A-Za-z][\w:.-]*)((?:\s+(?:[^\s"'>\/=]+)(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s"'>]+))?)*)\s*(\/?)>/)
    if (open) {
      flush()
      const tag = open[1].toLowerCase()
      const attrs = []
      const re = /([^\s"'>\/=]+)(?:\s*=\s*("[^"]*"|'[^']*'|[^\s"'>]+))?/g
      let m
      while ((m = re.exec(open[2])) !== null) {
        let v = m[2] ?? null
        if (v !== null && /^["']/.test(v)) v = v.slice(1, -1)
        attrs.push([m[1], v])
      }
      const el = { type: 'el', tag, attrs, children: [] }
      top().children.push(el)
      i += open[0].length
      if (tag === 'pre') {
        // Code stays literal up to the closing tag.
        const end = source.toLowerCase().indexOf('</pre>', i)
        const body = source.slice(i, end < 0 ? source.length : end)
        el.children.push({ type: 'text', text: body, raw: true })
        i = end < 0 ? source.length : end + 6
        continue
      }
      if (!open[3] && !VOID.has(tag)) stack.push(el)
      continue
    }
    text += source[i]
    i++
  }
  flush()
  return root
}

// ---------- Classes ----------

const FAMILIES = 'slate gray zinc neutral stone red orange amber yellow lime green emerald teal cyan sky blue indigo violet purple fuchsia pink rose'.split(' ')
const SHADES = '50 100 200 300 400 500 600 700 800 900 950'.split(' ')

/** "slate-50", "white", "[#ff0000]" → a Papercut colour, or null. */
function color(v) {
  if (v === 'white' || v === 'black' || v === 'transparent') return v
  const m = v.match(/^([a-z]+)-(\d+)$/)
  if (m && FAMILIES.includes(m[1]) && SHADES.includes(m[2])) return v
  const a = v.match(/^\[(#[0-9a-fA-F]{3,8}|rgba?\([^\]]+\))\]$/)
  return a ? a[1].replace(/_/g, ' ') : null
}

const FONT_SIZES = { xs: 12, sm: 14, base: 16, lg: 18, xl: 20, '2xl': 24, '3xl': 30, '4xl': 36, '5xl': 48, '6xl': 60, '7xl': 72, '8xl': 96, '9xl': 128 }
const WEIGHTS = { thin: 100, extralight: 200, light: 300, normal: 400, medium: 500, semibold: 600, bold: 700, extrabold: 800, black: 900 }
const LEADING = { none: 1, tight: 1.25, snug: 1.375, normal: 1.5, relaxed: 1.625, loose: 2 }
const TRACKING = { tighter: -0.05, tight: -0.025, normal: 0, wide: 0.025, wider: 0.05, widest: 0.1 }
const RADIUS = { none: 0, sm: 2, '': 4, md: 6, lg: 8, xl: 12, '2xl': 16, '3xl': 24 }
const MAX_W = { xs: 320, sm: 384, md: 448, lg: 512, xl: 576, '2xl': 672, '3xl': 768, '4xl': 896, '5xl': 1024, '6xl': 1152, '7xl': 1280 }
const JUSTIFY = { start: 'start', end: 'end', center: 'center', between: 'space-between', around: 'space-around', evenly: 'space-evenly' }
const ALIGN = { start: 'start', end: 'end', center: 'center', stretch: 'stretch' }

/** A CSS-pixel number as a Papercut length in the chosen unit. */
function len(px, unit) {
  if (px === 0) return '0'
  const n = unit === 'px' ? px : px * 0.75
  return `${+n.toFixed(3)}${unit}`
}

/** The spacing scale: "4" → 16px, "px" → 1px, "0.5" → 2px, "[13px]" → literal. Returns {px} or {raw} or null. */
function spacing(v) {
  if (v === 'px') return { px: 1 }
  if (/^\d+(\.\d+)?$/.test(v)) return { px: parseFloat(v) * 4 }
  const a = v.match(/^\[(-?\d*\.?\d+)(px|pt|mm|cm|in|rem|em)\]$/)
  if (a) return a[2] === 'rem' ? { px: parseFloat(a[1]) * 16 } : a[2] === 'px' ? { px: parseFloat(a[1]) } : { raw: a[1] + a[2] }
  return null
}
const show = (s, unit) => (s.raw !== undefined ? s.raw : len(s.px, unit))

/** Width-like values: the spacing scale plus fractions, full and auto. */
function size(v, unit) {
  if (v === 'full') return '100%'
  if (v === 'auto') return 'auto'
  const f = v.match(/^(\d+)\/(\d+)$/)
  if (f) return `${+((100 * f[1]) / f[2]).toFixed(4)}%`
  const p = v.match(/^\[(\d*\.?\d+%)\]$/)
  if (p) return p[1]
  const s = spacing(v)
  return s ? show(s, unit) : null
}

/**
 * Reads a class list into Papercut style properties. Returns { props, skipped, hidden }.
 * Classes are applied in Tailwind's order of specificity, then `print:` ones on top.
 */
export function readClasses(list, unit = 'pt') {
  const props = {}
  const skipped = []
  const edges = { padding: [[], [], []], margin: [[], [], []], 'border-width': [[], [], []] }
  let hidden = false
  let shown = false
  const raws = { flex: [], hidden: [] }
  let borderSet = false
  const gaps = {}
  const plain = []
  const printed = []
  for (const raw of list) {
    const c = raw.replace(/^!/, '')
    if (c.startsWith('print:')) printed.push(c.slice(6))
    else if (/^[\w-]+(?:\[[^\]]*\])?:/.test(c) && !c.startsWith('[')) skipped.push(raw)
    else plain.push(c)
  }
  const side = (prop, level, sides, value) => edges[prop][level].push([sides, value])

  const one = (c, raw) => {
    let m
    // Display and pagination
    if (c === 'hidden') return (raws.hidden.push(raw), (hidden = true))
    if (['block', 'flex', 'inline-flex', 'inline-block', 'inline', 'grid'].includes(c)) {
      shown = true
      if (c === 'flex' || c === 'inline-flex') (raws.flex.push(raw), (props.$flex = props.$flex ?? 'row'))
      if (c === 'grid') return skipped.push(raw)
      return
    }
    if (c === 'flex-row') return (raws.flex.push(raw), (props.$flex = 'row'))
    if (c === 'flex-col') return (raws.flex.push(raw), (props.$flex = 'column'))
    if (c === 'break-inside-avoid' || c === 'break-inside-avoid-page' || c === 'keep-together') return (props['keep-together'] = true)
    if (c === 'keep-with-next') return (props['keep-with-next'] = true)
    if (c === 'break-before-page') return (props['break-before'] = true)
    if (c === 'break-after-page') return (props['break-after'] = true)

    // Flex
    if (c === 'flex-wrap') return (props.wrap = true)
    if (c === 'flex-nowrap') return
    if (c === 'flex-1') return Object.assign(props, { grow: '1', shrink: '1', basis: '0%' })
    if (c === 'flex-auto') return Object.assign(props, { grow: '1', shrink: '1' })
    if (c === 'flex-none') return Object.assign(props, { grow: '0', shrink: '0' })
    if ((m = c.match(/^(grow|flex-grow|shrink|flex-shrink)(?:-(\d+))?$/))) return (props[m[1].replace('flex-', '')] = m[2] ?? '1')
    if ((m = c.match(/^basis-(.+)$/)) && size(m[1], unit)) return (props.basis = size(m[1], unit))
    if ((m = c.match(/^justify-(\w+)$/)) && JUSTIFY[m[1]]) return (props.justify = JUSTIFY[m[1]])
    if ((m = c.match(/^items-(\w+)$/)) && ALIGN[m[1]]) return (props['align-items'] = ALIGN[m[1]])
    if ((m = c.match(/^self-(\w+)$/)) && ALIGN[m[1]]) return (props['align-self'] = ALIGN[m[1]])
    if ((m = c.match(/^gap(?:-([xy]))?-(.+)$/)) && spacing(m[2])) return (gaps[m[1] ?? 'all'] = { v: show(spacing(m[2]), unit), raw })
    if ((m = c.match(/^space-([xy])-(.+)$/)) && spacing(m[2])) return (gaps['space-' + m[1]] = { v: show(spacing(m[2]), unit), raw })

    // Padding and margin
    if ((m = c.match(/^(-?)([pm])([xytrbl]?)-(.+)$/))) {
      const prop = m[2] === 'p' ? 'padding' : 'margin'
      if (m[4] === 'auto' || m[1] === '-') return skipped.push(raw)
      const s = spacing(m[4])
      if (!s) return skipped.push(raw)
      const v = show(s, unit)
      const sides = { '': 'trbl', x: 'rl', y: 'tb', t: 't', r: 'r', b: 'b', l: 'l' }[m[3]]
      return side(prop, m[3] === '' ? 0 : m[3] === 'x' || m[3] === 'y' ? 1 : 2, sides, v)
    }

    // Sizes
    if ((m = c.match(/^(w|h|min-w|min-h|max-w|max-h|size)-(.+)$/))) {
      const name = { w: 'width', h: 'height', 'min-w': 'min-width', 'min-h': 'min-height', 'max-w': 'max-width', 'max-h': 'max-height' }[m[1]]
      let v = m[1] === 'max-w' && MAX_W[m[2]] ? len(MAX_W[m[2]], unit) : size(m[2], unit)
      if (v === 'auto' && m[1] !== 'w' && m[1] !== 'h') v = null
      if (!v) return skipped.push(raw)
      if (m[1] === 'size') return Object.assign(props, { width: v, height: v })
      return (props[name] = v)
    }

    // Borders and corners
    if (c === 'border-solid' || c === 'border-dashed') return (props['border-style'] = c.slice(7))
    if ((m = c.match(/^border(?:-([xytrbl]))?(?:-(\d+|\[\d*\.?\d+px\]))?$/))) {
      const px = m[2] === undefined ? 1 : parseFloat(m[2].replace(/[[\]px]/g, ''))
      const sides = { undefined: 'trbl', x: 'rl', y: 'tb', t: 't', r: 'r', b: 'b', l: 'l' }[m[1]]
      borderSet = true
      return side('border-width', m[1] === undefined ? 0 : m[1] === 'x' || m[1] === 'y' ? 1 : 2, sides, len(px, unit))
    }
    if ((m = c.match(/^border-(.+)$/)) && color(m[1])) return (props['border-color'] = color(m[1]))
    if ((m = c.match(/^rounded(?:-(none|sm|md|lg|xl|2xl|3xl|\[\d*\.?\d+px\]))?$/))) {
      const k = m[1] ?? ''
      return (props.radius = RADIUS[k] !== undefined ? len(RADIUS[k], unit) : len(parseFloat(k.slice(1)), unit))
    }

    // Colour
    if ((m = c.match(/^bg-(.+)$/)) && color(m[1])) return (props.background = color(m[1]))
    if ((m = c.match(/^opacity-(\d+)$/))) return (props.opacity = String(+(m[1] / 100).toFixed(2)))

    // Text
    if ((m = c.match(/^text-(.+)$/))) {
      if (FONT_SIZES[m[1]]) return (props['font-size'] = len(FONT_SIZES[m[1]], unit))
      if (['left', 'center', 'right', 'justify'].includes(m[1])) return (props['text-align'] = m[1])
      if (m[1] === 'start' || m[1] === 'end') return (props['text-align'] = m[1] === 'start' ? 'left' : 'right')
      if (color(m[1])) return (props.color = color(m[1]))
      const s = m[1].match(/^\[(\d*\.?\d+)(px|pt|rem)\]$/)
      if (s) return (props['font-size'] = s[2] === 'pt' ? s[1] + 'pt' : len(parseFloat(s[1]) * (s[2] === 'rem' ? 16 : 1), unit))
    }
    if ((m = c.match(/^font-(\w+)$/)) && WEIGHTS[m[1]]) {
      delete props.bold
      delete props.weight
      return WEIGHTS[m[1]] === 700 ? (props.bold = true) : (props.weight = String(WEIGHTS[m[1]]))
    }
    if (c === 'italic') return (props.italic = true)
    if (c === 'not-italic') return (props.italic = false)
    if (c === 'underline') return (props.underline = true)
    if (c === 'line-through') return (props.strike = true)
    if (c === 'no-underline') return (props.underline = false)
    if ((m = c.match(/^leading-(\w+|\[\d*\.?\d+\])$/))) {
      if (LEADING[m[1]] !== undefined) return (props['line-height'] = String(LEADING[m[1]]))
      if (/^\[\d*\.?\d+\]$/.test(m[1])) return (props['line-height'] = m[1].slice(1, -1))
    }
    if ((m = c.match(/^tracking-(\w+)$/)) && TRACKING[m[1]] !== undefined) return (props['letter-spacing'] = TRACKING[m[1]] === 0 ? '0' : `${TRACKING[m[1]]}em`)
    if (c === 'whitespace-pre-wrap' || c === 'whitespace-pre') return (props['white-space'] = 'pre-wrap')
    if (c === 'whitespace-normal') return (props['white-space'] = 'normal')

    skipped.push(raw)
  }

  // Tailwind's own order: all sides, then an axis, then one side.
  for (const c of plain) one(c, c)
  for (const c of printed) one(c, 'print:' + c)

  for (const [prop, levels] of Object.entries(edges)) {
    if (levels.every((l) => l.length === 0)) continue
    const out = { t: '0', r: '0', b: '0', l: '0' }
    for (const level of levels) for (const [sides, v] of level) for (const s of sides) out[s] = v
    const { t, r, b, l } = out
    props[prop] = t === r && r === b && b === l ? t : t === b && r === l ? `${t} ${r}` : `${t} ${r} ${b} ${l}`
  }
  if (borderSet && !props['border-color']) props['border-color'] = 'gray-200'
  if (shown && printed.some((c) => c !== 'hidden')) hidden = false
  return { props, skipped, hidden, gaps, raws }
}

/** The classes a tag couldn't use: flex and gaps outside boxes, hidden where nothing can be removed. */
export function unused(r, { flex = false, gap = false, hidden = false } = {}) {
  return [...(flex ? [] : r.raws.flex), ...(gap ? [] : Object.values(r.gaps).map((g) => g.raw)), ...(hidden ? [] : r.raws.hidden)]
}

// ---------- Translating ----------

const INLINE = new Set(['span', 'b', 'strong', 'i', 'em', 'u', 's', 'del', 'strike', 'mark', 'code', 'a', 'br', 'small', 'sub', 'sup', 'abbr', 'time', 'label'])
const INLINE_TAG = { b: 'b', strong: 'b', i: 'i', em: 'i', u: 'u', s: 's', del: 's', strike: 's', mark: 'mark', code: 'code' }
const SPAN_PROPS = ['color', 'font', 'font-size', 'weight', 'bold', 'italic', 'underline', 'strike', 'letter-spacing']
const TEXT_PROPS = new Set([...SPAN_PROPS, 'line-height', 'text-align', 'white-space'])
const ORDER = ['gap', 'direction', 'justify', 'align-items', 'align-self', 'wrap', 'grow', 'shrink', 'basis', 'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height', 'padding', 'margin', 'border-width', 'border-style', 'border-color', 'radius', 'background', 'opacity', 'font', 'font-size', 'weight', 'bold', 'italic', 'underline', 'strike', 'color', 'line-height', 'letter-spacing', 'text-align', 'white-space', 'keep-together', 'keep-with-next', 'break-before', 'break-after']
const TEXT_TAGS = new Set(['text', 'p', 'h1', 'h2', 'h3', 'h4'])
const BOX_TAGS = new Set(['row', 'column', 'view'])

const classesOf = (el) => (el.attrs.find(([k]) => k === 'class')?.[1] ?? '').split(/\s+/).filter(Boolean)
const attr = (el, name) => el.attrs.find(([k]) => k === name)?.[1]
const esc = (v) => String(v).replace(/"/g, '&quot;')
/** Papercut only follows absolute links; a Blade echo (like route()) gives one at render time. */
const linkable = (h) => h != null && /^(https?:|mailto:|tel:|#|\{\{|\{!!)/i.test(h.trim())
/** Escapes "<" in text so stray tags don't reach Papercut's inline markup; Blade echoes are left alone. */
const escText = (t) => t.split(/(\{\{[\s\S]*?\}\}|\{!![\s\S]*?!!\})/).map((part, i) => (i % 2 ? part : part.replace(/</g, '&lt;'))).join('')
const isBladeLine = (t) => t.trim() !== '' && t.trim().split('\n').every((l) => /^\s*(@\w+|\{\{--.*--\}\})/.test(l) || l.trim() === '')

/** Renders props as attributes for a Papercut tag. */
function attrs(props, tag, extra = []) {
  const out = [...extra]
  const p = {}
  for (const k of Object.keys(props).sort((a, b) => rank(a) - rank(b))) p[k] = props[k]
  if (TEXT_TAGS.has(tag)) {
    if (p['font-size']) (p.size = p['font-size']), delete p['font-size']
    if (p['text-align']) (p.align = p['text-align']), delete p['text-align']
  } else if (BOX_TAGS.has(tag) && p['align-items']) {
    p.align = p['align-items']
    delete p['align-items']
  }
  const was = { size: 'font-size', align: TEXT_TAGS.has(tag) ? 'text-align' : 'align-items' }
  const keys = Object.keys(p).filter((k) => !k.startsWith('$'))
  keys.sort((a, b) => rank(was[a] ?? a) - rank(was[b] ?? b))
  for (const k of keys) {
    const v = p[k]
    if (v === true) out.push(k)
    else if (v === false) out.push(`${k}="false"`)
    else out.push(`${k}="${esc(v)}"`)
  }
  return out.length ? ' ' + out.join(' ') : ''
}
function rank(k) {
  const i = ORDER.indexOf(k)
  return i < 0 ? 99 : i
}

/** Whether an element holds only text and inline elements. */
function inlineOnly(el) {
  return el.children.every((c) => c.type === 'text' || (INLINE.has(c.tag) && inlineOnly(c)))
}
const hasContent = (el) => el.children.some((c) => c.type === 'el' || c.text.trim() !== '')

/**
 * Translates Tailwind-styled markup into Papercut Blade.
 * Returns { output, skipped } where skipped lists every class or tag that didn't carry over.
 */
export function translate(source, { unit = 'pt' } = {}) {
  const root = parse(source.replace(/\r\n/g, '\n'))
  const skippedAll = []
  const lines = []
  const pad = (d) => '    '.repeat(d)

  const note = (d, items) => {
    if (items.length === 0) return
    skippedAll.push(...items)
    lines.push(`${pad(d)}{{-- not translated: ${items.join(' ')} --}}`)
  }

  /** Inline markup for a text tag's content. */
  function inline(nodes, skipped) {
    let s = ''
    for (const n of nodes) {
      if (n.type === 'text') {
        s += escText(n.text.replace(/\s+/g, ' '))
        continue
      }
      if (n.tag === 'br') {
        s += '<br>'
        continue
      }
      const r = readClasses(classesOf(n), unit)
      const { props } = r
      const sk = [...r.skipped, ...unused(r)]
      const spanProps = {}
      for (const [k, v] of Object.entries(props)) {
        if (SPAN_PROPS.includes(k)) spanProps[k] = v
        else if (!k.startsWith('$')) sk.push(`${k}(on <${n.tag}>)`)
      }
      skipped.push(...sk)
      let inner = inline(n.children, skipped)
      if (Object.keys(spanProps).length) inner = `<span${attrs(spanProps, 'span')}>${inner}</span>`
      if (n.tag === 'a' && linkable(attr(n, 'href'))) s += `<a href="${esc(attr(n, 'href'))}">${inner}</a>`
      else if (n.tag === 'a') (skipped.push(`href="${attr(n, 'href') ?? ''}"(not absolute)`), (s += inner))
      else if (INLINE_TAG[n.tag]) s += `<${INLINE_TAG[n.tag]}>${inner}</${INLINE_TAG[n.tag]}>`
      else s += inner
    }
    return s
  }

  /** A text tag with the given element's inline content. */
  function textTag(tag, props, content, d, skipped, extra = []) {
    const body = inline(content, skipped).trim()
    note(d, skipped)
    lines.push(`${pad(d)}<x-doc::${tag}${attrs(props, tag, extra)}>${body}</x-doc::${tag}>`)
  }

  /** Block children: runs of inline content become text tags. */
  function blocks(children, d, parentProps = {}) {
    const row = parentProps.$flex === 'row'
    let run = []
    const flushRun = () => {
      if (run.some((n) => n.type === 'el' || n.text.trim() !== '')) {
        const text = run.map((n) => (n.type === 'text' ? n.text : '')).join('')
        if (run.every((n) => n.type === 'text') && isBladeLine(text)) {
          for (const l of text.trim().split('\n')) lines.push(pad(d) + l.trim())
        } else {
          // Blade directives inside a run go on their own lines around the text.
          textTag('text', {}, run, d, [])
        }
      }
      run = []
    }
    for (const n of children) {
      if (n.type === 'text' && isBladeLine(n.text)) {
        flushRun()
        for (const l of n.text.trim().split('\n')) lines.push(pad(d) + l.trim())
      } else if (n.type === 'text' || (!row && INLINE.has(n.tag) && inlineOnly(n))) {
        if (row && n.text.trim() !== '' && !isBladeLine(n.text)) (flushRun(), run.push(n), flushRun())
        else if (!row) run.push(n)
      } else {
        flushRun()
        element(n, d, parentProps)
      }
    }
    flushRun()
  }

  function element(el, d, parentProps) {
    const tag = el.tag
    const r = readClasses(classesOf(el), unit)
    const { props, skipped, hidden, gaps } = r
    const notBox = () => skipped.push(...unused(r, { hidden: true }))
    if (hidden) {
      skippedAll.push(...skipped)
      lines.push(`${pad(d)}{{-- removed <${tag}>: hidden${skipped.length ? ', also ' + skipped.join(' ') : ''} --}}`)
      return
    }
    const flex = props.$flex
    const extraAttrs = el.attrs.filter(([k]) => k !== 'class').map(([k, v]) => (v === null ? k : `${k}="${esc(v)}"`))

    // Already a Papercut tag: keep it, turn its classes into attributes, its own attributes win.
    if (tag.startsWith('x-doc::')) {
      const name = tag.slice(7)
      skipped.push(...r.raws.flex)
      const own = new Set(el.attrs.map(([k]) => k.replace(/^:/, '')))
      for (const k of Object.keys(props)) if (own.has(k)) delete props[k]
      if (!own.has('gap')) applyGap(props, gaps, name === 'row' ? 'x' : 'y', skipped)
      note(d, skipped)
      const open = `${pad(d)}<x-doc::${name}${attrs(props, name, extraAttrs)}`
      if (!hasContent(el)) return lines.push(`${open} />`)
      if (TEXT_TAGS.has(name) || inlineOnly(el)) {
        const sk = []
        const body = inline(el.children, sk).trim()
        note(d, sk)
        return lines.push(`${open}>${body}</x-doc::${name}>`)
      }
      lines.push(`${open}>`)
      blocks(el.children, d + 1, props)
      return lines.push(`${pad(d)}</x-doc::${name}>`)
    }
    // Other Blade components pass through untouched.
    if (tag.startsWith('x-') || tag.startsWith('livewire:')) {
      lines.push(`${pad(d)}<${tag}${el.attrs.length ? ' ' + el.attrs.map(([k, v]) => (v === null ? k : `${k}="${v}"`)).join(' ') : ''}>`)
      blocks(el.children, d + 1)
      return lines.push(`${pad(d)}</${tag}>`)
    }

    if (['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'hr', 'img', 'pre', 'table'].includes(tag) || (tag === 'a' && linkable(attr(el, 'href')))) notBox()
    if (/^h[1-6]$/.test(tag)) {
      const level = Math.min(4, +tag[1])
      if (+tag[1] > 4) skipped.push(`<${tag}>(as h4)`)
      return textTag(`h${level}`, props, el.children, d, skipped)
    }
    if (tag === 'p') return textTag('p', props, el.children, d, skipped)
    if (tag === 'br') return
    if (tag === 'hr') {
      note(d, skipped)
      return lines.push(`${pad(d)}<x-doc::view${attrs({ 'border-width': `${len(1, unit)} 0 0 0`, 'border-color': 'gray-200', ...props }, 'view')} />`)
    }
    if (tag === 'img') {
      note(d, skipped)
      return lines.push(`${pad(d)}<x-doc::image src="${esc(attr(el, 'src') ?? '')}"${attrs(props, 'image')} />`)
    }
    if (tag === 'pre') {
      note(d, skipped)
      const body = (el.children[0]?.text ?? '').replace(/^\s*<code[^>]*>/, '').replace(/<\/code>\s*$/, '')
      return lines.push(`${pad(d)}<x-doc::pre${attrs(props, 'pre')}>${body}</x-doc::pre>`)
    }
    if (tag === 'ul' || tag === 'ol') {
      const marker = classesOf(el).find((c) => /^list-(none|disc|decimal)$/.test(c))
      const extra = marker ? [`marker="${marker.slice(5)}"`] : []
      const keep = skipped.filter((s) => !/^list-(none|disc|decimal|inside|outside)$/.test(s))
      keep.push(...unused(r, { hidden: true }))
      note(d, keep)
      lines.push(`${pad(d)}<x-doc::${tag}${attrs(props, tag, extra)}>`)
      for (const c of el.children) {
        if (c.type === 'el' && c.tag === 'li') {
          const li = readClasses(classesOf(c), unit)
          note(d + 1, [...li.skipped, ...unused(li)])
          if (inlineOnly(c)) {
            const sk = []
            const body = inline(c.children, sk).trim()
            note(d + 1, sk)
            lines.push(`${pad(d + 1)}<x-doc::li${attrs(li.props, 'li')}>${body}</x-doc::li>`)
          } else {
            lines.push(`${pad(d + 1)}<x-doc::li${attrs(li.props, 'li')}>`)
            blocks(c.children, d + 2)
            lines.push(`${pad(d + 1)}</x-doc::li>`)
          }
        } else if (c.type === 'text' && isBladeLine(c.text)) {
          for (const l of c.text.trim().split('\n')) lines.push(pad(d + 1) + l.trim())
        }
      }
      return lines.push(`${pad(d)}</x-doc::${tag}>`)
    }
    if (tag === 'table') return table(el, props, skipped, d)

    // A link around blocks.
    if (tag === 'a' && !linkable(attr(el, 'href'))) skipped.push(`href="${attr(el, 'href') ?? ''}"(not absolute)`)
    if (tag === 'a' && linkable(attr(el, 'href'))) {
      note(d, skipped)
      lines.push(`${pad(d)}<x-doc::link href="${esc(attr(el, 'href') ?? '#')}"${attrs(props, 'link')}>`)
      blocks(el.children, d + 1)
      return lines.push(`${pad(d)}</x-doc::link>`)
    }

    // Everything else is a box. Inline-only boxes become text.
    if (!INLINE.has(tag) && !['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav', 'figure', 'figcaption', 'address', 'blockquote', 'span', 'label', 'small', 'strong', 'b', 'em', 'i', 'li', 'dl', 'dt', 'dd', 'form', 'fieldset'].includes(tag)) {
      skipped.push(`<${tag}>(as a column)`)
    }
    if (!flex && inlineOnly(el) && hasContent(el)) {
      skipped.push(...unused(r, { flex: true, hidden: true }))
      // Bold, links and the like keep their tag inside the text; their classes go on the text tag.
      const keepTag = INLINE_TAG[tag] || tag === 'a'
      return textTag('text', props, keepTag ? [{ ...el, attrs: el.attrs.filter(([k]) => k !== 'class') }] : el.children, d, skipped)
    }
    const box = flex ?? 'column'
    applyGap(props, gaps, box === 'row' ? 'x' : 'y', skipped)
    note(d, skipped)
    if (!hasContent(el)) return lines.push(`${pad(d)}<x-doc::${box}${attrs(props, box)} />`)
    lines.push(`${pad(d)}<x-doc::${box}${attrs(props, box)}>`)
    blocks(el.children, d + 1, props)
    lines.push(`${pad(d)}</x-doc::${box}>`)
  }

  /** Papercut has one gap per box: use the one on its own axis. */
  function applyGap(props, gaps, axis, skipped) {
    const mine = [axis, `space-${axis}`, 'all']
    const own = mine.map((k) => gaps[k]).find(Boolean)
    if (own) props.gap = own.v
    for (const [k, g] of Object.entries(gaps)) if (!mine.includes(k)) skipped.push(g.raw)
  }

  function table(el, props, skipped, d) {
    note(d, skipped)
    lines.push(`${pad(d)}<x-doc::table${attrs(props, 'table')}>`)
    const rows = []
    let head = null
    let headProps = {}
    let headSkipped = []
    const walk = (nodes, inHead) => {
      for (const n of nodes) {
        if (n.type === 'text' && isBladeLine(n.text)) (inHead ? (head ??= []) : rows).push(n)
        if (n.type !== 'el') continue
        if (n.tag === 'thead') {
          const t = readClasses(classesOf(n), unit)
          headProps = t.props
          headSkipped = [...t.skipped, ...unused(t)]
          walk(n.children, true)
        }
        else if (n.tag === 'tbody' || n.tag === 'tfoot') walk(n.children, false)
        else if (n.tag === 'tr') (inHead ? (head ??= []) : rows).push(n)
      }
    }
    walk(el.children, false)
    const cells = (tr, d2, inherited = {}) => {
      for (const c of tr.children) {
        if (c.type !== 'el' || (c.tag !== 'td' && c.tag !== 'th')) continue
        const r = readClasses(classesOf(c), unit)
        r.props = { ...inherited, ...r.props }
        const extra = ['colspan', 'rowspan'].filter((k) => attr(c, k)).map((k) => `${k}="${attr(c, k)}"`)
        note(d2, [...r.skipped, ...unused(r)])
        if (inlineOnly(c)) {
          const sk = []
          const body = inline(c.children, sk).trim()
          note(d2, sk)
          lines.push(`${pad(d2)}<x-doc::${c.tag}${attrs(r.props, c.tag, extra)}>${body}</x-doc::${c.tag}>`)
        } else {
          lines.push(`${pad(d2)}<x-doc::${c.tag}${attrs(r.props, c.tag, extra)}>`)
          blocks(c.children, d2 + 1)
          lines.push(`${pad(d2)}</x-doc::${c.tag}>`)
        }
      }
    }
    const row = (tr, d2, tag = 'tr', inherited = {}) => {
      if (tr.type === 'text') {
        for (const l of tr.text.trim().split('\n')) lines.push(pad(d2) + l.trim())
        return
      }
      const r = readClasses(classesOf(tr), unit)
      note(d2, [...r.skipped, ...unused(r)])
      // A one-row thead has no row tag, so the row's classes go on its cells.
      lines.push(`${pad(d2)}<x-doc::${tag}${tag === 'tr' ? attrs(r.props, 'tr') : ''}>`)
      cells(tr, d2 + 1, tag === 'tr' ? inherited : { ...inherited, ...r.props })
      lines.push(`${pad(d2)}</x-doc::${tag}>`)
    }
    if (head) {
      note(d + 1, headSkipped)
      const trs = head.filter((n) => n.type === 'el')
      if (trs.length === 1 && head.length === 1) row(head[0], d + 1, 'thead', headProps)
      else {
        lines.push(`${pad(d + 1)}<x-doc::thead>`)
        for (const tr of head) row(tr, d + 2, 'tr', headProps)
        lines.push(`${pad(d + 1)}</x-doc::thead>`)
      }
    }
    for (const tr of rows) row(tr, d + 1)
    lines.push(`${pad(d)}</x-doc::table>`)
  }

  blocks(root.children, 0)
  return { output: lines.join('\n') + '\n', skipped: skippedAll }
}

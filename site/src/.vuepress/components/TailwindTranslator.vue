<script setup>
import { computed, ref } from 'vue'
import { samples } from './tailwind-samples.js'
import { translate } from './tailwind-translate.js'

const source = ref(samples[0].source)
const unit = ref('pt')
const copied = ref(false)

const result = computed(() => {
  try {
    return { ...translate(source.value, { unit: unit.value }), error: null }
  } catch (e) {
    return { output: '', skipped: [], error: String(e) }
  }
})

function pick(event) {
  const s = samples.find((x) => x.name === event.target.value)
  if (s) source.value = s.source
}

async function copy() {
  try {
    await navigator.clipboard.writeText(result.value.output)
    copied.value = true
    setTimeout(() => (copied.value = false), 1500)
  } catch {
    copied.value = false
  }
}
</script>

<template>
  <div class="tw-translator">
    <div class="tw-bar">
      <label>
        Example
        <select @change="pick">
          <option v-for="s in samples" :key="s.name" :value="s.name">{{ s.name }}</option>
        </select>
      </label>
      <label>
        Units
        <select v-model="unit">
          <option value="pt">points (1px = 0.75pt)</option>
          <option value="px">pixels</option>
        </select>
      </label>
    </div>
    <div class="tw-panes">
      <div class="tw-pane">
        <div class="tw-head">Tailwind HTML or Blade</div>
        <textarea v-model="source" spellcheck="false" aria-label="Tailwind HTML or Blade"></textarea>
      </div>
      <div class="tw-pane">
        <div class="tw-head">
          Papercut Blade
          <button type="button" @click="copy">{{ copied ? 'Copied' : 'Copy' }}</button>
        </div>
        <pre class="tw-out" aria-live="polite">{{ result.error ?? result.output }}</pre>
      </div>
    </div>
    <p v-if="result.skipped.length" class="tw-skipped">
      <strong>Not translated ({{ result.skipped.length }}):</strong>
      <code v-for="(s, i) in result.skipped" :key="i">{{ s }}</code>
    </p>
    <p v-else class="tw-skipped">Every class was translated.</p>
  </div>
</template>

<style scoped>
.tw-translator {
  margin: 1.5rem 0;
}
.tw-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 0.75rem;
}
.tw-bar label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
}
.tw-bar select {
  font: inherit;
  padding: 0.25rem 0.4rem;
  border: 1px solid var(--vp-c-border);
  border-radius: 4px;
  background: var(--vp-c-bg);
  color: var(--vp-c-text);
}
.tw-panes {
  display: grid;
  gap: 1rem;
}
.tw-pane {
  display: flex;
  flex-direction: column;
  min-width: 0;
  border: 1px solid var(--vp-c-border);
  border-radius: 6px;
  overflow: hidden;
}
.tw-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.4rem 0.75rem;
  font-size: 0.8rem;
  font-weight: 600;
  border-bottom: 1px solid var(--vp-c-border);
  background: var(--vp-c-bg-alt);
}
.tw-head button {
  font: inherit;
  font-weight: 500;
  padding: 0.1rem 0.6rem;
  border: 1px solid var(--vp-c-border);
  border-radius: 4px;
  background: var(--vp-c-bg);
  color: var(--vp-c-text);
  cursor: pointer;
}
.tw-pane textarea,
.tw-out {
  flex: 1;
  min-height: 14rem;
  margin: 0;
  padding: 0.75rem;
  border: 0;
  border-radius: 0;
  font-family: var(--vp-font-family-mono, ui-monospace, monospace);
  font-size: 0.8rem;
  line-height: 1.5;
  background: var(--vp-c-bg);
  color: var(--vp-c-text);
  white-space: pre;
  overflow: auto;
  resize: vertical;
}
.tw-pane textarea:focus {
  outline: 2px solid var(--vp-c-accent-soft);
}
.tw-skipped {
  font-size: 0.85rem;
}
.tw-skipped code {
  margin-left: 0.4rem;
}
</style>

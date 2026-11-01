<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { withBase } from 'vuepress/client'

const props = defineProps({
  tile: { type: Object, required: true },
  startIndex: { type: Number, default: 0 },
})
const emit = defineEmits(['close'])

// The default look, then each named layout variant.
const slides = computed(() => [
  { id: props.tile.id, label: null, alt: props.tile.alt },
  ...props.tile.variants.map((v) => ({ id: `${props.tile.id}.${v.id}`, label: v.label, alt: v.alt })),
])
const index = ref(props.startIndex)
const slide = computed(() => slides.value[index.value])
const many = computed(() => slides.value.length > 1)
const dialog = ref(null)
const closeButton = ref(null)

function go(step) {
  if (!many.value) return
  const n = slides.value.length
  index.value = (index.value + step + n) % n
}

function onKey(e) {
  if (e.key === 'Escape') {
    e.preventDefault()
    emit('close')
  } else if (e.key === 'ArrowRight') {
    go(1)
  } else if (e.key === 'ArrowLeft') {
    go(-1)
  } else if (e.key === 'Tab') {
    // Keep focus inside the dialog while it is open.
    const items = [...dialog.value.querySelectorAll('button, a[href]')].filter((el) => !el.disabled)
    if (items.length === 0) return
    const first = items[0]
    const last = items[items.length - 1]
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault()
      first.focus()
    }
  }
}

// Swipe: a horizontal drag of 40px or more steps through the slides.
let startX = null
function onPointerDown(e) {
  startX = e.clientX
}
function onPointerUp(e) {
  if (startX === null) return
  const dx = e.clientX - startX
  startX = null
  if (Math.abs(dx) >= 40) go(dx < 0 ? 1 : -1)
}

onMounted(async () => {
  document.addEventListener('keydown', onKey)
  document.documentElement.style.overflow = 'hidden'
  await nextTick()
  closeButton.value?.focus()
})
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKey)
  document.documentElement.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div class="gallery-lightbox" @click.self="emit('close')">
      <div ref="dialog" class="gallery-dialog" role="dialog" aria-modal="true" :aria-label="`${tile.title}, enlarged`">
        <button ref="closeButton" type="button" class="gallery-close" aria-label="Close" @click="emit('close')">×</button>
        <div class="gallery-stage" @pointerdown="onPointerDown" @pointerup="onPointerUp">
          <button v-if="many" type="button" class="gallery-step" aria-label="Previous variant" @click="go(-1)">‹</button>
          <img :src="withBase(`/gallery/${slide.id}.png`)" :alt="slide.alt" draggable="false" />
          <button v-if="many" type="button" class="gallery-step" aria-label="Next variant" @click="go(1)">›</button>
        </div>
        <div class="gallery-caption">
          <div aria-live="polite">
            <strong>{{ tile.title }}</strong>
            <code v-if="slide.label">{{ slide.label }}</code>
            <span v-if="many" class="gallery-count">{{ index + 1 }} of {{ slides.length }}</span>
          </div>
          <div v-if="many" class="gallery-dots" aria-hidden="true">
            <span v-for="(s, i) in slides" :key="s.id" :class="{ on: i === index }" />
          </div>
          <a :href="withBase(tile.api)">{{ tile.title }} API →</a>
        </div>
      </div>
    </div>
  </Teleport>
</template>

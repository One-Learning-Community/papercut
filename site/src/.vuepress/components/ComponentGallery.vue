<script setup>
import { nextTick, ref } from 'vue'
import { withBase } from 'vuepress/client'
import manifest from '../../../gallery/gallery.json'
import GalleryLightbox from './GalleryLightbox.vue'

// The tile shown in the lightbox, and the link that opened it, so focus can go back there.
const open = ref(null)
let opener = null
function show(tile, event) {
  opener = event.currentTarget
  open.value = tile
}
async function close() {
  open.value = null
  await nextTick()
  opener?.focus()
}

const image = (id) => withBase(`/gallery/${id}.png`)
const slug = (title) => title.toLowerCase().replace(/[^a-z0-9]+/g, '-')
const badge = (n) => (n === 1 ? '1 variant' : `${n} variants`)
</script>

<template>
  <div class="component-gallery">
    <section v-for="group in manifest.groups" :key="group.title" class="gallery-group">
      <h2 :id="slug(group.title)">{{ group.title }}</h2>
      <div class="gallery-grid">
        <figure v-for="tile in group.tiles" :key="tile.id" class="gallery-tile">
          <a class="gallery-thumb" :href="image(tile.id)" @click.prevent="show(tile, $event)">
            <img :src="image(tile.id)" :alt="tile.alt" loading="lazy" />
            <span v-if="tile.variants.length" class="gallery-badge">{{ badge(tile.variants.length) }}</span>
          </a>
          <figcaption>
            <span class="gallery-name">{{ tile.title }}</span>
            <a class="gallery-api" :href="withBase(tile.api)">API →</a>
          </figcaption>
        </figure>
      </div>
    </section>
    <GalleryLightbox v-if="open" :tile="open" @close="close" />
  </div>
</template>

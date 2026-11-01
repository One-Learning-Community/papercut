<script setup>
import { withBase } from 'vuepress/client'
import manifest from '../../../gallery/gallery.json'

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
          <a class="gallery-thumb" :class="{ pages: tile.pages > 1 || tile.id === 'pdf-template' }" :href="image(tile.id)" :data-tile="tile.id">
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
  </div>
</template>

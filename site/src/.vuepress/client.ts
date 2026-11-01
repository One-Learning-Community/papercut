import { defineClientConfig } from 'vuepress/client'
import ComponentGallery from './components/ComponentGallery.vue'
import TailwindTranslator from './components/TailwindTranslator.vue'

export default defineClientConfig({
  enhance({ app }) {
    app.component('ComponentGallery', ComponentGallery)
    app.component('TailwindTranslator', TailwindTranslator)
  },
})

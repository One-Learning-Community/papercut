import { defineClientConfig } from 'vuepress/client'
import TailwindTranslator from './components/TailwindTranslator.vue'

export default defineClientConfig({
  enhance({ app }) {
    app.component('TailwindTranslator', TailwindTranslator)
  },
})

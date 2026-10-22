import { viteBundler } from '@vuepress/bundler-vite'
import { searchPlugin } from '@vuepress/plugin-search'
import { shikiPlugin } from '@vuepress/plugin-shiki'
import { defaultTheme } from '@vuepress/theme-default'
import { defineUserConfig } from 'vuepress'

const repo = 'One-Learning-Community/papercut'
const hostname = 'https://one-learning-community.github.io'
// GitHub Pages serves a project site from /<repo>/. Override with DOCS_BASE for a custom domain.
const base = (process.env.DOCS_BASE ?? '/papercut/') as `/${string}/`

export default defineUserConfig({
  lang: 'en-US',
  title: 'Papercut',
  description: 'Paperwork without the sting. Page-aware PDF layout for PHP and Laravel, written in Blade or plain PHP.',
  base,

  head: [
    ['meta', { name: 'theme-color', content: '#4f46e5' }],
    ['link', { rel: 'icon', href: `${base}favicon.svg`, type: 'image/svg+xml' }],
    ['link', { rel: 'icon', href: `${base}favicon.ico`, sizes: '32x32' }],
    ['link', { rel: 'apple-touch-icon', href: `${base}favicon-180.png` }],
    ['meta', { property: 'og:image', content: `${hostname}${base}papercut-social.png` }],
    ['meta', { property: 'og:image:width', content: '1280' }],
    ['meta', { property: 'og:image:height', content: '640' }],
    ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
  ],

  bundler: viteBundler(),

  theme: defaultTheme({
    hostname,
    logo: '/papercut-mark.svg',
    logoDark: '/papercut-mark-dark.svg',
    logoAlt: 'Papercut',
    repo,
    docsRepo: repo,
    docsBranch: 'main',
    docsDir: 'site/src',
    editLinkText: 'Edit this page on GitHub',
    contributors: false,
    themePlugins: {
      prismjs: false,
    },

    navbar: [
      { text: 'Guide', link: '/guide/' },
      { text: 'Laravel', link: '/laravel/' },
      { text: 'Reference', link: '/reference/blade-tags.html' },
      { text: 'Examples', link: '/examples.html' },
      { text: 'Changelog', link: `https://github.com/${repo}/blob/main/CHANGELOG.md` },
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Start',
          children: ['/guide/', '/guide/installation.md', '/guide/first-document.md'],
        },
        {
          text: 'Building documents',
          children: [
            '/guide/layout.md',
            '/guide/text.md',
            '/guide/tables.md',
            '/guide/pages.md',
            '/guide/images-and-links.md',
            '/guide/decorators.md',
          ],
        },
        {
          text: 'Look and output',
          children: ['/guide/styles.md', '/guide/fonts.md', '/guide/rich-text.md', '/guide/html-export.md', '/guide/web-fonts.md', '/guide/passwords-and-signatures.md'],
        },
      ],
      '/laravel/': [
        {
          text: 'Laravel',
          children: ['/laravel/', '/laravel/configuration.md', '/laravel/preview.md'],
        },
      ],
      '/reference/': [
        {
          text: 'Reference',
          children: ['/reference/blade-tags.md', '/reference/style-attributes.md', '/reference/decorator-styles.md'],
        },
      ],
    },
  }),

  plugins: [
    shikiPlugin({
      langs: ['php', 'blade', 'bash', 'json', 'yaml', 'html'],
      themes: { light: 'github-light', dark: 'github-dark' },
    }),
    searchPlugin({ maxSuggestions: 8 }),
  ],
})

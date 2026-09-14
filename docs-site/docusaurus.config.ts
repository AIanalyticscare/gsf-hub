import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

const repository = process.env.GITHUB_REPOSITORY ?? 'gsf-hub/gsf-hub';
const [organizationName, projectName] = repository.split('/');
const isLocal = process.env.NODE_ENV !== 'production';

const config: Config = {
  title: 'GSF Hub Documentation',
  tagline: 'Technical reference, user guides, and video tutorials',
  url: process.env.DOCS_URL ?? `https://${organizationName}.github.io`,
  baseUrl: isLocal ? '/' : (process.env.DOCS_BASE_URL ?? `/${projectName}/`),
  organizationName,
  projectName,
  onBrokenLinks: 'throw',
  markdown: {
    hooks: {
      onBrokenMarkdownLinks: 'warn',
    },
  },
  trailingSlash: false,
  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },
  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          editUrl: `https://github.com/${repository}/edit/main/docs-site/`,
          showLastUpdateAuthor: true,
          showLastUpdateTime: true,
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],
  themeConfig: {
    image: 'img/gsf-logo.png',
    navbar: {
      title: 'GSF Hub',
      logo: {
        alt: 'GSF Hub',
        src: 'img/gsf-logo.png',
      },
      items: [
        {type: 'docSidebar', sidebarId: 'technicalSidebar', position: 'left', label: 'Technical WordPress'},
        {type: 'docSidebar', sidebarId: 'guidesSidebar', position: 'left', label: 'User Guides'},
        {type: 'docSidebar', sidebarId: 'videosSidebar', position: 'left', label: 'Video Tutorials'},
        {href: `https://github.com/${repository}`, label: 'GitHub', position: 'right'},
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Documentation',
          items: [
            {label: 'Technical WordPress', to: '/docs/technical/overview'},
            {label: 'User Guides', to: '/docs/user-guides/overview'},
            {label: 'Video Tutorials', to: '/docs/video-tutorials/overview'},
          ],
        },
        {
          title: 'Project',
          items: [{label: 'Source repository', href: `https://github.com/${repository}`}],
        },
      ],
      copyright: `Copyright © ${new Date().getFullYear()} GSF Hub.`,
    },
    colorMode: {
      defaultMode: 'light',
      respectPrefersColorScheme: true,
    },
  } satisfies Preset.ThemeConfig,
};

export default config;

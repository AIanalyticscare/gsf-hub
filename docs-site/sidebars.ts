import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';

const sidebars: SidebarsConfig = {
  technicalSidebar: [
    {
      type: 'category',
      label: 'Technical WordPress',
      link: {type: 'doc', id: 'technical/overview'},
      items: [
        'technical/local-development',
        'technical/themes-and-plugins',
        'technical/content-and-data',
        'technical/deployment',
      ],
    },
  ],
  guidesSidebar: [
    {
      type: 'category',
      label: 'User Guides',
      link: {type: 'doc', id: 'user-guides/overview'},
      items: [
        'user-guides/end-user-guide',
        'user-guides/staff-content-operations-guide',
        'user-guides/administrator-guide',
      ],
    },
  ],
  videosSidebar: [
    {
      type: 'category',
      label: 'Video Tutorials',
      link: {type: 'doc', id: 'video-tutorials/overview'},
      items: [
        'video-tutorials/v01-dashboard',
        'video-tutorials/v01-v02-wordpress-essentials',
        'video-tutorials/v03-v05-scripts',
      ],
    },
  ],
};

export default sidebars;

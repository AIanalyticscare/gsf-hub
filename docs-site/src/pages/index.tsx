import clsx from 'clsx';
import Link from '@docusaurus/Link';
import Layout from '@theme/Layout';
import Heading from '@theme/Heading';
import styles from './index.module.css';

const sections = [
  {
    title: 'Technical WordPress',
    description: 'Run the local stack, understand the custom themes and plugins, and prepare safe deployments.',
    to: '/docs/technical/overview',
  },
  {
    title: 'User Guides',
    description: 'Role-based instructions for members, content staff, and WordPress administrators.',
    to: '/docs/user-guides/overview',
  },
  {
    title: 'Video Tutorials',
    description: 'Watch the essential workflows with captions, transcripts, and companion scripts.',
    to: '/docs/video-tutorials/overview',
  },
];

export default function Home() {
  return (
    <Layout title="Documentation" description="GSF Hub technical documentation, user guides, and tutorials">
      <header className={clsx('hero hero--primary', styles.heroBanner)}>
        <div className="container">
          <p className={styles.eyebrow}>GSF HUB KNOWLEDGE BASE</p>
          <Heading as="h1" className="hero__title">Build, operate, and use the GSF Hub</Heading>
          <p className="hero__subtitle">One organized home for the WordPress technical reference, role-based guides, and video tutorials.</p>
          <Link className="button button--secondary button--lg" to="/docs/welcome">Open the documentation</Link>
        </div>
      </header>
      <main className={styles.sections}>
        <div className="container">
          <div className="row">
            {sections.map((section) => (
              <div className="col col--4" key={section.title}>
                <Link className={styles.card} to={section.to}>
                  <Heading as="h2">{section.title}</Heading>
                  <p>{section.description}</p>
                  <span>Explore →</span>
                </Link>
              </div>
            ))}
          </div>
        </div>
      </main>
    </Layout>
  );
}

export default function WeeklyCompetitionShell() {
  return (
    <section style={styles.page}>
      <div style={styles.hero}>
        <div style={styles.eyebrow}>Competition</div>
        <h1 style={styles.title}>Weekly Competition</h1>
        <p style={styles.text}>
          This is the admin shell for launching weekly competitions. The real competition workflow is not implemented yet,
          so this page only prepares the structure and publishing entry.
        </p>
      </div>

      <div style={styles.grid}>
        <article style={styles.card}>
          <h2 style={styles.cardTitle}>Upcoming Round</h2>
          <p style={styles.cardText}>Configure the next weekly challenge, title, opening time, and status banner.</p>
          <button type="button" style={styles.primaryButton}>Create Competition Shell</button>
        </article>

        <article style={styles.card}>
          <h2 style={styles.cardTitle}>Publishing Notes</h2>
          <ul style={styles.list}>
            <li>Competition logic and scoring are not wired yet.</li>
            <li>This page is only for future admin workflow preparation.</li>
            <li>Visual style follows the current admin console palette.</li>
          </ul>
        </article>
      </div>
    </section>
  )
}

const styles = {
  page: {
    maxWidth: '1126px',
    margin: '0 auto',
    padding: '40px 24px 72px',
  },
  hero: {
    padding: '28px 30px',
    borderRadius: '28px',
    background: 'linear-gradient(135deg, rgba(255,255,255,0.94), rgba(244,241,234,0.94))',
    border: '1px solid rgba(58, 78, 107, 0.1)',
    boxShadow: '0 24px 60px rgba(58, 78, 107, 0.08)',
    marginBottom: '24px',
  },
  eyebrow: {
    fontSize: '0.78rem',
    letterSpacing: '0.16rem',
    textTransform: 'uppercase',
    color: 'rgba(58, 78, 107, 0.58)',
    marginBottom: '10px',
  },
  title: {
    margin: '0 0 12px',
    fontSize: '2.2rem',
    color: 'var(--text-h)',
    fontFamily: 'var(--heading)',
  },
  text: {
    margin: 0,
    maxWidth: '760px',
    color: 'var(--text)',
    lineHeight: 1.7,
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(2, minmax(0, 1fr))',
    gap: '20px',
  },
  card: {
    borderRadius: '24px',
    background: 'rgba(255,255,255,0.92)',
    border: '1px solid rgba(58, 78, 107, 0.1)',
    padding: '24px',
    boxShadow: '0 18px 40px rgba(58, 78, 107, 0.06)',
  },
  cardTitle: {
    margin: '0 0 10px',
    fontSize: '1.2rem',
    color: 'var(--text-h)',
  },
  cardText: {
    margin: '0 0 20px',
    lineHeight: 1.65,
    color: 'var(--text)',
  },
  primaryButton: {
    minHeight: '44px',
    padding: '0 18px',
    borderRadius: '999px',
    border: 'none',
    background: 'var(--accent)',
    color: '#fff',
    fontWeight: 700,
    cursor: 'pointer',
  },
  list: {
    margin: 0,
    paddingLeft: '18px',
    color: 'var(--text)',
    lineHeight: 1.7,
  },
}

import './PersonalHub.css'

export default function PortalChoice({ onChoose }) {
  return (
    <div className="forum-container">
      <section className="portal-choice">
        <div className="portal-choice__eyebrow">Section III — Collective Intellect</div>
        <h1 className="portal-choice__title">Choose Your Space</h1>
        <p className="portal-choice__sub">
          Enter the discussion forum or switch into a personal communication workspace for direct and group chats.
        </p>

        <div className="portal-choice__grid">
          <button type="button" className="portal-card" onClick={() => onChoose('personal')}>
            <span className="portal-card__tag">Personal</span>
            <span className="portal-card__title">Private Messages</span>
            <span className="portal-card__desc">
              Search classmates by username, open direct chats immediately, and spin up group conversations from any thread.
            </span>
          </button>

          <button type="button" className="portal-card portal-card--forum" onClick={() => onChoose('forum')}>
            <span className="portal-card__tag">Forum</span>
            <span className="portal-card__title">Current Forum</span>
            <span className="portal-card__desc">
              Continue into the existing post-based discussion area with labels, replies, and media-rich threads.
            </span>
          </button>
        </div>
      </section>
    </div>
  )
}

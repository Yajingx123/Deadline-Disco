import { HOME_MODULES } from "../home/homeData";

export default function SiteNav({ activeModule, currentSection, onSelectModule, onAction }) {
  return (
    <header className="site-nav-wrap">
      <nav className="site-nav">
        <button className="logo" onClick={() => onSelectModule("Vocabulary")} aria-label="AcadBeat home">
          Acad<span>Beat</span>
        </button>
        <div className="nav-menu">
          {Object.entries(HOME_MODULES).map(([name, module]) => (
            <div key={name} className="nav-item-group">
              <button
                className={
                  (currentSection === "home" && activeModule === name) ||
                  (currentSection === "materials" && name === "Listening")
                    ? "nav-item active"
                    : "nav-item"
                }
                onClick={() => onSelectModule(name)}
              >
                {name}
              </button>
              <div className="nav-dropdown">
                {module.buttons.map((label) => (
                  <button key={label} className="nav-dropdown-btn" onClick={() => onAction(name, label)}>
                    {label}
                  </button>
                ))}
              </div>
            </div>
          ))}
        </div>
        <div className="user-section">
          <span className="nav-user-name">ALEX RIVERA</span>
          <div className="avatar">AR</div>
        </div>
      </nav>
    </header>
  );
}

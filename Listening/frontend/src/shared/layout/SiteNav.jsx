import { HOME_MODULES } from "../home/homeData";

function initialsForUser(username) {
  const name = username || "NA";
  return name.slice(0, 2).toUpperCase();
}

export default function SiteNav({
  activeModule,
  currentSection,
  currentUser,
  onSelectModule,
  onAction,
  onAuthClick, 
  onLogout     
}) {
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
          {currentUser ? (
            <>
              <span className="nav-user-name">{currentUser}</span>
              <div className="avatar">{initialsForUser(currentUser)}</div>
              <button className="nav-item" onClick={onLogout} style={{ marginLeft: "10px" }}>Logout</button>
            </>
          ) : (
            <button className="nav-item active" onClick={onAuthClick} style={{ padding: "6px 16px", borderRadius: "20px" }}>
              Login / Register
            </button>
          )}
        </div>

      </nav>
    </header>
  );
}
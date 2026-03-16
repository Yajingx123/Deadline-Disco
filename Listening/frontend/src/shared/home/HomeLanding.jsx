import { HOME_MODULES, VOCABULARY_QUOTES } from "./homeData";

function ModuleVisual({ moduleName, quoteIndex }) {
  if (moduleName === "Vocabulary") {
    const quote = VOCABULARY_QUOTES[quoteIndex % VOCABULARY_QUOTES.length];
    return (
      <div className="quote-wrap">
        <p className="quote-text">"{quote.text}"</p>
        <p className="quote-author">{quote.author}</p>
      </div>
    );
  }

  if (moduleName === "Listening") {
    return (
      <div className="sound-landscape">
        {Array.from({ length: 30 }).map((_, index) => (
          <div
            key={index}
            className="bar"
            style={{
              height: `${28 + ((index * 17) % 72)}%`,
              animationDuration: `${0.45 + ((index * 11) % 5) * 0.12}s`,
            }}
          />
        ))}
      </div>
    );
  }

  if (moduleName === "Speaking") {
    return (
      <div className="vocal-dialogue">
        <div className="bubble bubble-left" />
        <div className="bubble bubble-right" />
      </div>
    );
  }

  if (moduleName === "Reading") {
    return (
      <div className="reading-topography">
        <div className="scan-focus" />
        {Array.from({ length: 6 }).map((_, index) => (
          <div key={index} className="text-strata" />
        ))}
        <span className="tag-text" style={{ top: "5%", left: "15%", animationDelay: "0s" }}>
          SYNTAX
        </span>
        <span className="tag-text" style={{ top: "35%", left: "60%", animationDelay: "0.5s" }}>
          LEXIS
        </span>
        <span className="tag-text" style={{ top: "60%", left: "10%", animationDelay: "1.2s" }}>
          LOGIC
        </span>
        <span className="tag-text" style={{ top: "85%", left: "75%", animationDelay: "0.8s" }}>
          NUANCE
        </span>
        <span className="tag-text" style={{ top: "20%", left: "35%", animationDelay: "2s" }}>
          STRUCTURE
        </span>
        <span className="tag-text" style={{ bottom: "10%", left: "45%", animationDelay: "1.5s" }}>
          CONTEXT
        </span>
      </div>
    );
  }

  return (
    <div className="writing-hand">
      <svg viewBox="0 0 450 100" width="450">
        <path
          className="cursive-path"
          d="M40,60 C60,10 80,90 100,40 C110,20 130,80 150,60 C180,60 170,20 200,20 C230,20 220,80 250,80 C280,80 270,30 310,30 C340,30 330,70 370,70 S410,20 430,30"
        />
      </svg>
    </div>
  );
}

export default function HomeLanding({ activeModule, onAction, quoteIndex }) {
  const module = HOME_MODULES[activeModule];

  return (
    <main className="site-home-main" id="app-viewport">
      <div className="module-content">
        <div className="module-label">{module.label}</div>
        <ModuleVisual moduleName={activeModule} quoteIndex={quoteIndex} />
        {activeModule !== "Vocabulary" ? (
          <div className="daily-focus-card">
            <div className="card-number">{module.id}</div>
            <div className="card-content">
              <h4>Daily Focus</h4>
              <p>{module.topic}</p>
            </div>
          </div>
        ) : null}
        <div className="bottom-actions">
          <button className="btn-small btn-primary-small" onClick={() => onAction(activeModule, module.buttons[0])}>
            {module.buttons[0]}
          </button>
          <button className="btn-small" onClick={() => onAction(activeModule, module.buttons[1])}>
            {module.buttons[1]}
          </button>
        </div>
      </div>
    </main>
  );
}

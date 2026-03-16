function normalizeFill(text) {
  return String(text || "").trim().toLowerCase();
}

export function isAnswerCorrect(question, answer) {
  if (question.type === "multiple_choice") {
    return answer === question.correctAnswer;
  }
  if (question.type === "multiple_select") {
    const a = [...(answer || [])].sort();
    const b = [...(question.correctAnswer || [])].sort();
    return JSON.stringify(a) === JSON.stringify(b);
  }
  if (question.type === "fill_blank") {
    return normalizeFill(answer) === normalizeFill(question.correctAnswer);
  }
  if (question.type === "matching") {
    return JSON.stringify(answer || {}) === JSON.stringify(question.correctAnswer || {});
  }
  if (question.type === "ordering") {
    return JSON.stringify(answer || []) === JSON.stringify(question.correctAnswer || []);
  }
  return false;
}

export default function QuestionRenderer({ question, answer, onAnswerChange }) {
  if (!question) return null;

  if (question.type === "multiple_choice") {
    return (
      <div className="question-render">
        <h4 className="question-title">{question.questionText}</h4>
        {question.options.map((opt) => (
          <label key={opt.id} className="option-row rich">
            <input
              type="radio"
              name={question.id}
              checked={answer === opt.id}
              onChange={() => onAnswerChange(opt.id)}
            />
            {opt.id}. {opt.label}
          </label>
        ))}
      </div>
    );
  }

  if (question.type === "multiple_select") {
    const selected = new Set(answer || []);
    return (
      <div className="question-render">
        <h4 className="question-title">{question.questionText}</h4>
        {question.options.map((opt) => (
          <label key={opt.id} className="option-row rich">
            <input
              type="checkbox"
              checked={selected.has(opt.id)}
              onChange={(e) => {
                const next = new Set(selected);
                if (e.target.checked) next.add(opt.id);
                else next.delete(opt.id);
                onAnswerChange([...next]);
              }}
            />
            {opt.id}. {opt.label}
          </label>
        ))}
      </div>
    );
  }

  if (question.type === "fill_blank") {
    return (
      <div className="question-render">
        <h4 className="question-title">{question.questionText}</h4>
        <input
          type="text"
          className="fill-input"
          value={answer || ""}
          onChange={(e) => onAnswerChange(e.target.value)}
          placeholder="Type your answer"
        />
      </div>
    );
  }

  if (question.type === "matching") {
    return (
      <div className="question-render">
        <h4 className="question-title">{question.questionText}</h4>
        {question.leftItems.map((item) => (
          <div key={item.id} className="match-row rich">
            <div className="match-left">{item.label}</div>
            <div className="match-options">
              {question.rightItems.map((right) => {
                const active = answer?.[item.id] === right.id;
                return (
                  <button
                    key={right.id}
                    type="button"
                    className={`match-choice ${active ? "active" : ""}`}
                    onClick={() =>
                      onAnswerChange({
                        ...(answer || {}),
                        [item.id]: active ? "" : right.id
                      })
                    }
                    aria-pressed={active}
                  >
                    {active ? "✓ " : ""}
                    {right.id}: {right.label}
                  </button>
                );
              })}
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (question.type === "ordering") {
    const itemIds = question.orderingItems.map((item) => item.id);
    const currentSlots =
      Array.isArray(answer) && answer.length === itemIds.length
        ? answer
        : Array(itemIds.length).fill(null);
    const byId = Object.fromEntries(question.orderingItems.map((item) => [item.id, item]));
    const placedIds = new Set(currentSlots.filter(Boolean));
    const bankItems = question.orderingItems.filter((item) => !placedIds.has(item.id));

    const onDropToSlot = (targetIndex, payload) => {
      const next = [...currentSlots];
      const incomingId = payload.itemId;
      const fromIndex = payload.fromIndex;
      if (!incomingId) return;
      if (typeof fromIndex === "number" && fromIndex >= 0 && fromIndex < next.length) {
        if (fromIndex === targetIndex) return;
        [next[targetIndex], next[fromIndex]] = [next[fromIndex], next[targetIndex]];
      } else {
        next[targetIndex] = incomingId;
      }
      onAnswerChange(next);
    };

    const handleDrop = (e, targetIndex) => {
      e.preventDefault();
      const raw = e.dataTransfer.getData("text/plain");
      if (!raw) return;
      try {
        const payload = JSON.parse(raw);
        onDropToSlot(targetIndex, payload);
      } catch {
        // Ignore malformed drag payload.
      }
    };

    return (
      <div className="question-render">
        <h4 className="question-title">{question.questionText}</h4>

        <div className="ordering-layout">
          <div className="ordering-bank rich">
            <p className="ordering-title">Options (drag into slots)</p>
            <div className="ordering-bank-list">
              {bankItems.map((item) => (
                <div
                  key={item.id}
                  className="ordering-item"
                  draggable
                  onDragStart={(e) => {
                    e.dataTransfer.setData("text/plain", JSON.stringify({ itemId: item.id }));
                  }}
                >
                  {item.id}. {item.label}
                </div>
              ))}
            </div>
          </div>

          <div className="ordering-slots">
            {currentSlots.map((id, index) => (
              <div key={`slot-wrap-${index}`} className="order-slot-wrap">
                <div className="slot-label">{index + 1}</div>
                <div
                  className={`order-slot rich ${id ? "filled" : ""}`}
                  onDragOver={(e) => e.preventDefault()}
                  onDrop={(e) => handleDrop(e, index)}
                >
                  {id ? (
                    <div
                      className="ordering-item"
                      draggable
                      onDragStart={(e) => {
                        e.dataTransfer.setData(
                          "text/plain",
                          JSON.stringify({ itemId: id, fromIndex: index })
                        );
                      }}
                    >
                      {id}. {byId[id]?.label}
                      <button
                        type="button"
                        className="slot-clear secondary"
                        onClick={() => {
                          const next = [...currentSlots];
                          next[index] = null;
                          onAnswerChange(next);
                        }}
                      >
                        Remove
                      </button>
                    </div>
                  ) : (
                    <span className="slot-placeholder">Drop item here</span>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  return <p>Unsupported question type.</p>;
}

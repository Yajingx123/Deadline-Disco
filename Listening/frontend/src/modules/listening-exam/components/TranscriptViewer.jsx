export default function TranscriptViewer({ transcript, activeReference }) {
  const text = String(transcript || "");
  const paragraphs = text.split("\n").filter(Boolean);

  const highlight = (line) => {
    if (!activeReference || !line.includes(activeReference)) return line;
    const [before, afterStart] = line.split(activeReference, 2);
    return (
      <>
        {before}
        <mark>{activeReference}</mark>
        {afterStart}
      </>
    );
  };

  return (
    <div className="transcript-content">
      {paragraphs.length
        ? paragraphs.map((line, i) => (
            <p key={i} className="transcript-line">
              {highlight(line)}
            </p>
          ))
        : null}
    </div>
  );
}

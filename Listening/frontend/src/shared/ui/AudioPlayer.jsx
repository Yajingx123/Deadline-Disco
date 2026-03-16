import { memo, useEffect, useRef, useState } from "react";

function AudioPlayer({ src, mode, initialTime = 0, onTimeUpdate }) {
  const audioRef = useRef(null);
  const lastReportedSecondRef = useRef(-1);
  const lastInitTimeRef = useRef(null);
  const [hasStarted, setHasStarted] = useState(false);
  const [hasEnded, setHasEnded] = useState(false);
  const [loadError, setLoadError] = useState(false);
  const [isBuffering, setIsBuffering] = useState(false);

  useEffect(() => {
    setHasStarted(false);
    setHasEnded(false);
    setLoadError(false);
    setIsBuffering(false);
    lastReportedSecondRef.current = -1;
    lastInitTimeRef.current = null;
  }, [src, mode]);

  useEffect(() => {
    if (
      audioRef.current &&
      Number.isFinite(initialTime) &&
      lastInitTimeRef.current !== initialTime
    ) {
      lastInitTimeRef.current = initialTime;
      audioRef.current.currentTime = initialTime;
    }
  }, [initialTime]);

  const play = async () => {
    if (!audioRef.current) return;
    if (mode === "exam" && hasEnded) return;
    if (mode === "exam" && hasStarted) return;
    setHasStarted(true);
    try {
      await audioRef.current.play();
    } catch {
      // Autoplay may be blocked by browser policy.
    }
  };

  useEffect(() => {
    if (mode !== "exam") return;
    play();
  }, [mode, src]);

  return (
    <section className="audio-player">
      <audio
        ref={audioRef}
        src={src}
        controls={mode === "practice"}
        preload="auto"
        playsInline
        onTimeUpdate={(e) => {
          const wholeSecond = Math.floor(e.currentTarget.currentTime);
          // Reduce parent updates: only sync every 5 seconds.
          if (wholeSecond !== lastReportedSecondRef.current && wholeSecond % 5 === 0) {
            lastReportedSecondRef.current = wholeSecond;
            onTimeUpdate?.(wholeSecond);
          }
        }}
        onPause={(e) => onTimeUpdate?.(Math.floor(e.currentTarget.currentTime))}
        onEnded={() => setHasEnded(true)}
        onPlaying={() => setIsBuffering(false)}
        onWaiting={() => setIsBuffering(true)}
        onError={() => setLoadError(true)}
      />
      {mode === "exam" ? <p className="hint">Exam mode: audio can only be played once.</p> : null}
      {isBuffering ? <p className="hint">Audio buffering...</p> : null}
      {loadError ? (
        <p className="hint">
          Audio file not found. Put your lecture audio at <code>/public/audio/ListeningP3_1.mp3</code>.
        </p>
      ) : null}
    </section>
  );
}

export default memo(AudioPlayer);

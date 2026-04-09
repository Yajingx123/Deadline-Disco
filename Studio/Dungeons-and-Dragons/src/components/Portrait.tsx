import "./Portrait.css";

export type PortraitVariant =
  | "princess"
  | "mage"
  | "merchant"
  | "boss"
  | "foe";

type Props = {
  variant: PortraitVariant;
  label?: string;
  className?: string;
};

type R = [number, number, number, number, string];

function rectsToSvg(rects: R[]) {
  return rects.map(([x, y, w, h, fill], i) => (
    <rect key={i} x={x} y={y} width={w} height={h} fill={fill} />
  ));
}

const BUSTS: Record<PortraitVariant, R[]> = {
  princess: [
    [8, 6, 8, 8, "#ffdbac"],
    [6, 4, 12, 4, "#ffd60a"],
    [7, 8, 10, 4, "#6f4e37"],
    [5, 14, 14, 14, "#e63946"],
    [9, 28, 6, 8, "#e63946"],
    [6, 16, 3, 12, "#c1121f"],
    [15, 16, 3, 12, "#c1121f"],
    [10, 9, 1, 1, "#222"],
    [14, 9, 1, 1, "#222"],
    [11, 12, 2, 1, "#c1121f"],
  ],
  mage: [
    [8, 8, 8, 8, "#ffdbac"],
    [5, 4, 14, 8, "#3a0ca3"],
    [7, 2, 10, 4, "#3a0ca3"],
    [4, 14, 16, 16, "#4361ee"],
    [18, 18, 2, 14, "#7209b7"],
    [8, 30, 4, 6, "#240046"],
    [12, 30, 4, 6, "#240046"],
    [10, 11, 1, 1, "#fff"],
    [14, 11, 1, 1, "#fff"],
    [11, 13, 3, 1, "#3a0ca3"],
  ],
  merchant: [
    [8, 8, 8, 8, "#ffdbac"],
    [6, 6, 12, 4, "#8d5524"],
    [5, 14, 14, 12, "#606c38"],
    [6, 22, 12, 3, "#b69162"],
    [7, 26, 10, 8, "#283618"],
    [3, 16, 4, 10, "#dda15e"],
    [10, 11, 1, 1, "#222"],
    [14, 11, 1, 1, "#222"],
    [11, 13, 2, 1, "#8d5524"],
  ],
  boss: [
    [7, 6, 10, 10, "#8d99ae"],
    [5, 4, 14, 4, "#2b2d42"],
    [6, 2, 4, 4, "#2b2d42"],
    [14, 2, 4, 4, "#2b2d42"],
    [4, 14, 16, 18, "#1d1e2c"],
    [6, 18, 12, 8, "#ef233c"],
    [7, 30, 4, 6, "#111"],
    [13, 30, 4, 6, "#111"],
    [9, 10, 1, 1, "#ffd60a"],
    [14, 10, 1, 1, "#ffd60a"],
    [10, 22, 4, 2, "#7209b7"],
  ],
  foe: [
    [7, 6, 10, 12, "#c9b18a"],
    [6, 5, 12, 3, "#a68b6a"],
    [8, 8, 8, 8, "#e8d5b7"],
    [5, 16, 14, 14, "#b89f7a"],
    [4, 18, 3, 10, "#9a8260"],
    [17, 18, 3, 10, "#9a8260"],
    [8, 30, 3, 6, "#8b7355"],
    [13, 30, 3, 6, "#8b7355"],
    [9, 11, 1, 1, "#1a1a2e"],
    [14, 11, 1, 1, "#1a1a2e"],
    [10, 13, 4, 1, "#5c4033"],
    [6, 20, 2, 6, "#d4c4a8"],
    [16, 20, 2, 6, "#d4c4a8"],
  ],
};

export function Portrait({ variant, label, className = "" }: Props) {
  return (
    <div className={`portrait-frame ${className}`.trim()}>
      {label ? <div className="portrait-label">{label}</div> : null}
      <svg
        className="portrait-svg"
        viewBox="0 0 24 36"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden
      >
        {rectsToSvg(BUSTS[variant])}
      </svg>
    </div>
  );
}

export function eventScenarioToPortrait(
  scenarioId: string
): PortraitVariant {
  return scenarioId === "princess-forest" ? "princess" : "mage";
}

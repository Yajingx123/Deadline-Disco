import "./Hud.css";
import type { RoomState } from "../types/game";

type Props = {
  hp: number;
  maxHp: number;
  gold: number;
  roomState: RoomState | null;
};

export function Hud({ hp, maxHp, gold, roomState }: Props) {
  const hpPct = maxHp > 0 ? Math.max(0, (hp / maxHp) * 100) : 0;
  const roomTypeLabel =
    roomState?.roomType === "boss"
      ? "Boss"
      : roomState?.roomType === "event"
        ? "Event"
        : roomState?.roomType === "shop"
          ? "Shop"
          : "Combat";

  return (
    <aside className="hud" aria-label="Status">
      <div className="hud-row hud-row--hp">
        <span className="hud-label">HP</span>
        <div className="hud-bar">
          <div className="hud-bar-fill hp" style={{ width: `${hpPct}%` }} />
        </div>
        <span className="hud-value hud-value--num">
          {hp}/{maxHp}
        </span>
      </div>
      <div className="hud-row hud-row--gold">
        <span className="hud-label">Gold</span>
        <span className="hud-placeholder" aria-hidden />
        <span className="hud-value hud-value--gold">{gold}</span>
      </div>
      {roomState && (
        <>
          <div className="hud-row hud-row--stat">
            <span className="hud-label">Room</span>
            <span className="hud-value hud-value--wide">
              {roomState.roomIndex}/{roomState.totalRooms}
            </span>
          </div>
          <div className="hud-row hud-row--stat">
            <span className="hud-label">Type</span>
            <span className="hud-value hud-value--wide">{roomTypeLabel}</span>
          </div>
          <div className="hud-row hud-row--stat">
            <span className="hud-label">Left</span>
            <span className="hud-value hud-value--wide">
              {roomState.enemiesLeft}
              {roomState.portalOpen ? (
                <span className="hud-portal-tag">Portal open</span>
              ) : null}
            </span>
          </div>
        </>
      )}
    </aside>
  );
}

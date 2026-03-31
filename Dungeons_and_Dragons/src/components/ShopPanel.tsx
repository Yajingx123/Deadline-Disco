import type { EffectDelta, ShopItem } from "../types/game";
import { Portrait } from "./Portrait";
import "./ShopPanel.css";

type Props = {
  items: ShopItem[];
  currentGold: number;
  onResolve: (effect: EffectDelta) => void;
};

export function ShopPanel({ items, currentGold, onResolve }: Props) {
  return (
    <div className="shop-backdrop" role="dialog" aria-modal="true">
      <div className="shop-panel pixel-border">
        <div className="shop-panel-inner">
          <Portrait variant="merchant" label="Shopkeep" />
          <div className="shop-panel-main">
            <div className="shop-header">
              <span className="shop-tag">Shop Room</span>
              <span className="shop-gold">Gold: {currentGold}</span>
            </div>
            <p className="shop-desc">Choose one item or leave the shop.</p>
            <ul className="shop-items">
              {items.map((item) => {
                const affordable = currentGold >= item.cost;
                return (
                  <li key={item.id}>
                    <button
                      type="button"
                      disabled={!affordable}
                      onClick={() =>
                        onResolve({
                          ...item.effect,
                          goldDelta: (item.effect.goldDelta ?? 0) - item.cost,
                        })
                      }
                    >
                      <strong>{item.name}</strong> ({item.cost}g)
                      <span>{item.description}</span>
                    </button>
                  </li>
                );
              })}
            </ul>
            <button
              type="button"
              className="shop-skip"
              onClick={() => onResolve({ message: "You left the shop." })}
            >
              Leave Shop
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

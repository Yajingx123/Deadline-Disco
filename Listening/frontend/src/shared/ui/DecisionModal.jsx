export default function DecisionModal({
  title,
  message,
  confirmLabel = "Confirm",
  cancelLabel = "Cancel",
  onConfirm,
  onCancel
}) {
  return (
    <div className="modal-backdrop">
      <div className="modal decision-modal centered-modal">
        <h3>{title}</h3>
        <p>{message}</p>
        <div className="mode-grid centered-row">
          <button onClick={onConfirm}>{confirmLabel}</button>
          <button className="secondary" onClick={onCancel}>
            {cancelLabel}
          </button>
        </div>
      </div>
    </div>
  );
}

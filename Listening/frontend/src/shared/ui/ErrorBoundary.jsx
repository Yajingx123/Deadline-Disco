import React from "react";

export default class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, message: "" };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, message: String(error?.message || error) };
  }

  componentDidCatch(error, errorInfo) {
    console.error("UI crash:", error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <main style={{ padding: 24, fontFamily: "sans-serif" }}>
          <h2>Page crashed</h2>
          <p>{this.state.message}</p>
          <p>Open browser console for full stack trace.</p>
        </main>
      );
    }
    return this.props.children;
  }
}


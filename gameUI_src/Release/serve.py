from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer


class GodotWebHandler(SimpleHTTPRequestHandler):
    def end_headers(self):
        # Force fresh files to avoid stale cached index.js/index.html.
        self.send_header("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0")
        self.send_header("Pragma", "no-cache")
        self.send_header("Expires", "0")

        # Required for SharedArrayBuffer in Godot web export.
        self.send_header("Cross-Origin-Opener-Policy", "same-origin")
        self.send_header("Cross-Origin-Embedder-Policy", "require-corp")
        self.send_header("Cross-Origin-Resource-Policy", "same-origin")
        super().end_headers()


if __name__ == "__main__":
    server = ThreadingHTTPServer(("127.0.0.1", 5500), GodotWebHandler)
    print("Serving on http://127.0.0.1:5500")
    server.serve_forever()

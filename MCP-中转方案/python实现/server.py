"""
A lightweight SSE proxy server that forwards incoming POST requests
to a target Server-Sent Events (SSE) backend service.

This project is suitable for open-source use. All sensitive parameters
(API keys, endpoints) should be configured using environment variables.
"""

import http.server
import socketserver
import requests
import json
import logging
import os

# ------------------------------
# Logging Configuration
# ------------------------------
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)
logger = logging.getLogger(__name__)

# ------------------------------
# Environment-based Configuration
# ------------------------------
# Users should configure real values in environment variables:
#   export PROXY_TARGET_ENDPOINT="https://example.com/sse-endpoint"
#   export PROXY_API_KEY="sk-xxx"
TARGET_SSE_ENDPOINT = os.getenv("PROXY_TARGET_ENDPOINT", "")
TARGET_API_KEY = os.getenv("PROXY_API_KEY", "")

if not TARGET_SSE_ENDPOINT:
    logger.warning("Environment variable PROXY_TARGET_ENDPOINT is not set.")
if not TARGET_API_KEY:
    logger.warning("Environment variable PROXY_API_KEY is not set.")


class SSEProxyHandler(http.server.BaseHTTPRequestHandler):
    """
    HTTP handler that forwards POST requests to a backend SSE service
    and streams the SSE response back to the client.
    """

    def _send_sse_headers(self, status_code=200):
        """Send standard SSE headers."""
        self.send_response(status_code)
        self.send_header("Content-Type", "text/event-stream")
        self.send_header("Cache-Control", "no-cache")
        self.send_header("Connection", "keep-alive")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.end_headers()

    def do_POST(self):
        """Handle client POST request and forward it to SSE backend."""
        try:
            # Read client request body
            content_length = int(self.headers.get("Content-Length", 0))
            raw_body = self.rfile.read(content_length)

            logger.info(f"Received request body (first 200 chars): "
                        f"{raw_body.decode('utf-8')[:200]}...")

            # Parse JSON input
            try:
                request_data = json.loads(raw_body.decode("utf-8"))
            except json.JSONDecodeError:
                logger.error("Failed to decode JSON body.")
                self._send_sse_headers(400)
                self.wfile.write(b"event: error\ndata: Invalid JSON format\n\n")
                return

            # Prepare headers for SSE backend
            headers = {
                "Authorization": f"Bearer {TARGET_API_KEY}",
                "Content-Type": "application/json",
                "Accept": "text/event-stream"
            }

            logger.info(f"Forwarding request to: {TARGET_SSE_ENDPOINT}")

            # Make streaming request
            response = requests.post(
                TARGET_SSE_ENDPOINT,
                json=request_data,
                headers=headers,
                stream=True,
                timeout=60
            )

            # Send SSE headers back to client
            self._send_sse_headers()

            # Stream SSE back to client
            for line in response.iter_lines():
                if line:
                    self.wfile.write(line + b"\n")
                    self.wfile.flush()
                    logger.debug(f"Forwarded SSE chunk: {line[:100]}")

        except Exception as e:
            logger.error(f"Internal server error: {str(e)}", exc_info=True)
            self._send_sse_headers(500)
            self.wfile.write(
                f"event: error\ndata: Server error - {str(e)}\n\n".encode("utf-8")
            )

    def do_OPTIONS(self):
        """Handle CORS preflight requests."""
        self.send_response(200)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, Authorization")
        self.send_header("Access-Control-Max-Age", "86400")
        self.end_headers()

    def log_message(self, format, *args):
        """Suppress default HTTP server logging."""
        return


def run_server(port=8000):
    """Start the SSE proxy server."""
    socketserver.TCPServer.allow_reuse_address = True
    server = socketserver.ThreadingTCPServer(("", port), SSEProxyHandler)

    logger.info(f"SSE proxy server started on port {port}")

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        logger.info("Shutting down server...")
        server.shutdown()


if __name__ == "__main__":
    run_server()

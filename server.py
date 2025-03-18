import http.server
import ssl
import socketserver

class Handler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        # Deny access to server.py
        if self.path == '/server.py':
            self.send_response(403)  # Forbidden
            self.send_header("Content-type", "text/html")
            self.end_headers()
            self.wfile.write(b"Access denied.")
        else:
            http.server.SimpleHTTPRequestHandler.do_GET(self)

httpd = socketserver.ThreadingTCPServer(("", 443), Handler)
httpd.socket = ssl.wrap_socket(httpd.socket,
                               server_side=True,
                               certfile="certificate.crt",
                               keyfile="private.key")

print("Serving HTTPS on port 443...")
httpd.serve_forever()

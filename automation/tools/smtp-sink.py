#!/usr/bin/env python3
"""
A local SMTP server that accepts everything and delivers nothing.

    python3 automation/tools/smtp-sink.py &

Listens on 127.0.0.1:2525 and writes each message it receives to
automation/.dev/mail/*.eml. Point config.php's smtp block at it to exercise
the real PHPMailer -> SMTP path — authentication, MIME, encoding and all —
without sending mail to anyone. No dependencies.

This is a TEST DOUBLE. It speaks just enough SMTP to be convincing and
accepts any credentials; never run it anywhere it could be reached from
outside the machine.
"""
import socketserver, os, sys, time

OUT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), ".dev", "mail")


class Handler(socketserver.StreamRequestHandler):
    def w(self, s):
        self.wfile.write((s + "\r\n").encode())
        self.wfile.flush()

    def handle(self):
        self.w("220 localhost ESMTP sink")
        in_data, lines = False, []
        while True:
            raw = self.rfile.readline()
            if not raw:
                return
            line = raw.decode("utf-8", "replace").rstrip("\r\n")

            if in_data:
                if line == ".":
                    in_data = False
                    name = "%d-%06d.eml" % (time.time(), int(time.time() * 1e6) % 1000000)
                    with open(os.path.join(OUT, name), "w", encoding="utf-8") as fh:
                        fh.write("\n".join(lines))
                    lines = []
                    self.w("250 2.0.0 Ok: queued")
                else:
                    # Undo dot-stuffing (RFC 5321 §4.5.2).
                    lines.append(line[1:] if line.startswith("..") else line)
                continue

            up = line.upper()
            if up.startswith(("EHLO", "HELO")):
                self.w("250-localhost")
                self.w("250-AUTH LOGIN PLAIN")
                self.w("250-SIZE 35882577")
                self.w("250 8BITMIME")
            elif up.startswith("AUTH LOGIN"):
                self.w("334 VXNlcm5hbWU6"); self.rfile.readline()
                self.w("334 UGFzc3dvcmQ6"); self.rfile.readline()
                self.w("235 2.7.0 Authentication successful")
            elif up.startswith("AUTH PLAIN"):
                if len(line.split()) == 2:
                    self.rfile.readline()
                self.w("235 2.7.0 Authentication successful")
            elif up == "DATA":
                in_data = True
                self.w("354 End data with <CR><LF>.<CR><LF>")
            elif up == "QUIT":
                self.w("221 2.0.0 Bye")
                return
            else:
                self.w("250 2.0.0 Ok")


class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


if __name__ == "__main__":
    os.makedirs(OUT, exist_ok=True)
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 2525
    print("smtp sink on 127.0.0.1:%d -> %s" % (port, OUT), flush=True)
    Server(("127.0.0.1", port), Handler).serve_forever()

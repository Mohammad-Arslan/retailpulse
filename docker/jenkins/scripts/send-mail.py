#!/usr/bin/env python3
"""Send a RetailPulse Jenkins build notification over SMTP.

Transport (host/port/TLS/auth) is read entirely from JENKINS_SMTP_* / JENKINS_MAIL_FROM
environment variables at send time. Switching from Mailpit to a real SMTP provider is
therefore a `.env` change on the Jenkins container only -- this script and the Jenkinsfile
that calls it never need to change.
"""
import argparse
import mimetypes
import os
import smtplib
import sys
from email.message import EmailMessage


def env(name, default=""):
    return os.environ.get(name, default)


def env_bool(name, default=False):
    return env(name, str(default)).strip().lower() in ("1", "true", "yes", "on")


def build_message(to_addrs, subject, body_file, attachments):
    with open(body_file, "r", encoding="utf-8") as fh:
        body = fh.read()

    msg = EmailMessage()
    msg["Subject"] = subject
    msg["From"] = env("JENKINS_MAIL_FROM", "jenkins@retailpulse.local")
    msg["To"] = to_addrs
    msg.set_content(body)

    for path in attachments:
        if not path or not os.path.isfile(path):
            continue
        ctype, _ = mimetypes.guess_type(path)
        maintype, subtype = (ctype or "application/octet-stream").split("/", 1)
        with open(path, "rb") as fh:
            msg.add_attachment(
                fh.read(), maintype=maintype, subtype=subtype, filename=os.path.basename(path)
            )
    return msg


def send(msg):
    host = env("JENKINS_SMTP_HOST", "mailpit")
    port = int(env("JENKINS_SMTP_PORT", "1025"))
    use_ssl = env_bool("JENKINS_SMTP_USE_SSL")
    use_tls = env_bool("JENKINS_SMTP_USE_TLS")
    username = env("JENKINS_SMTP_USERNAME")
    password = env("JENKINS_SMTP_PASSWORD")

    smtp_cls = smtplib.SMTP_SSL if use_ssl else smtplib.SMTP
    with smtp_cls(host, port, timeout=30) as smtp:
        if use_tls and not use_ssl:
            smtp.starttls()
        # Mailpit needs no auth. Only attempt login when both are actually set,
        # so a real SMTP provider is enabled purely by setting these two env vars.
        if username and password:
            smtp.login(username, password)
        smtp.send_message(msg)
    print(f"[send-mail] Sent '{msg['Subject']}' to {msg['To']} via {host}:{port}")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--to", required=True, help="Comma-separated recipient list")
    parser.add_argument("--subject", required=True)
    parser.add_argument("--body-file", required=True)
    parser.add_argument("--attach", action="append", default=[], help="Path to attach (repeatable)")
    args = parser.parse_args()

    msg = build_message(args.to, args.subject, args.body_file, args.attach)
    send(msg)


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:  # a notification failure must never fail the build
        print(f"[send-mail] WARNING: failed to send email: {exc}", file=sys.stderr)
        sys.exit(0)

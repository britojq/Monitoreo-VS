"""
CLI y punto de entrada PAM para ssh_alert.
"""
import argparse
import asyncio
import logging
import os
import sys

from monitor.shield.ssh_alert.pam_processor import process_pam_event

logger = logging.getLogger("monitor.shield.ssh_alert.cli")


def main():
    parser = argparse.ArgumentParser(description="PAM Security Alert Dispatcher")
    parser.add_argument("pos_user", nargs="?", default="")
    parser.add_argument("pos_rhost", nargs="?", default="")
    parser.add_argument("pos_tty", nargs="?", default="")
    parser.add_argument("--user", default="")
    parser.add_argument("--rhost", default="")
    parser.add_argument("--tty", default="")
    parser.add_argument("--service", default="")
    parser.add_argument("--type", default="")
    parser.add_argument("--sudo-user", default="")
    parser.add_argument("--sudo-cmd", default="")
    parser.add_argument("--is-agent", action="store_true", default=False)
    parser.add_argument("--caller-pid", type=int, default=0)

    args = parser.parse_args()

    user = args.user or os.getenv("PAM_USER") or args.pos_user or "desconocido"
    rhost = args.rhost or os.getenv("PAM_RHOST") or args.pos_rhost or "desconocida/local"
    tty = args.tty or os.getenv("PAM_TTY") or args.pos_tty or "consola"
    service = args.service or os.getenv("PAM_SERVICE") or "sshd"
    pam_type = args.type or os.getenv("PAM_TYPE") or "open_session"
    sudo_user = args.sudo_user or os.getenv("SUDO_USER") or ""
    sudo_cmd = args.sudo_cmd or os.getenv("SUDO_COMMAND") or ""

    try:
        asyncio.run(
            process_pam_event(
                user=user,
                rhost=rhost,
                tty=tty,
                service=service,
                pam_type=pam_type,
                sudo_user=sudo_user,
                sudo_cmd=sudo_cmd,
                is_agent=args.is_agent,
                caller_pid=args.caller_pid,
            )
        )
    except Exception as e:
        logger.error(f"Excepción en monitor.shield.ssh_alert: {e}")
    sys.exit(0)


if __name__ == "__main__":
    main()

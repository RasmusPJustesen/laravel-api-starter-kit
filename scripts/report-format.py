import json
import pathlib
import subprocess

for name in subprocess.check_output(["git", "diff", "--name-only", "--", "*.php"], text=True).splitlines():
    path = pathlib.Path(name)
    if path.is_file():
        print("PINT_FILE " + json.dumps({"path": name, "content": path.read_text()}))

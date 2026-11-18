#!/usr/bin/env python3
"""Test the actual lesson comments in two stages, restoring original files on exit."""
import os
from pathlib import Path
import shutil
import subprocess
import sys

root = Path(__file__).resolve().parents[1]
php = shutil.which(os.environ.get("PHP_BINARY", "php"))
if not php or not (root / "vendor/autoload.php").exists():
    sys.exit("Install PHP and run composer install first. No files were changed.")
paths = [root / "app/Http/Controllers/DashboardController.php", root / "app/Http/Controllers/EmployeeController.php"]
originals = {path: path.read_bytes() for path in paths}
try:
    dashboard = originals[paths[0]].decode()
    start = dashboard.index("        // MYSQL ONLY START")
    end = dashboard.index("        // MYSQL ONLY END", start)
    mysql_block = dashboard[start:end]
    for line in mysql_block.splitlines():
        if line.strip().startswith("$"):
            dashboard = dashboard.replace(line, line.replace("        $", "        // $", 1), 1)
    dashboard = dashboard.replace("/* ENABLE CACHE START", "// ENABLE CACHE START").replace("ENABLE CACHE END */", "// ENABLE CACHE END")
    paths[0].write_text(dashboard)
    env = {**os.environ, "RUN_REDIS_LESSON_TESTS": "1"}
    for invalidate in ["0", "1"]:
        if invalidate == "1":
            update = originals[paths[1]].decode().replace("/* INVALIDATE CACHE START", "// INVALIDATE CACHE START").replace("INVALIDATE CACHE END */", "// INVALIDATE CACHE END")
            paths[1].write_text(update)
        print("Testing " + ("cache and invalidation" if invalidate == "1" else "cache with stale data"), flush=True)
        subprocess.run([php, "artisan", "test", "--filter=RedisLessonTest"], cwd=root,
                       env={**env, "CACHE_LESSON_INVALIDATION": invalidate}, check=True)
finally:
    for path, content in originals.items():
        path.write_bytes(content)
    print("Original controller files restored; lesson blocks remain as delivered.", flush=True)

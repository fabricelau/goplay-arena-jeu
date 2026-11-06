#!/usr/bin/env python3
"""Régénère games/manifest.json (version + empreintes SHA-256) avant chaque envoi."""
import hashlib, json, os, datetime
d = os.path.join(os.path.dirname(os.path.abspath(__file__)), "games")
files = sorted(f for f in os.listdir(d) if f.endswith(".html"))
old = {}
try: old = json.load(open(os.path.join(d, "manifest.json")))
except Exception: pass
m = {"version": datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=4))).strftime("%Y.%m.%d-%H%M%S"),
     "files": {f: hashlib.sha256(open(os.path.join(d, f), "rb").read()).hexdigest() for f in files},
     "games": old.get("games", {})}
json.dump(m, open(os.path.join(d, "manifest.json"), "w"), indent=1, ensure_ascii=False)
print(m["version"], len(files), "fichiers")

# ATLAS editions

This repository holds the daily briefing and the editor's ledger. Nothing else.

Add the edition as `briefings/YYYY-MM-DD.json`.

The edition lists all eight desks, in this order, and each desk has at least one item: `esa_eu`, `cee`, `nordics`, `west`, `eurasia`, `mena`, `asia_pac`, `americas`. A desk with no fresh development gets one lookback of the last five days. On the first Tuesday of the month, UTC, every desk also gets an overview of the previous calendar month. That Tuesday is the monthly edition: a Saturday or Sunday the 1st is not a publishing morning, and a Monday the 1st is one day too soon.

Keep `audio_script` and `audio_sections` in the JSON, and cover every desk in the narration. The site records the Kokoro voice from that script. Do not upload an mp4 unless you already have the file at `audio/brief_YYYY-MM-DD.mp4`.

Ledger files belong in `claude/atlas/ledger/`. The run report belongs in `claude/atlas/reports/YYYY-MM-DD.md`.

A fresh item needs at least three independent sources. One is in the country's own language. One is a government or embassy page. If that mix cannot be found, the item needs five independent sources instead. The same wire copied by three sites counts once.

The site copies a file that passes its checks at 05:00 and 05:40 UTC, before the 06:20 UTC email. A briefing that contains a key, a private key, or a token is refused and is not published.

Do not put application code or secrets in this repository.

Pull `main` at the start of a run. A session opened earlier in the day is looking at an older file.

`regions` is a list of `{id, name, items}`, in the desk order above. It is not a map, so `regions.items()` raises `AttributeError`. Count desks like this:

```python
{r["id"]: len(r["items"]) for r in d["regions"]}
```

`audio_script` is the section texts joined with a blank line. Join them as `"\n\n".join(...)`. Writing `chr(10)*2 .join(...)` is a syntax trap: Python treats `2 .join` as a method call on the integer 2.

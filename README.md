# ATLAS editions

This repository holds the daily briefing and nothing else.

Add the edition as:

- `briefings/YYYY-MM-DD.json`
- `audio/brief_YYYY-MM-DD.mp4` (optional)

The site copies a file that passes its checks at 05:00 and 05:40 UTC, before the 06:20 UTC email. Any other path is ignored. A briefing that contains a key, a private key, or a token is refused and is not published.

Do not put application code or secrets in this repository.

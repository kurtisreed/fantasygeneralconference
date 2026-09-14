# Fantasy General Conference

A pick-'em game for General Conference. Players guess who speaks when, who
conducts, what color tie President Oaks wears and how many times somebody says
"covenant." A scorekeeper enters the results as conference happens, and the
standings update live.

Vanilla PHP 8 + MySQL/MariaDB. No framework, no Composer, no build step —
upload the folder and it runs.

## The sheet (71 points)

| Section | Questions | Points |
|---|---:|---:|
| Which session each apostle speaks in | 15 | 15 |
| Who conducts each session | 4 | 4 |
| First speaker of each session | 4 | 8 |
| Which choir sings each session | 4 | 4 |
| Tie colors (Oaks, Eyring, Christofferson) + choir, from a fixed palette | 4 | 12 |
| How many women pray / speak, and how many speakers are from outside the US | 3 | 9 |
| Over/under on "Jesus Christ", "temple(s)", "covenant(s)", "Book of Mormon" | 4 | 12 |
| Over/under on times President Oaks is quoted | 1 | 3 |
| Sessions watched (1 pt each) | 1 | 4 |

Point values and over/under lines are editable in the admin at any time.

### Settling the over/under lines

`tools/count_words.php` fetches every talk of a conference from the Church's
study API and counts the words — only what was spoken, with footnotes, titles
and bylines stripped. Talk text posts within a day or two of each session,
weeks before the Liahona PDF.

```bash
php tools/count_words.php 2026/10           # print the counts
php tools/count_words.php 2026/10 --write   # store them as results and rescore
```

The lines shipped in the seed are set from the last three conferences, measured
with this same script, and every line ends in a half so a tie is impossible.
See [docs/word-counts.md](docs/word-counts.md) for the numbers and the rules.

## Local setup (XAMPP)

```bash
mysql -u root -e "CREATE DATABASE fantasygc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cp config.example.php config.php   # then edit the db credentials
FGC_ADMIN_USER=you FGC_ADMIN_PASS=your-password php tools/install.php
```

Open the folder in a browser. The admin is at `admin/`.

## Deploying to shared hosting

1. Create a MySQL database and user in your host's control panel.
2. Upload everything **except** `config.php` (it's gitignored on purpose).
3. Create `config.php` on the server from `config.example.php`:
   - fill in the database credentials
   - set `'socket' => null`
   - set `'secure_cookies' => true` (assuming HTTPS)
   - set `'debug' => false`
   - change `'setup_token'` to something random
4. Visit `https://yoursite/tools/install.php?token=YOUR_TOKEN&admin_user=you&admin_pass=your-password`
5. **Delete `tools/install.php`.**

`.htaccess` blocks direct access to `config.php`, `lib/` and `sql/` as a
fallback in case PHP ever stops executing.

## Running the game

1. **Questions** — set the over/under lines and any point changes.
2. **Event** — set a lock time (Mountain Time) and flip status to *open*.
   Share the site link.
3. Players enter a name, make picks, and get a 6-character code they can use
   to come back and edit until picks lock.
4. Picks freeze automatically at the lock time, or flip status to *locked*.
5. **Results** — after each session, fill in what you know. Saving recomputes
   every score, so the standings move between sessions.
6. **Players** — enter sessions watched when you score the sheets together.

Every answer is a dropdown, a number or an over/under, so scoring is exact and
there is nothing to arbitrate. Colors come from a fixed palette
(`FGC_COLORS` in `lib/seed.php`) for the same reason — "navy" and "dark blue"
can't be argued about if they're the same list entry.

Scores recompute automatically whenever you save results or sessions watched.
`Event → Rescore everyone` is there for peace of mind.

## Running another conference

Every six months:

```bash
php tools/new_conference.php april-2027 "April 2027 General Conference" \
     --lock="2027-04-03 10:00"
```

That creates the event and its questions, and sets the moment picks freeze
(normally the start of the Saturday morning session, Mountain Time). The site
always shows the newest event, so the previous conference stays intact as
history. Add `--open` to open it for picks right away instead of leaving it in
draft.

Two things to check before each conference:

1. **The speaker list** in `lib/seed.php` (`FGC_APOSTLES`) — update it if the
   First Presidency or Quorum of the Twelve has changed.
2. **The over/under lines** — re-run `tools/count_words.php` against the two
   most recent conferences and move the lines if the trend has shifted. See
   [docs/word-counts.md](docs/word-counts.md).

Everything else — scoring, admin, standings — is generic and needs no changes.

## Layout

```
index.php          name entry / resume with code
play.php           the sheet
submitted.php      confirmation + entry code
leaderboard.php    standings, and one player's sheet with ?player=N
admin/             login, dashboard, results, players, questions, event
lib/               db, scoring engine, question loading, auth, layout
sql/schema.sql     tables
tools/install.php  one-time installer (delete after setup)
tools/count_words.php   fetches conference talks and counts the over/under words
tools/new_conference.php  sets up the sheet for the next conference
docs/word-counts.md    historical counts, counting rules, how the lines were set
```

## Question types

Adding a question means picking a type; the scoring engine already handles all of them.

- `pick_one` — options list; renders as pills (≤6 options) or a dropdown
- `number` — exact match, optional `tolerance` in config
- `over_under` — `line` in config; player picks a side. Lines end in a half so
  ties can't happen; the push branch remains for hand-set whole-number lines
- `text` — free text, matched on normalized text (unused by the current sheet)
- `watched` — self-reported participation, `per` points each up to `max`

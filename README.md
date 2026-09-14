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
| Tie colors (Oaks, Eyring, Christofferson) + choir dress | 4 | 12 |
| How many women pray / speak, and how many speakers are from outside the US | 3 | 9 |
| Over/under on "Jesus Christ", "temple", "covenant", "Book of Mormon" | 4 | 12 |
| Over/under on times President Oaks is quoted | 1 | 3 |
| Sessions watched (1 pt each) | 1 | 4 |

Point values and over/under lines are editable in the admin at any time.

### Setting the over/under lines

The seeded lines are placeholders. Before you open the sheet, count the words
in the *previous* conference's talks on the Church's site and set each line a
bit above or below that number, so the pick is genuinely 50/50. An exact tie
between the line and the real count scores nobody.

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
6. **Text answers** — rule on the tie/dress color spellings. Answers are
   grouped by normalized text, so you rule once per spelling and everyone who
   typed it gets the same call.
7. **Players** — enter sessions watched when you score the sheets together.

Scores recompute automatically whenever you save results, rulings or sessions
watched. `Event → Rescore everyone` is there for peace of mind.

## Running another conference

The seed only knows April 2026. For the next one, copy `fgc_seed()` in
`lib/seed.php`, change the slug, name and speaker list, and call it. Everything
else — scoring, admin, standings — is generic and needs no changes.

## Layout

```
index.php          name entry / resume with code
play.php           the sheet
submitted.php      confirmation + entry code
leaderboard.php    standings, and one player's sheet with ?player=N
admin/             login, dashboard, results, text rulings, players, questions, event
lib/               db, scoring engine, question loading, auth, layout
sql/schema.sql     tables
tools/install.php  one-time installer (delete after setup)
```

## Question types

Adding a question means picking a type; the scoring engine already handles all of them.

- `pick_one` — options list; renders as pills (≤6 options) or a dropdown
- `number` — exact match, optional `tolerance` in config
- `over_under` — `line` in config; player picks a side; exact tie = push
- `text` — free text, resolved by the scorekeeper's rulings
- `watched` — self-reported participation, `per` points each up to `max`

# Deploying to HostGator (or any cPanel host)

The goal is a setup where updating the live site is one click, and where
`config.php` — the only file holding real passwords — lives on the server and
is never in git.

## Before anything: PHP 8.1

cPanel → **MultiPHP Manager** → tick the domain → set **PHP 8.1** or later.

The app refuses to run on anything older and says so plainly, rather than
showing a blank page. Do this first; it's the most common reason a fresh
install appears broken.

## 1. Make a database

cPanel → **MySQL® Databases**:

1. Create a database, e.g. `fantasygc`. cPanel prefixes it with your account
   name, so the real name is something like `kurtis_fantasygc`.
2. Create a user, e.g. `fgc`, with a long random password. Again it becomes
   `kurtis_fgc`.
3. **Add the user to the database** and tick *All Privileges*. This step is
   easy to miss, and without it the app connects but can't read anything.

Write down all three: database name, user name, password. Note the **prefix** —
they are not the short names you typed.

## 2. Get the code onto the server

Pick whichever of these your account offers. The first two make updates a
single action; the third is fine but manual every time.

### Option A — cPanel Git Version Control (easiest to keep updated)

1. cPanel → **SSH Access** → *Manage SSH Keys* → **Generate a New Key**.
   - Leave the **passphrase empty**. cPanel's git runs unattended and cannot
     type a passphrase; a protected key fails every clone and pull.
   - Key type **ed25519** (or RSA 4096 if ed25519 isn't offered).
2. Still in *Manage SSH Keys*, find the key under **Public Keys** and click
   **View/Download**. Copy the text that begins `ssh-ed25519` or `ssh-rsa`.
3. GitHub → your repo → **Settings → Deploy keys → Add deploy key**. Paste it,
   name it "hostgator", and leave *Allow write access* **off**. A read-only
   deploy key is all the server needs.
4. cPanel → **Git™ Version Control** → *Create* → *Clone a Repository*:
   - **Clone URL**: `git@github.com:kurtisreed/fantasygeneralconference.git`
   - **Repository Path**: `public_html/fantasygc`
5. **To update later**: cPanel → Git Version Control → *Manage* → **Pull**.

#### If GitHub says "Key is invalid — must be in OpenSSH public key format"

You've pasted either the private key or the wrong format. GitHub wants a
**single line** starting with the key type:

```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI...  kurtis@hostgator
```

Three things it must *not* be:

| What you pasted | How to tell |
|---|---|
| The private key | starts `-----BEGIN OPENSSH PRIVATE KEY-----` |
| SSH2 / RFC4716 format | starts `---- BEGIN SSH2 PUBLIC KEY ----` |
| A wrapped copy | the key is broken across several lines |

cPanel's **Download Key** button often gives the *private* half — use
**View/Download** on the row under *Public Keys* instead.

If cPanel only shows you the `---- BEGIN SSH2 PUBLIC KEY ----` block, convert
it. Save it as `key.pub` and run, on any machine with ssh installed:

```bash
ssh-keygen -i -f key.pub
```

That prints the one-line OpenSSH form to paste into GitHub.

#### If the clone fails with "could not read Username for 'https://github.com'"

The Clone URL was the HTTPS one. A private repo over HTTPS asks for a login and
there is no terminal to answer, so it fails immediately. Use the SSH form —
note the `git@` and the **colon**, not a slash:

```
git@github.com:kurtisreed/fantasygeneralconference.git
```

If cPanel already created the repository entry with the wrong URL, remove it
(Git Version Control → Manage → Remove, which only unregisters it), delete the
folder in File Manager, and clone again with the SSH URL.

### Option B — SSH

HostGator shared hosting uses **port 2222**:

```bash
ssh -p 2222 youruser@yourdomain.com
cd public_html
git clone git@github.com:kurtisreed/fantasygeneralconference.git fantasygc
```

Add the server's public key (`~/.ssh/id_rsa.pub` on the server) as a GitHub
deploy key first, as in step A2.

**To update later**: `cd ~/public_html/fantasygc && git pull`

### Option C — upload a zip

GitHub → **Code → Download ZIP**, unzip, and upload the contents into
`public_html/fantasygc` with cPanel's File Manager or FTP.

To update, upload again and overwrite — but **never overwrite `config.php`**,
and don't upload a `.git` folder.

## 3. Create config.php on the server

This file is deliberately not in git, which is what lets you `git pull` updates
without ever clobbering your database password.

Copy `config.example.php` to `config.php` (File Manager can do this) and edit:

```php
'db' => [
    'host'   => 'localhost',
    'port'   => 3306,
    'name'   => 'kurtis_fantasygc',   // with the cPanel prefix
    'user'   => 'kurtis_fgc',         // with the cPanel prefix
    'pass'   => 'the password you set',
    'socket' => null,                 // must be null on shared hosting
],
'setup_token'    => 'something-long-and-random',
'secure_cookies' => true,             // assuming https
'debug'          => false,            // never true on a live site
```

## 4. Run the installer once, then delete it

Visit, with your own token and a password you choose:

```
https://yourdomain.com/fantasygc/tools/install.php?token=YOUR_TOKEN&admin_user=kurtis&admin_pass=your-admin-password
```

It creates the tables, seeds the current conference and makes your admin login.

**Then delete `tools/install.php` from the server.** It is guarded by the setup
token, but there is no reason to leave it reachable.

If you deployed with git, deleting it locally makes the next `git pull`
complain. Simplest is to leave the file in the repo and delete it on the server
after each fresh install — you only ever run it once per database.

## 5. Check it

- `https://yourdomain.com/fantasygc/` — the player page
- `https://yourdomain.com/fantasygc/admin/` — sign in
- Admin → **Event** → set the lock time and flip to *open* when you're ready

## Updating later

1. Commit and push from your machine.
2. cPanel → Git Version Control → **Pull** (or `git pull` over SSH).

`config.php` is untouched because git doesn't track it. The database is
untouched because pulling only changes files.

**If a pull changes the database schema** — which only happens when
`sql/schema.sql` gains a table or column — re-run the installer once; it uses
`CREATE TABLE IF NOT EXISTS` and leaves existing data alone. Column changes on
an existing table need to be applied by hand in phpMyAdmin; the commit message
will say so when that comes up.

## Security notes

`.htaccess` blocks direct access to `config.php`, `lib/`, `sql/`, and `.git/`.
That last one matters if you deploy by cloning: without it, anyone could read
your entire source and history at `/fantasygc/.git/`. If your host ignores
`.htaccess` (HostGator doesn't), clone outside `public_html` and copy the files
in instead.

`debug => false` on a live site keeps PHP errors — which can contain paths and
query fragments — out of the page.

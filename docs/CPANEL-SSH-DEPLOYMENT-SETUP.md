# cPanel SSH Deployment Setup Guide

This guide walks you through setting up SSH-based deployment from GitHub Actions to your cPanel hosting account.

---

## Prerequisites

- A cPanel hosting account with SSH access enabled
- Your deployment branch pushed to GitHub

---

## Step 1: Enable SSH Access in cPanel

1. Log in to **cPanel**.
2. Search for **"SSH Access"** in the search bar.
3. Open **SSH Access**.
4. If SSH is disabled, click **Manage SSH Keys** (or **Enable SSH** if available).
5. Make sure your account has **Shell access** enabled. If not, contact your hosting provider.

---

## Step 2: Generate an SSH Key Pair

Generate a deploy key on your local machine (or use any SSH-capable environment):

```bash
# Generate a new key pair (no passphrase for automated deployment)
ssh-keygen -t ed25519 -C "github-deploy-marcelinos" -f deploy_key -N ""
```

This creates:
- `deploy_key` — private key (for GitHub Secrets)
- `deploy_key.pub` — public key (for cPanel)

---

## Step 3: Add the Public Key to cPanel

1. In cPanel, go to **SSH Access** → **Manage SSH Keys**.
2. Click **Import Key**.
3. Choose **Import** (new key).
4. Give it a name (e.g., `github-deploy`).
5. Paste the contents of `deploy_key.pub` into the **Public Key** field.
6. Click **Import**.
7. After importing, click **Manage** next to the key.
8. Click **Authorize** to add it to `~/.ssh/authorized_keys`.

---

## Step 4: Get Your cPanel SSH Details

### Host

- Your domain (e.g. `example.com`) or server hostname
- Or the server IP address (e.g. `123.45.67.89`)

### Username

- Usually your cPanel username (the one you use to log in to cPanel)

### Port

- Default SSH port: **22**
- If your host uses a different port, add it to the workflow (see Step 7)

### Remote Path

- Web root for your app, for example:
  - `~/public_html/test-marcelinos/` (staging)
  - `~/public_html/` (main site)

In cPanel, `~` = `/home/your_username/`.

Full path examples:
- `/home/your_username/public_html/test-marcelinos`
- `/home/your_username/test-marcelinos`

---

## Step 5: Create the Remote Directory

1. In cPanel, open **File Manager**.
2. Go to `public_html` (or desired location).
3. Create a folder for the app (e.g. `test-marcelinos`).
4. Set permissions so the cPanel user can write (usually 755 or 775).
5. Put your `.env` file there and configure it (the workflow does not deploy `.env`).

---

## Step 6: Add GitHub Secrets

1. Open your GitHub repository.
2. Go to **Settings** → **Secrets and variables** → **Actions**.
3. Click **New repository secret** and add:

| Secret Name       | Value                                   |
|-------------------|-----------------------------------------|
| `SSH_PRIVATE_KEY` | Entire contents of `deploy_key` (private key, including `-----BEGIN...` and `-----END...`) |
| `SSH_HOST`        | Your domain or server IP (e.g. `example.com`) |
| `SSH_USER`        | Your cPanel username                    |
| `SSH_REMOTE_PATH` | Full path to deploy folder (e.g. `/home/username/public_html/test-marcelinos`) |

### Getting the Private Key

```bash
# On your machine (where you generated the key)
cat deploy_key
```

Copy the full output, including:
```
-----BEGIN OPENSSH PRIVATE KEY-----
...key content...
-----END OPENSSH PRIVATE KEY-----
```

Paste it into the `SSH_PRIVATE_KEY` secret value.

---

## Step 7: Custom Port (Optional)

If SSH uses a non‑22 port:

1. Edit `.github/workflows/deployment.yaml`.
2. In the `rsync` step, change the SSH command to:
   ```
   ssh -i ~/.ssh/deploy_key -o StrictHostKeyChecking=no -p YOUR_PORT
   ```
3. Replace `YOUR_PORT` with your SSH port.

---

## Step 8: Test the Deployment

1. Push a commit to the `testing` branch (or the branch configured in the workflow).
2. Go to the **Actions** tab in GitHub.
3. Open the latest workflow run and check the logs.
4. If it fails, verify:
   - Public key is imported and authorized in cPanel
   - Username, host, and path are correct
   - Remote directory exists and is writable
   - `.env` is present on the server

---

## Troubleshooting

### "Permission denied (publickey)"

- Confirm the public key is imported and authorized in cPanel.
- Check that you are using the correct cPanel username for `SSH_USER`.
- Ensure the private key secret is complete (no extra spaces or line breaks).

### "No such file or directory"

- `SSH_REMOTE_PATH` must exist. Create it in cPanel File Manager if needed.
- Use absolute paths, e.g. `/home/username/public_html/test-marcelinos`.

### "Connection refused"

- SSH may be disabled or on a non‑standard port. Check with your host.
- Try the same connection manually: `ssh -i deploy_key user@host`.

### Migrations / Artisan errors on deploy

- Ensure `.env` is on the server and has correct DB credentials.
- Adjust or remove the post-deploy `php artisan` commands in the workflow if needed.

---

## Security Notes

- Keep the private key secret. Do not commit it.
- Prefer a dedicated deploy key instead of your main account SSH key.
- Limit write access to the deploy directory.
- If possible, use a user with restricted permissions for deployment.

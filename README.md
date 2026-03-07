# Multi-Tenant FBO Blog Platform

## Architecture

```
/  (webroot)
  .htaccess               ← URL rewrites
  index.php               ← Front-Controller → core/bootstrap.php
  config.php              ← DB credentials (copy from config.example.php)
  fbo/                    ← Central PHP app code (copy fbo-blog-template/fbo/ here once)
    index.php
    snippets/
    assets/               ← CSS/JS served at /fbo/assets/
  blogs/                  ← Auto-created per tenant
    demo/
      backend/            ← posts.json, settings.json, .auth.json
      media/              ← uploaded files, served at /blogs/demo/media/
      uploads/
  core/
    bootstrap.php         ← Path router: /blog/<word> → blogs/<word>/
    db.php                ← MySQL PDO helper
    tenant.php            ← Provision logic
    provision.php         ← CLI provisioner
    schema.sql            ← Run once to create `blogs` table
  templates/
    index.php             ← Landing page + self-service onboarding
  config.example.php
```

## URL scheme

| URL | What loads |
|---|---|
| `yourdomain.tld/` | Landing / onboarding form |
| `yourdomain.tld/blog/demo` | demo's blog |
| `yourdomain.tld/blog/anna` | anna's blog |

No wildcard DNS needed. Works on Shared Hosting.

## Setup on Webhoster (5 steps)

1. **Upload** `multi-tenant/` contents **and** `fbo/` folder to `/` (same level)

2. **Create DB** then run schema:
```sql
CREATE TABLE IF NOT EXISTS blogs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blog_word  VARCHAR(24) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

3. **Configure** `config.php` (copy from `config.example.php`, fill in DB credentials)

4. **Point** domain root to `/` — the `.htaccess` handles all routing

5. **Create first blog** by opening `https://yourdomain.tld/` and filling out the form

## CLI provisioning (when SSH is available)

```sh
php core/provision.php demo mysecretpw
```

## Security notes

- All blog lookups go through the DB — no user input can traverse the filesystem
- `core/`, `blogs/`, `templates/`, and `config.php` are blocked from direct HTTP access via `.htaccess`
- Media files live in `blogs/<name>/media/` which is web-accessible but contains only uploaded files


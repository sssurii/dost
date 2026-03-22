# Docker File Ownership And Permissions

## Problem

This project bind-mounts the repo into the `app` container at `/var/www/html`.

When a file is created on a bind mount, the host sees the UID and GID of the process that created it. In this repo:

- `docker compose exec app ...` runs as `root`
- `docker compose exec --user sail app ...` runs as `sail` (`uid=1000 gid=1000`)

That is why raw `docker compose exec app php artisan make:*` creates root-owned files on the host.

## Permanent Fix Used In This Repo

We use three pieces together:

1. Run dev commands as `sail`
2. Force `umask 0002` for those dev commands
3. Optionally align the repo to a shared host group for multi-user editing

### Runtime UID **and** GID alignment

`start-container` remaps `sail` to the host user's UID **and GID** at startup:

```bash
# GID first — groupmod must complete before usermod touches group membership
groupmod -g "$WWWGROUP" sail 2>/dev/null || true
usermod  -u "$WWWUSER"  sail
```

Both `WWWUSER` and `WWWGROUP` are passed as runtime environment variables via `docker-compose.yml`. This ensures that files created in the container carry the host user's primary GID, making `umask 0002` group-writability actually useful.

## Day-To-Day Commands

Use the wrapper scripts in [`bin/`](/home/fnl/dev/dost/bin):

```bash
./bin/artisan make:livewire Voice/RecordingButton
./bin/artisan make:model Recording
./bin/composer check
./bin/npm run dev
./bin/dapp bash
```

What they do:

- run commands with `docker compose exec --user sail`
- set `umask 0002`
- use `/var/www/html/storage/tmp` as a writable temp directory

Result:

- new files are created by `sail` instead of `root`
- new files default to group-writable modes such as `664`
- new directories default to group-writable modes such as `775`
- tools such as PHPStan avoid stale root-owned cache files under container `/tmp`

## Multi-User Shared Editing

If only one local user works in this repo, the wrappers above are enough.

If multiple host users need to edit the same checkout, also align the repo to a shared host group:

```bash
./bin/share-repo-group developers
```

This helper:

- changes the repo group recursively (excluding `.git/` internals)
- applies the setgid bit on directories (`2775`)
- makes files group-writable

Why this matters:

- setgid on directories makes new files inherit the directory group
- `umask 0002` keeps that group writable

Without this step, files may still be owned by the correct user but only that user's primary group may be attached.

## Verification

Check the current problem:

```bash
docker compose exec -T app sh -lc 'id && whoami'
```

Expected current output:

- `uid=0(root)`
- `root`

Check the fixed workflow:

```bash
docker compose exec -T --user sail app sh -lc 'id && umask'
```

Expected output:

- `uid=1000(sail)`
- `0022` when run raw

Check the wrapper behavior:

```bash
./bin/dapp sh -lc 'id && umask && touch .perm-check && stat -c "%a %U:%G %n" .perm-check && rm .perm-check'
```

Expected output:

- user is `sail`
- `umask` is `0002`
- new file mode is `664`

## Important Notes

- `umask 0002` alone does not fix root ownership.
- shared-group setup alone does not fix root ownership.
- the ownership fix is running commands as `sail`.
- the group-write improvement is `umask 0002`.
- the shared-team improvement is the host shared-group setup.
- **GID must also be aligned** — UID alignment alone leaves files with a GID the host user's shell cannot write to.

## Quality Commands

Prefer wrappers for common container tasks:

```bash
./bin/composer check
./bin/artisan test --compact
./bin/npm run build
```

## Problem

This project bind-mounts the repo into the `app` container at `/var/www/html`.

When a file is created on a bind mount, the host sees the UID and GID of the process that created it. In this repo:

- `docker compose exec app ...` runs as `root`
- `docker compose exec --user sail app ...` runs as `sail` (`uid=1000 gid=1000`)

That is why raw `docker compose exec app php artisan make:*` creates root-owned files on the host.

## Permanent Fix Used In This Repo

We use three pieces together:

1. Run dev commands as `sail`
2. Force `umask 0002` for those dev commands
3. Optionally align the repo to a shared host group for multi-user editing

## Day-To-Day Commands

Use the wrapper scripts in [`bin/`](/home/fnl/dev/dost/bin):

```bash
./bin/artisan make:livewire Voice/RecordingButton
./bin/artisan make:model Recording
./bin/composer check
./bin/npm run dev
./bin/dapp bash
```

What they do:

- run commands with `docker compose exec --user sail`
- set `umask 0002`
- use `/var/www/html/storage/tmp` as a writable temp directory

Result:

- new files are created by `sail` instead of `root`
- new files default to group-writable modes such as `664`
- new directories default to group-writable modes such as `775`
- tools such as PHPStan avoid stale root-owned cache files under container `/tmp`

## Multi-User Shared Editing

If only one local user works in this repo, the wrappers above are enough.

If multiple host users need to edit the same checkout, also align the repo to a shared host group:

```bash
./bin/share-repo-group developers
```

This helper:

- changes the repo group recursively
- applies the setgid bit on directories (`2775`)
- makes files group-writable

Why this matters:

- setgid on directories makes new files inherit the directory group
- `umask 0002` keeps that group writable

Without this step, files may still be owned by the correct user but only that user's primary group may be attached.

## Verification

Check the current problem:

```bash
docker compose exec -T app sh -lc 'id && whoami'
```

Expected current output:

- `uid=0(root)`
- `root`

Check the fixed workflow:

```bash
docker compose exec -T --user sail app sh -lc 'id && umask'
```

Expected output:

- `uid=1000(sail)`
- `0022` when run raw

Check the wrapper behavior:

```bash
./bin/dapp sh -lc 'id && umask && touch .perm-check && stat -c "%a %U:%G %n" .perm-check && rm .perm-check'
```

Expected output:

- user is `sail`
- `umask` is `0002`
- new file mode is `664`

## Important Notes

- `umask 0002` alone does not fix root ownership.
- shared-group setup alone does not fix root ownership.
- the ownership fix is running commands as `sail`.
- the group-write improvement is `umask 0002`.
- the shared-team improvement is the host shared-group setup.

## Quality Commands

Prefer wrappers for common container tasks:

```bash
./bin/composer check
./bin/artisan test --compact
./bin/npm run build
```

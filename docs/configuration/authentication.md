# Authentication

Configure SSH authentication for remote systems.

## Authentication Methods

php-sync-tool supports several SSH authentication methods:

| Method | Security | CI/CD | Config Key |
|--------|----------|-------|------------|
| SSH Agent | High | Varies | `ssh_agent` (root level) |
| SSH Key | High | Yes | `ssh_key` |
| Password | Low | No | `password` |
| Interactive prompt | Low | No | `--force-password` |

The endpoint's own `ssh_key` and `password` are tried first, in that order; a
loaded agent is used when neither is configured, whether or not `ssh_agent` says
so. A `ssh_key` that is set therefore wins over the agent, so remove it when you
want the agent.

When an endpoint has none of them, the tool asks for a password on a terminal,
unless `ssh_agent: true` says the agent is the way in. Without a terminal, which
is the normal case in CI and on a deploy host, it stops instead and names the
endpoint and what it is missing, rather than waiting on a question nobody will
answer. Only endpoints the run actually connects to are asked about: an
import-only run never reaches the origin, a dump-only run never reaches the
target.

::: warning ~/.ssh/config is not read
The primary connection to `origin` and `target` runs through phpseclib, not the
system SSH client, so `~/.ssh/config` does not apply: `host` must be a real
hostname or IP, not a `Host` alias, and `user`, `port` and `IdentityFile` from
that file are not picked up either. Only [jump hosts](/configuration/advanced#jump-host)
tunnel through the system `ssh` client.
:::

## SSH Agent (Recommended)

An agent that is running and holds at least one key is used on its own, as long
as the endpoint has no `ssh_key` and no `password` of its own. This is the way to
use a passphrase-protected key: phpseclib cannot decrypt one itself, the agent
holds it unlocked.

Set `ssh_agent: true` at the root of the configuration to insist on the agent,
for instance when the tool cannot reach the socket to see it for itself.

```yaml
# config.yaml
ssh_agent: true

origin:
  host: prod.example.com
  user: deploy
```

```bash
# Start the agent and add your key
eval "$(ssh-agent)"
ssh-add ~/.ssh/id_ed25519   # macOS: ssh-add --apple-use-keychain ~/.ssh/id_ed25519

# Confirm the key is loaded, then sync
ssh-add -l
bin/sync-tool -f config.yaml
```

With no agent running, no `ssh_key` and no `password`, the tool asks for a
password on a terminal and stops with `No SSH authentication configured for …`
without one.

`ssh_agent: true` opts out of that fallback. It states that the agent is the way
in, so an agent that cannot be reached ends the run with `No SSH agent available
for …` rather than quietly asking for a password instead. Leave it unset to get
the agent when it is there and the prompt when it is not.

## SSH Key

Point at a private key file per endpoint:

```yaml
origin:
  host: prod.example.com
  user: deploy
  ssh_key: /home/user/.ssh/id_ed25519
```

::: tip CI/CD Usage
SSH key authentication is recommended for pipelines. Store the key as a secret,
write it to a file, and reference it via `ssh_key` (or `--origin-key` /
`--target-key`).
:::

## Password (Not Recommended)

You can specify a password directly, but avoid it where possible:

```yaml
origin:
  host: prod.example.com
  user: deploy
  password: my_password  # avoid — prefer keys or an agent
```

Passwords are masked in log output.

### Force Interactive Password

Use `--force-password` to always prompt for the SSH password instead of using a
key or agent. This is the way in when a configured key is the wrong one, or is
protected by a passphrase and no agent is running:

```bash
bin/sync-tool -f config.yaml --force-password
```

The flag needs a terminal. An empty answer is rejected rather than sent as a
password.

::: warning rsync + password
rsync takes no password of its own, so a password-authenticated transfer needs
`sshpass` on the executing host. When a password is in play and the binary is
installed, it is used without further configuration. Without it the transfer
stops at a prompt, so use key or agent authentication instead, or fall back to
SFTP with `--no-rsync`.
:::

## Host Key Verification

SSH host keys are verified by default to prevent man-in-the-middle attacks. On
first connection, ensure the remote host is present in your `~/.ssh/known_hosts`:

```bash
# Add a host key ahead of time
ssh-keyscan -H prod.example.com >> ~/.ssh/known_hosts

# …or connect once manually and accept the key
ssh deploy@prod.example.com
```

This applies to both channels a sync uses: the SSH connection that runs commands
and the `rsync` connection that moves the dump and any files. Both consult the
same `~/.ssh/known_hosts` and both follow the setting below.

For controlled environments (e.g. ephemeral CI containers or DDEV) where
maintaining `known_hosts` is impractical, verification can be disabled:

```yaml
ssh_strict_host_key_checking: false
```

::: warning
Only disable host-key verification in trusted, controlled environments. Never
disable it against production hosts on untrusted networks.
:::

::: tip
`rsync` transfers used to pass `StrictHostKeyChecking=no` regardless of this
setting, so they never failed on an unknown host. They now honour it. If a
transfer starts failing, the host is missing from `known_hosts`; add it with
`ssh-keyscan` as shown above.
:::

## Jump Host Authentication

For [jump host](/configuration/advanced#jump-host) setups, provide the jump
host's own credentials under `jump_host`; if omitted, the endpoint's user/port
are reused:

```yaml
origin:
  host: internal.example.com
  user: app_user
  ssh_key: /home/user/.ssh/internal_key
  jump_host:
    host: bastion.example.com
    user: bastion_user
    ssh_key: /home/user/.ssh/bastion_key
```

## Troubleshooting

### Permission Denied

1. Check key permissions: `chmod 600 ~/.ssh/id_ed25519`
2. Verify the user has access: `ssh deploy@prod.example.com`
3. Confirm the key is loaded: `ssh-add -l`

### Host Key Verification Failed

Add the host to `known_hosts` (see above), or set
`ssh_strict_host_key_checking: false` in a trusted environment.

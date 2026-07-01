# Authentication

Configure SSH authentication for remote systems.

## Authentication Methods

php-sync-tool supports several SSH authentication methods:

| Method | Security | CI/CD | Config Key |
|--------|----------|-------|------------|
| SSH Agent | High | Varies | (automatic) |
| SSH Key | High | Yes | `ssh_key` |
| Password | Low | No | `password` |
| Interactive prompt | Low | No | `--force-password` |

## SSH Agent (Recommended)

With no key or password configured, php-sync-tool authenticates using your
running SSH agent:

```bash
# Start the agent and add your key
eval "$(ssh-agent)"
ssh-add ~/.ssh/id_ed25519

# Run the sync — the agent is used automatically
bin/sync-tool -f config.yaml
```

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
key or agent:

```bash
bin/sync-tool -f config.yaml --force-password
```

::: warning rsync + password
Password-based **rsync** transfers require `sshpass` on the executing host. If
it is unavailable, use key/agent authentication or fall back to SFTP with
`--no-rsync`.
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

For controlled environments (e.g. ephemeral CI containers or DDEV) where
maintaining `known_hosts` is impractical, verification can be disabled:

```yaml
ssh_strict_host_key_checking: false
```

::: warning
Only disable host-key verification in trusted, controlled environments. Never
disable it against production hosts on untrusted networks.
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

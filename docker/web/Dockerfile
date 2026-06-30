# Web node image: an SSH-reachable host with the PHP CLI, MySQL client,
# rsync, gzip and sshpass — everything php-sync-tool needs to dump, transfer
# and import between containers.
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        openssh-server \
        openssh-client \
        default-mysql-client \
        rsync \
        gzip \
        sshpass \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /run/sshd \
    && echo 'root:root' | chpasswd \
    && sed -i 's/#\?PermitRootLogin.*/PermitRootLogin yes/' /etc/ssh/sshd_config \
    && sed -i 's/#\?PasswordAuthentication.*/PasswordAuthentication yes/' /etc/ssh/sshd_config

# A throwaway keypair baked into the (single, shared) image so every node trusts
# the same key — enables key-based `ssh -J` jump-host hops without passwords.
# StrictHostKeyChecking is disabled for this controlled playground only.
RUN mkdir -p /root/.ssh \
    && ssh-keygen -t ed25519 -f /root/.ssh/id_ed25519 -N '' -q \
    && cp /root/.ssh/id_ed25519.pub /root/.ssh/authorized_keys \
    && chmod 700 /root/.ssh \
    && chmod 600 /root/.ssh/authorized_keys /root/.ssh/id_ed25519 \
    && printf 'Host *\n    StrictHostKeyChecking no\n    UserKnownHostsFile /dev/null\n' > /root/.ssh/config

WORKDIR /app

EXPOSE 22

CMD ["/usr/sbin/sshd", "-D", "-e"]

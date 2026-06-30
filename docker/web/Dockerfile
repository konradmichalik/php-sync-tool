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

WORKDIR /app

EXPOSE 22

CMD ["/usr/sbin/sshd", "-D", "-e"]

# docker-ssl-proxy
[![Apache](https://img.shields.io/badge/license-APACHE-blue.svg)](http://www.apache.org/licenses/LICENSE-2.0)
[![Docker](https://img.shields.io/docker/build/samejack/ssl-proxy.svg)](https://hub.docker.com/r/samejack/ssl-proxy)

HAProxy plug Let's encrypt auto request and renew function.

## Getting and Start
### Pull image first
```
docker pull samejack/ssl-proxy
```
### Make config as follows:
```
vim ./hle/email@example.com
```
Filename email@example.com is your let's encrypt account, and config file content as follows:
```
[
    {
        "domain": ["example.com", "www.example.com"],
        "server": ["127.0.0.1:8080"]
    },
    {
        "domain": ["example2.com"],
        "server": ["192.168.0.1:80", "192.168.0.2:80"]
    }
]
```
Sample is means email@example.com Let's encrypt account resuest two certification of web site.

### Run container (Use HLE_STAGING=true to start staging mode)
```
docker run -it \
    --publish 443:443 --publish 80:80 \
    --name ssl-proxy \
    --env HLE_STAGING=true \
    --env HLE_INTERVAL=10 \
    --volume hle:/etc/hle \
    --volume ssl:/etc/ssl/le-storage \
    --volume your-document-root:/var/www/html \
    samejack/ssl-proxy:latest
```
#### Volume Defined
Path  |Description|
------|-----------|
/etc/hle|SSL Proxy Config Files|
/etc/ssl/le-storage|Certification File Path|
/var/www/html|Default WWW Document Root|

### How to reload config force
```
docker exec -it ssl-proxy php /usr/share/hle/hle-renew.php
```

## Docker Environment
Name  |Default|Description|
------|------|------|
HLE_INTERVAL|300|Config file check interval|
HLE_STAGING|(Undefined)|Let's encrypt staging mode|

## Developer
Make docker image in linux OS.
```
git clone https://github.com/samejack/docker-ssl-proxy.git
cd docker-ssl-proxy.git
make
```

## License
Apache License 2.0

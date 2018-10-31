#!/bin/bash

# ACME directory
if [ ! -d "/var/www/letsencrypt/.well-known/acme-challenge" ]; then
  mkdir -p "/var/www/letsencrypt/.well-known/acme-challenge"
fi
chown www-data:www-data -R /var/www/letsencrypt
chown www-data:www-data -R /var/www/html

if [ ! -d "/etc/hle/pem" ]; then
  mkdir -p "/etc/hle/pem"
fi

cp /etc/haproxy/haproxy.cfg.init /etc/haproxy/haproxy.cfg

# Supervisord start
/usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf

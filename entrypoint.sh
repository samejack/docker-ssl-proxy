#!/bin/bash

# ACME directory
if [ ! -d "/var/www/letsencrypt/.well-known/acme-challenge" ]; then
  mkdir -p "/var/www/letsencrypt/.well-known/acme-challenge"
fi

# Supervisord start
/usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf

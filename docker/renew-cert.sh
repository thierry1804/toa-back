#!/bin/bash
set -euo pipefail

docker run --rm \
  -v toa-back_certbot_conf:/etc/letsencrypt \
  -v toa-back_certbot_webroot:/var/www/certbot \
  certbot/certbot renew --webroot -w /var/www/certbot --quiet

cd /home/debian/toa-back
docker-compose exec -T nginx nginx -s reload

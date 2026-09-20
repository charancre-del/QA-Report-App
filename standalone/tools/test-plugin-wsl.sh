#!/bin/sh
set -eu
# Temporary, unprivileged PHP extensions; does not alter system PHP or install packages.
cqa_bundle=$(mktemp -d /tmp/cqa-php-XXXXXX)
cd "$cqa_bundle"
apt download php8.3-xml php8.3-mbstring libonig5 >/dev/null 2>&1
for cqa_package in ./*.deb; do dpkg-deb -x "$cqa_package" "$cqa_bundle/root"; done
cqa_extensions="$cqa_bundle/root/usr/lib/php/20230831"
printf 'Temporary PHP extension bundle: %s\n' "$cqa_bundle"
cd "$1"
LD_LIBRARY_PATH="$cqa_bundle/root/usr/lib/x86_64-linux-gnu${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}" /usr/bin/php \
  -d "extension=$cqa_extensions/dom.so" \
  -d "extension=$cqa_extensions/simplexml.so" \
  -d "extension=$cqa_extensions/xml.so" \
  -d "extension=$cqa_extensions/xmlwriter.so" \
  -d "extension=$cqa_extensions/mbstring.so" vendor/bin/phpunit

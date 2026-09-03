#!/usr/bin/env bash
# Sign in to the local panel and leave the session in a cookie jar.
#   bash tools/login.sh [jar] [email] [password]
set -euo pipefail
BASE=${BASE:-http://127.0.0.1:8088}
JAR=${1:-/tmp/wwt.jar}; EMAIL=${2:-owner@wwwebtech.in}; PASS=${3:-devpassword123}
rm -f "$JAR"
TOK=$(curl -s -c "$JAR" "$BASE/admin/?p=login" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
[ -n "$TOK" ] || { echo "no csrf token" >&2; exit 1; }
CODE=$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' -X POST \
  --data-urlencode "_csrf=$TOK" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASS" \
  "$BASE/admin/?p=login")
echo "login $EMAIL -> HTTP $CODE  (jar: $JAR)"
[ "$CODE" = "303" ] || [ "$CODE" = "302" ]

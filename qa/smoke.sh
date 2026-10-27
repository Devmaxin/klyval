#!/usr/bin/env bash
# Phase 4 smoke test. Needs: php 8.1+ CLI, curl, a configured .env, and `php bin/migrate.php` already run.
# Usage: bash qa/smoke.sh        (starts php -S on :8099, cleans up its own test rows)
set -u
cd "$(dirname "$0")/.."
PORT=8099; BASE="http://127.0.0.1:$PORT"; BAD='Fatal error|Warning:|Notice:|Deprecated:|Parse error|PDOException|SQLSTATE|Stack trace|Uncaught'
pass=0; fail=0; ok(){ pass=$((pass+1)); echo "  PASS  $1"; }; bad(){ fail=$((fail+1)); echo "  FAIL  $1"; }
check(){ [ "$2" = "$3" ] && ok "$1" || bad "$1 (expected $3, got $2)"; }
leaks(){ grep -qE "$BAD" "$1" && bad "$2 leaks a PHP/DB error" || ok "$2 shows no raw errors"; }
php -S 127.0.0.1:$PORT -t public >/tmp/klyval-smoke.log 2>&1 & SRV=$!
trap 'kill $SRV $SRV2 2>/dev/null; php -r "require \"app/helpers/bootstrap.php\"; Database::query(\"DELETE FROM contact_submissions WHERE email LIKE ?\",[\"smoke+%@example.test\"]);" 2>/dev/null' EXIT; sleep 1
count(){ php -r 'require "app/helpers/bootstrap.php"; echo Database::query("SELECT COUNT(*) c FROM contact_submissions WHERE email LIKE ?",["smoke+%@example.test"])->fetch()["c"];'; }
tok(){ curl -s -c "$1" -b "$1" "$BASE/contact" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post(){ jar=$1; shift; curl -s -c "$jar" -b "$jar" -o /tmp/ks.html -w '%{http_code}' -X POST "$BASE/contact" "$@"; }
MAIL="smoke+$RANDOM@example.test"
VALID=(--data-urlencode "name=Smoke Test" --data-urlencode "email=$MAIL" --data-urlencode "project_type=Website" --data-urlencode "message=This is an automated smoke test message.")

echo "Routes"
for p in / /work "/work?kind=client" /studio /services /contact /admin/login; do
  check "GET $p" "$(curl -s -o /tmp/ks.html -w '%{http_code}' "$BASE$p")" 200; leaks /tmp/ks.html "GET $p"; done
for p in /work/nope /team/nope /nope; do check "GET $p is 404" "$(curl -s -o /tmp/ks.html -w '%{http_code}' "$BASE$p")" 404; leaks /tmp/ks.html "GET $p"; done

echo "Admin is protected"
check "GET /admin redirects to login when signed out" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin")" 302
check "GET /admin/settings redirects when signed out" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin/settings")" 302
check "POST /admin/projects/create without session is not accepted" "$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/projects/create")" 419
check "uploads folder cannot list" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/uploads/")" 403
echo "Contact form"
J=$(mktemp); T=$(tok "$J"); sleep 4; B=$(count)
check "invalid input rejected (422)" "$(post "$J" --data-urlencode "_csrf=$T" --data-urlencode "name=" --data-urlencode "email=nope" --data-urlencode "project_type=x" --data-urlencode "message=hi")" 422
grep -q 'Tell us your name' /tmp/ks.html && ok "field errors shown" || bad "field errors shown"; leaks /tmp/ks.html "invalid post"
check "no row stored for invalid input" "$(count)" "$B"
check "missing CSRF rejected (419)" "$(post "$J" "${VALID[@]}")" 419
check "wrong CSRF rejected (419)" "$(post "$J" --data-urlencode "_csrf=wrong" "${VALID[@]}")" 419
check "honeypot: silent success (302)" "$(post "$J" --data-urlencode "_csrf=$T" --data-urlencode "website=http://spam" "${VALID[@]}")" 302
check "honeypot: nothing stored" "$(count)" "$B"
check "valid submission redirects (302)" "$(post "$J" --data-urlencode "_csrf=$T" "${VALID[@]}")" 302
check "valid submission inserted" "$(count)" "$((B+1))"
curl -s -b "$J" "$BASE/contact?sent=1" | grep -q 'Message sent' && ok "confirmation page" || bad "confirmation page"
check "rate limit: immediate second post (422)" "$(post "$J" --data-urlencode "_csrf=$T" "${VALID[@]}")" 422
grep -q 'Please wait' /tmp/ks.html && ok "rate-limit message shown" || bad "rate-limit message shown"
check "rate limit: nothing extra stored" "$(count)" "$((B+1))"
J2=$(mktemp); T2=$(tok "$J2"); check "instant bot-speed post: silent, not stored" "$(post "$J2" --data-urlencode "_csrf=$T2" "${VALID[@]}")" 302
check "bot-speed: nothing extra stored" "$(count)" "$((B+1))"

echo "Database failure is not exposed"
DB_NAME=klyval_does_not_exist php -S 127.0.0.1:$((PORT+1)) -t public >/dev/null 2>&1 & SRV2=$!; sleep 1
J3=$(mktemp); T3=$(curl -s -c "$J3" -b "$J3" "http://127.0.0.1:$((PORT+1))/contact" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'); sleep 4
code=$(curl -s -c "$J3" -b "$J3" -o /tmp/ks.html -w '%{http_code}' -X POST "http://127.0.0.1:$((PORT+1))/contact" --data-urlencode "_csrf=$T3" "${VALID[@]}")
check "DB down: friendly failure (422)" "$code" 422; leaks /tmp/ks.html "DB-down response"
grep -q "wasn't sent" /tmp/ks.html && ok "DB down: visitor told it wasn't sent" || bad "DB down message"

echo; echo "Passed $pass, failed $fail"; [ $fail -eq 0 ]

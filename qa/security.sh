#!/usr/bin/env bash
# HTTP security + content-visibility + redirect + throttle checks. Needs php CLI (with gd), curl, a migrated scratch DB.
# Creates temporary admin/editor accounts and test content, and removes them at the end. NEVER run against production data.
set -u
cd "$(dirname "$0")/.."
PORT=8097; BASE="http://127.0.0.1:$PORT"; TAG="qa$RANDOM"; PW="Correct-Horse-9-$RANDOM"; AE="$TAG-admin@example.test"; EE="$TAG-editor@example.test"
pass=0; fail=0; ok(){ pass=$((pass+1)); echo "  PASS  $1"; }; bad(){ fail=$((fail+1)); echo "  FAIL  $1"; }
check(){ [ "$2" = "$3" ] && ok "$1" || bad "$1 (expected $3, got $2)"; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
php -S 127.0.0.1:$PORT -t public >/tmp/klyval-sec.log 2>&1 & SRV=$!
cleanup(){ kill $SRV 2>/dev/null; php -r 'require "app/helpers/bootstrap.php"; $t=$argv[1];
 Database::query("DELETE FROM admin_users WHERE email LIKE ?",["$t-%@example.test"]);
 foreach(["projects","team_members","services"] as $x) Database::query("DELETE FROM $x WHERE slug LIKE ?",["$t-%"]);
 Database::query("DELETE FROM login_attempts");' "$TAG" 2>/dev/null; }
trap cleanup EXIT; sleep 1
php -r 'require "app/helpers/bootstrap.php"; [$t,$pw]=[$argv[1],$argv[2]];
 foreach(["admin","editor"] as $r) Database::query("INSERT INTO admin_users (name,email,password_hash,role) VALUES (?,?,?,?)",["QA $r","$t-$r@example.test",password_hash($pw,PASSWORD_DEFAULT),$r]);
 $ins=function($sql,$p){Database::query($sql,$p);return (int)Database::pdo()->lastInsertId();};
 $pub=$ins("INSERT INTO projects (slug,name,kind,status,featured,is_placeholder,published_at) VALUES (?,?,?,?,1,1,NOW())",["$t-pub","QAVISIBLE-PUB","client","published"]);
 $dr=$ins("INSERT INTO projects (slug,name,kind,status,featured,is_placeholder) VALUES (?,?,?,?,1,1)",["$t-draft","QAHIDDEN-DRAFT","client","draft"]);
 $ar=$ins("INSERT INTO projects (slug,name,kind,status,featured,is_placeholder) VALUES (?,?,?,?,1,1)",["$t-arch","QAHIDDEN-ARCH","client","archived"]);
 $mi=$ins("INSERT INTO team_members (slug,name,role,featured,is_active,is_placeholder) VALUES (?,?,?,1,0,1)",["$t-inactive","QAHIDDEN-MEMBER","Dev"]);
 $ma=$ins("INSERT INTO team_members (slug,name,role,featured,is_active,is_placeholder) VALUES (?,?,?,1,1,1)",["$t-active","QAVISIBLE-MEMBER","Dev"]);
 $sv=$ins("INSERT INTO services (slug,name,pillar,show_on_home,is_active,is_placeholder) VALUES (?,?,?,1,0,1)",["$t-svc","QAHIDDEN-SERVICE","build"]);
 Database::query("INSERT INTO project_team (project_id,member_id) VALUES (?,?),(?,?)",[$pub,$mi,$pub,$ma]);
 echo $pub;' "$TAG" "$PW" > /tmp/qa-pub-id || { echo "setup failed (is the DB migrated?)"; exit 1; }
PUBID=$(cat /tmp/qa-pub-id)
tok(){ curl -s -c "$1" -b "$1" "$BASE$2" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ T=$(tok "$1" /admin/login); code -c "$1" -b "$1" -X POST "$BASE/admin/login" --data-urlencode "_csrf=$T" --data-urlencode "email=$2" --data-urlencode "password=$PW"; }

echo "1. Every POST route: CSRF first, then authentication (signed out)"
J=$(mktemp); T=$(tok "$J" /admin/login)
grep -o "\$router->post('[^']*'" routes/web.php | sed "s/.*('//;s/'//;s/{id}/1/" | while read -r path; do
  c1=$(code -X POST "$BASE$path"); [ "$c1" = 419 ] && echo "PASS no token -> 419 $path" || echo "FAIL no token $path got $c1"
  case "$path" in /admin/login|/contact) ;; *) c2=$(code -c "$J" -b "$J" -X POST "$BASE$path" --data-urlencode "_csrf=$T"); [ "$c2" = 302 ] && echo "PASS token but signed out -> 302 $path" || echo "FAIL signed out $path got $c2";; esac
done | tee /tmp/qa-post.txt | grep FAIL; p=$(grep -c PASS /tmp/qa-post.txt); f=$(grep -c FAIL /tmp/qa-post.txt); pass=$((pass+p)); fail=$((fail+f)); echo "  ($p passed, $f failed)"

echo "2. Roles"
JA=$(mktemp); JE=$(mktemp); check "admin can sign in" "$(login "$JA" "$AE")" 302; check "editor can sign in" "$(login "$JE" "$EE")" 302
check "editor can open Projects" "$(code -b "$JE" "$BASE/admin/projects")" 200
check "editor can open Media" "$(code -b "$JE" "$BASE/admin/media")" 200
check "editor is blocked from Settings (GET)" "$(code -b "$JE" "$BASE/admin/settings")" 403
check "admin can open Settings" "$(code -b "$JA" "$BASE/admin/settings")" 200
TE=$(tok "$JE" /admin); 
for path in /admin/settings /admin/media/1/delete /admin/projects/1/delete /admin/team/1/delete /admin/services/1/delete; do
  check "editor POST $path -> 403" "$(code -b "$JE" -c "$JE" -X POST "$BASE$path" --data-urlencode "_csrf=$TE")" 403; done

echo "3. Archived / inactive / draft content never appears publicly"
check "published project page" "$(code "$BASE/work/$TAG-pub")" 200
check "draft project page is 404" "$(code "$BASE/work/$TAG-draft")" 404
check "archived project page is 404" "$(code "$BASE/work/$TAG-arch")" 404
check "inactive member page is 404" "$(code "$BASE/team/$TAG-inactive")" 404
check "active member page" "$(code "$BASE/team/$TAG-active")" 200
for p in / /work /studio /services; do body=$(curl -s "$BASE$p"); echo "$body" | grep -q 'QAHIDDEN' && bad "hidden content leaked on $p" || ok "no hidden content on $p"; done
curl -s "$BASE/work/$TAG-pub" | grep -q 'QAHIDDEN-MEMBER' && bad "inactive member shown on project page" || ok "inactive member not shown on project page"
curl -s "$BASE/sitemap.xml" | grep -q "$TAG-draft\|$TAG-arch\|$TAG-inactive" && bad "hidden URLs in sitemap" || ok "no hidden URLs in sitemap"

echo "4. Old slugs redirect with 301"
TP=$(tok "$JA" "/admin/projects/$PUBID/edit")
check "rename saved" "$(code -b "$JA" -c "$JA" -X POST "$BASE/admin/projects/$PUBID/save" --data-urlencode "_csrf=$TP" --data-urlencode "name=QAVISIBLE-PUB" --data-urlencode "slug=$TAG-renamed" --data-urlencode "kind=client" --data-urlencode "status=published" --data-urlencode "display_order=0")" 302
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE/work/$TAG-pub"); case "$r" in "301 "*"/work/$TAG-renamed") ok "old slug -> 301 to new slug";; *) bad "old slug redirect (got: $r)";; esac
check "new slug serves the page" "$(code "$BASE/work/$TAG-renamed")" 200

echo "5. Uploads over the PHP limit are handled"
head -c 12000000 /dev/zero > /tmp/qa-big.bin; TM=$(tok "$JE" /admin/media)
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$JE" -c "$JE" -F "_csrf=$TM" -F "image=@/tmp/qa-big.bin;filename=big.jpg" "$BASE/admin/media/upload"); case "$r" in "302 "*"/admin/media") ok "oversize upload -> friendly redirect";; *) bad "oversize upload (got: $r)";; esac
rm -f /tmp/qa-big.bin

echo "6. Login throttle (new session every time, so it cannot be session-based)"
for i in 1 2 3 4 5 6 7 8; do JT=$(mktemp); T=$(tok "$JT" /admin/login); code -c "$JT" -b "$JT" -X POST "$BASE/admin/login" --data-urlencode "_csrf=$T" --data-urlencode "email=$EE" --data-urlencode "password=wrong-$i" >/dev/null; done
JT=$(mktemp); T=$(tok "$JT" /admin/login)
check "correct password is refused after repeated failures (429)" "$(code -c "$JT" -b "$JT" -X POST "$BASE/admin/login" --data-urlencode "_csrf=$T" --data-urlencode "email=$EE" --data-urlencode "password=$PW")" 429

echo; echo "Passed $pass, failed $fail"; [ $fail -eq 0 ]

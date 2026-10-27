<?php
/** Image-only media library. Everything uploaded is decoded and re-encoded, so metadata and any non-image payload are discarded.
 *  Files live in Supabase Storage (public read bucket); every original filename is preserved as the object key. */
final class MediaService {
    public const MAX_BYTES = 4 * 1024 * 1024;   // upload size — must stay below Vercel's 4.5 MB request-body cap
    public const MAX_SIDE = 6000;               // reject larger source images
    public const MAX_PIXELS = 24000000;         // decompression-bomb guard
    public const MIN_SIDE = 16;
    public const OUT_SIDE = 2400;               // stored images are scaled down to this longest side
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function url(?array $m): string { return $m ? media_file_url((string)$m['filename']) : ''; }
    private static function validName(string $f): bool { return (bool)preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $f); }

    /** Entry point for a real HTTP upload ($_FILES entry). @return array{id?:int,error?:string} */
    public static function upload(array $file, string $alt, ?int $userId): array {
        if (!isset($file['error']) || is_array($file['error'])) return ['error' => 'Upload failed.'];
        if ($file['error'] === UPLOAD_ERR_NO_FILE) return ['error' => 'Choose an image to upload.'];
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) return ['error' => 'That file is too large (max ' . media_max_mb() . ' MB).'];
        if ($file['error'] !== UPLOAD_ERR_OK) return ['error' => 'Upload failed. Please try again.'];
        $tmp = (string)($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) return ['error' => 'Upload failed.'];
        return self::process($tmp, (string)($file['name'] ?? ''), $alt, $userId);
    }

    /** Validate, decode, re-encode and store via the Storage API. Separate from upload() so tests can exercise it. */
    public static function process(string $tmp, string $origName, string $alt, ?int $userId): array {
        if (!is_file($tmp) || (int)filesize($tmp) > self::MAX_BYTES) return ['error' => 'That file is too large (max ' . media_max_mb() . ' MB).'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);          // content, not the extension or the browser's claim
        if (!isset(self::TYPES[$mime])) return ['error' => 'Only JPEG, PNG or WebP images are allowed.'];
        $info = @getimagesize($tmp);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return ['error' => 'That file is not a valid image.'];
        [$w, $h] = $info;
        if ($w < self::MIN_SIDE || $h < self::MIN_SIDE) return ['error' => 'That image is too small.'];
        if ($w > self::MAX_SIDE || $h > self::MAX_SIDE || $w * $h > self::MAX_PIXELS) return ['error' => 'That image is too large (max 6000 px on a side).'];

        $img = @imagecreatefromstring((string)file_get_contents($tmp));
        if (!$img) return ['error' => 'That image could not be read.'];
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $o = (int)(@exif_read_data($tmp)['Orientation'] ?? 1);
            $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($deg && ($r = imagerotate($img, $deg, 0))) { imagedestroy($img); $img = $r; }
        }
        if (!imageistruecolor($img) && function_exists('imagepalettetotruecolor')) imagepalettetotruecolor($img);
        $w = imagesx($img); $h = imagesy($img);
        $scale = min(1, self::OUT_SIDE / max($w, $h));
        if ($scale < 1) {
            $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false); imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img); $img = $dst; $w = $nw; $h = $nh;
        }
        imagesavealpha($img, true);

        // Re-encode in memory (no filesystem writes).
        ob_start();
        match ($mime) { 'image/jpeg' => imagejpeg($img, null, 85), 'image/png' => imagepng($img, null, 6), default => imagewebp($img, null, 82) };
        $bytes = (string)ob_get_clean();
        imagedestroy($img);
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) return ['error' => 'The image could not be saved.'];

        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];   // server-generated; the uploaded name is never used as a key
        if (!self::put($name, $bytes, $mime)) return ['error' => 'The image could not be saved.'];

        $orig = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '', basename($origName)) ?? ''), 0, 255);
        try {
            $id = Database::insert('INSERT INTO media (filename, original_name, mime, width, height, bytes, alt_text, uploaded_by) VALUES (?,?,?,?,?,?,?,?)',
                [$name, $orig ?: null, $mime, $w, $h, strlen($bytes), $alt !== '' ? mb_substr($alt, 0, 250) : null, $userId]);
        } catch (Throwable $e) {
            self::remove($name);   // compensating cleanup: never leave an orphaned object
            error_log('media insert failed: ' . $e->getMessage());
            return ['error' => 'The image could not be saved.'];
        }
        return ['id' => $id];
    }

    // ---------- Supabase Storage REST (service-role key, server-side only; the key is never logged) ----------
    /** @return array{0:string,1:string} [object URL, service key]; empty strings when storage is not configured. */
    private static function objectUrl(string $name): array {
        $base = rtrim((string)Env::get('SUPABASE_URL', ''), '/');
        $key = (string)Env::get('SUPABASE_SERVICE_ROLE_KEY', '');
        if ($base === '' || $key === '') return ['', ''];
        return [$base . '/storage/v1/object/' . rawurlencode((string)(Env::get('MEDIA_BUCKET') ?: 'media')) . '/' . rawurlencode($name), $key];
    }

    private static function put(string $name, string $bytes, string $mime): bool {
        [$url, $key] = self::objectUrl($name);
        if ($url === '' || $key === '') { error_log('media put failed: storage is not configured'); return false; }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $bytes, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'apikey: ' . $key, 'Content-Type: ' . $mime, 'x-upsert: false'],
        ]);
        $ok = curl_exec($ch) !== false && (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE) < 300;
        curl_close($ch);
        return $ok;
    }

    private static function remove(string $name): void {
        [$url, $key] = self::objectUrl($name);
        if ($url === '' || $key === '') return;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'apikey: ' . $key],
        ]);
        curl_exec($ch); curl_close($ch);
    }

    /** Storage reachability check for the admin dashboard (replaces the old "uploads folder writable" check). */
    public static function storageHealthy(): bool {
        [$bucketUrl, $key] = self::objectUrl('');
        if ($bucketUrl === '' || $key === '') return false;
        $url = substr($bucketUrl, 0, strrpos($bucketUrl, '/'));   // /storage/v1/object/media → drop the final '/name'
        $url = str_replace('/storage/v1/object/', '/storage/v1/bucket/', $url);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'apikey: ' . $key],
        ]);
        $ok = curl_exec($ch) !== false && (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE) < 300;
        curl_close($ch);
        return $ok;
    }

    public static function all(int $limit = 300): array {
        return Database::query('SELECT id, filename, original_name, alt_text, width, height, bytes, created_at FROM media ORDER BY created_at DESC, id DESC LIMIT ' . (int)$limit)->fetchAll();
    }
    public static function find(int $id): ?array { return Database::query('SELECT * FROM media WHERE id = ?', [$id])->fetch() ?: null; }

    /** Where is this image used? "blocking" = explicit assignments. Gallery membership is cleaned up automatically on delete. */
    public static function usage(int $id): array {
        $b = [];
        foreach (Database::query('SELECT name FROM projects WHERE cover_media_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN) as $n) $b[] = "cover of project \"$n\"";
        foreach (Database::query('SELECT name FROM team_members WHERE portrait_media_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN) as $n) $b[] = "portrait of $n";
        foreach (Database::query('SELECT name FROM services WHERE visual_media_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN) as $n) $b[] = "visual of service \"$n\"";
        foreach (Database::query('SELECT slot FROM site_media WHERE media_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN) as $s) $b[] = "site $s";
        $g = (int)Database::query('SELECT COUNT(*) FROM project_media WHERE media_id = ?', [$id])->fetchColumn();
        return ['blocking' => $b, 'gallery' => $g];
    }

    /** @return string|null error message, or null on success */
    public static function delete(int $id): ?string {
        $m = self::find($id);
        if (!$m) return 'That image no longer exists.';
        $u = self::usage($id);
        if ($u['blocking']) return 'Still in use as ' . implode(', ', $u['blocking']) . '. Replace it there first.';
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            Database::query('DELETE FROM project_media WHERE media_id = ?', [$id]);
            Database::query('DELETE FROM media WHERE id = ?', [$id]);
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('media delete failed: ' . $e->getMessage()); return 'Could not delete that image.'; }
        // Object removal: only when no remaining media record points at it, and only a name we generated ourselves.
        $left = (int)Database::query('SELECT COUNT(*) FROM media WHERE filename = ?', [$m['filename']])->fetchColumn();
        if ($left === 0 && self::validName($m['filename'])) self::remove($m['filename']);
        return null;
    }
}

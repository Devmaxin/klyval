<?php
/** Image-only media library. Everything uploaded is decoded and re-encoded, so metadata and any non-image payload are discarded. */
final class MediaService {
    public const MAX_BYTES = 5 * 1024 * 1024;   // upload size
    public const MAX_SIDE = 6000;               // reject larger source images
    public const MAX_PIXELS = 24000000;         // decompression-bomb guard
    public const MIN_SIDE = 16;
    public const OUT_SIDE = 2400;               // stored images are scaled down to this longest side
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function dir(): string { return ROOT . '/public/uploads'; }
    public static function url(?array $m): string { return $m ? '/uploads/' . rawurlencode($m['filename']) : ''; }
    private static function validName(string $f): bool { return (bool)preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $f); }

    /** Entry point for a real HTTP upload ($_FILES entry). @return array{id?:int,error?:string} */
    public static function upload(array $file, string $alt, ?int $userId): array {
        if (!isset($file['error']) || is_array($file['error'])) return ['error' => 'Upload failed.'];
        if ($file['error'] === UPLOAD_ERR_NO_FILE) return ['error' => 'Choose an image to upload.'];
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) return ['error' => 'That file is too large (max 5 MB).'];
        if ($file['error'] !== UPLOAD_ERR_OK) return ['error' => 'Upload failed. Please try again.'];
        $tmp = (string)($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) return ['error' => 'Upload failed.'];
        return self::process($tmp, (string)($file['name'] ?? ''), $alt, $userId);
    }

    /** Validate, decode, re-encode and store a file on disk. Separate from upload() so qa/upload-test.php can exercise it. */
    public static function process(string $tmp, string $origName, string $alt, ?int $userId): array {
        if (!is_file($tmp) || (int)filesize($tmp) > self::MAX_BYTES) return ['error' => 'That file is too large (max 5 MB).'];
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

        if (!is_dir(self::dir()) && !@mkdir(self::dir(), 0755, true)) { imagedestroy($img); return ['error' => 'The upload folder is not writable.']; }
        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];   // server-generated; the uploaded name is never used on disk
        $path = self::dir() . '/' . $name;
        $ok = match ($mime) { 'image/jpeg' => imagejpeg($img, $path, 85), 'image/png' => imagepng($img, $path, 6), default => imagewebp($img, $path, 82) };
        imagedestroy($img);
        if (!$ok || !is_file($path)) return ['error' => 'The image could not be saved.'];
        @chmod($path, 0644);

        $orig = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '', basename($origName)) ?? ''), 0, 255);
        try {
            Database::query('INSERT INTO media (filename, original_name, mime, width, height, bytes, alt_text, uploaded_by) VALUES (?,?,?,?,?,?,?,?)',
                [$name, $orig ?: null, $mime, $w, $h, (int)filesize($path), $alt !== '' ? mb_substr($alt, 0, 250) : null, $userId]);
        } catch (Throwable $e) { @unlink($path); error_log('media insert failed: ' . $e->getMessage()); return ['error' => 'The image could not be saved.']; }
        return ['id' => (int)Database::pdo()->lastInsertId()];
    }

    /** Files in uploads/ that this app did not create (anything not a random image name). Should always be empty. */
    public static function strayFiles(): array {
        $out = [];
        if (!is_dir(self::dir())) return $out;
        foreach (scandir(self::dir()) ?: [] as $f) {
            if (in_array($f, ['.', '..', '.htaccess', 'index.html'], true)) continue;
            if (!self::validName($f)) $out[] = $f;
        }
        return $out;
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
        // Physical file: only if no remaining media record points at it, and only a name we generated ourselves.
        $left = (int)Database::query('SELECT COUNT(*) FROM media WHERE filename = ?', [$m['filename']])->fetchColumn();
        if ($left === 0 && self::validName($m['filename'])) { $p = self::dir() . '/' . $m['filename']; if (is_file($p)) @unlink($p); }
        return null;
    }
}

<?php
/** Server-side validation. Collects cleaned values in $data and messages in $errors. Never trusts client-side checks. */
final class Form {
    public array $errors = [];
    public array $data = [];
    public function __construct(private array $in) {}

    private function raw(string $k): string { $v = $this->in[$k] ?? ''; return is_string($v) ? $v : ''; }
    public function str(string $k, string $label, int $max, bool $req = false): string {
        $v = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $this->raw($k)) ?? '');
        return $this->finish($k, $label, $v, $max, $req);
    }
    /** Multi-line plain text. Newlines kept, other control characters removed. */
    public function text(string $k, string $label, int $max, bool $req = false): string {
        $v = str_replace(["\r\n", "\r"], "\n", $this->raw($k));
        $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '');
        return $this->finish($k, $label, $v, $max, $req);
    }
    private function finish(string $k, string $label, string $v, int $max, bool $req): string {
        if ($req && $v === '') $this->errors[$k] = "$label is required.";
        elseif (mb_strlen($v) > $max) $this->errors[$k] = "$label must be $max characters or fewer.";
        return $this->data[$k] = $v;
    }
    /** http(s) only. Rejects javascript:, data: and anything else. */
    public function url(string $k, string $label, bool $httpsOnly = false, bool $req = false): string {
        $v = trim($this->raw($k));
        if ($v === '') { if ($req) $this->errors[$k] = "$label is required."; return $this->data[$k] = ''; }
        $scheme = strtolower((string)parse_url($v, PHP_URL_SCHEME));
        $okScheme = $httpsOnly ? $scheme === 'https' : in_array($scheme, ['http', 'https'], true);
        if (strlen($v) > 500 || !$okScheme || !filter_var($v, FILTER_VALIDATE_URL) || !parse_url($v, PHP_URL_HOST))
            $this->errors[$k] = "$label must be a full " . ($httpsOnly ? 'https' : 'http(s)') . ' address.';
        return $this->data[$k] = $v;
    }
    public function email(string $k, string $label, bool $req = false): string {
        $v = trim($this->raw($k));
        if ($v === '') { if ($req) $this->errors[$k] = "$label is required."; return $this->data[$k] = ''; }
        if (strlen($v) > 190 || !filter_var($v, FILTER_VALIDATE_EMAIL)) $this->errors[$k] = "$label must be a valid email address.";
        return $this->data[$k] = $v;
    }
    public function enum(string $k, string $label, array $allowed, string $default = ''): string {
        $v = $this->raw($k);
        if (!in_array($v, $allowed, true)) { if ($v !== '' || $default === '') $this->errors[$k] = "Choose a valid $label."; $v = $default; }
        return $this->data[$k] = $v;
    }
    public function bool(string $k): int { return $this->data[$k] = (!empty($this->in[$k]) && $this->in[$k] !== '0') ? 1 : 0; }
    public function int(string $k, string $label, int $min, int $max, int $default = 0): int {
        $v = trim($this->raw($k));
        if ($v === '') return $this->data[$k] = $default;
        if (!preg_match('/^-?\d+$/', $v) || (int)$v < $min || (int)$v > $max) { $this->errors[$k] = "$label must be a whole number from $min to $max."; return $this->data[$k] = $default; }
        return $this->data[$k] = (int)$v;
    }
    /** Lowercase letters, digits and single hyphens. */
    public function slug(string $k, string $label): string {
        $v = trim($this->raw($k));
        if ($v === '') $this->errors[$k] = "$label is required.";
        elseif (strlen($v) > 120 || !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $v)) $this->errors[$k] = "$label may only use lowercase letters, numbers and single hyphens.";
        return $this->data[$k] = $v;
    }
    /** From <input type="datetime-local"> (Y-m-d\TH:i). Returns 'Y-m-d H:i:00' or '' when blank. */
    public function datetime(string $k, string $label): string {
        $v = trim($this->raw($k));
        if ($v === '') return $this->data[$k] = '';
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $v);
        if (!$dt || $dt->format('Y-m-d\TH:i') !== $v) { $this->errors[$k] = "$label must be a valid date and time."; return $this->data[$k] = ''; }
        return $this->data[$k] = $dt->format('Y-m-d H:i:00');
    }
    public function ok(): bool { return !$this->errors; }
}

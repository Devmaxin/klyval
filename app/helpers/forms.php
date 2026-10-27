<?php
declare(strict_types=1);
/** Small form-field renderers for the admin. Everything is escaped here, once. */

function fld(string $name, string $label, ?string $val, array $err = [], array $o = []): string {
    $type = $o['type'] ?? 'text'; $req = !empty($o['required']); $id = 'f-' . $name; $msg = $err[$name] ?? null;
    $a = ' id="' . e($id) . '" name="' . e($name) . '"' . (!empty($o['max']) ? ' maxlength="' . (int)$o['max'] . '"' : '') . ($req ? ' required' : '')
       . ($msg ? ' aria-invalid="true" aria-describedby="' . e($id) . '-err"' : '') . (!empty($o['placeholder']) ? ' placeholder="' . e($o['placeholder']) . '"' : '')
       . (!empty($o['autocomplete']) ? ' autocomplete="' . e($o['autocomplete']) . '"' : '');
    $in = $type === 'textarea'
        ? '<textarea' . $a . ' rows="' . (int)($o['rows'] ?? 5) . '">' . e($val) . '</textarea>'
        : '<input type="' . e($type) . '" value="' . e($val) . '"' . $a . ($type === 'number' ? ' min="0" step="1"' : '') . '>';
    return '<div class="field"><label for="' . e($id) . '"><span>' . e($label) . ($req ? '' : ' <em>(optional)</em>') . '</span></label>' . $in
        . (!empty($o['hint']) ? '<small class="hint">' . e($o['hint']) . '</small>' : '')
        . ($msg ? '<span class="field__error" id="' . e($id) . '-err">' . e($msg) . '</span>' : '') . '</div>';
}
function fcheck(string $name, string $label, bool $checked, string $hint = '', string $value = '1'): string {
    return '<div class="check"><label><input type="checkbox" name="' . e($name) . '" value="' . e($value) . '"' . ($checked ? ' checked' : '') . '> <span>' . e($label) . '</span></label>'
        . ($hint ? '<small class="hint">' . e($hint) . '</small>' : '') . '</div>';
}
function fselect(string $name, string $label, array $options, ?string $current, array $err = [], string $hint = ''): string {
    $id = 'f-' . $name; $msg = $err[$name] ?? null; $o = '';
    foreach ($options as $v => $l) $o .= '<option value="' . e((string)$v) . '"' . ((string)$v === (string)$current ? ' selected' : '') . '>' . e((string)$l) . '</option>';
    return '<div class="field"><label for="' . e($id) . '"><span>' . e($label) . '</span></label><select id="' . e($id) . '" name="' . e($name) . '"' . ($msg ? ' aria-invalid="true"' : '') . '>' . $o . '</select>'
        . ($hint ? '<small class="hint">' . e($hint) . '</small>' : '') . ($msg ? '<span class="field__error">' . e($msg) . '</span>' : '') . '</div>';
}
/** Pick one image (or none) from the library. Plain radio buttons, no JavaScript needed. */
function fmedia(string $name, string $label, array $media, ?int $current, array $err = []): string {
    $h = '<fieldset class="media-pick"><legend>' . e($label) . ' <em>(optional)</em></legend><div class="media-pick__grid">'
       . '<label class="media-pick__none"><input type="radio" name="' . e($name) . '" value=""' . (!$current ? ' checked' : '') . '><span>None</span></label>';
    foreach ($media as $m) {
        $h .= '<label><input type="radio" name="' . e($name) . '" value="' . (int)$m['id'] . '"' . ((int)$m['id'] === (int)$current ? ' checked' : '') . '>'
            . '<img src="/uploads/' . e(rawurlencode($m['filename'])) . '" alt="' . e((string)$m['alt_text']) . '" loading="lazy" width="96" height="96"></label>';
    }
    if (!$media) $h .= '<p class="hint">No images yet. Upload some in Media first.</p>';
    return $h . '</div>' . (isset($err[$name]) ? '<span class="field__error">' . e($err[$name]) . '</span>' : '') . '</fieldset>';
}
function admin_pill(string $text, string $tone = ''): string { return '<span class="pill' . ($tone ? ' pill--' . e($tone) : '') . '">' . e($text) . '</span>'; }
/** Form values may arrive as arrays when a client tampers with them: only ever accept the expected shape. */
function post_ints(string $key): array { $v = $_POST[$key] ?? []; return is_array($v) ? array_values(array_unique(array_filter(array_map('intval', $v), fn($i) => $i > 0))) : []; }
function post_map(string $key): array { $v = $_POST[$key] ?? []; return is_array($v) ? $v : []; }

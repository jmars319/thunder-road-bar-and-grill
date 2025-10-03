<?php
/**
 * admin/save-content.php
 * JSON/form endpoint used by the admin UI to persist site content to
 * `data/content.json`.
 *
 * Contract:
 *  - Inputs:
 *      - JSON body { csrf_token, section?, content? } OR form-encoded
 *      - CSRF token is required and validated via session helpers
 *  - Outputs: JSON { success: bool, message: string }
 *  - Side effects: overwrites or merges into `data/content.json`.
 *
 * Validation and invariants:
 *  - CSRF is required and verified; requests without a valid token
 *    return 403.
 *  - The menu section receives special sanitization: prices are
 *    normalized to numeric strings and `quantity` fields are coerced
 *    to integers with section-specific rules (e.g., `wings-tenders`
 *    requires quantity >= 1).
 *  - Server-side validation is authoritative; client-side checks are
 *    for UX only.
 */

session_start();
require_once 'config.php';
checkAuth();

header('Content-Type: application/json');

// recursive merge: values from $b overwrite or merge into $a
function array_recursive_merge($a, $b) {
    foreach ($b as $k => $v) {
        if (is_array($v) && isset($a[$k]) && is_array($a[$k])) {
            $a[$k] = array_recursive_merge($a[$k], $v);
        } else {
            $a[$k] = $v;
        }
    }
    return $a;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Extract CSRF token from header or body
    $csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    $isJson = stripos($contentType, 'application/json') !== false;
    $section = '';
    $payload = [];

    if ($isJson) {
        $raw = file_get_contents('php://input');
        $json = $raw ? json_decode($raw, true) : null;
        if (!is_array($json)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
            exit;
        }
        $csrf = $csrf ?: ($json['csrf_token'] ?? '');
        $section = $json['section'] ?? '';
        $payload = $json['content'] ?? ($json['data'] ?? $json);
    } else {
        // form-encoded fallback (legacy)
        $section = $_POST['section'] ?? '';
        $payload = $_POST;
        unset($payload['section']);
        unset($payload['csrf_token']);
    }

    if (!verify_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $contentFile = CONTENT_FILE;
    if (!file_exists($contentFile)) {
        $content = [
            'business_info' => [],
            'hero' => [],
            'about' => [],
            'hours' => [],
            'images' => [],
            'last_updated' => (function_exists('eastern_now') ? eastern_now('Y-m-d H:i:s') : date('Y-m-d H:i:s'))
        ];
    } else {
        $content = json_decode(file_get_contents($contentFile), true);
        if (!is_array($content)) $content = [];
    }

    if ($section) {
        if (!isset($content[$section]) || !is_array($content[$section])) $content[$section] = [];
        // If payload is an indexed (sequential) array, replace the section entirely
        $is_sequential = is_array($payload) && array_keys($payload) === range(0, count($payload) - 1);
        if ($is_sequential) {
            $content[$section] = $payload;
        } else {
            // If the incoming payload is for the `menu` section treat it as the
            // authoritative representation (replace stored menu) but still run the
            // menu-specific sanitization/normalization below. For other sections we
            // preserve the existing merge semantics.
            if ($section === 'menu' && is_array($payload)) {
                $merged = $payload;
            } elseif (is_array($payload)) {
                // If payload is associative array, merge recursively
                $merged = array_recursive_merge($content[$section], $payload);
            } else {
                // scalar payload - set to value
                $content[$section] = $payload;
                $merged = null;
            }

            // If we have merged data for the menu, sanitize/normalize it
            if (isset($merged) && is_array($merged) && $section === 'menu') {
                foreach ($merged as $si => $sdata) {
                    // Extract a stable identifier for the menu section, if present.
                    $secId = isset($sdata['id']) ? $sdata['id'] : '';

                    if (isset($sdata['items']) && is_array($sdata['items'])) {
                        foreach ($sdata['items'] as $ii => $item) {
                            // If an incoming item has `short` but no `description`,
                            // promote `short` -> `description` so long descriptions
                            // are always stored in the canonical `description` key.
                            if (( !isset($merged[$si]['items'][$ii]['description']) || trim((string)$merged[$si]['items'][$ii]['description']) === '' ) && isset($item['short']) && trim((string)$item['short']) !== '') {
                                $merged[$si]['items'][$ii]['description'] = trim((string)$item['short']);
                            }
                            // Special-case: if this is the "current-ice-cream-flavors"
                            // section we intentionally remove any `price` fields because
                            // flavors are stored as names only. After removing the
                            // price we `continue` so the rest of the validation for
                            // this item is skipped.
                            if ($secId === 'current-ice-cream-flavors') {
                                if (isset($merged[$si]['items'][$ii]['price'])) unset($merged[$si]['items'][$ii]['price']);
                                continue;
                            }

                            // New: support `quantities` array (multiple quantity options)
                            if (isset($item['quantities']) && is_array($item['quantities'])) {
                                foreach ($item['quantities'] as $qidx => $qopt) {
                                    // qopt may be string or associative; prefer associative with 'value'
                                    $val = null;
                                    if (is_array($qopt) && isset($qopt['value'])) $val = $qopt['value'];
                                    elseif (!is_array($qopt)) $val = $qopt;
                                    $qclean = preg_replace('/[^0-9\-]/', '', (string)$val);
                                    if ($qclean === '' || !is_numeric($qclean)) {
                                        http_response_code(400);
                                        echo json_encode(['success' => false, 'message' => 'Invalid quantity option for item: ' . ($item['title'] ?? 'unknown')]);
                                        exit;
                                    }
                                    $qint = intval($qclean);
                                    if ($secId === 'wings-tenders') {
                                        if ($qint < 1) {
                                            http_response_code(400);
                                            echo json_encode(['success' => false, 'message' => 'Quantity option must be 1 or more for Wings & Tenders: ' . ($item['title'] ?? 'unknown')]);
                                            exit;
                                        }
                                    } else {
                                        if ($qint < 0) {
                                            http_response_code(400);
                                            echo json_encode(['success' => false, 'message' => 'Invalid quantity option for item: ' . ($item['title'] ?? 'unknown')]);
                                            exit;
                                        }
                                    }
                                    // optional per-quantity price validation and normalization
                                    $price = '';
                                    if (is_array($qopt) && isset($qopt['price'])) {
                                        $raw = preg_replace('/[^0-9\.\-]/', '', (string)$qopt['price']);
                                        if ($raw === '' || !is_numeric($raw)) {
                                            http_response_code(400);
                                            echo json_encode(['success' => false, 'message' => 'Invalid price for quantity option in item: ' . ($item['title'] ?? 'unknown')]);
                                            exit;
                                        }
                                        $price = number_format((float)$raw, 2, '.', '');
                                    }
                                    // normalize back into merged structure
                                    $merged[$si]['items'][$ii]['quantities'][$qidx] = [
                                        'label' => is_array($qopt) && isset($qopt['label']) ? $qopt['label'] : '',
                                        'value' => $qint,
                                        'price' => $price
                                    ];
                                }
                            } elseif (isset($item['quantity'])) {
                                // Backwards compatibility: accept legacy single `quantity` value
                                $qclean = preg_replace('/[^0-9\-]/', '', (string)$item['quantity']);
                                if ($qclean === '' || !is_numeric($qclean)) {
                                    http_response_code(400);
                                    echo json_encode(['success' => false, 'message' => 'Invalid quantity for item: ' . ($item['title'] ?? 'unknown')]);
                                    exit;
                                }
                                $qint = intval($qclean);
                                if ($secId === 'wings-tenders') {
                                    if ($qint < 1) {
                                        http_response_code(400);
                                        echo json_encode(['success' => false, 'message' => 'Quantity must be 1 or more for Wings & Tenders: ' . ($item['title'] ?? 'unknown')]);
                                        exit;
                                    }
                                } else {
                                    if ($qint < 0) {
                                        http_response_code(400);
                                        echo json_encode(['success' => false, 'message' => 'Invalid quantity for item: ' . ($item['title'] ?? 'unknown')]);
                                        exit;
                                    }
                                }
                                $merged[$si]['items'][$ii]['quantity'] = $qint;
                            }

                            // Price sanitization and normalization. We permit numeric
                            // strings (for example "$12.00" or "12") so we first
                            // strip everything except digits, dot, and minus sign.
                            // After that we confirm it's numeric, cast to float and
                            // store a normalized fixed-precision string with two
                            // decimal places. This keeps the stored JSON consistent
                            // (e.g. "12.00").
                            if (isset($item['price'])) {
                                // allow numeric strings, strip non-numeric except dot and minus
                                $clean = preg_replace('/[^0-9\.\-]/', '', (string)$item['price']);
                                if ($clean === '' || !is_numeric($clean)) {
                                    http_response_code(400);
                                    echo json_encode(['success' => false, 'message' => 'Invalid price for item: ' . ($item['title'] ?? 'unknown')]);
                                    exit;
                                }
                                $num = (float)$clean;
                                // number_format ensures we store prices like "12.00"
                                $merged[$si]['items'][$ii]['price'] = number_format($num, 2, '.', '');
                            }
                        }
                    }
                }
                // Ensure every item has either a 'quantities' array or a legacy 'quantity' integer.
                // This makes downstream rendering and the admin UI more predictable.
                foreach ($merged as $si => $sdata) {
                    if (!isset($merged[$si]['items']) || !is_array($merged[$si]['items'])) continue;
                    $secId = isset($sdata['id']) ? $sdata['id'] : '';
                    foreach ($merged[$si]['items'] as $ii => $item) {
                        if (!isset($merged[$si]['items'][$ii]['quantities']) && !isset($merged[$si]['items'][$ii]['quantity'])) {
                            // For wings/tenders default to 1 (common serving quantity), otherwise default to 0.
                            $merged[$si]['items'][$ii]['quantity'] = ($secId === 'wings-tenders') ? 1 : 0;
                        }
                    }
                }
                $content[$section] = $merged;
            }
            if (isset($merged) && is_array($merged) && $section !== 'menu') {
                // Non-menu merged associative payload -> store merged
                $content[$section] = $merged;
            }
        }
    } else {
        // no section - merge top-level
        if (is_array($payload)) {
            foreach ($payload as $k => $v) $content[$k] = $v;
        }
    }

    $content['last_updated'] = date('Y-m-d H:i:s');

    $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to encode content to JSON']);
        exit;
    }
    $tmp = $contentFile . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) !== false && @rename($tmp, $contentFile)) {
        @chmod($contentFile, 0640);
        echo json_encode(['success' => true, 'message' => 'Content saved successfully!', 'timestamp' => $content['last_updated']]);
    } else {
        @unlink($tmp);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save content. Check file permissions.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>

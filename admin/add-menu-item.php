<?php
// New POST handler: validate input, verify CSRF, use MenuRepository to create a DB-backed menu item.
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/config.php';
require_admin();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo 'Method not allowed';
    exit;
}

// Verify CSRF token
$csrf = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING) ?: '';
if (!\CSRF::verify($csrf)) {
    header('Location: index.php?error=csrf');
    exit;
}

// Read inputs using filter_input
$name = trim((string)filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
$categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$description = trim((string)filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
$priceRaw = trim((string)filter_input(INPUT_POST, 'price', FILTER_UNSAFE_RAW) ?: '');
$imagePath = trim((string)filter_input(INPUT_POST, 'image_path', FILTER_SANITIZE_URL) ?: '');
$isActive = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($isActive === null) $isActive = true; // default true

$errors = [];

// Validate required fields
if ($name === '') {
    $errors['name'] = 'Name is required.';
}
if ($categoryId <= 0) {
    $errors['category_id'] = 'Please select a valid category.';
}

// Validate price: numeric with up to two decimals
if ($priceRaw !== '') {
    // Allow formats like 10, 10.5, 10.50
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $priceRaw)) {
        $errors['price'] = 'Price must be a number with up to two decimal places.';
    }
    $price = (float)$priceRaw;
} else {
    $price = 0.0;
}

// If validation failed, store old inputs and errors in session and redirect back
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    // Sanitize old values for re-population (encode special chars)
    $_SESSION['old_input'] = [
        'name' => e($name),
        'category_id' => $categoryId,
        'description' => e($description),
        'price' => e($priceRaw),
        'image_path' => e($imagePath),
        'is_active' => $isActive ? 1 : 0,
    ];
    header('Location: index.php?error=validation');
    exit;
}

// Passed validation — create menu item via repository
require_once __DIR__ . '/../lib/MenuRepository.php';

try {
    $repo = new MenuRepository(db());
    $row = $repo->create([
        'name' => $name,
        'category_id' => $categoryId,
        'description' => $description !== '' ? $description : null,
        'price' => $price,
        'image_path' => $imagePath !== '' ? $imagePath : null,
        'is_active' => $isActive ? 1 : 0,
    ]);

    // Clear any previous form state
    unset($_SESSION['form_errors'], $_SESSION['old_input']);

    header('Location: index.php?msg=added_item&id=' . urlencode((string)($row['id'] ?? '')));
    exit;
} catch (Exception $e) {
    error_log('add-menu-item: create failed - ' . $e->getMessage());
    $_SESSION['form_errors'] = ['general' => 'Failed to save menu item.'];
    $_SESSION['old_input'] = [
        'name' => e($name),
        'category_id' => $categoryId,
        'description' => e($description),
        'price' => e($priceRaw),
        'image_path' => e($imagePath),
        'is_active' => $isActive ? 1 : 0,
    ];
    header('Location: index.php?error=save_failed');
    exit;
}

?>

<?php
// Validated storage for vehicle images uploaded from the admin panel.
// The browser shrinks photos before upload (js/image-manager.js); these checks still
// apply to every file, so a request that skips the browser gets the same validation.
require_once __DIR__ . '/../../includes/webp.php';

const VEHICLE_IMAGE_DIR = __DIR__ . '/../img/vehicleimages/';
const VEHICLE_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
// Detected MIME type => extension used for the stored file.
const VEHICLE_IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

// Returns true when the admin left an optional file input empty.
function vehicle_image_missing(string $field): bool
{
    return ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;
}

// Checks one uploaded image and throws RuntimeException with a message safe to show the admin.
function validate_vehicle_image(string $field, string $label): void
{
    $file = $_FILES[$field] ?? null;
    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException("$label is too large (max 5 MB).");
    }
    if ($errorCode !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException("$label could not be uploaded. Please choose the file again.");
    }
    if ($file['size'] > VEHICLE_IMAGE_MAX_BYTES) {
        throw new RuntimeException("$label is too large (max 5 MB).");
    }
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) || !isset(VEHICLE_IMAGE_TYPES[$mime])) {
        throw new RuntimeException("$label must be a JPG, PNG or WebP image.");
    }
    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException("$label is not a valid image.");
    }
}

// Moves a validated upload into img/vehicleimages/ under a random name and returns that name.
function store_vehicle_image(string $field): string
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']);
    $name = bin2hex(random_bytes(16)) . '.' . VEHICLE_IMAGE_TYPES[$mime];
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], VEHICLE_IMAGE_DIR . $name)) {
        throw new RuntimeException('The image could not be saved. Please try again.');
    }
    // WebP sizes for the public pages; the original is still used if this fails.
    vehicle_webp_generate($name);
    return $name;
}

// Deletes image files (and their WebP copies) that no vehicle uses any more.
function delete_unused_vehicle_images(PDO $dbh, array $files): void
{
    $check = $dbh->prepare("SELECT COUNT(*) FROM tblvehicles WHERE :f IN (Vimage1, Vimage2, Vimage3, Vimage4, Vimage5)");
    foreach (array_unique(array_map('basename', $files)) as $file) {
        if ($file === '' || $file === '.' || $file === '..') {
            continue;
        }
        $check->execute([':f' => $file]);
        if ((int)$check->fetchColumn() === 0) {
            @unlink(VEHICLE_IMAGE_DIR . $file);
            foreach (WEBP_WIDTHS as $width) {
                @unlink(VEHICLE_IMAGE_DIR . "webp/$file-$width.webp");
            }
        }
    }
}

// Replaces image $n (1-5) of a vehicle with the validated upload in field img$n.
// Returns the stored file name. Throws RuntimeException with a message for the admin.
function replace_vehicle_image(PDO $dbh, int $vehicleId, int $n): string
{
    if ($n < 1 || $n > 5) {
        throw new RuntimeException('Unknown image slot.');
    }
    validate_vehicle_image("img$n", "Image $n");
    // The column name comes from the checked number above, never from input.
    $old = $dbh->prepare("SELECT Vimage$n FROM tblvehicles WHERE id = :id");
    $old->execute([':id' => $vehicleId]);
    $oldFile = (string)$old->fetchColumn();
    $file = store_vehicle_image("img$n");
    $query = $dbh->prepare("UPDATE tblvehicles SET Vimage$n = :file WHERE id = :id");
    $query->bindParam(':file', $file, PDO::PARAM_STR);
    $query->bindParam(':id', $vehicleId, PDO::PARAM_INT);
    $query->execute();
    delete_unused_vehicle_images($dbh, [$oldFile]);
    return $file;
}
?>

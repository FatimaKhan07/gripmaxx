<?php

function normalize_upload_error_message($errorCode) {
    switch ((int)$errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "The selected image is too large.";
        case UPLOAD_ERR_PARTIAL:
            return "The image upload was interrupted. Please try again.";
        case UPLOAD_ERR_NO_FILE:
            return "Please choose an image file.";
        default:
            return "Unable to upload the selected image.";
    }
}

function upload_public_image($file, $targetDirectory, $fallbackBaseName = 'image', $isRequired = true) {
    if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($isRequired) {
            return [
                "success" => false,
                "message" => "Please choose an image file."
            ];
        }

        return [
            "success" => true,
            "filename" => null
        ];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [
            "success" => false,
            "message" => normalize_upload_error_message($file['error'])
        ];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return [
            "success" => false,
            "message" => "Invalid image upload."
        ];
    }

    $allowedMimeMap = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
        "image/gif" => "gif"
    ];

    $originalName = $file['name'] ?? '';
    $imageInfo = @getimagesize($file['tmp_name']);
    $mimeType = $imageInfo['mime'] ?? '';

    if (!isset($allowedMimeMap[$mimeType])) {
        return [
            "success" => false,
            "message" => "Please upload a JPG, PNG, WEBP, or GIF image."
        ];
    }

    $extension = $allowedMimeMap[$mimeType];

    if (!is_dir($targetDirectory)) {
        return [
            "success" => false,
            "message" => "Image directory is missing."
        ];
    }

    $safeBaseName = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $safeBaseName = trim(strtolower($safeBaseName), '-');

    if ($safeBaseName === '') {
        $safeBaseName = $fallbackBaseName;
    }

    $newFilename = $safeBaseName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = rtrim($targetDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            "success" => false,
            "message" => "Unable to save the uploaded image."
        ];
    }

    return [
        "success" => true,
        "filename" => $newFilename
    ];
}

function upload_product_image($file, $targetDirectory, $isRequired = true) {
    return upload_public_image($file, $targetDirectory, 'product-image', $isRequired);
}

?>

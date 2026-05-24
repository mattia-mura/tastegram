<?php
function avatarSrc(string $avatarUrl, string $base = '../img/'): string {
    if (empty($avatarUrl) || $avatarUrl === 'default_avatar.png') {
        return $base . 'default_avatar.png';
    }
    return $base . 'uploads/avatars/' . $avatarUrl;
}

function postImageSrc(?string $imagePath, string $base = '../img/'): string {
    if (empty($imagePath)) {
        return '';
    }
    return $base . 'uploads/foto/' . $imagePath;
}

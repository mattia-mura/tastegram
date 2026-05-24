<?php
// config/paths.php
// Includi questo file dove ti serve il path assoluto alla root del progetto
// Rileva automaticamente la root di tastegram su XAMPP

define('TASTEGRAM_ROOT', rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/tastegram');
define('UPLOAD_FOTO_DIR',    TASTEGRAM_ROOT . '/img/uploads/foto/');
define('UPLOAD_AVATARS_DIR', TASTEGRAM_ROOT . '/img/uploads/avatars/');
define('API_BASE_URL', '/tastegram/backend/api/');

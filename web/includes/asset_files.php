<?php

function ensureAssetFilesSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $stmt = $pdo->query("SHOW TABLES LIKE 'adjuntos_activos'");
    $exists = $stmt ? $stmt->fetchColumn() : false;

    if (!$exists) {
        $pdo->exec("
            CREATE TABLE adjuntos_activos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type ENUM('equipo', 'telefono', 'sim') NOT NULL,
                entity_id INT NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                mime_type VARCHAR(150) DEFAULT NULL,
                file_size BIGINT UNSIGNED DEFAULT NULL,
                uploaded_by VARCHAR(100) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_adjuntos_entidad (entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    $ready = true;
}

function getAssetFilesBaseDir(): string
{
    return dirname(__DIR__) . '/uploads/adjuntos';
}

function normalizeAssetEntityType(string $entityType): string
{
    $entityType = strtolower(trim($entityType));
    $allowed = ['equipo', 'telefono', 'sim'];

    if (!in_array($entityType, $allowed, true)) {
        throw new InvalidArgumentException('Tipo de entidad no valido para adjuntos.');
    }

    return $entityType;
}

function ensureAssetEntityDir(string $entityType, int $entityId): string
{
    $baseDir = getAssetFilesBaseDir();
    if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
        throw new RuntimeException('No se pudo crear el directorio base de adjuntos.');
    }

    $dir = $baseDir . '/' . $entityType . '/' . $entityId;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de adjuntos.');
    }

    return $dir;
}

function buildAssetPathFromRelative(string $relativePath): string
{
    return dirname(__DIR__) . '/' . ltrim($relativePath, '/');
}

function formatAttachmentSize(?int $bytes): string
{
    $bytes = (int)$bytes;
    if ($bytes <= 0) {
        return '-';
    }
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }

    return $bytes . ' B';
}

function listEntityAttachments(PDO $pdo, string $entityType, int $entityId): array
{
    ensureAssetFilesSchema($pdo);
    $entityType = normalizeAssetEntityType($entityType);

    $stmt = $pdo->prepare("
        SELECT id, entity_type, entity_id, original_name, stored_name, file_path, mime_type, file_size, uploaded_by, created_at
        FROM adjuntos_activos
        WHERE entity_type = :entity_type
          AND entity_id = :entity_id
        ORDER BY created_at DESC, id DESC
    ");
    $stmt->execute([
        ':entity_type' => $entityType,
        ':entity_id'   => $entityId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function uploadEntityAttachments(PDO $pdo, string $entityType, int $entityId, array $files, ?string $uploadedBy = null): array
{
    ensureAssetFilesSchema($pdo);
    $entityType = normalizeAssetEntityType($entityType);

    if (!isset($files['name'])) {
        return [];
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [$files['tmp_name'] ?? ''];
    $errors = is_array($files['error'] ?? null) ? $files['error'] : [$files['error'] ?? UPLOAD_ERR_NO_FILE];
    $sizes = is_array($files['size'] ?? null) ? $files['size'] : [$files['size'] ?? 0];
    $types = is_array($files['type'] ?? null) ? $files['type'] : [$files['type'] ?? null];

    $saved = [];
    $dir = ensureAssetEntityDir($entityType, $entityId);

    $stmtInsert = $pdo->prepare("
        INSERT INTO adjuntos_activos (
            entity_type,
            entity_id,
            original_name,
            stored_name,
            file_path,
            mime_type,
            file_size,
            uploaded_by
        ) VALUES (
            :entity_type,
            :entity_id,
            :original_name,
            :stored_name,
            :file_path,
            :mime_type,
            :file_size,
            :uploaded_by
        )
    ");

    foreach ($names as $index => $originalName) {
        $errorCode = (int)($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Uno de los adjuntos no se pudo subir correctamente.');
        }

        $originalName = trim((string)$originalName);
        $originalName = $originalName !== '' ? basename($originalName) : 'adjunto';
        $tmpName = (string)($tmpNames[$index] ?? '');

        $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);
        $baseName = (string)pathinfo($originalName, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $baseName);
        $baseName = trim((string)$baseName, '._-');
        if ($baseName === '') {
            $baseName = 'adjunto';
        }

        $storedName = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $baseName;
        if ($extension !== '') {
            $storedName .= '.' . $extension;
        }

        $relativePath = 'uploads/adjuntos/' . $entityType . '/' . $entityId . '/' . $storedName;
        $targetPath = $dir . '/' . $storedName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('No se pudo guardar uno de los adjuntos.');
        }

        $stmtInsert->execute([
            ':entity_type'   => $entityType,
            ':entity_id'     => $entityId,
            ':original_name' => $originalName,
            ':stored_name'   => $storedName,
            ':file_path'     => $relativePath,
            ':mime_type'     => (string)($types[$index] ?? '') ?: null,
            ':file_size'     => (int)($sizes[$index] ?? 0) ?: null,
            ':uploaded_by'   => $uploadedBy ?: null,
        ]);

        $saved[] = [
            'id'        => (int)$pdo->lastInsertId(),
            'file_path' => $relativePath,
        ];
    }

    return $saved;
}

function cleanupUploadedAttachments(array $savedFiles): void
{
    foreach ($savedFiles as $file) {
        $relativePath = (string)($file['file_path'] ?? '');
        if ($relativePath === '') {
            continue;
        }

        $fullPath = buildAssetPathFromRelative($relativePath);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}

function deleteEntityAttachment(PDO $pdo, string $entityType, int $entityId, int $attachmentId): bool
{
    ensureAssetFilesSchema($pdo);
    $entityType = normalizeAssetEntityType($entityType);

    $stmt = $pdo->prepare("
        SELECT id, file_path
        FROM adjuntos_activos
        WHERE id = :id
          AND entity_type = :entity_type
          AND entity_id = :entity_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id'          => $attachmentId,
        ':entity_type' => $entityType,
        ':entity_id'   => $entityId,
    ]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        return false;
    }

    $stmtDelete = $pdo->prepare("DELETE FROM adjuntos_activos WHERE id = :id");
    $stmtDelete->execute([':id' => $attachmentId]);

    $fullPath = buildAssetPathFromRelative((string)$attachment['file_path']);
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }

    return true;
}

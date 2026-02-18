<?php

function mailEnabled(): bool
{
    $raw = getenv('MAIL_ENABLED');
    if ($raw === false || $raw === '') {
        return true;
    }
    $v = strtolower(trim((string)$raw));
    return !in_array($v, ['0', 'false', 'no', 'off'], true);
}

function mailFromAddress(): string
{
    $from = trim((string)(getenv('MAIL_FROM') ?: 'no-reply@inventario.local'));
    return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : 'no-reply@inventario.local';
}

function mailFromName(): string
{
    $name = trim((string)(getenv('MAIL_FROM_NAME') ?: 'Inventario IT'));
    return $name !== '' ? $name : 'Inventario IT';
}

function sendPlainEmail(string $to, string $subject, string $body, ?string &$error = null): bool
{
    if (!mailEnabled()) {
        $error = 'El envío de correo está desactivado por configuración (MAIL_ENABLED=0).';
        return false;
    }

    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'El destinatario no es un correo válido.';
        return false;
    }

    $fromEmail = mailFromAddress();
    $fromName = mailFromName();

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $fromEmail;

    $ok = @mail($to, $subject, $body, implode("\r\n", $headers), '-f' . $fromEmail);
    if (!$ok) {
        $error = 'No se pudo entregar el correo con la configuración actual del servidor.';
    }
    return $ok;
}

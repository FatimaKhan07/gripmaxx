<?php

include_once __DIR__ . "/settings_store.php";

function smtp_encode_header($value) {
    $cleanValue = trim(preg_replace("/[\r\n]+/", " ", (string)$value));

    if ($cleanValue === '') {
        return '';
    }

    return '=?UTF-8?B?' . base64_encode($cleanValue) . '?=';
}

function smtp_read_response($socket, &$errorMessage) {
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket, 515);

        if ($line === false) {
            break;
        }

        $response .= $line;

        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }

    if ($response === '') {
        $errorMessage = "No response received from the SMTP server.";
    }

    return $response;
}

function smtp_send_command($socket, $command, $expectedCodes, &$errorMessage) {
    if ($command !== null && fwrite($socket, $command . "\r\n") === false) {
        $errorMessage = "Unable to write to the SMTP server.";
        return false;
    }

    $response = smtp_read_response($socket, $errorMessage);

    if ($response === '') {
        return false;
    }

    $statusCode = (int)substr($response, 0, 3);

    if (!in_array($statusCode, (array)$expectedCodes, true)) {
        $errorMessage = trim($response);
        return false;
    }

    return true;
}

function is_smtp_configured($settings) {
    $normalized = normalize_app_settings($settings);

    return $normalized["smtp_host"] !== ''
        && $normalized["smtp_from_email"] !== ''
        && filter_var($normalized["smtp_from_email"], FILTER_VALIDATE_EMAIL);
}

function send_smtp_email($toEmail, $toName, $subject, $body, $settings, &$errorMessage) {
    $normalized = normalize_app_settings($settings);
    $host = trim((string)$normalized["smtp_host"]);
    $port = (int)$normalized["smtp_port"];
    $encryption = normalize_smtp_encryption($normalized["smtp_encryption"], "tls");
    $username = trim((string)$normalized["smtp_username"]);
    $password = (string)$normalized["smtp_password"];
    $fromEmail = trim((string)$normalized["smtp_from_email"]);
    $fromName = trim((string)$normalized["smtp_from_name"]);
    $recipientEmail = trim((string)$toEmail);
    $recipientName = trim((string)$toName);
    $cleanSubject = trim(preg_replace("/[\r\n]+/", " ", (string)$subject));
    $cleanBody = trim((string)$body);

    if ($host === '') {
        $errorMessage = "SMTP host is not configured.";
        return false;
    }

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "SMTP from email is invalid.";
        return false;
    }

    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Recipient email is invalid.";
        return false;
    }

    if ($cleanSubject === '' || $cleanBody === '') {
        $errorMessage = "Reply subject and message are required.";
        return false;
    }

    $transportHost = $encryption === "ssl" ? "ssl://" . $host : $host;
    $socket = @stream_socket_client($transportHost . ":" . $port, $errorNumber, $errorString, 30, STREAM_CLIENT_CONNECT);

    if (!$socket) {
        $errorMessage = "SMTP connection failed: " . $errorString;
        return false;
    }

    stream_set_timeout($socket, 30);

    if (!smtp_send_command($socket, null, [220], $errorMessage)) {
        fclose($socket);
        return false;
    }

    if (!smtp_send_command($socket, "EHLO gripmaxx.local", [250], $errorMessage)) {
        fclose($socket);
        return false;
    }

    if ($encryption === "tls") {
        if (!smtp_send_command($socket, "STARTTLS", [220], $errorMessage)) {
            fclose($socket);
            return false;
        }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            $errorMessage = "Unable to enable TLS encryption for SMTP.";
            return false;
        }

        if (!smtp_send_command($socket, "EHLO gripmaxx.local", [250], $errorMessage)) {
            fclose($socket);
            return false;
        }
    }

    if ($username !== '') {
        if (!smtp_send_command($socket, "AUTH LOGIN", [334], $errorMessage)
            || !smtp_send_command($socket, base64_encode($username), [334], $errorMessage)
            || !smtp_send_command($socket, base64_encode($password), [235], $errorMessage)) {
            fclose($socket);
            return false;
        }
    }

    if (!smtp_send_command($socket, "MAIL FROM:<" . $fromEmail . ">", [250], $errorMessage)
        || !smtp_send_command($socket, "RCPT TO:<" . $recipientEmail . ">", [250, 251], $errorMessage)
        || !smtp_send_command($socket, "DATA", [354], $errorMessage)) {
        fclose($socket);
        return false;
    }

    $encodedSubject = smtp_encode_header($cleanSubject);
    $encodedFromName = smtp_encode_header($fromName);
    $safeBody = preg_replace("/(?m)^\./", "..", $cleanBody);

    $headers = [
        "Date: " . date(DATE_RFC2822),
        "From: " . ($encodedFromName !== '' ? $encodedFromName . " " : "") . "<" . $fromEmail . ">",
        "To: " . ($recipientName !== '' ? smtp_encode_header($recipientName) . " " : "") . "<" . $recipientEmail . ">",
        "Reply-To: <" . $fromEmail . ">",
        "Subject: " . $encodedSubject,
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit"
    ];

    $messageData = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\r"], "\n", $safeBody);
    $messageData = str_replace("\n", "\r\n", $messageData) . "\r\n.\r\n";

    if (fwrite($socket, $messageData) === false) {
        fclose($socket);
        $errorMessage = "Unable to send the email body to the SMTP server.";
        return false;
    }

    if (!smtp_send_command($socket, null, [250], $errorMessage)) {
        fclose($socket);
        return false;
    }

    smtp_send_command($socket, "QUIT", [221, 250], $errorMessage);
    fclose($socket);

    return true;
}

?>

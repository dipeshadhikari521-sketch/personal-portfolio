<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// ── CONFIG ──────────────────────────────────────────────────────────────────
$gmail_user = 'dipeshadhikari521@gmail.com';  // Your Gmail address
$gmail_pass = 'lzldnlkhbknflcwa';        // 16-char Gmail App Password
$to_email   = 'dipeshadhikari521@gmail.com';  // Where to receive messages
// ────────────────────────────────────────────────────────────────────────────

// Sanitize inputs
$name    = trim(strip_tags($_POST['name']           ?? ''));
$email   = trim(strip_tags($_POST['email']          ?? ''));
$subject = trim(strip_tags($_POST['custom_subject'] ?? ''));
$message = trim(strip_tags($_POST['message']        ?? ''));

if (empty($name) || empty($email) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Name, email, and message are required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$mail_subject = $subject ?: 'New Portfolio Contact from ' . $name;

$mail_body  = "New message from your portfolio contact form\n";
$mail_body .= str_repeat('─', 45) . "\n";
$mail_body .= "Name    : {$name}\n";
$mail_body .= "Email   : {$email}\n";
$mail_body .= "Subject : {$mail_subject}\n";
$mail_body .= str_repeat('─', 45) . "\n\n";
$mail_body .= "Message:\n{$message}\n\n";
$mail_body .= str_repeat('─', 45) . "\n";
$mail_body .= "Sent from: Dipesh Adhikari Portfolio\n";

// ── Gmail SMTP sender (no Composer / no dependencies) ───────────────────────
function gmail_send($user, $pass, $to, $subject, $body, $reply_to) {
    $host   = 'ssl://smtp.gmail.com';
    $port   = 465;
    $socket = fsockopen($host, $port, $errno, $errstr, 30);
    if (!$socket) return "Connection failed: $errstr ($errno)";

    function smtp_cmd($socket, $cmd, $expect) {
        if ($cmd) fwrite($socket, $cmd . "\r\n");
        $res = '';
        while ($line = fgets($socket, 515)) {
            $res .= $line;
            if ($line[3] === ' ') break; // end of multi-line response
        }
        if ($expect && substr($res, 0, 3) !== (string)$expect) {
            return "Expected $expect, got: $res";
        }
        return true;
    }

    $r = smtp_cmd($socket, null, 220);           if ($r !== true) return $r;
    $r = smtp_cmd($socket, 'EHLO localhost', 250); if ($r !== true) return $r;
    $r = smtp_cmd($socket, 'AUTH LOGIN', 334);   if ($r !== true) return $r;
    $r = smtp_cmd($socket, base64_encode($user), 334); if ($r !== true) return $r;
    $r = smtp_cmd($socket, base64_encode($pass), 235); if ($r !== true) return $r;
    $r = smtp_cmd($socket, "MAIL FROM:<{$user}>", 250); if ($r !== true) return $r;
    $r = smtp_cmd($socket, "RCPT TO:<{$to}>", 250);     if ($r !== true) return $r;
    $r = smtp_cmd($socket, 'DATA', 354);         if ($r !== true) return $r;

    $headers  = "From: Portfolio Contact <{$user}>\r\n";
    $headers .= "To: {$to}\r\n";
    $headers .= "Reply-To: {$reply_to}\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "\r\n";

    fwrite($socket, $headers . $body . "\r\n.\r\n");
    $res = '';
    while ($line = fgets($socket, 515)) {
        $res .= $line;
        if ($line[3] === ' ') break;
    }
    if (substr($res, 0, 3) !== '250') return "Message not accepted: $res";

    smtp_cmd($socket, 'QUIT', null);
    fclose($socket);
    return true;
}

$result = gmail_send(
    $gmail_user,
    $gmail_pass,
    $to_email,
    $mail_subject,
    $mail_body,
    "{$name} <{$email}>"
);

if ($result === true) {
    echo json_encode(['success' => true, 'message' => "Thank you {$name}! Your message has been sent. I'll get back to you soon."]);
} else {
    // Log error server-side, show generic message to user
    error_log("Contact form SMTP error: " . $result);
    echo json_encode(['success' => false, 'message' => 'Failed to send. Please email me directly at dipeshadhikari521@gmail.com']);
}
?>
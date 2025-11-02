<?php
// Utilitaire d'envoi d'e-mails sans dépendances externes.
// Tente SMTP via fsockopen; bascule sur mail() si indisponible.

function sendEmail($toEmail, $subject, $htmlBody, $textBody = '') {
	$config = require __DIR__ . '/../config/mail.php';

	$fromEmail = $config['from_email'];
	$fromName = $config['from_name'];

	$boundary = '==Multipart_Boundary_x' . md5((string)microtime(true)) . 'x';
	$headers = [];
	$headers[] = 'From: ' . encodeHeader("$fromName") . " <{$fromEmail}>";
	$headers[] = 'Reply-To: ' . $fromEmail;
	$headers[] = 'MIME-Version: 1.0';
	$headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

	if ($textBody === '') {
		$textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
	}

	$body = '';
	$body .= "--{$boundary}\r\n";
	$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
	$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
	$body .= chunk_split(base64_encode($textBody));
	$body .= "--{$boundary}\r\n";
	$body .= "Content-Type: text/html; charset=UTF-8\r\n";
	$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
	$body .= chunk_split(base64_encode($htmlBody));
	$body .= "--{$boundary}--\r\n";

	$encodedSubject = encodeHeader($subject);

	// Si SMTP configuré, tenter en premier
	if ($config['transport'] === 'smtp' && !empty($config['host'])) {
		$sent = smtpSend($config, $toEmail, $fromEmail, $fromName, $encodedSubject, $headers, $body);
		if ($sent === true) {
			return [true, null];
		}
		// fallback mail() si SMTP échoue
	}

	$success = @mail($toEmail, $encodedSubject, $body, implode("\r\n", $headers));
	return [$success === true, $success ? null : 'mail() a échoué'];
}

function encodeHeader($text) {
	// Encodage RFC 2047 UTF-8
	return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function smtpSend($config, $toEmail, $fromEmail, $fromName, $subject, $headers, $body) {
	$host = $config['host'];
	$port = (int)$config['port'];
	$encryption = strtolower((string)$config['encryption']);
	$timeout = 10;

	$remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
	$fp = @fsockopen($remote, $port, $errno, $errstr, $timeout);
	if (!$fp) {
		return "Connexion SMTP impossible: $errstr ($errno)";
	}
	$read = function() use ($fp) {
		$resp = '';
		while ($line = fgets($fp, 515)) {
			$resp .= $line;
			if (isset($line[3]) && $line[3] === ' ') break;
		}
		return $resp;
	};
	$write = function($cmd) use ($fp) {
		fwrite($fp, $cmd . "\r\n");
	};

	$resp = $read();
	if (strpos($resp, '220') !== 0) {
		fclose($fp);
		return "Bannière SMTP invalide: $resp";
	}

	$hostname = gethostname() ?: 'localhost';
	$write('EHLO ' . $hostname);
	$resp = $read();
	if (strpos($resp, '250') !== 0) {
		$write('HELO ' . $hostname);
		$resp = $read();
		if (strpos($resp, '250') !== 0) {
			fclose($fp);
			return "EHLO/HELO refusé: $resp";
		}
	}

	// STARTTLS si demandé
	if ($encryption === 'tls') {
		$write('STARTTLS');
		$resp = $read();
		if (strpos($resp, '220') !== 0) {
			fclose($fp);
			return "STARTTLS refusé: $resp";
		}
		if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
			fclose($fp);
			return 'Négociation TLS échouée';
		}
		// Ré-annoncer EHLO après TLS
		$write('EHLO ' . $hostname);
		$resp = $read();
		if (strpos($resp, '250') !== 0) {
			fclose($fp);
			return "EHLO après TLS refusé: $resp";
		}
	}

	// AUTH si credentials fournis
	if (!empty($config['username'])) {
		$write('AUTH LOGIN');
		$resp = $read();
		if (strpos($resp, '334') !== 0) {
			fclose($fp);
			return "AUTH LOGIN refusé: $resp";
		}
		$write(base64_encode($config['username']));
		$resp = $read();
		$write(base64_encode($config['password']));
		$resp = $read();
		if (strpos($resp, '235') !== 0) {
			fclose($fp);
			return "AUTH échoué: $resp";
		}
	}

	$write('MAIL FROM: <' . $fromEmail . '>');
	$resp = $read();
	if (strpos($resp, '250') !== 0) {
		fclose($fp);
		return "MAIL FROM refusé: $resp";
	}
	$write('RCPT TO: <' . $toEmail . '>');
	$resp = $read();
	if (strpos($resp, '250') !== 0 && strpos($resp, '251') !== 0) {
		fclose($fp);
		return "RCPT TO refusé: $resp";
	}
	$write('DATA');
	$resp = $read();
	if (strpos($resp, '354') !== 0) {
		fclose($fp);
		return "DATA refusé: $resp";
	}

	$allHeaders = $headers;
	$allHeaders[] = 'To: <' . $toEmail . '>';
	$allHeaders[] = 'Subject: ' . $subject;
	$payload = implode("\r\n", $allHeaders) . "\r\n\r\n" . $body . "\r\n.";
	$write($payload);
	$resp = $read();
	$write('QUIT');
	fclose($fp);

	return (strpos($resp, '250') === 0) ? true : "Envoi échoué: $resp";
}
?>


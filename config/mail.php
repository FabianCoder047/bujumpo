<?php
// Configuration simple pour l'envoi d'e-mails
return [
    'transport' => 'smtp', // smtp | mail
    // Paramètres SMTP (à adapter à votre fournisseur)
    'host' => getenv('SMTP_HOST') ?: 'smtp.example.com',
    'port' => getenv('SMTP_PORT') ?: 587,
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls', // tls | ssl | ''
    'username' => getenv('SMTP_USERNAME') ?: 'no-reply@example.com',
    'password' => getenv('SMTP_PASSWORD') ?: '',
    // Expéditeur par défaut
    'from_email' => getenv('MAIL_FROM') ?: 'no-reply@example.com',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'Port de BUJUMBURA',
];
?>


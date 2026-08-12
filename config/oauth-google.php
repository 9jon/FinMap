<?php
// config/oauth-google.php
// Preenchido com os valores copiados do Google Cloud Console

define('GOOGLE_CLIENT_ID', '424836112785-6lts7ap0e5l9u06bqj5ciljfnjs94kjo.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');

// Precisa ser IDÊNTICO ao que você cadastrou no Google Cloud Console
define('GOOGLE_REDIRECT_URI', 'http://localhost/finmap/login/google-callback.php');
?>
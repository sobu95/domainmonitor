<?php
return [
    'db_host' => 'localhost',
    'db_name' => 'domain_monitor',
    'db_username' => 'your_username',
    'db_password' => 'your_password',
    
    'gemini_api_key' => 'your_gemini_api_key',
    'gemini_model' => 'gemini-2.5-flash',

    // Semstorm API credentials
    'semstorm_app_key' => 'your_semstorm_app_key',
    'semstorm_app_secret' => 'your_semstorm_app_secret',
    // Optional API version, defaults to 1 if not specified
    'semstorm_api_version' => 1,
    
    'email_smtp_host' => 'smtp.gmail.com',
    'email_smtp_port' => 587,
    'email_username' => 'your_email@gmail.com',
    'email_password' => 'your_app_password',
    'email_from_name' => 'Domain Monitor',
    
    'site_url' => 'https://yourdomain.com',
    'site_name' => 'Domain Monitor',
    
    'timezone' => 'Europe/Warsaw'
];
?>
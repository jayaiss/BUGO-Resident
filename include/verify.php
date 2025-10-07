<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $recaptcha_secret = "6LezFtYqAAAAAJAtwKXmPohLi94j88F_pHG1W_vV"; // Replace with your Secret Key
    $recaptcha_response = $_POST["g-recaptcha-response"];

    // Verify with Google
    $url = "https://www.google.com/recaptcha/api/siteverify";
    $data = [
        "secret" => $recaptcha_secret,
        "response" => $recaptcha_response,
        "remoteip" => $_SERVER["REMOTE_ADDR"]
    ];

    $options = [
        "http" => [
            "method"  => "POST",
            "header"  => "Content-type: application/x-www-form-urlencoded",
            "content" => http_build_query($data)
        ]
    ];
    $context  = stream_context_create($options);
    $verify = file_get_contents($url, false, $context);
    $captcha_success = json_decode($verify);

    if ($captcha_success->success) {
        echo "reCAPTCHA verified. Form submission successful!";
    } else {
        echo "reCAPTCHA verification failed. Please try again.";
    }
}
?>

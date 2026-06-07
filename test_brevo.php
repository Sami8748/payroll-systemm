<?php

require 'functions.php';

$result = send_email_brevo(
    'samitanunkongrod@gmail.com',
    'Test',
    'Brevo API Test',
    'Hello World'
);

echo $result ? 'SUCCESS' : 'FAILED';
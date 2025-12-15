<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 600px;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: left;
        }

        .header {
            display: flex;
            align-items: center;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
            margin-bottom: 20px;
        }

        .header img {
            height: 40px;
            margin-right: 10px;
        }

        .header h1 {
            font-size: 1.5em;
            color: #003366;
            margin: 0;
        }

        .title {
            font-size: 1.4em;
            color: #1a1ca3;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
        }

        .light-text {
            font-size: 0.9em;
            color: #666;
            margin-top: 10px;
        }

        .verify-button {
            display: block;
            width: fit-content;
            margin: 20px auto;
            /* Centered button */
            padding: 10px 20px;
            font-size: 1em;
            color: #fff;
            background-color: #007bff;
            border-radius: 5px;
            text-decoration: none;
            text-align: center;
        }

        .pageend {
            font-size: 0.8em;
            color: #777;
            margin-top: 40px;
            text-align: center;
        }

        .pageend p {
            margin: 5px 0;
        }

        .pageend img {
            max-width: 100%;
            height: auto;
            margin-top: 10px;
        }

        .separator {
            border-top: 1px solid #ddd;
            margin: 10px 0;
        }

        .contact-info {
            font-size: 0.85em;
            color: #555;
            margin-top: 10px;
            text-align: center;
            margin-bottom: 40px;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header with logo and title -->
        <div class="header">
            {{-- Get the app URL and append the path to the logo --}}
            <img src="{{ asset('https://bondkonnect.com/images/logo.png') }}" alt="logo">
            <h1></h1>
        </div>


        <p class="title">Email Verification</p>
        <p>Hi ,</p>
        <p class="light-text">Welcome to bondkonnect! To complete your registration, please confirm your
            email address by clicking the link below:</p>

        <a href="{{ $link }}" class="verify-button" style="color: #fff">Verify Your Email</a>

        <p class="light-text">This link will expire in <strong>24 hours</strong>. Once verified, you’ll have full access
            to the company's pension portal for navigation.
            If you did not register with us, please disregard this message or contact our support team.</p>

        <div class="pageend">
            <div class="separator"></div>
            <img src="{{ asset('https://api-doctor-reinsurer-uat.bondkonnect.com/images/fot.png') }}" alt="another image">
            <div class="contact-info">
                <p>Head Office, bondkonnect Tower, </p>
                <p>Tel: +254 705 100 100 | Email: <a
                        href="mailto:customerservice@bondkonnect.com">customerservice@bondkonnect.com</a></p>
                <p>Web: <a href="https://www.brim.com" target="_blank">https://www.bk.com</a></p>
            </div>
        </div>
    </div>
</body>

</html>

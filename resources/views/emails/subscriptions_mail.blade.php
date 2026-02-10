<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriptions</title>
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
      
        <!-- Title and message content -->
        <p class="title">{{ $general_subject }}</p>
        {{-- <p>Hello {{ $first_name }},</p> --}}


        <!-- OTP Section -->
        <p class="otp">
            {!! nl2br(e($general_body)) !!}
        </p>

        <!-- Expiration Note -->
        {{-- <p class="note">
            Thank you very much for choosing to use to service you. We genuinely appreciate the opportunity to assist
            you and are delighted to have you as part of our community. we look forward to serving you now and in the
            future.</p> --}}

        <div class="pageend">

            <div class="separator"></div>
            {{-- <img src="{{ asset('https://api-doctor-reinsurer-uat.bondkonnect.com/images/fot.png') }}" alt="another image"> --}}
            <div class="contact-info">
                <p>Head Office, bondkonnect Tower, Hospital Road, Upper Hill</p>
                <p>Tel: +254 705 100 100 | Email: <a
                        href="mailto:corporatepensions@bondkonnect.com">coensions@bondkonnect.com</a></p>
                <p>Web: <a href="https://www.bondkonnect.com" target="_blank">https://www.bondkonnect.com</a></p>
            </div>
        </div>
    </div>
</body>

</html>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f8ff;
            color: #444;
            padding: 20px;
            margin: 0;
        }

        .container {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: 40px auto;
            text-align: left;
        }

        h1 {
            color: #5a9;
            font-size: 22px;
            margin-bottom: 10px;
        }

        p {
            line-height: 1.6;
            font-size: 16px;
        }

        .verify-link {
            display: inline-block;
            padding: 12px 25px;
            font-size: 16px;
            color: #fff;
            background-color: #ff6f61;
            text-decoration: none;
            border-radius: 25px;
            margin-top: 20px;
            transition: background-color 0.3s ease;
        }

        .verify-link:hover {
            background-color: #ff4d40;
        }

        .note {
            font-size: 14px;
            color: #999;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
      
        <p>Please verify your email by clicking the button below:</p>
        <p><a href="{{ $verification_link }}" class="verify-link">Verify Email</a></p>
        <p class="note">This link will expire in 24 hours.</p>
        <p class="note">If you didn’t request this verification, please ignore this message.</p>
    </div>
</body>

</html>

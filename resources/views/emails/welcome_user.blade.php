<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Welcome</title>
</head>

<body style="font-family:Arial;background:#f5f6f8;padding:20px;">

    <div style="max-width:600px;margin:auto;background:#ffffff;padding:30px;border-radius:8px;">

        {{-- Logo --}}
        @if ($company->logo)
            <div style="text-align:center;margin-bottom:20px;">
                <img src="{{ url($company->logo) }}" alt="Logo" style="max-width:150px;height:auto;">
            </div>
        @endif


        <h2 style="text-align:center;color:#333;">
            Welcome to {{ $company->company_name }}
        </h2>


        <p>Dear {{ $user->name }},</p>


        <p>
            🎉 Congratulations! You are now registered as a
            <strong>{{ ucfirst($user->getRoleNames()->first()) }}</strong>
            on <strong>{{ $company->company_name }}</strong>.
        </p>


        <hr>


        <h4>Login Information</h4>

        <p>
            Email: <strong>{{ $user->email }}</strong><br>
            Temporary Password: <strong>{{ $password }}</strong>
        </p>


        <p style="color:#dc3545;">
            ⚠ Please change your password after first login.
        </p>


        <p style="margin:20px 0;">
            <a href="{{ $loginUrl }}"
                style="background:#0d6efd;color:#fff;
          padding:10px 25px;border-radius:5px;
          text-decoration:none;">

                Login Now
            </a>
        </p>


        {{-- App Links For Customers --}}
        @if ($user->getRoleNames()->first() == 'customer')

            @if ($company->play_store_link || $company->apple_store_link)
                <hr>

                <h4>Download Our App</h4>

                @if ($company->play_store_link)
                    <p>📱 Android:
                        <a href="{{ $company->play_store_link }}">
                            Download
                        </a>
                    </p>
                @endif

                @if ($company->apple_store_link)
                    <p>📱 iOS:
                        <a href="{{ $company->apple_store_link }}">
                            Download
                        </a>
                    </p>
                @endif
            @endif

        @endif


        <hr>


        <p>
            Need help? Contact us:
            <br>
            📧 {{ $company->email }}
            <br>
            📞 {{ $company->hotline }}
        </p>


        <p style="margin-top:25px;">
            Regards,<br>
            <strong>{{ $company->company_name }}</strong> Team
        </p>


    </div>
</body>

</html>

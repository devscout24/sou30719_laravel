<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $transaction->id }}</title>
    <style>
        @page {
            margin: 30px 36px;
        }

        body {
            margin: 0;
            padding: 0;
        }
    </style>
</head>

<body>
    @include('backend.partial.invoice-content', ['transaction' => $transaction, 'company' => $company])
</body>

</html>

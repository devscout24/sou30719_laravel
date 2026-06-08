  <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset($systemSettings?->system_favicon ?? 'uploads/systems/favicon/default-favicon.png') }}" type="image/x-icon">

    <!-- BOOTSTRAP CSS -->
    <link id="style" href="{{ asset('admin/assets/plugins/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet" />

    <!-- STYLE CSS -->
    <link href="{{ asset('admin/assets/css/style.css') }}" rel="stylesheet" />
    <link href="{{ asset('admin/assets/css/skin-modes.css') }}" rel="stylesheet" />

    <!-- FONT-ICONS CSS -->
    <link href="{{ asset('admin/assets/plugins/icons/icons.css') }}" rel="stylesheet" />

    <!-- INTERNAL Switcher CSS -->
    <link href="{{ asset('admin/assets/switcher/css/switcher.css') }}" rel="stylesheet">
    <link href="{{ asset('admin/assets/switcher/demo.css') }}" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">

    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    {{-- dropify css --}}
<link rel="stylesheet" href="{{ asset('/') }}admin/assets/css/dropify.min.css">

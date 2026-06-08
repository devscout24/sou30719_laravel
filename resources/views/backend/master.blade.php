<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>{{ companyName() }} || @yield('page_title', 'Dashboard')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description"
        content="Paces is a modern, responsive admin dashboard available on ThemeForest. Ideal for building CRM, CMS, project management tools, and custom web applications with a clean UI, flexible layouts, and rich features." />
    <meta name="keywords"
        content="Paces, admin dashboard, ThemeForest, Bootstrap 5 admin, responsive admin, CRM dashboard, CMS admin, web app UI, admin theme, premium admin template" />
    <meta name="author" content="Manjurul Alam Mahi" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ asset('backend') }}/assets/images/favicon.ico" />


    <!-- Vector Maps css -->
    <link href="{{ asset('backend') }}/assets/plugins/jsvectormap/jsvectormap.min.css" rel="stylesheet"
        type="text/css" />

    <!-- Theme Config Js -->
    <script src="{{ asset('backend') }}/assets/js/config.js"></script>
    <script src="{{ asset('backend') }}/assets/js/demo.js"></script>
    <!-- Vendor css -->
    <link href="{{ asset('backend') }}/assets/css/vendors.min.css" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link href="{{ asset('backend') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css">

    <style>
        /* Light Mode */
        .logo-preview-light .dropify-wrapper {
            background-color: #f1f1f1 !important;
        }

        /* Dark Mode */
        .logo-preview-dark .dropify-wrapper {
            background-color: #1f2937 !important;
        }

        /* Also force preview area */
        .logo-preview-light .dropify-preview {
            background-color: #f1f1f1 !important;
        }

        .logo-preview-dark .dropify-preview {
            background-color: #1f2937 !important;
        }

        /* Keep image transparent */
        .dropify-preview img {
            background: transparent !important;
        }
    </style>

    @stack('styles')
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">

        <!-- ========== Header ========== -->
        @include('backend.partial.header')
        <!-- ========== Header ========== -->

        <!-- ========== Left Sidebar ========== -->
        @include('backend.partial.sidebar')
        <!-- ========== Left Sidebar ========== -->

        <script>
            // Sidenav Link Activation
            const currentUrlT = window.location.href.split(/[?#]/)[0];
            const currentPageT = window.location.pathname.split("https://sou30719.test/").pop();
            const sideNavT = document.querySelector('.side-nav');

            console.log('Current URL:', currentUrlT);
            console.log('Current Page:', currentPageT);

            document.querySelectorAll('.side-nav-link[href]').forEach(link => {
                const linkHref = link.getAttribute('href');
                if (!linkHref) return;

                const match = linkHref === currentPageT || link.href === currentUrlT;

                if (match) {
                    // Mark link and its li active
                    link.classList.add('active');
                    const li = link.closest('li.side-nav-item');
                    if (li) li.classList.add('active');

                    // Expand all parent .collapse and set toggles
                    let parentCollapse = link.closest('.collapse');
                    while (parentCollapse) {
                        parentCollapse.classList.add('show');
                        parentCollapse.style.height = '100%';

                        const parentToggle = document.querySelector(`a[href="#${parentCollapse.id}"]`);
                        if (parentToggle) {
                            parentToggle.setAttribute('aria-expanded', 'true');
                            const parentLi = parentToggle.closest('li.side-nav-item');
                            if (parentLi) parentLi.classList.add('active');
                        }

                        parentCollapse = parentCollapse.parentElement.closest('.collapse');
                    }
                }
            });
        </script>

        <!-- ============================================================== -->
        <!-- Start Main Content -->
        <!-- ============================================================== -->

        <div class="content-page">
            <div class="container-fluid">
                <div class="page-title-head d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h4 class="page-main-title m-0">@yield('page_title')</h4>
                    </div>

                    {{-- <div class="text-end">
                            <ol class="breadcrumb m-0 py-0">
                                <li class="breadcrumb-item"><a href="javascript: void(0);">Paces</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0);">Dashboard</a></li>
                                <li class="breadcrumb-item active">eCommerce</li>
                            </ol>
                        </div> --}}
                </div>

                @yield('content')

            </div>
            <!-- container -->

            <!-- Footer Start -->
            @include('backend.partial.footer')
            <!-- end Footer -->

        </div>

        <!-- ============================================================== -->
        <!-- End of Main Content -->
        <!-- ============================================================== -->
    </div>
    <!-- END wrapper -->

    @include('backend.partial.script')

    @stack('scripts')

</body>

</html>

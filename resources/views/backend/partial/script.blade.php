 <!-- Jquery -->
 <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
 <!-- Vendor js -->
 <script src="{{ asset('backend') }}/assets/js/vendors.min.js"></script>

 <!-- App js -->
 <script src="{{ asset('backend') }}/assets/js/app.js"></script>


 <!-- Apex Chart js -->
 <script src="{{ asset('backend') }}/assets/plugins/apexcharts/apexcharts.min.js"></script>

 <!-- Vector Map Js -->
 <script src="{{ asset('backend') }}/assets/plugins/jsvectormap/jsvectormap.min.js"></script>
 <script src="{{ asset('backend') }}/assets/js/maps/world-merc.js"></script>
 <script src="{{ asset('backend') }}/assets/js/maps/world.js"></script>

 <!-- Custom table -->
 <script src="{{ asset('backend') }}/assets/js/pages/custom-table.js"></script>

 <!-- Dashboard js -->
 <script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"></script>

 <script src=""></script>

 <script>
     $(document).ready(function() {
         $('.dropify').dropify();
     });
 </script>

 @include('backend.partial.sweetalert')

 <script>
     $(document).ready(function() {

         const box = $('#logoPreviewBox');

         $('#logoBgToggle').on('change', function() {

             if (this.checked) {

                 box.removeClass('logo-preview-light')
                     .addClass('logo-preview-dark');

             } else {

                 box.removeClass('logo-preview-dark')
                     .addClass('logo-preview-light');

             }

             // Force repaint (important for Dropify)
             box.find('.dropify-wrapper').css('display', 'none').offset();
             box.find('.dropify-wrapper').css('display', 'block');
         });

     });
 </script>

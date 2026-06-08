 <!-- Sweet Alert js -->
 <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

 <script>
     const Toast = Swal.mixin({
         toast: true,
         position: 'top-end',
         showConfirmButton: false,
         timer: 3000,
         timerProgressBar: true,
         didOpen: (toast) => {
             toast.addEventListener('mouseenter', Swal.stopTimer)
             toast.addEventListener('mouseleave', Swal.resumeTimer)
         }
     })

     @if (session('success'))
         Toast.fire({
             icon: 'success',
             title: '{{ session('success') }}',
         })
     @endif

     @if (session('error'))
         Toast.fire({
             icon: 'error',
             title: '{{ session('error') }}',
         })
     @endif

     $(".del").click(function() {
         let url = $(this).attr("name")
         deleteData(url);
     })

     function deleteData(url) {
         Swal.fire({
             title: 'Are you sure?',
             text: "You won't be able to revert this!",
             icon: 'warning',
             showCancelButton: true,
             confirmButtonColor: '#3085d6',
             cancelButtonColor: '#d33',
             confirmButtonText: 'Yes, delete it!'
         }).then((result) => {
             if (result.isConfirmed) {
                 var link = url;
                 window.location.href = link;
             }
         })
     }
 </script>

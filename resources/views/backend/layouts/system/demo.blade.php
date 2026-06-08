@extends('backend.master')

@section('page_title', 'System Settings')

@section('content')
    <div class="px-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="#" enctype="multipart/form-data">
                            @csrf
                            <h5
                                class="mb-3 text-uppercase bg-light-subtle p-1 border-dashed border rounded border-light d-flex justify-content-center align-items-center gap-1">
                                <i class="ti ti-building fs-lg"></i>
                                Demo
                            </h5>


                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span
                                        style="font-size: 10px; font-style:italic;" name="current_password"
                                        class="text-muted">( To make the changes
                                        you have to enter your current password)</span></label>
                                <input type="password" class="form-control" name="current_password" id="password"
                                    placeholder="Enter current password" />
                            </div>

                            <!-- Submit -->
                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-success">Save Changes</button>
                            </div>

                        </form>
                    </div>
                    <!-- end card-body-->
                </div>
                <!-- end card-->
            </div>
            <!-- end col-->
        </div>
        <!-- end row-->
    </div>
@endsection
